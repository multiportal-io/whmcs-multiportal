<?php

require_once __DIR__ . '/lib/ApiClient.php';
require_once __DIR__ . '/lib/ResellerManager.php';
require_once __DIR__ . '/lib/TenantManager.php';
require_once __DIR__ . '/lib/VDCManager.php';
require_once __DIR__ . '/lib/CustomFieldFunctions.php';
require_once __DIR__ . '/lib/ModuleConfiguration.php';

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Module metadata - required for WHMCS server modules
 */
function multiportal_MetaData()
{
    return array(
        'DisplayName' => 'MultiPortal',
        'APIVersion' => '1.0',
        'RequiresServer' => true,
        'DefaultNonSSLPort' => '80',
        'DefaultSSLPort' => '443',
        'ServiceSingleSignOnLabel' => 'Access MultiPortal',
        'AdminSingleSignOnLabel' => 'Access MultiPortal Admin',
    );
}

/**
 * Generate a secure password
 */
function generateSecurePassword($length = 16) {
    // Ensure minimum length of 8
    $length = max(8, $length);
    
    // Character sets
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $numbers = '0123456789';
    $symbols = '!@#$%^&*';
    
    // Ensure at least one of each required character type
    $password = '';
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $symbols[random_int(0, strlen($symbols) - 1)];
    
    // Fill the rest of the password
    $allChars = $lowercase . $uppercase . $numbers . $symbols;
    $remaining = $length - 4;
    
    for ($i = 0; $i < $remaining; $i++) {
        $password .= $allChars[random_int(0, strlen($allChars) - 1)];
    }
    
    // Shuffle the password to avoid predictable patterns
    $password = str_shuffle($password);
    
    return $password;
}

/**
 * Ensure custom fields exist - create if missing
 */
function ensureCustomFieldsExist()
{
    $created = [];

    try {
        // Check and create client custom field
        $clientField = Capsule::table('tblcustomfields')
            ->where('type', 'client')
            ->where('fieldname', 'MultiPortal Tenant UUID')
            ->first();

        if (!$clientField) {
            Capsule::table('tblcustomfields')->insert([
                'type' => 'client',
                'fieldname' => 'MultiPortal Tenant UUID',
                'fieldtype' => 'text',
                'description' => 'Stores the MultiPortal tenant identifier',
                'fieldoptions' => '',
                'regexpr' => '',
                'adminonly' => 'on',
                'required' => '',
                'showorder' => 'on',
                'showinvoice' => '',
                'sortorder' => 0
            ]);
            $created[] = 'Client field: MultiPortal Tenant UUID';
        }
    } catch (Exception $e) {
        throw new Exception('Error creating client field: ' . $e->getMessage());
    }

    try {
        // Check and create VDC UUID field
        $vdcField = Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('fieldname', 'VDC UUID')
            ->first();

        if (!$vdcField) {
            // This needs to be created PER PRODUCT in the Setup Wizard, not here
            // We'll create it for the specific product in multiportal_SetupWizard
        }
    } catch (Exception $e) {
        throw new Exception('Error creating Virtual Data Center UUID field: ' . $e->getMessage());
    }

    try {
        // Check and create delete confirmation field
        $deleteField = Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('fieldname', 'delete_vdc_confirm')
            ->first();

        if (!$deleteField) {
            Capsule::table('tblcustomfields')->insert([
                'type' => 'product',
                'relid' => 0, // 0 means applies to all products
                'fieldname' => 'delete_vdc_confirm',
                'fieldtype' => 'text',
                'description' => '',
                'fieldoptions' => '',
                'regexpr' => '',
                'adminonly' => 'on',
                'required' => '',
                'showorder' => '',
                'showinvoice' => '',
                'sortorder' => 999
            ]);
            $created[] = 'Product field: delete_vdc_confirm';
        }
    } catch (Exception $e) {
        throw new Exception('Error creating delete field: ' . $e->getMessage());
    }

    return $created;
}

/**
 * Log debug information to WHMCS module log
 */
function multiportal_log($action, $request, $response, $processedData = [], $replaceVars = [])
{
    // Replace sensitive data in logs
    $debugData = [
        'request' => $request,
        'response' => $response,
        'processed' => $processedData
    ];

    foreach ($replaceVars as $search => $replace) {
        $debugData = json_decode(
            str_replace($search, $replace, json_encode($debugData)),
            true
        );
    }

    logModuleCall('multiportal', $action, $debugData['request'], $debugData['response'], $debugData['processed']);
}

/**
 * Validate required configuration options
 */
function multiportal_validateConfig($params)
{
    // Use ModuleConfiguration class for validation
    return ModuleConfiguration::validate($params);
}


/**
 * Module Activation - Create required custom fields
 */
function multiportal_activate()
{
    try {
        logModuleCall('multiportal', 'activate', 'Starting activation', '', '');
        // Create client custom field for Tenant UUID
        $clientField = Capsule::table('tblcustomfields')
            ->where('type', 'client')
            ->where('fieldname', 'MultiPortal Tenant UUID')
            ->first();

        if (!$clientField) {
            Capsule::table('tblcustomfields')->insert([
                'type' => 'client',
                'fieldname' => 'MultiPortal Tenant UUID',
                'fieldtype' => 'text',
                'description' => 'Stores the MultiPortal tenant identifier',
                'fieldoptions' => '',
                'regexpr' => '',
                'adminonly' => 'on',
                'required' => '',
                'showorder' => 'on',
                'showinvoice' => '',
                'sortorder' => 0
            ]);
        }
        
        // Create client custom field for MultiPortal Username
        $clientUsernameField = Capsule::table('tblcustomfields')
            ->where('type', 'client')
            ->where('fieldname', 'MultiPortal Username')
            ->first();

        if (!$clientUsernameField) {
            Capsule::table('tblcustomfields')->insert([
                'type' => 'client',
                'fieldname' => 'MultiPortal Username',
                'fieldtype' => 'text',
                'description' => 'MultiPortal login username',
                'fieldoptions' => '',
                'regexpr' => '',
                'adminonly' => 'on',
                'required' => '',
                'showorder' => 'on',
                'showinvoice' => '',
                'sortorder' => 0
            ]);
        }
        
        // Create client custom field for MultiPortal Password
        $clientPasswordField = Capsule::table('tblcustomfields')
            ->where('type', 'client')
            ->where('fieldname', 'MultiPortal Password')
            ->first();

        if (!$clientPasswordField) {
            Capsule::table('tblcustomfields')->insert([
                'type' => 'client',
                'fieldname' => 'MultiPortal Password',
                'fieldtype' => 'password',
                'description' => 'MultiPortal login password',
                'fieldoptions' => '',
                'regexpr' => '',
                'adminonly' => 'on',
                'required' => '',
                'showorder' => 'on',
                'showinvoice' => '',
                'sortorder' => 0
            ]);
        }
        
        // Create client custom field for MultiPortal URL
        $clientUrlField = Capsule::table('tblcustomfields')
            ->where('type', 'client')
            ->where('fieldname', 'MultiPortal URL')
            ->first();

        if (!$clientUrlField) {
            Capsule::table('tblcustomfields')->insert([
                'type' => 'client',
                'fieldname' => 'MultiPortal URL',
                'fieldtype' => 'text',
                'description' => 'MultiPortal tenant URL',
                'fieldoptions' => '',
                'regexpr' => '',
                'adminonly' => 'on',
                'required' => '',
                'showorder' => 'on',
                'showinvoice' => '',
                'sortorder' => 0
            ]);
        }

        // Create product custom field for VDC UUID
        $productField = Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('fieldname', 'VDC UUID')
            ->first();

        if (!$productField) {
            Capsule::table('tblcustomfields')->insert([
                'type' => 'product',
                'fieldname' => 'VDC UUID',
                'fieldtype' => 'text',
                'description' => 'Stores the Virtual Data Center identifier',
                'fieldoptions' => '',
                'regexpr' => '',
                'adminonly' => 'on',
                'required' => '',
                'showorder' => '',
                'showinvoice' => '',
                'sortorder' => 0,
                'relid' => 0 // 0 means applies to all products
            ]);
        }
        
        // Create product custom field for MultiPortal Username
        $usernameField = Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('fieldname', 'MultiPortal Username')
            ->first();
        if (!$usernameField) {
            Capsule::table('tblcustomfields')->insert([
                'type' => 'product',
                'fieldname' => 'MultiPortal Username',
                'fieldtype' => 'text',
                'description' => 'MultiPortal login username',
                'fieldoptions' => '',
                'regexpr' => '',
                'adminonly' => 'on',
                'required' => '',
                'showorder' => '',
                'showinvoice' => '',
                'sortorder' => 1,
                'relid' => 0
            ]);
        }
        
        // Create product custom field for MultiPortal Password
        $passwordField = Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('fieldname', 'MultiPortal Password')
            ->first();
        if (!$passwordField) {
            Capsule::table('tblcustomfields')->insert([
                'type' => 'product',
                'fieldname' => 'MultiPortal Password',
                'fieldtype' => 'password',
                'description' => 'MultiPortal login password',
                'fieldoptions' => '',
                'regexpr' => '',
                'adminonly' => 'on',
                'required' => '',
                'showorder' => '',
                'showinvoice' => '',
                'sortorder' => 2,
                'relid' => 0
            ]);
        }
        
        // Create product custom field for MultiPortal URL
        $urlField = Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('fieldname', 'MultiPortal URL')
            ->first();
        if (!$urlField) {
            Capsule::table('tblcustomfields')->insert([
                'type' => 'product',
                'fieldname' => 'MultiPortal URL',
                'fieldtype' => 'text',
                'description' => 'MultiPortal tenant URL',
                'fieldoptions' => '',
                'regexpr' => '',
                'adminonly' => 'on',
                'required' => '',
                'showorder' => '',
                'showinvoice' => '',
                'sortorder' => 3,
                'relid' => 0
            ]);
        }

        return ['status' => 'success', 'description' => 'MultiPortal module activated successfully'];
    } catch (Exception $e) {
        return ['status' => 'error', 'description' => 'MultiPortal activation failed: ' . $e->getMessage()];
    }
}

/**
 * Module Deactivation - Optionally remove custom fields
 */
function multiportal_deactivate()
{
    // We typically don't delete custom fields to preserve existing data
    // Uncomment below if you want to remove them on deactivation

    // Capsule::table('tblcustomfields')
    //     ->whereIn('fieldname', ['MultiPortal Tenant UUID', 'VDC UUID'])
    //     ->delete();

    return ['status' => 'success', 'description' => 'MultiPortal module deactivated'];
}

/**
 * Get client custom field value by name
 */
function getClientCustomFieldValue($params, $fieldName)
{
    $customField = Capsule::table('tblcustomfields')
        ->where('type', 'client')
        ->where('fieldname', $fieldName)
        ->first();

    if (!$customField) {
        return null;
    }

    // Check if value exists in params (format: customfieldsX where X is the field ID)
    $fieldKey = 'customfields' . $customField->id;
    if (isset($params['clientsdetails'][$fieldKey])) {
        return $params['clientsdetails'][$fieldKey];
    }

    // If not in params, query the database directly
    $value = Capsule::table('tblcustomfieldsvalues')
        ->where('fieldid', $customField->id)
        ->where('relid', $params['userid'])
        ->first();

    return $value ? $value->value : null;
}

/**
 * Get product custom field value for a service
 */
function getProductCustomFieldValue($serviceId, $fieldName)
{
    $customField = Capsule::table('tblcustomfields')
        ->where('type', 'product')
        ->where('fieldname', $fieldName)
        ->first();

    if (!$customField) {
        return null;
    }

    $value = Capsule::table('tblcustomfieldsvalues')
        ->where('fieldid', $customField->id)
        ->where('relid', $serviceId)
        ->first();

    return $value ? $value->value : null;
}


/**
 * Update or create product custom field value for a service
 */
function updateProductCustomFieldValue($serviceId, $fieldName, $fieldValue)
{
    // Get the service details to find the product ID
    $service = Capsule::table('tblhosting')
        ->where('id', $serviceId)
        ->first();
    
    if (!$service) {
        return false;
    }
    
    // Find the custom field
    $customField = Capsule::table('tblcustomfields')
        ->where('type', 'product')
        ->where('fieldname', $fieldName)
        ->where('relid', $service->packageid)
        ->first();
    
    if (!$customField) {
        return false;
    }
    
    // Check if value already exists
    $existingValue = Capsule::table('tblcustomfieldsvalues')
        ->where('fieldid', $customField->id)
        ->where('relid', $serviceId)
        ->first();
    
    if ($existingValue) {
        // Update existing value
        Capsule::table('tblcustomfieldsvalues')
            ->where('id', $existingValue->id)
            ->update(['value' => $fieldValue]);
    } else {
        // Insert new value
        Capsule::table('tblcustomfieldsvalues')->insert([
            'fieldid' => $customField->id,
            'relid' => $serviceId,
            'value' => $fieldValue
        ]);
    }
    
    return true;
}

function multiportal_ConfigOptions($params)
{
    // $clientId = $params['serverusername']; // set this in module settings
    // $clientSecret = $params['serverpassword'];

    // if (empty($clientId) || empty($clientSecret)) {
    //     throw new Exception('Client ID and Client Secret are required.');
    // }

    // $api = new ApiClient($clientId, $clientSecret);
    return [
        'API Base URL' => ['Type' => 'text', 'Size' => '50', 'Default' => 'https://myfqdn.domain.local/api/v1', 'Description' => 'MultiPortal API endpoint URL'],
        'Data Center UUID' => ['Type' => 'text', 'Size' => '40', 'Description' => 'UUID of the data center'],
        'Reseller UUID' => ['Type' => 'text', 'Size' => '40', 'Description' => 'UUID of the reseller'],
        'PAYG CPU Rate ($/hour)' => ['Type' => 'text', 'Size' => '10', 'Default' => '0.10', 'Description' => 'Cost per CPU core per hour for PAYG'],
        'PAYG Memory Rate ($/GB/hour)' => ['Type' => 'text', 'Size' => '10', 'Default' => '0.05', 'Description' => 'Cost per GB of RAM per hour for PAYG'],
        'PAYG Storage Rate ($/GB/hour)' => ['Type' => 'text', 'Size' => '10', 'Default' => '0.01', 'Description' => 'Cost per GB of storage per hour for PAYG'],
    ];
}

function multiportal_AdminCustomButtonArray($params)
{
    $buttonarray = array();

    // Debug logging to understand params structure
    multiportal_log('AdminCustomButtonArray', [
        'serviceid' => $params['serviceid'],
        'customfields_exists' => isset($params['customfields']),
        'customfields' => $params['customfields'] ?? 'not set',
        'params_keys' => array_keys($params)
    ], 'Debug params structure');

    // Get VDC UUID - try params first, then database
    $vdcId = isset($params['customfields']['VDC UUID']) ? $params['customfields']['VDC UUID'] : null;

    if (!$vdcId) {
        // If not in params, get directly from database
        $vdcField = Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('fieldname', 'VDC UUID')
            ->first();

        if ($vdcField) {
            $value = Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $vdcField->id)
                ->where('relid', $params['serviceid'])
                ->first();
            $vdcId = $value ? $value->value : null;
        }
    }

    // Add setup wizard button if no configurable options exist
    if (!$vdcId && empty($params['configoptions'])) {
        $buttonarray['Setup Product Options'] = 'SetupWizard';
    }

    if ($vdcId) {
        // VDC exists, show update button
        $buttonarray['Update'] = 'UpdateVDC';

        // Get VDC status to show appropriate suspend/unsuspend button
        try {
            $api = initiateAPI($params);
            $vdcMgr = new VDCManager($api);
            $vdc = $vdcMgr->getVDCById($vdcId);

            if ($vdc && isset($vdc['is_enabled'])) {
                if ($vdc['is_enabled'] == 1) {
                    $buttonarray['Suspend'] = 'DisableVdc';
                } else {
                    $buttonarray['Unsuspend'] = 'EnableVdc';
                }
            }

            $buttonarray['Sync Data'] = 'SyncVDC';
            $buttonarray['View Usage'] = 'ViewUsage';
            $buttonarray['Sync Usage & Bill'] = 'SyncUsageAndBill';
            $buttonarray['Create/Reset User'] = 'CreateMultiPortalUser';
            $buttonarray['Delete Virtual Data Center ⚠️'] = 'DestroyVdc';
        } catch (Exception $e) {
            // If we can't get VDC status, show both buttons
            multiportal_log('AdminCustomButtonArray', ['error' => $e->getMessage()], 'Failed to get Virtual Data Center status');
            $buttonarray['Suspend'] = 'DisableVdc';
            $buttonarray['Unsuspend'] = 'EnableVdc';
        }
    }

    return $buttonarray;
}

/**
 * Client Area Custom Button Array
 */
function multiportal_ClientAreaCustomButtonArray($params)
{
    $buttonarray = array();
    
    // Get VDC UUID to check if VDC exists
    $vdcId = isset($params['customfields']['VDC UUID']) ? $params['customfields']['VDC UUID'] : getProductCustomFieldValue($params['serviceid'], 'VDC UUID');
    
    if ($vdcId) {
        // Additional user creation has been removed
    }
    
    return $buttonarray;
}

function initiateAPI($params)
{
    $clientId = ModuleConfiguration::getClientId($params);
    $clientSecret = ModuleConfiguration::getClientSecret($params);
    $baseUrl = ModuleConfiguration::get($params, ModuleConfiguration::FIELD_API_URL);

    // Debug logging (optional - can be removed in production)
    multiportal_log('initiateAPI', [
        'clientId' => substr($clientId, 0, 10) . '...',
        'baseUrl' => $baseUrl
    ], 'API initialization');

    // SSL verification - defaults to true for security
    // SSL verification setting
    // TODO: Add SSL verification as a configuration option if needed
    $sslVerify = false; // TODO: Set to true in production
    if (!$sslVerify && function_exists('logModuleCall')) {
        logModuleCall('multiportal', 'SSL Warning', 'SSL verification is disabled. This should only be used in development environments.', '', '', []);
    }
    
    return new ApiClient($clientId, $clientSecret, $baseUrl, $sslVerify);
}

/**
 * Debug configuration - logs all configuration values
 */
function multiportal_debugConfig($params, $action = 'Debug Config')
{
    ModuleConfiguration::debug($params);
}

function multiportal_UpdateVDC(array $params)
{
    try {
        // Validate configuration
        $validationErrors = multiportal_validateConfig($params);
        if (!empty($validationErrors)) {
            throw new Exception('Configuration errors: ' . implode(', ', $validationErrors));
        }

        $api = initiateAPI($params);
        $vdcMgr = new VDCManager($api);

        $dataCenterId = ModuleConfiguration::get($params, ModuleConfiguration::FIELD_DATA_CENTER_ID);
        $vdcId = isset($params['customfields']['VDC UUID']) ? $params['customfields']['VDC UUID'] : getProductCustomFieldValue($params['serviceid'], 'VDC UUID');

        if (empty($vdcId)) {
            throw new Exception('Virtual Data Center UUID not found. Please create the Virtual Data Center first.');
        }

        multiportal_log('UpdateVDC', ['vdcId' => $vdcId, 'dataCenterId' => $dataCenterId], 'Starting Virtual Data Center update');

        $res = $vdcMgr->getStoragePoliciesByDataCenter($dataCenterId);
        $storagePolicyConfig = verifyStoragePolicyOptions($params['configoptions'], $res);

        // Determine allocation type from configurable option
        $allocationType = 1; // Default to Allocation
        if (isset($params['configoptions']['Allocation Type'])) {
            $selectedType = $params['configoptions']['Allocation Type'];
            // Convert the selection to allocation type ID
            if (stripos($selectedType, 'pay as you go') !== false || stripos($selectedType, 'payg') !== false) {
                $allocationType = 2;
            }
        }

        $vdc = $vdcMgr->updateVDC(
            $vdcId,
            [
                'vdc_name' => 'VDC - ' . $params['serviceid'],
                'allocation_type' => $allocationType,
                'memory_in_gb' => (int) $params['configoptions']['Memory Allocation'],
                'core_count' => (int) $params['configoptions']['CPU'],
                'is_enabled' => 1,
            ]
        );

        multiportal_log('UpdateVDC', ['vdcId' => $vdcId], $vdc, [], ['serverpassword' => '***']);

        if (isset($vdc['error'])) {
            throw new Exception('Failed to update VDC: ' . $vdc['error']);
        }

        setCustomFieldValue($params['serviceid'], 'VDC UUID', $vdc['uuid']);

        // Get existing storage policies
        $storagePolicies = $vdcMgr->getStoragePolicy($vdcId);

        $existingStoragePolicies = [];
        foreach ($storagePolicies['data'] as $storagePolicy) {
            $existingStoragePolicies[$storagePolicy['storage_policy_id']] = $storagePolicy;
        }

        // Process storage policy updates
        foreach ($storagePolicyConfig as $config) {
            $storagePolicyId = $config['storage_policy_id'];
            $capacity = (int) $config['capacity'];

            if (isset($existingStoragePolicies[$storagePolicyId])) {
                $vdcStoragePolicyId = $existingStoragePolicies[$storagePolicyId]['uuid'];
                if ($capacity === 0) {
                    // Delete policy if capacity is 0
                    $vdcMgr->deleteStoragePolicy($vdcId, $vdcStoragePolicyId);
                } else {
                    // Update existing policy
                    $vdcMgr->updateStoragePolicy($vdcId, $vdcStoragePolicyId, [
                        'storage_policy_id' => $storagePolicyId,
                        'capacity' => $capacity,
                    ]);
                }
            } else {
                if ($capacity > 0) {
                    // Add new policy if it doesn't exist and capacity is positive
                    $vdcMgr->addStoragePolicy($vdcId, [
                        'storage_policy_id' => $storagePolicyId,
                        'capacity' => $capacity,
                    ]);
                }
            }
        }

        return 'success';
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Sync VDC data from API and update local records
 */
function multiportal_SyncVDC(array $params)
{
    try {
        $api = initiateAPI($params);
        $vdcMgr = new VDCManager($api);

        $vdcId = isset($params['customfields']['VDC UUID']) ? $params['customfields']['VDC UUID'] : getProductCustomFieldValue($params['serviceid'], 'VDC UUID');
        if (empty($vdcId)) {
            throw new Exception('Virtual Data Center UUID not found. Cannot sync non-existent Virtual Data Center.');
        }

        multiportal_log('SyncVDC', ['vdcId' => $vdcId], 'Starting Virtual Data Center sync');

        // Get VDC details from API
        $vdc = $vdcMgr->getVDCById($vdcId);
        if (!$vdc || isset($vdc['error'])) {
            throw new Exception('Failed to fetch Virtual Data Center data: ' . ($vdc['error'] ?? 'VDC not found'));
        }

        // Get storage policies
        $storagePolicies = $vdcMgr->getStoragePolicy($vdcId);

        multiportal_log('SyncVDC', ['vdcId' => $vdcId], $vdc, ['storagePolicies' => $storagePolicies]);

        // Update service details with latest data (don't set domain to prevent IP/Website buttons)
        $updateData = [
            'notes' => "VDC Status: " . ($vdc['is_enabled'] ? 'Enabled' : 'Disabled') . "\n" .
                "CPU Cores: " . $vdc['core_count'] . "\n" .
                "Memory: " . $vdc['memory_in_gb'] . " GB\n" .
                "Last Synced: " . date('Y-m-d H:i:s')
        ];

        // Update service status based on VDC status
        if ($vdc['is_enabled'] == 1 && $params['status'] == 'Suspended') {
            $updateData['domainstatus'] = 'Active';
        } elseif ($vdc['is_enabled'] == 0 && $params['status'] == 'Active') {
            $updateData['domainstatus'] = 'Suspended';
        }

        // Update service in WHMCS
        Capsule::table('tblhosting')
            ->where('id', $params['serviceid'])
            ->update($updateData);

        // Update configurable options to reflect actual VDC values
        // First, get the product's configurable options
        $productId = $params['pid'];
        $serviceId = $params['serviceid'];

        // Find CPU and Memory configurable options
        // First get the config groups linked to this product
        $configGroups = Capsule::table('tblproductconfiglinks')
            ->where('pid', $productId)
            ->pluck('gid');

        $configOptions = Capsule::table('tblproductconfigoptions')
            ->whereIn('gid', $configGroups)
            ->whereIn('optionname', ['CPU', 'Memory Allocation'])
            ->get();

        foreach ($configOptions as $option) {
            // Determine which value to sync
            $syncValue = null;
            if ($option->optionname == 'CPU') {
                $syncValue = $vdc['core_count'];
            } elseif ($option->optionname == 'Memory Allocation') {
                $syncValue = $vdc['memory_in_gb'];
            }

            if ($syncValue !== null) {
                // For quantity-based options (text fields), get the first sub-option
                $subOption = Capsule::table('tblproductconfigoptionssub')
                    ->where('configid', $option->id)
                    ->first();

                if ($subOption) {
                    // Update or insert the configurable option value
                    $existingValue = Capsule::table('tblhostingconfigoptions')
                        ->where('relid', $serviceId)
                        ->where('configid', $option->id)
                        ->first();

                    if ($existingValue) {
                        Capsule::table('tblhostingconfigoptions')
                            ->where('id', $existingValue->id)
                            ->update([
                                'optionid' => $subOption->id,
                                'qty' => $syncValue
                            ]);
                    } else {
                        Capsule::table('tblhostingconfigoptions')
                            ->insert([
                                'relid' => $serviceId,
                                'configid' => $option->id,
                                'optionid' => $subOption->id,
                                'qty' => $syncValue
                            ]);
                    }
                }
            }
        }

        // Sync storage policies
        if (isset($storagePolicies['data']) && is_array($storagePolicies['data'])) {
            $dataCenterId = ModuleConfiguration::get($params, ModuleConfiguration::FIELD_DATA_CENTER_ID);
            $dcStoragePolicies = $vdcMgr->getStoragePoliciesByDataCenter($dataCenterId);

            foreach ($dcStoragePolicies['data'] as $dcPolicy) {
                $configOptionName = "Storage - {$dcPolicy['name']}";

                // Find this storage policy in VDC
                $vdcPolicyCapacity = 0;
                foreach ($storagePolicies['data'] as $vdcPolicy) {
                    if ($vdcPolicy['storage_policy_id'] == $dcPolicy['uuid']) {
                        $vdcPolicyCapacity = $vdcPolicy['capacity'];
                        break;
                    }
                }

                // Update the configurable option
                $storageOption = Capsule::table('tblproductconfigoptions')
                    ->whereIn('gid', $configGroups)
                    ->where('optionname', $configOptionName)
                    ->first();

                if ($storageOption) {
                    // For quantity-based options, update the quantity
                    $existingValue = Capsule::table('tblhostingconfigoptions')
                        ->where('relid', $serviceId)
                        ->where('configid', $storageOption->id)
                        ->first();

                    if ($existingValue) {
                        Capsule::table('tblhostingconfigoptions')
                            ->where('id', $existingValue->id)
                            ->update(['qty' => $vdcPolicyCapacity]);
                    } elseif ($vdcPolicyCapacity > 0) {
                        // Get the first sub-option (for quantity-based options)
                        $subOption = Capsule::table('tblproductconfigoptionssub')
                            ->where('configid', $storageOption->id)
                            ->first();

                        if ($subOption) {
                            Capsule::table('tblhostingconfigoptions')
                                ->insert([
                                    'relid' => $serviceId,
                                    'configid' => $storageOption->id,
                                    'optionid' => $subOption->id,
                                    'qty' => $vdcPolicyCapacity
                                ]);
                        }
                    }
                }
            }
        }

        return 'success';
    } catch (Exception $e) {
        multiportal_log('SyncVDC', $params, ['error' => $e->getMessage()]);
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * View VDC Usage
 */
function multiportal_ViewUsage(array $params)
{
    try {
        $api = initiateAPI($params);
        $vdcMgr = new VDCManager($api);

        $vdcId = isset($params['customfields']['VDC UUID']) ? $params['customfields']['VDC UUID'] : getProductCustomFieldValue($params['serviceid'], 'VDC UUID');
        if (empty($vdcId)) {
            throw new Exception('VDC UUID not found. Cannot view usage for non-existent VDC.');
        }

        multiportal_log('ViewUsage', ['vdcId' => $vdcId], 'Fetching VDC usage');

        // Get VDC usage data
        $usage = $vdcMgr->getVDCUsage($vdcId);

        multiportal_log('ViewUsage', ['vdcId' => $vdcId, 'usage_keys' => array_keys($usage ?? [])], $usage);

        // Format the usage data for display
        $message = "VDC Usage Statistics:\n\n";

        // Debug: Show what keys are in the response
        if (is_array($usage)) {
            $message .= "Debug - Available data: " . implode(', ', array_keys($usage)) . "\n\n";
        }

        if (isset($usage['cpu'])) {
            $message .= "CPU Usage:\n";
            $message .= "  - Used: " . ($usage['cpu']['used'] ?? 'N/A') . " cores\n";
            $message .= "  - Allocated: " . ($usage['cpu']['allocated'] ?? 'N/A') . " cores\n";
            $message .= "  - Usage: " . (isset($usage['cpu']['percentage']) ? $usage['cpu']['percentage'] . '%' : 'N/A') . "\n\n";
        }

        if (isset($usage['memory'])) {
            $message .= "Memory Usage:\n";
            $message .= "  - Used: " . ($usage['memory']['used'] ?? 'N/A') . " GB\n";
            $message .= "  - Allocated: " . ($usage['memory']['allocated'] ?? 'N/A') . " GB\n";
            $message .= "  - Usage: " . (isset($usage['memory']['percentage']) ? $usage['memory']['percentage'] . '%' : 'N/A') . "\n\n";
        }

        if (isset($usage['storage']) && is_array($usage['storage'])) {
            $message .= "Storage Usage:\n";
            foreach ($usage['storage'] as $storage) {
                $message .= "  - " . ($storage['policy_name'] ?? 'Unknown Policy') . ":\n";
                $message .= "    • Used: " . ($storage['used'] ?? 'N/A') . " GB\n";
                $message .= "    • Allocated: " . ($storage['allocated'] ?? 'N/A') . " GB\n";
                $message .= "    • Usage: " . (isset($storage['percentage']) ? $storage['percentage'] . '%' : 'N/A') . "\n";
            }
        }

        // Update service notes with usage information
        $updateData = [
            'notes' => "=== VDC Usage (Last Updated: " . date('Y-m-d H:i:s') . ") ===\n" . $message
        ];

        Capsule::table('tblhosting')
            ->where('id', $params['serviceid'])
            ->update($updateData);

        // Return success with message appended
        return $message;
    } catch (Exception $e) {
        multiportal_log('ViewUsage', $params, ['error' => $e->getMessage()]);
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Create or Reset MultiPortal User
 */
function multiportal_CreateMultiPortalUser(array $params)
{
    try {
        $api = initiateAPI($params);
        $tenantMgr = new TenantManager($api);
        
        // Get tenant UUID
        $tenantUUID = getClientCustomFieldValue($params, 'MultiPortal Tenant UUID');
        if (empty($tenantUUID)) {
            throw new Exception('Tenant UUID not found. Cannot create user without tenant.');
        }
        
        // Get tenant details
        $tenant = $tenantMgr->findTenantById($tenantUUID);
        if (!$tenant) {
            throw new Exception('Tenant not found in MultiPortal.');
        }
        
        multiportal_log('CreateMultiPortalUser', ['tenantUUID' => $tenantUUID], 'Starting user creation/reset');
        
        // Generate username from email
        $emailParts = explode('@', $params['clientsdetails']['email']);
        $baseUsername = $emailParts[0];
        $multiportalUsername = $baseUsername . '_' . $params['serviceid'];
        
        // Generate secure password
        $multiportalPassword = generateSecurePassword(16);
        
        // Create user in MultiPortal
        $user = $tenantMgr->createUser(
            $tenant['uuid'],
            $multiportalUsername,
            $multiportalPassword,
            $params['clientsdetails']['email'],
            $params['clientsdetails']['firstname'],
            $params['clientsdetails']['lastname'],
            'Tenant Administrator'
        );
        
        // Store credentials in WHMCS service fields
        Capsule::table('tblhosting')
            ->where('id', $params['serviceid'])
            ->update([
                'username' => $multiportalUsername,
                'password' => encrypt($multiportalPassword)
            ]);
        
        // Get MultiPortal URL from API configuration
        $multiportalUrl = '';
        $apiUrl = ModuleConfiguration::get($params, ModuleConfiguration::FIELD_API_URL);
        if (!empty($apiUrl)) {
            // Parse the API URL to get the base domain
            $parsedUrl = parse_url($apiUrl);
            if (isset($parsedUrl['host'])) {
                $multiportalUrl = 'https://' . $parsedUrl['host'];
            }
        }
        
        // If empty, throw error
        if (empty($multiportalUrl)) {
            throw new Exception('MultiPortal URL could not be determined. Please check API Base URL in server configuration.');
        }
        
        // Don't store URL in domain field to prevent WHMCS from showing IP/Website buttons
        // The URL is available in the client area template instead
        
        multiportal_log('CreateMultiPortalUser', [
            'username' => $multiportalUsername,
            'url' => $multiportalUrl,
            'tenant' => $tenant['name']
        ], 'User created/reset successfully');
        
        $message = "MultiPortal User Created/Reset Successfully\n\n";
        $message .= "Username: " . $multiportalUsername . "\n";
        $message .= "Password: " . $multiportalPassword . "\n";
        $message .= "Portal URL: " . $multiportalUrl . "\n\n";
        $message .= "These credentials have been stored in the service custom fields.";
        
        // Return success with message appended
        return $message;
        
    } catch (Exception $e) {
        multiportal_log('CreateMultiPortalUser', $params, ['error' => $e->getMessage()]);
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Create API User for MultiPortal tenant
 */
function multiportal_CreateApiUser(array $params)
{
    try {
        $api = initiateAPI($params);
        $tenantMgr = new TenantManager($api);
        
        // Get tenant UUID
        $tenantUUID = getClientCustomFieldValue($params, 'MultiPortal Tenant UUID');
        if (empty($tenantUUID)) {
            throw new Exception('Tenant UUID not found. Cannot create API user without tenant.');
        }
        
        // Check if API credentials already exist at client level
        $existingApiUsername = getClientCustomFieldValue($params, 'MultiPortal API Username');
        $existingApiPassword = getClientCustomFieldValue($params, 'MultiPortal API Password');
        
        if ($existingApiUsername && $existingApiPassword) {
            // API user already exists, return existing credentials
            $tenant = $tenantMgr->findTenantById($tenantUUID);
            
            // Get tenant URL
            if (isset($tenant['domain']) && !empty($tenant['domain'])) {
                $multiportalUrl = 'https://' . $tenant['domain'];
            } else {
                $tenantName = preg_replace('/[^a-z0-9\-]/', '', strtolower($tenant['name']));
                $multiportalUrl = 'https://' . $tenantName . '.multiportal.io';
            }
            
            $message = "Additional MultiPortal User Already Exists\n\n";
            $message .= "Username: " . $existingApiUsername . "\n";
            $message .= "Password: " . $existingApiPassword . "\n";
            $message .= "Portal URL: " . $multiportalUrl . "\n\n";
            $message .= "These credentials are shared across all services for this client.";
            
            // Store message in session for display
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['multiportal_message'] = $message;
            
            return 'success';
        }
        
        // Get tenant details
        $tenant = $tenantMgr->findTenantById($tenantUUID);
        if (!$tenant) {
            throw new Exception('Tenant not found in MultiPortal.');
        }
        
        multiportal_log('CreateApiUser', ['tenantUUID' => $tenantUUID], 'Starting API user creation');
        
        // Check if a user with this email already exists in the tenant
        $existingUsers = $tenantMgr->getUsersByTenant($tenantUUID);
        $emailExists = false;
        $usernameExists = false;
        
        if (!empty($existingUsers)) {
            foreach ($existingUsers as $user) {
                if (isset($user['email']) && strtolower($user['email']) === strtolower($params['clientsdetails']['email'])) {
                    $emailExists = true;
                }
                // Check for existing usernames for this client
                if (isset($user['username']) && strpos($user['username'], 'user' . $params['userid']) === 0) {
                    $usernameExists = true;
                }
            }
        }
        
        // Generate unique username for the user with API access
        if ($usernameExists) {
            // If username exists, add more randomness
            $apiUsername = 'user' . $params['userid'] . '_' . rand(1000, 9999);
        } else {
            $apiUsername = 'user' . $params['userid'] . '_' . time();
        }
        
        // Generate secure API password (16 chars instead of 32)
        $apiPassword = generateSecurePassword(16);
        
        // Use a different email if the client's email already exists
        if ($emailExists) {
            // Extract domain from client email
            $emailParts = explode('@', $params['clientsdetails']['email']);
            $emailDomain = isset($emailParts[1]) ? $emailParts[1] : 'example.com';
            $apiEmail = $apiUsername . '@' . $emailDomain;
        } else {
            $apiEmail = $params['clientsdetails']['email'];
        }
        
        multiportal_log('CreateApiUser', [
            'username' => $apiUsername,
            'email' => $apiEmail,
            'emailExists' => $emailExists,
            'tenantId' => $tenant['uuid'],
            'tenantName' => $tenant['name']
        ], 'Creating API user');
        
        try {
            // Create API user in MultiPortal with Tenant Administrator role
            $apiUser = $tenantMgr->createUser(
                $tenant['uuid'],
                $apiUsername,
                $apiPassword,
                $apiEmail,
                'API',
                'User',
                'Tenant Administrator'
            );
        } catch (Exception $e) {
            multiportal_log('CreateApiUser', [
                'error' => $e->getMessage(),
                'username' => $apiUsername,
                'email' => $apiEmail
            ], 'Failed to create API user');
            throw new Exception('Failed to create API user: ' . $e->getMessage());
        }
        
        // Create client custom fields if they don't exist
        $apiUsernameField = Capsule::table('tblcustomfields')
            ->where('type', 'client')
            ->where('fieldname', 'MultiPortal API Username')
            ->first();
        
        $apiPasswordField = Capsule::table('tblcustomfields')
            ->where('type', 'client')
            ->where('fieldname', 'MultiPortal API Password')
            ->first();
        
        if (!$apiUsernameField) {
            Capsule::table('tblcustomfields')->insert([
                'type' => 'client',
                'fieldname' => 'MultiPortal API Username',
                'fieldtype' => 'text',
                'description' => 'API username for MultiPortal access',
                'fieldoptions' => '',
                'regexpr' => '',
                'adminonly' => 'on',
                'required' => '',
                'showorder' => 'on',
                'showinvoice' => '',
                'sortorder' => 0
            ]);
        }
        
        if (!$apiPasswordField) {
            Capsule::table('tblcustomfields')->insert([
                'type' => 'client',
                'fieldname' => 'MultiPortal API Password',
                'fieldtype' => 'password',
                'description' => 'API password for MultiPortal access',
                'fieldoptions' => '',
                'regexpr' => '',
                'adminonly' => 'on',
                'required' => '',
                'showorder' => 'on',
                'showinvoice' => '',
                'sortorder' => 0
            ]);
        }
        
        // Store the API credentials at client level
        setClientCustomFieldValue($params['userid'], 'MultiPortal API Username', $apiUsername);
        setClientCustomFieldValue($params['userid'], 'MultiPortal API Password', $apiPassword);
        
        // Get tenant URL
        if (isset($tenant['domain']) && !empty($tenant['domain'])) {
            $multiportalUrl = 'https://' . $tenant['domain'];
        } else {
            $tenantName = preg_replace('/[^a-z0-9\-]/', '', strtolower($tenant['name']));
            $multiportalUrl = 'https://' . $tenantName . '.multiportal.io';
        }
        
        multiportal_log('CreateApiUser', [
            'username' => $apiUsername,
            'url' => $multiportalUrl,
            'tenant' => $tenant['name']
        ], 'API user created successfully');
        
        $message = "Additional MultiPortal User Created Successfully\n\n";
        $message .= "Username: " . $apiUsername . "\n";
        $message .= "Password: " . $apiPassword . "\n";
        $message .= "Portal URL: " . $multiportalUrl . "\n\n";
        $message .= "These credentials have been stored at the client level and will be shared across all services.\n";
        $message .= "This user has Tenant Administrator role with full access to the portal.";
        
        // Return success with message appended
        return $message;
        
    } catch (Exception $e) {
        multiportal_log('CreateApiUser', $params, ['error' => $e->getMessage()]);
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Sync usage data from Multiportal and create billable items for PAYG services
 */
function multiportal_SyncUsageAndBill(array $params)
{
    try {
        $api = initiateAPI($params);
        $vdcMgr = new VDCManager($api);

        // Get VDC UUID
        $vdcId = isset($params['customfields']['VDC UUID']) ? $params['customfields']['VDC UUID'] : getProductCustomFieldValue($params['serviceid'], 'VDC UUID');
        if (empty($vdcId)) {
            throw new Exception('VDC UUID not found. Cannot sync usage for non-existent VDC.');
        }

        // Get VDC details to check allocation type
        $vdc = $vdcMgr->getVDCById($vdcId);
        if (!$vdc) {
            throw new Exception('VDC not found.');
        }

        // Log the VDC details for debugging
        multiportal_log('SyncUsageAndBill', [
            'vdcId' => $vdcId,
            'vdc_data' => $vdc,
            'allocation_type' => $vdc['allocation_type'] ?? 'not set'
        ], 'VDC details fetched');

        // Check if this is a PAYG VDC (allocation_type = 2)
        if (!isset($vdc['allocation_type']) || $vdc['allocation_type'] != 2) {
            return 'This Virtual Data Center is not configured for PAYG billing (allocation type: ' . ($vdc['allocation_type'] ?? 'unknown') . '). Full Virtual Data Center data logged for debugging.';
        }

        multiportal_log('SyncUsageAndBill', ['vdcId' => $vdcId], 'Fetching Virtual Data Center usage for PAYG billing');

        // Get or set billing period (default to current month)
        $currentDate = new DateTime();
        $billingStart = new DateTime($currentDate->format('Y-m-01'));
        $billingEnd = clone $currentDate;

        // Check if we have a last sync date stored
        $lastSyncField = Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('fieldname', 'Last Usage Sync')
            ->first();

        if (!$lastSyncField) {
            // Create the custom field if it doesn't exist
            $lastSyncFieldId = Capsule::table('tblcustomfields')->insertGetId([
                'type' => 'product',
                'fieldname' => 'Last Usage Sync',
                'fieldtype' => 'text',
                'description' => 'Last PAYG usage sync date',
                'adminonly' => 'on'
            ]);
        } else {
            $lastSyncFieldId = $lastSyncField->id;
        }

        // Get last sync date
        $lastSyncValue = Capsule::table('tblcustomfieldsvalues')
            ->where('fieldid', $lastSyncFieldId)
            ->where('relid', $params['serviceid'])
            ->first();

        if ($lastSyncValue && $lastSyncValue->value) {
            $billingStart = new DateTime($lastSyncValue->value);
        }

        // Format dates for display
        $periodDescription = sprintf(
            "Usage from %s to %s",
            $billingStart->format('Y-m-d'),
            $billingEnd->format('Y-m-d H:i')
        );

        // Get VDC usage data for the billing period
        $usageParams = [
            'date_range' => $billingStart->format('Y/m/d 00:00:00') . ' - ' . $billingEnd->format('Y/m/d 23:59:59')
        ];
        $usage = $vdcMgr->getVDCUsage($vdcId, $usageParams);
        
        multiportal_log('SyncUsageAndBill', [
            'vdcId' => $vdcId,
            'usage_data' => $usage,
            'date_range' => $usageParams['date_range']
        ], 'Fetched usage data');

        // Check if we got usage data
        if (!$usage || !is_array($usage)) {
            throw new Exception('No usage data returned from API');
        }
        
        // Log the full usage response for debugging
        multiportal_log('SyncUsageAndBill_Debug', [
            'has_summary' => isset($usage['summary']),
            'has_usage_summary' => isset($usage['summary']['usage_summary']),
            'has_usage_breakdown' => isset($usage['usage_breakdown']),
            'usage_keys' => array_keys($usage),
            'summary_keys' => isset($usage['summary']) ? array_keys($usage['summary']) : [],
            'breakdown_keys' => isset($usage['usage_breakdown']) ? array_keys($usage['usage_breakdown']) : []
        ], 'Usage data structure');
        
        // Load rates from module configuration or fall back to config file
        $rates = [];
        
        // Check if rates are configured in module settings using ModuleConfiguration
        $cpuRate = ModuleConfiguration::get($params, ModuleConfiguration::FIELD_PAYG_CPU_RATE, false);
        if ($cpuRate !== null) {
            $rates['cpu_per_hour'] = (float) $cpuRate;
        }
        
        $memoryRate = ModuleConfiguration::get($params, ModuleConfiguration::FIELD_PAYG_MEMORY_RATE, false);
        if ($memoryRate !== null) {
            $rates['memory_per_gb_hour'] = (float) $memoryRate;
        }
        
        $storageRate = ModuleConfiguration::get($params, ModuleConfiguration::FIELD_PAYG_STORAGE_RATE, false);
        if ($storageRate !== null) {
            $rates['storage_per_gb_hour'] = (float) $storageRate;
        }
        
        // Fall back to config file if any rates are missing
        if (empty($rates['cpu_per_hour']) || empty($rates['memory_per_gb_hour']) || empty($rates['storage_per_gb_hour'])) {
            require_once __DIR__ . '/payg_config.php';
            $rates = array_merge([
                'cpu_per_hour' => 0.10,
                'memory_per_gb_hour' => 0.05,
                'storage_per_gb_hour' => 0.01
            ], $rates);
        }

        $totalCharge = 0;
        $chargeDetails = [];
        $debugInfo = [];

        // Calculate hours in billing period
        $interval = $billingStart->diff($billingEnd);
        $totalHours = ($interval->days * 24) + $interval->h + ($interval->i / 60);

        // Get total hours from summary if available, otherwise calculate
        if (isset($usage['summary']['total_hours'])) {
            $totalHours = $usage['summary']['total_hours'];
        }

        // Process resource usage based on the new API structure
        if (isset($usage['summary']['usage_summary'])) {
            $usageSummary = $usage['summary']['usage_summary'];
            $debugInfo['usage_summary'] = $usageSummary;
            
            // CPU charges
            // The API returns total_cpu_usage which appears to be in CPU-seconds
            // Convert to CPU-hours: cpu-seconds / 3600
            if (isset($usageSummary['total_cpu_usage']) && $usageSummary['total_cpu_usage'] > 0) {
                $cpuHours = $usageSummary['total_cpu_usage'] / 3600;
                $cpuCharge = $cpuHours * $rates['cpu_per_hour'];
                $totalCharge += $cpuCharge;
                $chargeDetails[] = sprintf(
                    "CPU: %.2f core-hours × $%.2f = $%.2f",
                    $cpuHours,
                    $rates['cpu_per_hour'],
                    $cpuCharge
                );
                $debugInfo['cpu'] = [
                    'raw_value' => $usageSummary['total_cpu_usage'],
                    'hours' => $cpuHours,
                    'charge' => $cpuCharge
                ];
            }
            
            // Memory charges
            // The API returns total_memory_usage which appears to be in byte-seconds
            // Convert to GB-hours: (byte-seconds / 1024^3) / 3600
            if (isset($usageSummary['total_memory_usage']) && $usageSummary['total_memory_usage'] > 0) {
                $memoryGBHours = ($usageSummary['total_memory_usage'] / (1024 * 1024 * 1024)) / 3600;
                $memoryCharge = $memoryGBHours * $rates['memory_per_gb_hour'];
                $totalCharge += $memoryCharge;
                $chargeDetails[] = sprintf(
                    "Memory: %.2f GB-hours × $%.2f = $%.2f",
                    $memoryGBHours,
                    $rates['memory_per_gb_hour'],
                    $memoryCharge
                );
                $debugInfo['memory'] = [
                    'raw_value' => $usageSummary['total_memory_usage'],
                    'gb_hours' => $memoryGBHours,
                    'charge' => $memoryCharge
                ];
            }
        } else {
            $debugInfo['error'] = 'No usage summary found in response';
        }

        // Process Storage charges
        if (isset($usage['usage_breakdown']['storage']) && is_array($usage['usage_breakdown']['storage'])) {
            $totalStorageGBHours = 0;
            $debugInfo['storage'] = [];
            
            foreach ($usage['usage_breakdown']['storage'] as $storageName => $storageData) {
                $debugInfo['storage'][$storageName] = [
                    'total_usage' => $storageData['total_usage'] ?? 0,
                    'uptime' => $storageData['uptime'] ?? 0
                ];
                
                if (isset($storageData['total_usage']) && isset($storageData['uptime']) && $storageData['uptime'] > 0) {
                    // Convert byte-seconds to GB-hours
                    $storageGBHours = ($storageData['total_usage'] / (1024 * 1024 * 1024)) / 3600;
                    $totalStorageGBHours += $storageGBHours;
                    $debugInfo['storage'][$storageName]['gb_hours'] = $storageGBHours;
                }
            }
            
            if ($totalStorageGBHours > 0) {
                $storageCharge = $totalStorageGBHours * $rates['storage_per_gb_hour'];
                $totalCharge += $storageCharge;
                $chargeDetails[] = sprintf(
                    "Storage: %.2f GB-hours × $%.2f = $%.2f",
                    $totalStorageGBHours,
                    $rates['storage_per_gb_hour'],
                    $storageCharge
                );
                $debugInfo['storage']['total_gb_hours'] = $totalStorageGBHours;
                $debugInfo['storage']['total_charge'] = $storageCharge;
            }
        }

        // Process Backup Storage charges if exists
        if (isset($usage['usage_breakdown']['backup_storage']) && 
            is_array($usage['usage_breakdown']['backup_storage']) &&
            count($usage['usage_breakdown']['backup_storage']) > 0) {
            $totalBackupGBHours = 0;
            
            foreach ($usage['usage_breakdown']['backup_storage'] as $backupName => $backupData) {
                if (isset($backupData['total_usage']) && isset($backupData['uptime']) && $backupData['uptime'] > 0) {
                    // Convert byte-seconds to GB-hours
                    $backupGBHours = ($backupData['total_usage'] / (1024 * 1024 * 1024)) / 3600;
                    $totalBackupGBHours += $backupGBHours;
                }
            }
            
            if ($totalBackupGBHours > 0) {
                $backupCharge = $totalBackupGBHours * $rates['storage_per_gb_hour'];
                $totalCharge += $backupCharge;
                $chargeDetails[] = sprintf(
                    "Backup Storage: %.2f GB-hours × $%.2f = $%.2f",
                    $totalBackupGBHours,
                    $rates['storage_per_gb_hour'],
                    $backupCharge
                );
            }
        }

        // Create separate billable items for each resource type
        $billableItems = [];
        $billingPeriodHash = md5($billingStart->format('Y-m-d') . '-' . $billingEnd->format('Y-m-d'));
        
        // Prepare billable items for each resource type
        if (isset($debugInfo['cpu']) && $debugInfo['cpu']['charge'] > 0) {
            $billableItems[] = [
                'description' => sprintf(
                    "PAYG CPU Usage - %s\nPeriod: %s\nUsage: %.2f core-hours @ $%.2f/hour\n[Period: %s]",
                    $params['domain'],
                    $periodDescription,
                    $debugInfo['cpu']['hours'],
                    $rates['cpu_per_hour'],
                    $billingPeriodHash
                ),
                'amount' => round($debugInfo['cpu']['charge'], 2),
                'qty' => round($debugInfo['cpu']['hours'], 2),
                'unit' => 'hours'
            ];
        }
        
        if (isset($debugInfo['memory']) && $debugInfo['memory']['charge'] > 0) {
            $billableItems[] = [
                'description' => sprintf(
                    "PAYG Memory Usage - %s\nPeriod: %s\nUsage: %.2f GB-hours @ $%.2f/GB-hour\n[Period: %s]",
                    $params['domain'],
                    $periodDescription,
                    $debugInfo['memory']['gb_hours'],
                    $rates['memory_per_gb_hour'],
                    $billingPeriodHash
                ),
                'amount' => round($debugInfo['memory']['charge'], 2),
                'qty' => round($debugInfo['memory']['gb_hours'], 2),
                'unit' => 'hours'
            ];
        }
        
        // Storage items - one for each storage policy
        if (isset($debugInfo['storage']) && is_array($debugInfo['storage'])) {
            foreach ($debugInfo['storage'] as $storageName => $storageInfo) {
                if ($storageName !== 'total_gb_hours' && $storageName !== 'total_charge' && 
                    isset($storageInfo['gb_hours']) && $storageInfo['gb_hours'] > 0) {
                    $storageCharge = $storageInfo['gb_hours'] * $rates['storage_per_gb_hour'];
                    $billableItems[] = [
                        'description' => sprintf(
                            "PAYG Storage Usage (%s) - %s\nPeriod: %s\nUsage: %.2f GB-hours @ $%.2f/GB-hour\n[Period: %s]",
                            $storageName,
                            $params['domain'],
                            $periodDescription,
                            $storageInfo['gb_hours'],
                            $rates['storage_per_gb_hour'],
                            $billingPeriodHash
                        ),
                        'amount' => round($storageCharge, 2),
                        'qty' => round($storageInfo['gb_hours'], 2),
                        'unit' => 'hours'
                    ];
                }
            }
        }
        
        // Create billable items if there are any
        if (count($billableItems) > 0) {
            $createdItems = 0;
            $failedItems = [];
            
            // Check if localAPI function exists
            if (!function_exists('localAPI')) {
                throw new Exception('WHMCS localAPI function not available. Please ensure this module is running within WHMCS.');
            }
            
            foreach ($billableItems as $item) {
                try {
                    // Check for duplicates by searching description for period hash
                    $existingItems = Capsule::table('tblbillableitems')
                        ->where('userid', $params['userid'])
                        ->where('description', 'LIKE', '%[Period: ' . $billingPeriodHash . ']%')
                        ->where('description', 'LIKE', '%' . explode(' - ', $item['description'])[0] . '%')
                        ->count();
                    
                    if ($existingItems > 0) {
                        multiportal_log('SyncUsageAndBill', [
                            'skipped' => true,
                            'reason' => 'Duplicate item for period',
                            'description' => explode("\n", $item['description'])[0]
                        ], 'Skipped duplicate billable item');
                        continue;
                    }
                    
                    $command = 'AddBillableItem';
                    $postData = array(
                        'clientid' => $params['userid'],
                        'description' => $item['description'],
                        'amount' => $item['amount'],
                        'unit' => $item['unit'],
                        'qty' => $item['qty'],
                        'invoiceaction' => 'nextinvoice',
                        'recur' => 0,
                        'duedate' => $currentDate->format('Y-m-d')
                    );
                    
                    $results = localAPI($command, $postData);
                    
                    if ($results['result'] === 'success') {
                        $createdItems++;
                        multiportal_log('SyncUsageAndBill', [
                            'billableItemId' => $results['billableitemid'] ?? null,
                            'description' => explode("\n", $item['description'])[0],
                            'amount' => $item['amount'],
                            'qty' => $item['qty']
                        ], 'Created billable item');
                    } else {
                        $failedItems[] = explode("\n", $item['description'])[0] . ' - Error: ' . ($results['message'] ?? 'Unknown');
                    }
                } catch (Exception $e) {
                    $failedItems[] = explode("\n", $item['description'])[0] . ' - Error: ' . $e->getMessage();
                }
            }
            
            multiportal_log('SyncUsageAndBill', [
                'vdcId' => $vdcId,
                'totalCharge' => $totalCharge,
                'createdItems' => $createdItems,
                'failedItems' => count($failedItems)
            ], 'Billable items creation summary');
            
            if (count($failedItems) > 0) {
                throw new Exception('Some billable items failed to create: ' . implode('; ', $failedItems));
            }
        }

        // Update last sync date
        Capsule::table('tblcustomfieldsvalues')->updateOrInsert(
            [
                'fieldid' => $lastSyncFieldId,
                'relid' => $params['serviceid']
            ],
            [
                'value' => $billingEnd->format('Y-m-d H:i:s')
            ]
        );

        // Log debug info
        multiportal_log('SyncUsageAndBill_Results', [
            'totalCharge' => $totalCharge,
            'chargeDetails' => $chargeDetails,
            'debugInfo' => $debugInfo
        ], 'Calculation results');

        // Prepare success message
        $message = "PAYG Usage Sync Completed\n\n";
        $message .= $periodDescription . "\n\n";

        if (isset($createdItems) && $createdItems > 0) {
            $message .= "Created " . $createdItems . " billable item(s):\n\n";
            
            // Show individual resource charges
            if (!empty($chargeDetails)) {
                foreach ($chargeDetails as $detail) {
                    $message .= "• " . $detail . "\n";
                }
                $message .= "\n";
            }
            
            $message .= "Total Charges: $" . number_format($totalCharge, 2) . "\n\n";
            $message .= "These items will appear on the next invoice.\n";
            
            if (isset($existingItems) && $existingItems > 0) {
                $message .= "\nNote: Some items were skipped as they were already billed for this period.";
            }
        } elseif ($totalCharge > 0) {
            $message .= "Usage detected but no new billable items created.\n";
            $message .= "This may be because items for this period already exist.\n\n";
            $message .= "Calculated charges:\n" . implode("\n", $chargeDetails);
        } else {
            $message .= "No usage charges for this period.\n\n";
            $message .= "Debug Info:\n";
            $message .= "- Has usage summary: " . (isset($usage['summary']['usage_summary']) ? 'Yes' : 'No') . "\n";
            $message .= "- Has storage data: " . (isset($usage['usage_breakdown']['storage']) ? 'Yes' : 'No') . "\n";
            if (isset($debugInfo['usage_summary'])) {
                $message .= "- Total CPU usage: " . ($debugInfo['usage_summary']['total_cpu_usage'] ?? 0) . " seconds\n";
                $message .= "- Total Memory usage: " . ($debugInfo['usage_summary']['total_memory_usage'] ?? 0) . " byte-seconds\n";
            }
        }

        // Update service notes
        $notes = "=== PAYG Usage Sync (Last Updated: " . date('Y-m-d H:i:s') . ") ===\n" . $message;
        Capsule::table('tblhosting')
            ->where('id', $params['serviceid'])
            ->update(['notes' => $notes]);

        return $message;
    } catch (Exception $e) {
        multiportal_log('SyncUsageAndBill', $params, ['error' => $e->getMessage()]);
        return 'Error: ' . $e->getMessage();
    }
}

function multiportal_CreateAccount(array $params)
{
    try {
        // Check if VDC already exists
        $existingVdcId = isset($params['customfields']['VDC UUID']) ? $params['customfields']['VDC UUID'] : getProductCustomFieldValue($params['serviceid'], 'VDC UUID');
        if (!empty($existingVdcId)) {
            multiportal_log('CreateAccount', ['vdc_id' => $existingVdcId], 'VDC already exists, skipping creation');
            return 'success'; // VDC already exists, nothing to do
        }
        
        // Validate configuration
        $validationErrors = multiportal_validateConfig($params);
        if (!empty($validationErrors)) {
            throw new Exception('Configuration errors: ' . implode(', ', $validationErrors));
        }

        multiportal_log('CreateAccount', $params['clientsdetails'], 'Starting account creation', [], ['serverpassword' => '***']);

        $api = initiateAPI($params);
        $resellerMgr = new ResellerManager($api);
        $tenantMgr = new TenantManager($api);
        $vdcMgr = new VDCManager($api);

        // Use ModuleConfiguration to get values
        $dataCenterId = ModuleConfiguration::get($params, ModuleConfiguration::FIELD_DATA_CENTER_ID);
        $resellerId = ModuleConfiguration::get($params, ModuleConfiguration::FIELD_RESELLER_UUID);
        $res = $vdcMgr->getStoragePoliciesByDataCenter($dataCenterId);
        $storagePolicyConfig = verifyStoragePolicyOptions($params['configoptions'], $res);

        // 1. Check Reseller
        $reseller = $resellerMgr->findResellerByID($resellerId);
        //throw error if reseller not found
        if (!$reseller) {
            throw new Exception('Reseller not found. (ID: ' . $resellerId . ')');
        }

        // 2. Create/check Tenant
        $tenantUUID = getClientCustomFieldValue($params, 'MultiPortal Tenant UUID');

        if (empty($tenantUUID)) {
            if (!empty($params['clientsdetails']['companyname']))
                $tenant_name = $params['clientsdetails']['companyname'];
            else
                $tenant_name = $params['clientsdetails']['firstname'] . ' ' . $params['clientsdetails']['lastname'];

            $tenant = $tenantMgr->createTenant(
                $tenant_name,
                $resellerId,
                $params['clientsdetails']['address1'],
                $params['clientsdetails']['firstname'] . ' ' . $params['clientsdetails']['lastname'],
                $params['clientsdetails']['phonenumber']
            );
            setClientCustomFieldValue($params['userid'], 'MultiPortal Tenant UUID', $tenant['uuid']);
        } else {
            $tenant = $tenantMgr->findTenantByID($tenantUUID);
        }

        if (!$tenant) {
            throw new Exception('Failed to create or find tenant.');
        }
        
        // 2.5. Create user for the tenant if it doesn't exist (ONE USER PER CLIENT)
        $multiportalUsername = '';
        $multiportalPassword = '';
        $multiportalUrl = '';
        
        // Check if client already has MultiPortal credentials
        $existingUsername = getClientCustomFieldValue($params, 'MultiPortal Username');
        $existingPassword = getClientCustomFieldValue($params, 'MultiPortal Password');
        $existingUrl = getClientCustomFieldValue($params, 'MultiPortal URL');
        
        if (empty($existingUsername) || empty($existingPassword)) {
            // Generate username from email (client-based, not service-based)
            $emailParts = explode('@', $params['clientsdetails']['email']);
            $baseUsername = $emailParts[0];
            $multiportalUsername = $baseUsername . '_' . $params['userid']; // Use client ID instead of service ID
            
            // Generate secure password
            $multiportalPassword = generateSecurePassword(16);
            
            try {
                multiportal_log('CreateAccount', [
                    'tenant_uuid' => $tenant['uuid'],
                    'username' => $multiportalUsername,
                    'email' => $params['clientsdetails']['email'],
                    'client_id' => $params['userid']
                ], 'Attempting to create user (client-based)');
                
                // Create user in MultiPortal
                $user = $tenantMgr->createUser(
                    $tenant['uuid'],
                    $multiportalUsername,
                    $multiportalPassword,
                    $params['clientsdetails']['email'],
                    $params['clientsdetails']['firstname'],
                    $params['clientsdetails']['lastname'],
                    'Tenant Administrator'
                );
                
                multiportal_log('CreateAccount', ['user_response' => $user], 'User creation API response');
                
                // Get tenant URL - check if tenant has a domain field, otherwise construct it
                if (isset($tenant['domain']) && !empty($tenant['domain'])) {
                    $multiportalUrl = 'https://' . $tenant['domain'];
                } else {
                    // Fallback: construct from tenant name
                    $tenantName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $tenant['name']));
                    $multiportalUrl = 'https://' . $tenantName . '.multiportal.io';
                }
                
                // Store credentials in CLIENT custom fields (not service fields)
                setClientCustomFieldValue($params['userid'], 'MultiPortal Username', $multiportalUsername);
                setClientCustomFieldValue($params['userid'], 'MultiPortal Password', $multiportalPassword);
                setClientCustomFieldValue($params['userid'], 'MultiPortal URL', $multiportalUrl);
                
                multiportal_log('CreateAccount', [
                    'username' => $multiportalUsername,
                    'url' => $multiportalUrl,
                    'client_id' => $params['userid']
                ], 'Client credentials stored successfully');
            } catch (Exception $e) {
                multiportal_log('CreateAccount', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'client_id' => $params['userid']
                ], 'Failed to create user - will retry later');
                
                // Continue with VDC creation even if user creation fails
            }
        } else {
            // User already exists for this client
            $multiportalUsername = $existingUsername;
            $multiportalPassword = $existingPassword;
            $multiportalUrl = $existingUrl;
            
            multiportal_log('CreateAccount', [
                'username' => $multiportalUsername,
                'client_id' => $params['userid']
            ], 'Using existing client credentials');
        }
        
        // 3. Create VDC
        // Determine allocation type from configurable option
        $allocationType = 1; // Default to Allocation
        if (isset($params['configoptions']['Allocation Type'])) {
            $selectedType = $params['configoptions']['Allocation Type'];
            // Convert the selection to allocation type ID
            if (stripos($selectedType, 'pay as you go') !== false || stripos($selectedType, 'payg') !== false) {
                $allocationType = 2;
            }
        }

        $vdc = $vdcMgr->createVDC(
            'VDC - ' . $params['serviceid'],
            $dataCenterId, // Use the variable we already got from ModuleConfiguration
            $tenant['uuid'],
            (int) $params['configoptions']['CPU'], // CPU
            (int) $params['configoptions']['Memory Allocation'],  // RAM
            true,
            $allocationType,
        );

        setCustomFieldValue($params['serviceid'], 'VDC UUID', $vdc['uuid']);
        if (isset($vdc['error'])) {
            throw new Exception('Failed to create VDC: ' . $vdc['error']);
        }


        foreach ($storagePolicyConfig as $storagePolicy) {
            $vdcMgr->addStoragePolicy($vdc['uuid'], [
                'storage_policy_id' => $storagePolicy['storage_policy_id'],
                'capacity' => $storagePolicy['capacity'],
            ]);
        }

        return 'success';
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Verify Storage Policy Options
 *
 * @param array $configOptions
 * @param array $res
 * @return array
 */
function verifyStoragePolicyOptions($configOptions, $res)
{
    $storageConfig = [];
    foreach ($res['data'] as $storagePolicy) {
        $configOptionsFormat = "Storage - {$storagePolicy['name']}";
        $storageQty = (int) $configOptions[$configOptionsFormat] ?? 0;
        if (isset($configOptions[$configOptionsFormat])) {
            $storageConfig[] = [
                'name' => $storagePolicy['name'],
                'storage_policy_id' => $storagePolicy['uuid'],
                'capacity' => $storageQty,
            ];
        }
    }
    return $storageConfig;
}

/**
 * Client Area Output - Display VDC information to clients
 */
function multiportal_ClientArea(array $params)
{
    // Debug: Create a simple file to track function calls
    file_put_contents(__DIR__ . '/debug_clientarea_calls.log', 
        date('Y-m-d H:i:s') . " - multiportal_ClientArea called for service " . ($params['serviceid'] ?? 'unknown') . "\n", 
        FILE_APPEND | LOCK_EX);
    
    // Debug: Log that the function is being called
    multiportal_log('ClientArea', $params, 'Function called', ['message' => 'multiportal_ClientArea function called']);
    
    try {
        // Get MultiPortal credentials from service fields
        $multiportalUsername = $params['username'];
        $multiportalPassword = $params['password'];
        
        // ALWAYS get URL from the API URL configuration - just remove the /api part
        try {
            $apiUrl = ModuleConfiguration::get($params, ModuleConfiguration::FIELD_API_URL);
            // Remove /api/v1 or /api from the end
            $multiportalUrl = rtrim($apiUrl, '/');
            $multiportalUrl = preg_replace('#/api(/v\d+)?$#', '', $multiportalUrl);
        } catch (Exception $e) {
            throw new Exception('MultiPortal API URL not configured. Please check server configuration.');
        }
        
        // API credentials have been removed - using main portal credentials only
        
        // Debug: Log retrieved credentials
        multiportal_log('ClientArea', $params, 'Credentials retrieved', [
            'multiportal_username' => $multiportalUsername ?: 'EMPTY',
            'multiportal_password' => $multiportalPassword ? '[SET]' : 'EMPTY',
            'multiportal_url' => $multiportalUrl ?: 'EMPTY'
        ]);
        
        // Get VDC UUID from custom field using helper function
        $vdcId = isset($params['customfields']['VDC UUID']) ? $params['customfields']['VDC UUID'] : getProductCustomFieldValue($params['serviceid'], 'VDC UUID');

        // Initialize template variables
        $templateVars = [
            'status' => 'active',
            'multiportal' => [
                'username' => $multiportalUsername,
                'password' => $multiportalPassword,
                'url' => $multiportalUrl
            ],
            // API credentials removed - using main portal credentials
            'vdc' => null,
            'storage_policies' => [],
            'usage' => null
        ];

        // If no VDC created yet, show credentials but pending VDC message
        if (empty($vdcId)) {
            $templateVars['status'] = 'pending';
            $templateVars['message'] = 'Your Virtual Data Center is being provisioned. Please check back later.';
            
            // Still show credentials if they exist
            return [
                'templatefile' => 'templates/clientarea',
                'vars' => $templateVars
            ];
        }

        // Try to get VDC details if VDC exists
        try {
            $api = initiateAPI($params);
            $vdcMgr = new VDCManager($api);

            // Get VDC details
            $vdc = $vdcMgr->getVDCById($vdcId);
            if (!$vdc || isset($vdc['error'])) {
                throw new Exception('Unable to fetch Virtual Data Center details: ' . ($vdc['error'] ?? 'Virtual Data Center not found'));
            }

            // Get storage policies
            $storagePolicies = $vdcMgr->getStoragePolicy($vdcId);

            // Get usage data with specific date range
            $usage = null;
            try {
                // Get current month usage
                // Use standard date format Y-m-d
                $monthStart = date('Y-m-01'); // First day of current month
                $monthEnd = date('Y-m-d'); // Today
                $currentMonthParams = [
                    'date_range' => $monthStart . ' 00:00:00 - ' . $monthEnd . ' 23:59:59'
                ];
                $usage = $vdcMgr->getVDCUsage($vdcId, $currentMonthParams);
                
                // Debug log the usage structure
                multiportal_log('ClientArea', [
                    'vdcId' => $vdcId,
                    'date_range' => $currentMonthParams['date_range'],
                    'usage_data' => $usage,
                    'usage_type' => gettype($usage),
                    'usage_keys' => is_array($usage) ? array_keys($usage) : 'not an array'
                ], 'Current day usage data fetched');
            } catch (Exception $e) {
                // Log but don't fail if usage data is unavailable
                multiportal_log('ClientArea', ['error' => 'Failed to fetch usage: ' . $e->getMessage()], 'Usage fetch failed');
            }

            // Update template variables with VDC data
            $templateVars['vdc'] = [
                'name' => $vdc['vdc_name'],
                'status' => $vdc['is_enabled'] ? 'Active' : 'Suspended',
                'cpu_cores' => $vdc['core_count'],
                'memory_gb' => $vdc['memory_in_gb'],
                'allocation_type' => $vdc['allocation_type'] == 1 ? 'Allocation Pool' : 'Pay As You Go',
                'created_at' => $vdc['created_at'] ?? 'N/A',
            ];
            
            // Format usage data for template - use the formatted_usage section from API
            if ($usage && isset($usage['formatted_usage'])) {
                $formatted = $usage['formatted_usage'];
                $resourceSummary = $formatted['resource_summary'] ?? [];
                $vmStats = $formatted['vm_statistics'] ?? [];
                $storageSummary = $formatted['storage_summary'] ?? [];
                
                // Get VM status
                $runningVMs = $vmStats['active_vms'] ?? 0;
                $totalVMs = $vmStats['total_vms'] ?? 0;
                
                // Get formatted totals from API
                $totalUptimeHours = $vmStats['total_runtime_hours'] ?? 0;
                $totalCpuHours = $resourceSummary['cpu']['total_usage_raw'] ?? 0;
                $totalMemoryGbHours = $resourceSummary['memory']['total_usage_gb_hours'] ?? 0;
                
                // Convert GB-hours to TiB-hours for consistency
                $totalMemoryTiBHours = $totalMemoryGbHours / 1024;
                
                // Get storage usage from formatted storage summary
                $storageBreakdown = [];
                foreach ($storageSummary as $storageName => $storageData) {
                    $avgUsageGB = floatval(str_replace(' GB', '', $storageData['average_usage'] ?? '0'));
                    $capacityGB = floatval(str_replace(' GB', '', $storageData['capacity'] ?? '0'));
                    $storageBreakdown[] = [
                        'name' => $storageName,
                        'usage_gb' => $avgUsageGB,
                        'capacity_gb' => $capacityGB,
                        'utilization' => $storageData['utilization'] ?? '0%'
                    ];
                }
                
                // Get the date range from the API response
                $dateRange = $usage['summary']['date_range'] ?? [];
                $apiStartDate = $dateRange['start'] ?? '';
                $apiEndDate = $dateRange['end'] ?? '';
                
                // Get the date range we requested (use same format as API)
                $requestedStart = $monthStart . ' 00:00:00';
                $requestedEnd = $monthEnd . ' 23:59:59';
                
                // Get pricing from config options using ModuleConfiguration
                $cpuPricePerHour = floatval(ModuleConfiguration::get($params, ModuleConfiguration::FIELD_PAYG_CPU_RATE, false) ?? 0.10);
                $memoryPricePerGBHour = floatval(ModuleConfiguration::get($params, ModuleConfiguration::FIELD_PAYG_MEMORY_RATE, false) ?? 0.05);
                $storagePricePerGBHour = floatval(ModuleConfiguration::get($params, ModuleConfiguration::FIELD_PAYG_STORAGE_RATE, false) ?? 0.01);
                
                // Calculate costs
                $cpuCost = $totalCpuHours * $cpuPricePerHour;
                $memoryCost = $totalMemoryGbHours * $memoryPricePerGBHour;
                
                // Storage cost calculation (total hours * average GB * rate)
                $totalStorageCost = 0;
                $periodHours = (strtotime($apiEndDate) - strtotime($apiStartDate)) / 3600;
                foreach ($storageBreakdown as $storage) {
                    $storageCost = $storage['usage_gb'] * $periodHours * $storagePricePerGBHour;
                    $totalStorageCost += $storageCost;
                }
                
                $totalCost = $cpuCost + $memoryCost + $totalStorageCost;
                
                $formattedUsage = [
                    'total_uptime' => round($totalUptimeHours, 1),
                    'total_cpu_hours' => round($totalCpuHours, 1),
                    'total_memory_tib' => round($totalMemoryTiBHours, 2),
                    'total_memory_gb_hours' => round($totalMemoryGbHours, 1),
                    'storage_breakdown' => $storageBreakdown,
                    'pricing' => [
                        'rates' => [
                            'cpu_per_hour' => $cpuPricePerHour,
                            'memory_per_gb_hour' => $memoryPricePerGBHour,
                            'storage_per_gb_hour' => $storagePricePerGBHour
                        ],
                        'costs' => [
                            'cpu' => round($cpuCost, 2),
                            'memory' => round($memoryCost, 2),
                            'storage' => round($totalStorageCost, 2),
                            'total' => round($totalCost, 2)
                        ]
                    ],
                    'period' => [
                        'requested_start' => $requestedStart,
                        'requested_end' => $requestedEnd,
                        'api_start' => $apiStartDate,
                        'api_end' => $apiEndDate
                    ],
                    'vms' => [
                        'running' => $runningVMs,
                        'total' => $totalVMs
                    ]
                ];
                
                $templateVars['usage'] = $formattedUsage;
            } else {
                $templateVars['usage'] = null;
            }

            // Format storage policies
            if (isset($storagePolicies['data']) && is_array($storagePolicies['data'])) {
                foreach ($storagePolicies['data'] as $policy) {
                    $templateVars['storage_policies'][] = [
                        'name' => $policy['storage_policy_name'] ?? 'Unknown',
                        'capacity' => $policy['capacity'] ?? 0,
                        'used' => $policy['used_capacity'] ?? 0,
                        'available' => ($policy['capacity'] ?? 0) - ($policy['used_capacity'] ?? 0)
                    ];
                }
            }
        } catch (Exception $e) {
            // VDC exists but can't fetch details - show error but still show credentials
            multiportal_log('ClientArea', ['vdcId' => $vdcId, 'error' => $e->getMessage()], 'VDC fetch failed');
            $templateVars['status'] = 'error';
            $templateVars['message'] = 'Unable to load Virtual Data Center information. Please contact support if this persists.';
        }

        // Debug: Log template variables being returned
        multiportal_log('ClientArea', $params, 'Template variables', [
            'template_vars' => $templateVars,
            'template_file' => 'templates/clientarea'
        ]);
        
        return [
            'templatefile' => 'templates/clientarea',
            'vars' => $templateVars,
            'breadcrumb' => [],
            'overrideDisplayName' => 'MultiPortal VDC'
        ];
    } catch (Exception $e) {
        multiportal_log('ClientArea', $params, ['error' => $e->getMessage()]);
        
        $errorVars = [
            'status' => 'error',
            'message' => 'Unable to load Virtual Data Center information. Please contact support.'
        ];
        
        // Debug: Log error template variables
        multiportal_log('ClientArea', $params, 'Error occurred', [
            'template_vars' => $errorVars,
            'template_file' => 'templates/clientarea',
            'error' => $e->getMessage()
        ]);
        
        return [
            'templatefile' => 'templates/clientarea',
            'vars' => $errorVars,
            'breadcrumb' => [],
            'overrideDisplayName' => 'MultiPortal VDC'
        ];
    }
}

/**
 * Suspend/Disable VDC
 */
function multiportal_DisableVdc(array $params)
{
    try {
        $api = initiateAPI($params);
        $vdcMgr = new VDCManager($api);

        $vdcId = isset($params['customfields']['VDC UUID']) ? $params['customfields']['VDC UUID'] : getProductCustomFieldValue($params['serviceid'], 'VDC UUID');
        if (empty($vdcId)) {
            throw new Exception('VDC UUID not found. Cannot suspend non-existent VDC.');
        }

        multiportal_log('DisableVdc', ['vdcId' => $vdcId], 'Suspending VDC');

        // Update VDC to set is_enabled to 0
        $response = $vdcMgr->updateVDC($vdcId, [
            'is_enabled' => 0
        ]);

        multiportal_log('DisableVdc', ['vdcId' => $vdcId], $response);

        if (isset($response['error'])) {
            throw new Exception('Failed to suspend VDC: ' . $response['error']);
        }

        // Update service status in WHMCS
        Capsule::table('tblhosting')
            ->where('id', $params['serviceid'])
            ->update(['domainstatus' => 'Suspended']);

        return 'success';
    } catch (Exception $e) {
        multiportal_log('DisableVdc', $params, ['error' => $e->getMessage()]);
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Unsuspend/Enable VDC
 */
function multiportal_EnableVdc(array $params)
{
    try {
        $api = initiateAPI($params);
        $vdcMgr = new VDCManager($api);

        $vdcId = isset($params['customfields']['VDC UUID']) ? $params['customfields']['VDC UUID'] : getProductCustomFieldValue($params['serviceid'], 'VDC UUID');
        if (empty($vdcId)) {
            throw new Exception('VDC UUID not found. Cannot unsuspend non-existent VDC.');
        }

        multiportal_log('EnableVdc', ['vdcId' => $vdcId], 'Unsuspending VDC');

        // Update VDC to set is_enabled to 1
        $response = $vdcMgr->updateVDC($vdcId, [
            'is_enabled' => 1
        ]);

        multiportal_log('EnableVdc', ['vdcId' => $vdcId], $response);

        if (isset($response['error'])) {
            throw new Exception('Failed to unsuspend VDC: ' . $response['error']);
        }

        // Update service status in WHMCS
        Capsule::table('tblhosting')
            ->where('id', $params['serviceid'])
            ->update(['domainstatus' => 'Active']);

        return 'success';
    } catch (Exception $e) {
        multiportal_log('EnableVdc', $params, ['error' => $e->getMessage()]);
        return 'Error: ' . $e->getMessage();
    }
}
function multiportal_DestroyVdc(array $params)
{
    try {
        // Use database for confirmation tracking
        $confirmKey = 'delete_vdc_confirm';
        $serviceId = $params['serviceid'];

        // Check if confirmation exists
        $confirmation = Capsule::table('tblcustomfieldsvalues')
            ->where('relid', $serviceId)
            ->where('fieldid', function ($query) use ($confirmKey) {
                $query->select('id')
                    ->from('tblcustomfields')
                    ->where('type', 'product')
                    ->where('fieldname', $confirmKey)
                    ->limit(1);
            })
            ->first();

        $now = time();

        if (!$confirmation || !$confirmation->value || ($now - (int)$confirmation->value > 30)) {
            // First click - set confirmation timestamp

            // Ensure the custom field exists
            $field = Capsule::table('tblcustomfields')
                ->where('type', 'product')
                ->where('fieldname', $confirmKey)
                ->first();

            if (!$field) {
                Capsule::table('tblcustomfields')->insert([
                    'type' => 'product',
                    'fieldname' => $confirmKey,
                    'fieldtype' => 'text',
                    'adminonly' => 'on',
                    'sortorder' => 999
                ]);
                $fieldId = Capsule::getPdo()->lastInsertId();
            } else {
                $fieldId = $field->id;
            }

            // Set or update the confirmation timestamp
            if ($confirmation) {
                Capsule::table('tblcustomfieldsvalues')
                    ->where('id', $confirmation->id)
                    ->update(['value' => $now]);
            } else {
                Capsule::table('tblcustomfieldsvalues')->insert([
                    'fieldid' => $fieldId,
                    'relid' => $serviceId,
                    'value' => $now
                ]);
            }

            return 'WARNING: Click Delete Virtual Data Center again within 30 seconds to confirm permanent deletion. This action cannot be undone!';
        }

        // Second click - clear confirmation and proceed with deletion
        Capsule::table('tblcustomfieldsvalues')
            ->where('relid', $serviceId)
            ->where('fieldid', $confirmation->fieldid)
            ->update(['value' => '']);

        $api = initiateAPI($params);
        $vdcMgr = new VDCManager($api);
        $vdcId = isset($params['customfields']['VDC UUID']) ? $params['customfields']['VDC UUID'] : getProductCustomFieldValue($params['serviceid'], 'VDC UUID');

        if (empty($vdcId)) {
            throw new Exception('VDC UUID not found.');
        }

        multiportal_log('DestroyVdc', ['vdcId' => $vdcId], 'Deleting VDC');

        $res = $vdcMgr->deleteVDC($vdcId);
        if (isset($res['error'])) {
            throw new Exception('Failed to delete VDC: ' . $res['error']);
        }

        multiportal_log('DestroyVdc', ['vdcId' => $vdcId], 'VDC deleted successfully');

        setCustomFieldValue($params['serviceid'], 'VDC UUID', '');
        return 'success';
    } catch (Exception $e) {
        // Clear confirmation on error
        unset($_SESSION['confirm_delete_vdc_' . $params['serviceid']]);
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Clean up stale delete confirmations
 */
function multiportal_cleanupConfirmations()
{
    if (isset($_SESSION)) {
        $now = time();
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, 'confirm_delete_vdc_') === 0 && is_array($value)) {
                if ($now - $value['time'] > 30) {
                    unset($_SESSION[$key]);
                }
            }
        }
    }
}

/**
 * Setup Wizard - Automatically create configurable options for this product
 */
function multiportal_SetupWizard(array $params)
{
    try {
        // First, validate API credentials and test connection
        try {
            // Debug: Log what params we're receiving
            multiportal_log('SetupWizard', [
                'params_keys' => array_keys($params),
                'has_serverusername' => isset($params['serverusername']),
                'has_serverpassword' => isset($params['serverpassword']),
                'serverusername_value' => isset($params['serverusername']) ? substr($params['serverusername'], 0, 5) . '...' : 'NOT SET',
                'server_id' => $params['serverid'] ?? 'NO SERVER ID',
                'product_id' => $params['pid'] ?? 'NO PRODUCT ID'
            ], 'Debug: Checking params structure');
            
            // Check if credentials are configured
            $clientId = ModuleConfiguration::getClientId($params);
            $clientSecret = ModuleConfiguration::getClientSecret($params);
            
            // Test API connection
            $api = initiateAPI($params);
            
            // Skip the reseller test - we'll validate with the data center check below
            multiportal_log('SetupWizard', ['action' => 'API initialized'], 'API client created');
            
        } catch (Exception $e) {
            $serverId = $params['serverid'] ?? 'unknown';
            
            // Simple error message
            if (strpos($e->getMessage(), 'Client ID') !== false || strpos($e->getMessage(), 'Client Secret') !== false) {
                return 'Error: Server credentials are empty. Go to System Settings > Servers and edit the Multiportal Server to add your API credentials.';
            }
            
            return 'Error: ' . $e->getMessage();
        }
        
        // Now ensure custom fields exist
        $customFieldsCreated = [];
        try {
            $customFieldsCreated = ensureCustomFieldsExist();
        } catch (Exception $e) {
            return 'Error creating custom fields: ' . $e->getMessage();
        }

        $productId = $params['pid'];
        $dataCenterId = ModuleConfiguration::get($params, ModuleConfiguration::FIELD_DATA_CENTER_ID);

        if (empty($dataCenterId)) {
            throw new Exception('Data Center UUID must be configured in the product module settings first.');
        }

        // Validate that the Data Center UUID exists
        try {
            $dataCenterResponse = $api->get('/data-center/' . $dataCenterId);
            if (!$dataCenterResponse || !isset($dataCenterResponse['data'])) {
                throw new Exception('Data Center UUID is invalid or not found.');
            }
            multiportal_log('SetupWizard', ['datacenter' => $dataCenterResponse['data']['name'] ?? 'Unknown'], 'Data Center validated');
        } catch (Exception $e) {
            return 'Data Center Validation Error: ' . $e->getMessage() . ' Please check the Data Center UUID in module settings.';
        }

        // Create product-specific custom fields
        $vdcField = Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('relid', $productId)
            ->where('fieldname', 'VDC UUID')
            ->first();

        if (!$vdcField) {
            Capsule::table('tblcustomfields')->insert([
                'type' => 'product',
                'relid' => $productId,  // THIS IS THE KEY - specific to this product!
                'fieldname' => 'VDC UUID',
                'fieldtype' => 'text',
                'description' => 'Stores the Virtual Data Center identifier',
                'fieldoptions' => '',
                'regexpr' => '',
                'adminonly' => 'on',
                'required' => '',
                'showorder' => '',
                'showinvoice' => '',
                'sortorder' => 0
            ]);
            $customFieldsCreated[] = 'Product field: Virtual Data Center UUID (for product ' . $productId . ')';
        }

        // Check if a configurable option group already exists for this product
        $existingGroup = Capsule::table('tblproductconfiglinks')
            ->join('tblproductconfiggroups', 'tblproductconfiglinks.gid', '=', 'tblproductconfiggroups.id')
            ->where('tblproductconfiglinks.pid', $productId)
            ->where('tblproductconfiggroups.name', 'LIKE', '%MultiPortal%')
            ->first();

        if ($existingGroup) {
            return 'Configurable options already exist for this product. Group: ' . $existingGroup->name;
        }

        // Create a new configurable option group
        $groupName = 'MultiPortal Options - Product ' . $productId;
        Capsule::table('tblproductconfiggroups')->insert([
            'name' => $groupName,
            'description' => 'Auto-generated MultiPortal configurable options'
        ]);
        $groupId = Capsule::getPdo()->lastInsertId();

        // Link the group to this product
        Capsule::table('tblproductconfiglinks')->insert([
            'gid' => $groupId,
            'pid' => $productId
        ]);

        // Create CPU option
        Capsule::table('tblproductconfigoptions')->insert([
            'gid' => $groupId,
            'optionname' => 'CPU',
            'optiontype' => 4, // Quantity
            'qtyminimum' => 1,
            'qtymaximum' => 128,
            'order' => 1,
            'hidden' => 0
        ]);
        $cpuOptionId = Capsule::getPdo()->lastInsertId();

        // Create sub-option for CPU
        Capsule::table('tblproductconfigoptionssub')->insert([
            'configid' => $cpuOptionId,
            'optionname' => 'CPU Core',
            'sortorder' => 0,
            'hidden' => 0
        ]);

        // Create Memory option
        Capsule::table('tblproductconfigoptions')->insert([
            'gid' => $groupId,
            'optionname' => 'Memory Allocation',
            'optiontype' => 4, // Quantity
            'qtyminimum' => 1,
            'qtymaximum' => 512,
            'order' => 2,
            'hidden' => 0
        ]);
        $memoryOptionId = Capsule::getPdo()->lastInsertId();

        // Create sub-option for Memory
        Capsule::table('tblproductconfigoptionssub')->insert([
            'configid' => $memoryOptionId,
            'optionname' => 'GB',
            'sortorder' => 0,
            'hidden' => 0
        ]);

        // Fetch storage policies from the data center
        $api = initiateAPI($params);
        $vdcMgr = new VDCManager($api);
        $storagePolicies = $vdcMgr->getStoragePoliciesByDataCenter($dataCenterId);

        if (isset($storagePolicies['data']) && is_array($storagePolicies['data'])) {
            $order = 3;
            foreach ($storagePolicies['data'] as $policy) {
                if (!empty($policy['name'])) {
                    // Create storage policy option
                    Capsule::table('tblproductconfigoptions')->insert([
                        'gid' => $groupId,
                        'optionname' => 'Storage - ' . $policy['name'],
                        'optiontype' => 4, // Quantity
                        'qtyminimum' => 0,
                        'qtymaximum' => 10000,
                        'order' => $order++,
                        'hidden' => 0
                    ]);
                    $storageOptionId = Capsule::getPdo()->lastInsertId();

                    // Create sub-option for storage
                    Capsule::table('tblproductconfigoptionssub')->insert([
                        'configid' => $storageOptionId,
                        'optionname' => 'GB',
                        'sortorder' => 0,
                        'hidden' => 0
                    ]);
                }
            }
        }

        // Optional: Create Allocation Type dropdown
        Capsule::table('tblproductconfigoptions')->insert([
            'gid' => $groupId,
            'optionname' => 'Allocation Type',
            'optiontype' => 1, // Dropdown
            'qtyminimum' => 0,
            'qtymaximum' => 0,
            'order' => 100,
            'hidden' => 0
        ]);
        $allocationOptionId = Capsule::getPdo()->lastInsertId();

        multiportal_log('SetupWizard', ['allocation_option_id' => $allocationOptionId], 'Created Allocation Type option');

        // Create allocation type sub-options
        $allocationTypes = ['Allocation', 'Pay As You Go'];
        foreach ($allocationTypes as $index => $type) {
            // Check if sub-option already exists
            $exists = Capsule::table('tblproductconfigoptionssub')
                ->where('configid', $allocationOptionId)
                ->where('optionname', $type)
                ->first();

            if (!$exists) {
                Capsule::table('tblproductconfigoptionssub')->insert([
                    'configid' => $allocationOptionId,
                    'optionname' => $type,
                    'sortorder' => $index,
                    'hidden' => 0
                ]);
                multiportal_log('SetupWizard', ['sub_option' => $type, 'index' => $index], 'Created sub-option');
            }
        }

        // Verify the sub-options were created
        $createdSubs = Capsule::table('tblproductconfigoptionssub')
            ->where('configid', $allocationOptionId)
            ->count();
        multiportal_log('SetupWizard', ['allocation_subs_count' => $createdSubs], 'Verified Allocation Type sub-options');

        // Build success message - keep it simple for WHMCS
        $message = 'Setup completed successfully! ';
        $message .= 'Created configurable options group "' . $groupName . '" with ';
        $message .= 'CPU (1-128), Memory (1-512 GB), ' . count($storagePolicies['data']) . ' storage policies, and Allocation Type. ';
        $message .= 'IMPORTANT: Due to WHMCS bug, you must now: ';
        $message .= '1) Go to Configurable Options, ';
        $message .= '2) Edit "' . $groupName . '", ';
        $message .= '3) Click any Storage option, ';
        $message .= '4) Save without changes. ';
        $message .= 'This fixes the Allocation Type dropdown.';
        
        // WHMCS is very limited in what it can display for module commands
        // We can only return 'success' or 'Error: message'
        // Any other format shows as an error
        
        // The best we can do is return a success message that fits on one line
        return 'success';
    } catch (Exception $e) {
        multiportal_log('SetupWizard', $params, ['error' => $e->getMessage()]);
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Test Connection button for server configuration page
 * This function performs connection test AND sets up custom fields
 */
function multiportal_TestConnection(array $params)
{
    try {
        // Debug what params we have
        multiportal_log('TestConnection', [
            'serverhostname' => $params['serverhostname'] ?? 'NOT SET',
            'serversecure' => $params['serversecure'] ?? 'NOT SET',
            'serverport' => $params['serverport'] ?? 'NOT SET',
            'serverid' => $params['serverid'] ?? 'NOT SET',
            'serveraccesshash' => $params['serveraccesshash'] ?? 'NOT SET',
            'all_params' => array_keys($params),
            'has_configoptions' => isset($params['configoption1'])
        ], 'Server params received');
        
        // First, test the API connection
        $clientId = ModuleConfiguration::getClientId($params);
        $clientSecret = ModuleConfiguration::getClientSecret($params);
        
        if (empty($clientId) || empty($clientSecret)) {
            return ['success' => false, 'error' => 'Client ID and Client Secret are required'];
        }
        
        // For TestConnection on server config page, we need to handle API URL differently
        // The configoption1 might not be available yet, so we'll use server hostname/IP
        $apiUrl = null;
        
        // Try to get API URL from module config first
        try {
            $apiUrl = ModuleConfiguration::get($params, ModuleConfiguration::FIELD_API_URL);
        } catch (Exception $e) {
            // If not in module config, try other sources
            multiportal_log('TestConnection', ['api_url_error' => $e->getMessage()], 'Could not get API URL from module config');
        }
        
        // If no API URL from module config, check if we have it in serverhostname
        if (empty($apiUrl) && !empty($params['serverhostname'])) {
            // If serverhostname is a full URL, use it
            if (filter_var($params['serverhostname'], FILTER_VALIDATE_URL)) {
                $apiUrl = $params['serverhostname'];
            } else {
                // Otherwise, construct URL from hostname
                // Check serversecure - it's a boolean in the params you showed
                $protocol = (!empty($params['serversecure']) && $params['serversecure'] === true) ? 'https' : 'http';
                // Don't add port if it's the default for the protocol
                $port = '';
                if (!empty($params['serverport'])) {
                    if (($protocol === 'https' && $params['serverport'] !== '443') ||
                        ($protocol === 'http' && $params['serverport'] !== '80')) {
                        $port = ':' . $params['serverport'];
                    }
                }
                $apiUrl = $protocol . '://' . $params['serverhostname'] . $port . '/api/v1';
            }
        }
        
        // If still no API URL, return error
        if (empty($apiUrl)) {
            return ['success' => false, 'error' => 'API URL could not be determined. Please ensure Server Hostname is set or save the configuration first.'];
        }
        
        multiportal_log('TestConnection', ['api_url' => $apiUrl], 'Using API URL');
        
        // Create API client directly since initiateAPI might fail
        try {
            $api = new ApiClient($clientId, $clientSecret, $apiUrl, false);
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to create API client: ' . $e->getMessage()];
        }
        
        // Test connection - we'll use the /reseller endpoint as a simple test
        // Since we need a reseller ID from config, we'll check if we can access the API
        try {
            multiportal_log('TestConnection', ['action' => 'Testing API connection'], 'About to test API');
            
            // First, let's see if we have a reseller UUID configured
            $resellerUuid = null;
            try {
                $resellerUuid = ModuleConfiguration::get($params, ModuleConfiguration::FIELD_RESELLER_UUID, false);
            } catch (Exception $e) {
                // Reseller UUID not configured yet, that's ok for initial test
                multiportal_log('TestConnection', ['reseller_uuid' => 'not configured'], 'No reseller UUID');
            }
            
            $testSuccessful = false;
            $testMessage = '';
            
            if (!empty($resellerUuid)) {
                // If we have a reseller UUID, test with that
                try {
                    $resellerResponse = $api->get('/reseller/' . $resellerUuid);
                    if ($resellerResponse && isset($resellerResponse['data'])) {
                        $testSuccessful = true;
                        $resellerName = $resellerResponse['data']['name'] ?? 'Unknown';
                        $testMessage = "Connected to reseller: {$resellerName}";
                    }
                } catch (Exception $e) {
                    // Fall back to company endpoint
                    multiportal_log('TestConnection', ['reseller_error' => $e->getMessage()], 'Reseller test failed');
                }
            }
            
            // If reseller test didn't work, try company endpoint
            if (!$testSuccessful) {
                try {
                    $companyResponse = $api->get('/company');
                    multiportal_log('TestConnection', [
                        'company_response' => array_keys($companyResponse ?? []),
                        'has_data' => isset($companyResponse['data'])
                    ], 'Company endpoint response');
                    
                    if ($companyResponse) {
                        $testSuccessful = true;
                        if (isset($companyResponse['data']['data']['items'])) {
                            $companyCount = count($companyResponse['data']['data']['items']);
                            $testMessage = "Found {$companyCount} companies";
                        } else {
                            $testMessage = "API connection successful";
                        }
                    }
                } catch (Exception $e) {
                    multiportal_log('TestConnection', ['company_error' => $e->getMessage()], 'Company test failed');
                }
            }
            
            // If still no success, try tenant endpoint
            if (!$testSuccessful) {
                try {
                    $tenantResponse = $api->get('/tenant');
                    if ($tenantResponse) {
                        $testSuccessful = true;
                        $testMessage = "API connection successful (tenant endpoint)";
                    }
                } catch (Exception $e) {
                    multiportal_log('TestConnection', ['tenant_error' => $e->getMessage()], 'Tenant test failed');
                }
            }
            
            if (!$testSuccessful) {
                return ['success' => false, 'error' => 'Could not verify API connection. Please check credentials and API URL.'];
            }
            
        } catch (Exception $e) {
            multiportal_log('TestConnection', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 'Exception during API call');
            return ['success' => false, 'error' => 'API Error: ' . $e->getMessage()];
        }
        
        // Check if Data Center ID is provided in Access Hash field
        $dataCenterId = isset($params['serveraccesshash']) ? trim($params['serveraccesshash']) : '';
        $dataCenterInfo = '';
        
        if (!empty($dataCenterId)) {
            // Validate the Data Center ID
            try {
                $dataCenterResponse = $api->get('/data-center/' . $dataCenterId);
                if ($dataCenterResponse && isset($dataCenterResponse['data'])) {
                    $dcName = $dataCenterResponse['data']['name'] ?? 'Unknown';
                    $dataCenterInfo = " Data Center '{$dcName}' verified.";
                    
                    // Also try to fetch storage policies to ensure full access
                    try {
                        $storagePolicies = $api->get('/data-center/' . $dataCenterId . '/storage-policy');
                        if ($storagePolicies && isset($storagePolicies['data'])) {
                            $policyCount = count($storagePolicies['data']);
                            $dataCenterInfo .= " Found {$policyCount} storage policies.";
                        }
                    } catch (Exception $e) {
                        // Storage policy fetch failed, but that's ok for connection test
                        multiportal_log('TestConnection', ['storage_policy_error' => $e->getMessage()], 'Could not fetch storage policies');
                    }
                } else {
                    $dataCenterInfo = " WARNING: Data Center ID not found!";
                }
            } catch (Exception $e) {
                $dataCenterInfo = " WARNING: Could not validate Data Center ID: " . $e->getMessage();
            }
        } else {
            $dataCenterInfo = " No Data Center ID configured in Access Hash field.";
        }
        
        // Now ensure custom fields exist (this creates global custom fields)
        $customFieldsCreated = [];
        try {
            $customFieldsCreated = ensureCustomFieldsExist();
            multiportal_log('TestConnection', ['custom_fields' => $customFieldsCreated], 'Custom fields created/verified');
        } catch (Exception $e) {
            // Don't fail the connection test if custom fields already exist
            multiportal_log('TestConnection', ['custom_fields_error' => $e->getMessage()], 'Custom fields may already exist');
        }
        
        // Build success message
        $message = $testMessage . $dataCenterInfo;
        
        if (!empty($customFieldsCreated)) {
            $message .= " Created " . count($customFieldsCreated) . " custom field(s).";
        } else {
            $message .= " Custom fields already configured.";
        }
        
        $message .= " To set up product options, go to the product and click 'Setup Product Options'.";
        
        multiportal_log('TestConnection', [
            'test_message' => $testMessage,
            'api_url' => $apiUrl,
            'custom_fields_created' => count($customFieldsCreated)
        ], 'Connection test and setup successful');
        
        return ['success' => true, 'error' => $message];
        
    } catch (Exception $e) {
        multiportal_log('TestConnection', $params, ['error' => $e->getMessage()]);
        
        // Provide more helpful error messages
        if (strpos($e->getMessage(), 'Client ID') !== false || strpos($e->getMessage(), 'Client Secret') !== false) {
            return ['success' => false, 'error' => 'Server credentials are empty. Please add your API credentials.'];
        }
        
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
