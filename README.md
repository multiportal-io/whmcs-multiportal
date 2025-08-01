# WHMCS MultiPortal Module

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![WHMCS 8.0+](https://img.shields.io/badge/WHMCS-8.0%2B-blue.svg)](https://www.whmcs.com/)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net/)

A comprehensive WHMCS server module for managing MultiPortal Virtual Data Centers (VDCs). This module enables automated provisioning, management, and synchronization of VDCs through the MultiPortal API.

## Key Features

- **📦 Automated VDC Provisioning**: Create VDCs with configurable CPU, memory, and storage
- **👥 Tenant Management**: Automatic tenant creation and association
- **💾 Dynamic Storage Policies**: Automatically fetches and configures storage options from your data center
- **🔄 VDC Operations**: Suspend, unsuspend, update, and delete VDCs
- **🔄 Real-time Sync**: Pull latest VDC configuration from API and update WHMCS
- **👁️ Client Area Integration**: Beautiful display of VDC information and storage usage
- **💳 PAYG Billing**: Support for Pay-As-You-Go billing with usage sync
- **🛡️ Safety Features**: Two-click confirmation required for VDC deletion
- **📊 Comprehensive Logging**: Debug logging for all API operations
- **🔧 Zero Manual Setup**: Custom fields created automatically on activation

## Requirements

- WHMCS 8.0 or higher
- PHP 7.4 or higher
- [MultiPortal API credentials](https://wiki.multiportal.io/en/service-provider/api)
- SSL/TLS support for API communication

## Quick Start Installation

### Step 1: Install Module

1. Download the module or clone the module to `/modules/servers/multiportal` in your WHMCS 
2. Navigate to **Setup > Products/Services > Servers**
3. Click **Add New Server**
4. Click **Go to Advanced** and configure:
   - **Module**: MultiPortal
   - **Name**: Your choice (e.g., "MultiPortal API")
   - **Hostname**: Your hostname
   - **Username**: Your MultiPortal API Client ID
   - **Password**: Your MultiPortal API Client Secret
   - **Access Hash**: Your data center UUID (e.g., `6178e7b1-7ecd-4df3-918c-75ce56bb455`)
   - **Secure**: ✓ (for HTTPS)
   - Save Changes

### Step 2: Create Product

1. Go to **Setup > Products/Services > Products/Services**
2. Click **Create a New Product**
3. Fill in the product details:
   - **Product Type**: Other
   - **Product Group**: Select or create a group
   - **Product Name**: Your choice (e.g., "Virtual Data Center")
   - **Module**: Select MultiPortal
4. Click **Continue**
5. Go to the **Module Settings** tab and configure:
   - **Module Name**: MultiPortal
   - **Server Group**: None (or select if you have multiple servers)
   - **API Base URL**: `https://myfqdn.domain.local/api/v1` (or your API URL)
   - **Data Center UUID**: Your Data Center  UUID (e.g., `ce52d396-0ff2-42d4-80cf-4f393088df4d`)
   - **Reseller UUID**: Your reseller UUID (e.g., `ce52d396-0ff2-42d4-80cf-4f393088df4d`)
   - **PAYG CPU Rate**: Cost per CPU core per hour (default: $0.10)
   - **PAYG Memory Rate**: Cost per GB RAM per hour (default: $0.05)
   - **PAYG Storage Rate**: Cost per GB storage per hour (default: $0.01)
6. Save Changes

### Step 4: Auto-Configure Options
To have all the custom fields setup automatically, you first need to create a dummy order.

1. Create a test order for the product
2. Go to the service in admin area
3. Click **"Setup Product Options"** button
4. The wizard automatically creates all configurable options
5. Configure pricing for each option

That's it! The module is now ready to use.

## Module Configuration

| Setting | Description | Example |
|---------|-------------|---------|
| **Module Name** | Must be set to "MultiPortal" | `MultiPortal` |
| **Server Group** | Select server group or None | `None` |
| **API Base URL** | The base URL for the MultiPortal API | `https://myfqdn.domain.local/api/v1` |
| **Data Centuer UUID** | UUID of the data center | `ce52d396-0ff2-42d4-80cf-4f393088df4d` |
| **Reseller UUID** | UUID of the reseller account | `ce52d396-0ff2-42d4-80cf-4f393088df4d` |
| **PAYG CPU Rate ($/hour)** | Cost per CPU core per hour for PAYG billing | `0.10` |
| **PAYG Memory Rate ($/GB/hour)** | Cost per GB RAM per hour for PAYG billing | `0.05` |
| **PAYG Storage Rate ($/GB/hour)** | Cost per GB storage per hour for PAYG billing | `0.01` |


### Configurable Options - Automatic Setup

The module includes an automatic setup step for configurable options that is done from a fresh product order before going live due to how we see WHMCS handle setup. As this is a Server Module it's handled slightly different to Addons. 

1. Create a new product with MultiPortal as the module type
2. Configure the module settings
3. Save the product
4. Go to the client area and view any test service for this product
5. Click **"Setup Product Options"** button in the Module Commands section
6. The wizard will automatically create all necessary configurable options

The setup creates:

- **CPU** (Quantity: 1-128 cores)
- **Memory Allocation** (Quantity: 1-512 GB)
- **Storage Policies** (Quantity: dynamically fetched from your data center)
- **Allocation Type** (Dropdown: Allocation/Pay As You Go)

After setup, configure pricing for each option in the product's configurable options.

**⚠️ IMPORTANT - WHMCS Bug Workaround**: After running the Setup Wizard, you MUST do this:

1. Go to Setup → Products/Services → Configurable Option Groups
2. Edit the option group that was just created (e.g., "MultiPortal Options - Product 1")
3. Click on ANY Storage option (e.g., Silver)
4. Click Save WITHOUT making any changes
5. This fixes the Allocation Type dropdown not showing its options (Allocation/Pay As You Go)

This is a known WHMCS caching issue that requires this manual refresh step.

### Custom Fields

The module automatically creates and manages these custom fields:

- **Client Custom Field**: `MultiPortal Tenant UUID` - Stores the tenant identifier (visible at Setup → Clients → Custom Fields)
- **Product Custom Field**: `VDC UUID` - Stores the VDC identifier (created per-product during Setup Wizard, visible in product's Custom Fields tab)

## Usage

### Admin Functions

The module provides the following admin actions:

- **Setup Product Options**: One-click automatic configuration (appears when no options exist)
- **Create**: Initial VDC provisioning with automatic tenant creation
- **Update**: Modify VDC configuration (CPU, Memory, Storage policies)
- **Suspend**: Disable VDC access (sets is_enabled to 0)
- **Unsuspend**: Re-enable VDC access (sets is_enabled to 1)
- **Sync Data**: Pull latest VDC data from API and update all configurable options
- **View Usage**: Display real-time CPU, memory, and storage usage statistics
- **Sync Usage & Bill**: (PAYG only) Sync usage data and create billable items
- **Delete Virtual Data Center ⚠️**: Permanently delete VDC (requires two clicks within 30 seconds)

### Client Area

- VDC status and configuration
- CPU and memory allocation
- Storage policy usage and capacity
- VDC creation date
- Real-time resource usage with visual progress bars
  - CPU usage percentage and cores used
  - Memory usage percentage and GB used
  - Storage usage per policy with percentages

## API Integration

The module integrates with the following MultiPortal API endpoints:

- `/virtual-data-center` - VDC management
- `/virtual-data-center/{id}/usage` - VDC usage statistics
- `/company` - Tenant management
- `/reseller` - Reseller verification
- `/data-center/{id}/storage-policy` - Storage policy retrieval
- `/virtual-data-center/{id}/storage-policy` - Storage policy assignment

## Module Structure

``` bash
multiportal/
├── multiportal.php              # Main module file
├── hooks.php                    # Module hooks (delete confirmation)
├── payg_config.php              # Default fallback for PAYG Charges if not configured
├── hooks.php                    # Hooks file for Admin manipulation
├── hooks_payg.php               # Adds calculation functions for PAYG service type
├── hooks_remove_domain.php      # Hook to remove the domain if found
├── lib/
│   ├── ApiClient.php            # API communication class
│   ├── MultiPortal.php          # MultiPortal class
│   ├── ResellerManager.php      # Reseller management
│   ├── TenantManager.php        # Tenant/Company management
│   ├── VDCManager.php           # VDC operations
│   ├── ModuleConfiguration.php  # Module Configuration class
│   └── CustomFieldFunctions.php # Custom field helpers
├── templates/
│   └── clientarea.tpl           # Client area template
├── lang/
│   └── english.php              # Language file (not yet implemented)
├── logs                         # Log File
├── LICENSE                         # LICENSE File
└── README.md                    # This file
```

## Troubleshooting

### Enable Debug Logging

1. Navigate to **Utilities > Logs > Module Log** in WHMCS admin
2. Enable module logging
3. Perform the action that's causing issues
4. Check the module log for detailed API requests/responses

### Common Issues

**VDC Creation Fails:**

- Verify API credentials are correct
- Check that Data Center UUID and Reseller UUID are valid
- Ensure sufficient resources are available

**Storage Policies Not Showing:**

- Verify the data center has storage policies configured
- Check that configurable option names match format: `Storage - [Policy Name]`

**Sync Data Not Updating:**

- Ensure VDC UUID custom field exists and has a value
- Check API connectivity
- Verify the VDC exists in MultiPortal

**Usage Data Not Showing:**

- Test the API endpoint using `test_usage.php` or `test_usage_simple.php`
- Verify the VDC has usage data available
- Check module logs for API errors

## Pay-As-You-Go (PAYG) Billing

The module supports PAYG billing for VDCs configured with allocation_type = 2 in Multiportal.

### PAYG Features

- **Usage Sync**: Fetch resource consumption data from Multiportal
- **Automated Billing**: Create billable items based on actual usage
- **Billing Period Tracking**: Prevents duplicate charges with sync date tracking
- **Flexible Rates**: Configurable per-hour rates for CPU, memory, and storage

### PAYG Setup

1. Configure PAYG rates in the product's Module Settings:
   - **PAYG CPU Rate**: Cost per CPU core per hour (default: $0.10)
   - **PAYG Memory Rate**: Cost per GB RAM per hour (default: $0.05)
   - **PAYG Storage Rate**: Cost per GB storage per hour (default: $0.01)
2. Create a VDC with "Pay As You Go" allocation type in the product configuration
3. The VDC will be created with allocation_type = 2 in Multiportal
4. Use the "Sync Usage & Bill" button to sync usage and create charges

## Contributing

Contributions are welcome! Please see our [Contributing Guidelines](CONTRIBUTING.md) for details.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For issues and feature requests, please use the [GitHub issue tracker](https://github.com/multi-portal/whmcs-multiportal/issues).

## What Gets Created Automatically

### On Setup Product OPtions Click

- ✅ Configurable option group named "MultiPortal Options - Product X"
- ✅ CPU option (quantity 1-128)
- ✅ Memory Allocation option (quantity 1-512 GB)
- ✅ Dynamic storage policies based on your data center
- ✅ Allocation Type dropdown (Allocation/Pay As You Go)
- ✅ Client custom field: "MultiPortal Tenant UUID"
- ✅ Product custom field: "VDC UUID"

### On VDC Creation

- ✅ Tenant and user automatically created if not exists
- ✅ VDC provisioned with selected configuration
- ✅ Storage policies attached based on selections
- ✅ All UUIDs stored in custom fields

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a detailed list of changes.
