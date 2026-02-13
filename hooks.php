<?php

use WHMCS\View\Menu\Item as MenuItem;
use WHMCS\Database\Capsule;

// Include PAYG billing hooks
require_once __DIR__ . '/hooks_payg.php';
// Include hooks to remove domain display
require_once __DIR__ . '/hooks_remove_domain.php';
require_once __DIR__ . '/lib/ApiClient.php';
require_once __DIR__ . '/lib/VDCManager.php';
require_once __DIR__ . '/lib/CustomFieldFunctions.php';

/**
 * Auto-migrate legacy data for a single MultiPortal service.
 *
 * Moves client-level credentials and old relid=0 Last Usage Sync into
 * per-product custom fields. Safe to call repeatedly — never overwrites
 * existing values. Returns true if any migration was performed.
 */
function multiportal_autoMigrateService($serviceId)
{
    $hosting = Capsule::table('tblhosting')->where('id', $serviceId)->first();
    if (!$hosting) {
        return false;
    }
    $productId = $hosting->packageid;
    $clientId = $hosting->userid;
    $migrated = false;

    // Ensure per-product custom fields exist before trying to migrate into them
    $requiredFields = [
        ['fieldname' => 'Tenant UUID', 'description' => 'MultiPortal tenant identifier for this service', 'sortorder' => 1],
        ['fieldname' => 'URL', 'description' => 'MultiPortal portal URL for this service', 'sortorder' => 2],
        ['fieldname' => 'Last Usage Sync', 'description' => 'Last PAYG usage sync date', 'sortorder' => 3],
    ];
    foreach ($requiredFields as $field) {
        $existing = Capsule::table('tblcustomfields')
            ->where('type', 'product')->where('fieldname', $field['fieldname'])
            ->where('relid', $productId)->first();
        if (!$existing) {
            Capsule::table('tblcustomfields')->insert([
                'type' => 'product', 'relid' => $productId,
                'fieldname' => $field['fieldname'], 'fieldtype' => 'text',
                'description' => $field['description'], 'fieldoptions' => '',
                'regexpr' => '', 'adminonly' => 'on', 'required' => '',
                'showorder' => '', 'showinvoice' => '', 'sortorder' => $field['sortorder'],
            ]);
        }
    }

    // Helper: get a client-level custom field value
    $getClientField = function ($fieldName) use ($clientId) {
        $field = Capsule::table('tblcustomfields')
            ->where('type', 'client')->where('fieldname', $fieldName)->first();
        if (!$field) return null;
        return Capsule::table('tblcustomfieldsvalues')
            ->where('fieldid', $field->id)->where('relid', $clientId)->value('value');
    };

    // Helper: get a product-level custom field value (per-product first, then relid=0)
    $getProductField = function ($fieldName) use ($serviceId, $productId) {
        $field = Capsule::table('tblcustomfields')
            ->where('type', 'product')->where('fieldname', $fieldName)
            ->where('relid', $productId)->first();
        if (!$field) {
            $field = Capsule::table('tblcustomfields')
                ->where('type', 'product')->where('fieldname', $fieldName)
                ->where('relid', 0)->first();
        }
        if (!$field) return null;
        return Capsule::table('tblcustomfieldsvalues')
            ->where('fieldid', $field->id)->where('relid', $serviceId)->value('value');
    };

    // Helper: set a product-level custom field value (per-product field only)
    $setProductField = function ($fieldName, $value) use ($serviceId, $productId) {
        $field = Capsule::table('tblcustomfields')
            ->where('type', 'product')->where('fieldname', $fieldName)
            ->where('relid', $productId)->first();
        if (!$field) return;
        Capsule::table('tblcustomfieldsvalues')->updateOrInsert(
            ['fieldid' => $field->id, 'relid' => $serviceId],
            ['value' => $value]
        );
    };

    $results = []; // Per-field migration status for admin note

    // 1. Migrate Tenant UUID
    $existingTenantUUID = $getProductField('Tenant UUID');
    if (empty($existingTenantUUID)) {
        $clientTenantUUID = $getClientField('MultiPortal Tenant UUID');
        if (!empty($clientTenantUUID)) {
            $setProductField('Tenant UUID', $clientTenantUUID);
            $migrated = true;
            $results['Tenant UUID'] = 'success';
        } else {
            $results['Tenant UUID'] = 'not run (no legacy data found)';
        }
    } else {
        $results['Tenant UUID'] = 'not run (already has value)';
    }

    // 2. Migrate Username
    if (empty($hosting->username)) {
        $clientUsername = $getClientField('MultiPortal Username');
        if (!empty($clientUsername)) {
            $results['Username'] = 'success';
        } else {
            $results['Username'] = 'not run (no legacy data found)';
        }
    } else {
        $results['Username'] = 'not run (already has value)';
    }

    // 3. Migrate Password (together with username)
    if (empty($hosting->username)) {
        $passwordField = Capsule::table('tblcustomfields')
            ->where('type', 'client')->where('fieldname', 'MultiPortal Password')->first();
        $clientPassword = null;
        if ($passwordField) {
            $clientPassword = Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $passwordField->id)->where('relid', $clientId)->value('value');
        }
        $clientUsername = $getClientField('MultiPortal Username');
        if (!empty($clientUsername) && !empty($clientPassword)) {
            Capsule::table('tblhosting')->where('id', $serviceId)
                ->update(['username' => $clientUsername, 'password' => $clientPassword]);
            $migrated = true;
            $results['Password'] = 'success';
        } else {
            $results['Password'] = 'not run (no legacy data found)';
        }
    } else {
        $results['Password'] = 'not run (already has value)';
    }

    // 4. Migrate URL
    $existingURL = $getProductField('URL');
    if (empty($existingURL)) {
        $clientURL = $getClientField('MultiPortal URL');
        if (!empty($clientURL)) {
            $setProductField('URL', $clientURL);
            $migrated = true;
            $results['URL'] = 'success';
        } else {
            $results['URL'] = 'not run (no legacy data found)';
        }
    } else {
        $results['URL'] = 'not run (already has value)';
    }

    // 5. Migrate Last Usage Sync from relid=0 to per-product
    $oldSyncField = Capsule::table('tblcustomfields')
        ->where('type', 'product')->where('fieldname', 'Last Usage Sync')
        ->where('relid', 0)->first();
    $newSyncField = Capsule::table('tblcustomfields')
        ->where('type', 'product')->where('fieldname', 'Last Usage Sync')
        ->where('relid', $productId)->first();
    if ($oldSyncField && $newSyncField) {
        $oldValue = Capsule::table('tblcustomfieldsvalues')
            ->where('fieldid', $oldSyncField->id)->where('relid', $serviceId)->value('value');
        if (!empty($oldValue)) {
            $newValue = Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $newSyncField->id)->where('relid', $serviceId)->value('value');
            if (empty($newValue)) {
                Capsule::table('tblcustomfieldsvalues')->updateOrInsert(
                    ['fieldid' => $newSyncField->id, 'relid' => $serviceId],
                    ['value' => $oldValue]
                );
                $migrated = true;
                $results['Last Usage Sync'] = 'success';
            } else {
                $results['Last Usage Sync'] = 'not run (already has value)';
            }
            Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $oldSyncField->id)->where('relid', $serviceId)
                ->update(['value' => '']);
        } else {
            $results['Last Usage Sync'] = 'not run (no legacy data found)';
        }
    } else {
        $parts = [];
        if (!$oldSyncField) $parts[] = 'old field missing';
        if (!$newSyncField) $parts[] = 'new field missing';
        $results['Last Usage Sync'] = 'not run (' . implode(', ', $parts) . ')';
    }

    // 6. Clear legacy client-level fields if we migrated anything
    if ($migrated) {
        $legacyFields = ['MultiPortal Tenant UUID', 'MultiPortal Username', 'MultiPortal Password', 'MultiPortal URL'];
        foreach ($legacyFields as $legacyFieldName) {
            $legacyField = Capsule::table('tblcustomfields')
                ->where('type', 'client')->where('fieldname', $legacyFieldName)->first();
            if ($legacyField) {
                $legacyValue = Capsule::table('tblcustomfieldsvalues')
                    ->where('fieldid', $legacyField->id)->where('relid', $clientId)->value('value');
                if (!empty($legacyValue)) {
                    Capsule::table('tblcustomfieldsvalues')
                        ->where('fieldid', $legacyField->id)->where('relid', $clientId)
                        ->update(['value' => '']);
                }
            }
        }

        // Write admin note with per-field results
        $noteLines = [];
        foreach ($results as $field => $status) {
            $noteLines[] = "Migrated {$field}: {$status}";
        }
        $noteMessage = "AUTO-MODULE-DATA-MIGRATION: " . implode(', ', $noteLines);
        if (function_exists('multiportal_appendAdminNote')) {
            multiportal_appendAdminNote($serviceId, $noteMessage);
        }

        logActivity("MultiPortal AutoMigrate svc={$serviceId}: MIGRATED | " . implode('; ', $noteLines));
    }

    return $migrated;
}

/**
 * Add JavaScript confirmation for dangerous module actions
 */
add_hook('AdminAreaFooterOutput', 1, function($vars) {
    // Only add on the client service management pages
    if ($vars['filename'] == 'clientsservices') {
        return <<<HTML
<script type="text/javascript">
$(document).ready(function() {
    // Override the moduleCommand function to add confirmation
    var originalModuleCommand = window.moduleCommand;
    window.moduleCommand = function(command, id) {
        if (command === 'DestroyVdc') {
            if (!confirm('WARNING: This will permanently delete the Virtual Data Center and all associated data.\\n\\nThis action cannot be undone!\\n\\nAre you sure you want to proceed?')) {
                return false;
            }
        } else if (command === 'DisableVdc') {
            if (!confirm('Are you sure you want to suspend this VDC?\\n\\nThis will disable access to the VDC.')) {
                return false;
            }
        }
        return originalModuleCommand(command, id);
    };
    
    // Also catch form submissions
    $('form').on('submit', function(e) {
        var customValue = $(this).find('input[name="modop"]:checked').val() || 
                         $(this).find('input[name="a"][value="custom"]').siblings('input[name="custom"]').val() ||
                         $(this).find('button[type="submit"][name="custom"]').val();
        
        if (customValue === 'DestroyVdc') {
            if (!confirm('WARNING: This will permanently delete the Virtual Data Center and all associated data.\\n\\nThis action cannot be undone!\\n\\nAre you sure you want to proceed?')) {
                e.preventDefault();
                return false;
            }
        } else if (customValue === 'DisableVdc') {
            if (!confirm('Are you sure you want to suspend this VDC?\\n\\nThis will disable access to the VDC.')) {
                e.preventDefault();
                return false;
            }
        }
    });
});
</script>
HTML;
    }
});

/**
 * Daily cron to sync usage data for all MultiPortal services
 */
add_hook('DailyCronJob', 1, function() {
    // Auto-migrate legacy data for all MultiPortal services
    logActivity('MultiPortal: Checking for legacy data to migrate');
    $allServices = Capsule::table('tblhosting as h')
        ->join('tblservers as s', 'h.server', '=', 's.id')
        ->where('s.type', 'multiportal')
        ->whereIn('h.domainstatus', ['Active', 'Suspended'])
        ->pluck('h.id');

    $migratedCount = 0;
    foreach ($allServices as $svcId) {
        try {
            if (multiportal_autoMigrateService($svcId)) {
                $migratedCount++;
            }
        } catch (Exception $e) {
            logActivity("MultiPortal: Auto-migration error for service ID {$svcId}: " . $e->getMessage());
        }
    }
    if ($migratedCount > 0) {
        logActivity("MultiPortal: Auto-migrated legacy data for {$migratedCount} service(s)");
    }

    logActivity('MultiPortal Daily Usage Sync: Starting');

    // Get all active MultiPortal services
    $services = Capsule::table('tblhosting as h')
        ->join('tblservers as s', 'h.server', '=', 's.id')
        ->where('s.type', 'multiportal')
        ->whereIn('h.domainstatus', ['Active', 'Suspended'])
        ->select('h.*', 's.username as server_username', 's.password as server_password')
        ->get();
    
    $syncedCount = 0;
    $errorCount = 0;
    
    foreach ($services as $service) {
        try {
            // Get VDC UUID
            $vdcUUID = getProductCustomFieldValue($service->id, 'VDC UUID');
            
            if (empty($vdcUUID)) {
                continue; // Skip if no VDC UUID
            }
            
            // Initialize API
            $api = new ApiClient($service->server_username, $service->server_password);
            $vdcMgr = new VDCManager($api);
            
            // Get usage data
            $usage = $vdcMgr->getVDCUsage($vdcUUID);
            
            if ($usage) {
                // Store usage data in service notes or custom field
                $usageJson = json_encode($usage);
                $timestamp = date('Y-m-d H:i:s');
                
                // Update a custom field or add to notes
                $notes = "Last Usage Sync: {$timestamp}\n";
                $notes .= "Usage Data: " . substr($usageJson, 0, 500) . "...\n\n"; // Truncate for readability
                
                Capsule::table('tblhosting')
                    ->where('id', $service->id)
                    ->update(['notes' => Capsule::raw("CONCAT(notes, '" . addslashes($notes) . "')")]);
                
                $syncedCount++;
            }
        } catch (Exception $e) {
            logActivity("MultiPortal Usage Sync Error for Service ID {$service->id}: " . $e->getMessage());
            $errorCount++;
        }
    }
    
    logActivity("MultiPortal Daily Usage Sync: Completed. Synced: {$syncedCount}, Errors: {$errorCount}");
    
    // Also check for pending user creations
    logActivity('MultiPortal: Checking for pending user creations');
    
    $pendingUsers = Capsule::table('tblcustomfieldsvalues as cfv')
        ->join('tblcustomfields as cf', 'cfv.fieldid', '=', 'cf.id')
        ->join('tblhosting as h', 'cfv.relid', '=', 'h.id')
        ->join('tblservers as s', 'h.server', '=', 's.id')
        ->where('cf.fieldname', 'MultiPortal User Creation Pending')
        ->where('cfv.value', 'yes')
        ->where('s.type', 'multiportal')
        ->where('h.domainstatus', 'Active')
        ->select('h.id as serviceid', 'h.userid', 's.username as server_username', 's.password as server_password')
        ->get();
    
    foreach ($pendingUsers as $pendingService) {
        try {
            // Get service details
            $service = Capsule::table('tblhosting')->where('id', $pendingService->serviceid)->first();
            $client = Capsule::table('tblclients')->where('id', $pendingService->userid)->first();
            
            if (!$service || !$client) {
                continue;
            }
            
            // Build params array
            $params = [
                'serviceid' => $pendingService->serviceid,
                'userid' => $pendingService->userid,
                'serverusername' => $pendingService->server_username,
                'serverpassword' => $pendingService->server_password,
                'clientsdetails' => [
                    'email' => $client->email,
                    'firstname' => $client->firstname,
                    'lastname' => $client->lastname
                ]
            ];
            
            // Call the CreateMultiPortalUser function
            $result = multiportal_CreateMultiPortalUser($params);
            
            if (strpos($result, 'success') === 0) {
                // Remove the pending flag
                Capsule::table('tblcustomfieldsvalues')
                    ->where('fieldid', function($query) {
                        $query->select('id')
                            ->from('tblcustomfields')
                            ->where('fieldname', 'MultiPortal User Creation Pending')
                            ->limit(1);
                    })
                    ->where('relid', $pendingService->serviceid)
                    ->delete();
                    
                logActivity("MultiPortal: Successfully created user for service ID {$pendingService->serviceid}");
            }
        } catch (Exception $e) {
            logActivity("MultiPortal: Failed to create user for service ID {$pendingService->serviceid}: " . $e->getMessage());
        }
    }
});

/**
 * Hook to sync usage when viewing service details in admin
 */
add_hook('AdminServiceEdit', 1, function($vars) {
    $serviceId = $vars['serviceid'];
    
    // Check if this is a MultiPortal service
    $service = Capsule::table('tblhosting as h')
        ->join('tblservers as s', 'h.server', '=', 's.id')
        ->where('h.id', $serviceId)
        ->where('s.type', 'multiportal')
        ->first();
    
    if ($service) {
        // Note: Auto-migration moved to multiportal_AdminCustomButtonArray() in multiportal.php
        // AdminServiceEdit fires on save, not page view, so it can't auto-migrate on view.

        // Trigger usage sync for this specific service
        try {
            $vdcUUID = getProductCustomFieldValue($serviceId, 'VDC UUID');
            
            if (!empty($vdcUUID)) {
                $api = new ApiClient($service->username, $service->password);
                $vdcMgr = new VDCManager($api);
                $usage = $vdcMgr->getVDCUsage($vdcUUID);
                
                if ($usage) {
                    // Store in session for display
                    $_SESSION['multiportal_usage_' . $serviceId] = $usage;
                    $_SESSION['multiportal_usage_timestamp_' . $serviceId] = time();
                }
            }
        } catch (Exception $e) {
            // Silent fail - don't interrupt admin view
        }
    }
});