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