<?php
/**
 * PAYG Billing Hooks for Multiportal Module
 * 
 * This hook automatically adds usage charges to invoices for PAYG services
 */

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/lib/ApiClient.php';
require_once __DIR__ . '/lib/VDCManager.php';

/**
 * Add PAYG usage charges when invoice is being created
 */
add_hook('InvoiceCreation', 1, function($vars) {
    $invoiceId = $vars['invoiceid'];
    
    // Get all services associated with this invoice
    $invoiceItems = Capsule::table('tblinvoiceitems')
        ->where('invoiceid', $invoiceId)
        ->where('type', 'Hosting')
        ->get();
    
    foreach ($invoiceItems as $item) {
        $serviceId = $item->relid;
        
        // Get service details
        $service = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->first();
            
        if (!$service) {
            continue;
        }
        
        // Check if this is a Multiportal service
        $server = Capsule::table('tblservers')
            ->where('id', $service->server)
            ->first();
            
        if (!$server || $server->type !== 'multiportal') {
            continue;
        }
        
        // Get VDC UUID
        $vdcField = Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('fieldname', 'VDC UUID')
            ->first();
            
        if (!$vdcField) {
            continue;
        }
        
        $vdcValue = Capsule::table('tblcustomfieldsvalues')
            ->where('fieldid', $vdcField->id)
            ->where('relid', $serviceId)
            ->first();
            
        if (!$vdcValue || empty($vdcValue->value)) {
            continue;
        }
        
        $vdcId = $vdcValue->value;
        
        try {
            // Initialize API client
            $api = new ApiClient($server->username, $server->password);
            $vdcMgr = new VDCManager($api);
            
            // Get VDC details to check if it's PAYG
            $vdc = $vdcMgr->getVDCById($vdcId);
            if (!$vdc || !isset($vdc['allocation_type']) || $vdc['allocation_type'] != 2) {
                continue; // Not a PAYG VDC
            }
            
            // Get last invoice date for this service
            $lastInvoiceDate = multiportal_getLastInvoiceDate($serviceId);
            $currentDate = new DateTime();
            
            // Determine billing period
            if ($lastInvoiceDate) {
                $billingStart = new DateTime($lastInvoiceDate);
            } else {
                // If no previous invoice, use service creation date or first of month
                if ($service->regdate !== '0000-00-00') {
                    $billingStart = new DateTime($service->regdate);
                } else {
                    $billingStart = new DateTime('first day of this month');
                }
            }
            $billingEnd = $currentDate;
            
            // Get usage data for the billing period
            $usageParams = [
                'date_range' => $billingStart->format('Y/m/d 00:00:00') . ' - ' . $billingEnd->format('Y/m/d 23:59:59')
            ];
            $usage = $vdcMgr->getVDCUsage($vdcId, $usageParams);
            
            // Calculate charges
            $charges = multiportal_calculatePAYGCharges($usage, $billingStart, $billingEnd);
            
            if ($charges['total'] > 0) {
                // Add usage charge to invoice
                $description = sprintf(
                    "PAYG Usage Charges (%s to %s)\n%s",
                    $billingStart->format('Y-m-d'),
                    $billingEnd->format('Y-m-d'),
                    implode("\n", $charges['details'])
                );
                
                Capsule::table('tblinvoiceitems')->insert([
                    'invoiceid' => $invoiceId,
                    'userid' => $service->userid,
                    'type' => 'Item',
                    'relid' => $serviceId,
                    'description' => $description,
                    'amount' => $charges['total'],
                    'taxed' => $item->taxed
                ]);
                
                // Update invoice total
                multiportal_updateInvoiceTotal($invoiceId);
                
                // Log the charge
                logActivity("PAYG Usage Charge Added - Service ID: $serviceId, Amount: $" . number_format($charges['total'], 2));
            }
            
        } catch (Exception $e) {
            // Log error but don't stop invoice creation
            logActivity("PAYG Billing Error for Service ID $serviceId: " . $e->getMessage());
        }
    }
});

/**
 * Calculate PAYG charges based on usage data
 */
function multiportal_calculatePAYGCharges($usage, $billingStart, $billingEnd) {
    // Load configuration
    $config = include __DIR__ . '/payg_config.php';
    $rates = $config['rates'];
    
    $totalCharge = 0;
    $chargeDetails = [];
    
    // Check different possible usage data structures
    // This handles various API response formats
    
    // CPU charges
    $cpuHours = 0;
    if (isset($usage['cpu']['hours_used'])) {
        $cpuHours = $usage['cpu']['hours_used'];
    } elseif (isset($usage['cpu_hours'])) {
        $cpuHours = $usage['cpu_hours'];
    } elseif (isset($usage['compute']['cpu']['hours'])) {
        $cpuHours = $usage['compute']['cpu']['hours'];
    }
    
    if ($cpuHours > 0) {
        $cpuCharge = $cpuHours * $rates['cpu_per_hour'];
        $totalCharge += $cpuCharge;
        $chargeDetails[] = sprintf(
            "CPU: %d core-hours × $%.2f = $%.2f",
            $cpuHours,
            $rates['cpu_per_hour'],
            $cpuCharge
        );
    }
    
    // Memory charges
    $memoryHours = 0;
    if (isset($usage['memory']['hours_used'])) {
        $memoryHours = $usage['memory']['hours_used'];
    } elseif (isset($usage['memory_gb_hours'])) {
        $memoryHours = $usage['memory_gb_hours'];
    } elseif (isset($usage['compute']['memory']['hours'])) {
        $memoryHours = $usage['compute']['memory']['hours'];
    }
    
    if ($memoryHours > 0) {
        $memoryCharge = $memoryHours * $rates['memory_per_gb_hour'];
        $totalCharge += $memoryCharge;
        $chargeDetails[] = sprintf(
            "Memory: %d GB-hours × $%.2f = $%.2f",
            $memoryHours,
            $rates['memory_per_gb_hour'],
            $memoryCharge
        );
    }
    
    // Storage charges
    if (isset($usage['storage']) && is_array($usage['storage'])) {
        foreach ($usage['storage'] as $storage) {
            $storageHours = 0;
            if (isset($storage['hours_used'])) {
                $storageHours = $storage['hours_used'];
            } elseif (isset($storage['gb_hours'])) {
                $storageHours = $storage['gb_hours'];
            }
            
            if ($storageHours > 0) {
                $storageCharge = $storageHours * $rates['storage_per_gb_hour'];
                $totalCharge += $storageCharge;
                $chargeDetails[] = sprintf(
                    "Storage (%s): %d GB-hours × $%.2f = $%.2f",
                    $storage['policy_name'] ?? 'Standard',
                    $storageHours,
                    $rates['storage_per_gb_hour'],
                    $storageCharge
                );
            }
        }
    }
    
    return [
        'total' => round($totalCharge, 2),
        'details' => $chargeDetails
    ];
}

/**
 * Get the date of the last invoice for a service
 */
function multiportal_getLastInvoiceDate($serviceId) {
    $lastInvoice = Capsule::table('tblinvoiceitems')
        ->join('tblinvoices', 'tblinvoiceitems.invoiceid', '=', 'tblinvoices.id')
        ->where('tblinvoiceitems.relid', $serviceId)
        ->where('tblinvoiceitems.type', 'Item')
        ->where('tblinvoiceitems.description', 'LIKE', 'PAYG Usage Charges%')
        ->orderBy('tblinvoices.date', 'desc')
        ->first();
    
    if ($lastInvoice) {
        // Extract end date from description
        if (preg_match('/\(.*to (\d{4}-\d{2}-\d{2})\)/', $lastInvoice->description, $matches)) {
            return $matches[1];
        }
        return $lastInvoice->date;
    }
    
    return null;
}

/**
 * Update invoice total after adding items
 */
function multiportal_updateInvoiceTotal($invoiceId) {
    $total = Capsule::table('tblinvoiceitems')
        ->where('invoiceid', $invoiceId)
        ->sum('amount');
    
    Capsule::table('tblinvoices')
        ->where('id', $invoiceId)
        ->update(['total' => $total]);
}