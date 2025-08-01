<?php
/**
 * Hook to remove domain display for MultiPortal VDC products
 * 
 * This prevents WHMCS from showing VDC names as domain links in the products list
 */

use WHMCS\Database\Capsule;

// Hook into the services array before display
add_hook('ClientAreaPageProductsServices', 1, function($vars) {
    // Check if we have services
    if (!empty($vars['services']) && is_array($vars['services'])) {
        foreach ($vars['services'] as $key => &$service) {
            // Check if this service uses MultiPortal module
            $hosting = Capsule::table('tblhosting')
                ->select('tblproducts.servertype')
                ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
                ->where('tblhosting.id', $service['id'])
                ->first();
            
            if ($hosting && $hosting->servertype === 'multiportal') {
                // Remove domain from the service array
                $service['domain'] = '';
                $service['domainname'] = '';
                
                // Also clear it in the database to prevent future issues
                Capsule::table('tblhosting')
                    ->where('id', $service['id'])
                    ->update(['domain' => '']);
            }
        }
    }
    
    return $vars;
});

// Also hook into the product details page
add_hook('ClientAreaPageProductDetails', 1, function($vars) {
    if (isset($vars['producttype']) && isset($vars['module']) && $vars['module'] === 'multiportal') {
        // Clear domain-related fields
        $vars['domain'] = '';
        $vars['domainname'] = '';
        $vars['groupname'] = $vars['groupname'] ?? 'Virtual Data Centers';
    }
    
    return $vars;
});