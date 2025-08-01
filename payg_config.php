<?php
/**
 * PAYG Billing Configuration
 * 
 * Configure rates and settings for Pay-As-You-Go billing
 */

$payg_config = [
    // Hourly rates for resources
    'rates' => [
        'cpu_per_hour' => 0.10,          // Cost per CPU core per hour
        'memory_per_gb_hour' => 0.05,    // Cost per GB of RAM per hour
        'storage_per_gb_hour' => 0.01,   // Cost per GB of storage per hour
    ],
    
    // Billing settings
    'billing' => [
        'minimum_charge' => 0.01,        // Minimum charge amount to create an invoice item
        'round_precision' => 2,          // Decimal places for rounding
        'tax_usage_charges' => true,     // Whether to apply tax to usage charges
    ],
    
    // Usage data field mappings
    // Map API response fields to billing categories
    'field_mappings' => [
        'cpu' => [
            'hours_used',
            'cpu_hours',
            'compute.cpu.hours'
        ],
        'memory' => [
            'hours_used',
            'memory_gb_hours',
            'compute.memory.hours'
        ],
        'storage' => [
            'hours_used',
            'gb_hours',
            'storage_hours'
        ]
    ]
];