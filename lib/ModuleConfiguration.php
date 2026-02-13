<?php
/**
 * MultiPortal Module Configuration
 * 
 * Centralizes all configuration field mappings and validation
 */

class ModuleConfiguration 
{
    // Configuration field positions - SINGLE SOURCE OF TRUTH
    const FIELD_API_URL = 1;
    const FIELD_DATA_CENTER_ID = 2;
    const FIELD_RESELLER_UUID = 3;
    const FIELD_PAYG_CPU_RATE = 4;
    const FIELD_PAYG_MEMORY_RATE = 5;
    const FIELD_PAYG_STORAGE_RATE = 6;
    const FIELD_ALLOCATION_TYPE = 7;

    // Server credential fields
    const FIELD_CLIENT_ID = 'serverusername';
    const FIELD_CLIENT_SECRET = 'serverpassword';
    
    // Field names for better error messages
    const FIELD_NAMES = [
        self::FIELD_API_URL => 'API Base URL',
        self::FIELD_DATA_CENTER_ID => 'Data Center UUID',
        self::FIELD_RESELLER_UUID => 'Reseller UUID',
        self::FIELD_PAYG_CPU_RATE => 'PAYG CPU Rate',
        self::FIELD_PAYG_MEMORY_RATE => 'PAYG Memory Rate',
        self::FIELD_PAYG_STORAGE_RATE => 'PAYG Storage Rate',
        self::FIELD_ALLOCATION_TYPE => 'Allocation Type'
    ];
    
    /**
     * Get a configuration value with validation
     */
    public static function get($params, $fieldConstant, $required = true) 
    {
        $fieldKey = 'configoption' . $fieldConstant;
        $fieldName = self::FIELD_NAMES[$fieldConstant] ?? "Field $fieldConstant";
        
        if (!isset($params[$fieldKey]) || empty($params[$fieldKey])) {
            if ($required) {
                throw new Exception("$fieldName is not configured. Please check product module settings.");
            }
            return null;
        }
        
        $value = $params[$fieldKey];
        
        // Validate based on field type
        switch ($fieldConstant) {
            case self::FIELD_API_URL:
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    throw new Exception("$fieldName must be a valid URL. Got: $value");
                }
                break;
                
            case self::FIELD_DATA_CENTER_ID:
            case self::FIELD_RESELLER_UUID:
                // Validate UUID format
                $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
                if (!preg_match($uuidPattern, $value)) {
                    throw new Exception("$fieldName must be a valid UUID. Got: $value");
                }
                break;
                
            case self::FIELD_PAYG_CPU_RATE:
            case self::FIELD_PAYG_MEMORY_RATE:
            case self::FIELD_PAYG_STORAGE_RATE:
                if (!is_numeric($value) || $value < 0) {
                    throw new Exception("$fieldName must be a positive number. Got: $value");
                }
                break;
        }
        
        return $value;
    }
    
    /**
     * Get server credentials
     */
    public static function getClientId($params) 
    {
        if (empty($params[self::FIELD_CLIENT_ID])) {
            throw new Exception('Client ID (Server Username) is not configured or is empty.');
        }
        return $params[self::FIELD_CLIENT_ID];
    }
    
    /**
     * Get server secret
     */
    public static function getClientSecret($params) 
    {
        if (empty($params[self::FIELD_CLIENT_SECRET])) {
            throw new Exception('Client Secret (Server Password) is not configured. Please check server configuration.');
        }
        return $params[self::FIELD_CLIENT_SECRET];
    }
    
    /**
     * Get all configuration values with validation
     */
    public static function getAll($params) 
    {
        return [
            'client_id' => self::getClientId($params),
            'client_secret' => self::getClientSecret($params),
            'api_url' => self::get($params, self::FIELD_API_URL),
            'data_center_id' => self::get($params, self::FIELD_DATA_CENTER_ID),
            'reseller_uuid' => self::get($params, self::FIELD_RESELLER_UUID),
            'payg_cpu_rate' => self::get($params, self::FIELD_PAYG_CPU_RATE, false) ?? 0.10,
            'payg_memory_rate' => self::get($params, self::FIELD_PAYG_MEMORY_RATE, false) ?? 0.05,
            'payg_storage_rate' => self::get($params, self::FIELD_PAYG_STORAGE_RATE, false) ?? 0.01,
            'allocation_type' => self::get($params, self::FIELD_ALLOCATION_TYPE, false) ?? 'Allocation',
        ];
    }
    
    /**
     * Validate all required configuration
     */
    public static function validate($params) 
    {
        $errors = [];
        
        // Check server credentials
        try {
            self::getClientId($params);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
        
        try {
            self::getClientSecret($params);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
        
        // Check required module configuration
        try {
            self::get($params, self::FIELD_API_URL);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
        
        try {
            self::get($params, self::FIELD_DATA_CENTER_ID);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
        
        try {
            self::get($params, self::FIELD_RESELLER_UUID);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
        
        // Determine if this is a PAYG product (skip CPU/Memory validation for PAYG)
        $allocationTypeKey = 'configoption' . self::FIELD_ALLOCATION_TYPE;
        $isPayg = isset($params[$allocationTypeKey]) &&
                  stripos($params[$allocationTypeKey], 'pay as you go') !== false;

        if (!$isPayg) {
            // Validate configurable options only for Allocation type products
            $cpu = isset($params['configoptions']['CPU'])
                ? (int)$params['configoptions']['CPU'] : 0;
            $memory = isset($params['configoptions']['Memory Allocation'])
                ? (int)$params['configoptions']['Memory Allocation'] : 0;

            if ($cpu < 1 || $cpu > 128) {
                $errors[] = 'CPU cores must be between 1 and 128 (currently: ' . $cpu . ')';
            }
            if ($memory < 1 || $memory > 512) {
                $errors[] = 'Memory must be between 1 and 512 GB (currently: ' . $memory . ' GB)';
            }
        }

        return $errors;
    }
    
    /**
     * Debug configuration - logs all config values
     */
    public static function debug($params) 
    {
        $debug = [
            'server' => [
                'username' => substr($params['serverusername'] ?? '', 0, 10) . '...',
                'has_password' => !empty($params['serverpassword']),
                'hostname' => $params['serverhostname'] ?? 'NOT SET',
            ],
            'module_config' => []
        ];
        
        // Log all configoption values
        for ($i = 1; $i <= 10; $i++) {
            if (isset($params['configoption' . $i])) {
                $fieldName = self::FIELD_NAMES[$i] ?? "configoption$i";
                $value = $params['configoption' . $i];
                
                // Hide sensitive data
                if (strpos(strtolower($fieldName), 'password') !== false || 
                    strpos(strtolower($fieldName), 'secret') !== false) {
                    $value = '***HIDDEN***';
                }
                
                $debug['module_config'][$fieldName] = $value;
            }
        }
        
        if (function_exists('logModuleCall')) {
            logModuleCall('multiportal', 'Configuration Debug', $debug, '', '', []);
        }
        
        return $debug;
    }
}