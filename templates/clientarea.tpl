{* Always show MultiPortal credentials if they exist *}
{if $multiportal.url && $multiportal.username}
<div class="row">
    <div class="col-md-12">
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fas fa-sign-in-alt"></i> MultiPortal Access</h3>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-8">
                        <table class="table table-condensed">
                            <tr>
                                <td width="30%"><strong>Portal URL:</strong></td>
                                <td><a href="{$multiportal.url}" target="_blank" class="btn btn-sm btn-primary"><i class="fas fa-external-link-alt"></i> {$multiportal.url}</a></td>
                            </tr>
                            <tr>
                                <td><strong>Username:</strong></td>
                                <td><code>{$multiportal.username|escape:'html'}</code></td>
                            </tr>
                            <tr>
                                <td><strong>Password:</strong></td>
                                <td>
                                    <span class="password-field" id="password-display">••••••••</span>
                                    <input type="hidden" id="password-value" value="{$multiportal.password|escape:'html'}" />
                                    <button type="button" class="btn btn-xs btn-default toggle-password" style="margin-left: 10px;">
                                        <i class="fas fa-eye"></i> Show
                                    </button>
                                    <button type="button" class="btn btn-xs btn-primary copy-password" style="margin-left: 5px;">
                                        <i class="fas fa-copy"></i> Copy
                                    </button>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-4 text-center">
                        <br>
                        <a href="{$multiportal.url}" target="_blank" class="btn btn-success btn-lg">
                            <i class="fas fa-external-link-alt"></i> Access MultiPortal
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
{/if}

{if $status eq 'pending'}
    <div class="alert alert-info">
        <i class="fas fa-spinner fa-spin"></i> {$message}
    </div>
{elseif $status eq 'error'}
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> {$message}
    </div>
{/if}

{* Show VDC and other information only if status is active and VDC exists *}
{if $status eq 'active' && $vdc}
    <h3>VDC Information</h3>
    
    {* Tabs Navigation *}
    <ul class="nav nav-tabs" role="tablist">
        <li role="presentation" class="active">
            <a href="#overview" aria-controls="overview" role="tab" data-toggle="tab">
                <i class="fas fa-info-circle"></i> Overview
            </a>
        </li>
        <li role="presentation">
            <a href="#usage" aria-controls="usage" role="tab" data-toggle="tab">
                <i class="fas fa-chart-line"></i> Usage
            </a>
        </li>
        <li role="presentation">
            <a href="#storage" aria-controls="storage" role="tab" data-toggle="tab">
                <i class="fas fa-hdd"></i> Storage
            </a>
        </li>
        <li role="presentation">
            <a href="#billing" aria-controls="billing" role="tab" data-toggle="tab">
                <i class="fas fa-dollar-sign"></i> Billing
            </a>
        </li>
    </ul>
    
    {* Tab Content *}
    <div class="tab-content" style="margin-top: 20px;">
        {* Overview Tab *}
        <div role="tabpanel" class="tab-pane active" id="overview">
            <div class="row">
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="fas fa-server"></i> VDC Details</h3>
                        </div>
                        <div class="panel-body">
                            <table class="table table-striped">
                                <tr>
                                    <td><strong>Name:</strong></td>
                                    <td>{if $vdc.name}{$vdc.name}{else}VDC{/if}</td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        {if $vdc.status eq 'Active'}
                                            <span class="label label-success">{$vdc.status}</span>
                                        {else}
                                            <span class="label label-warning">{$vdc.status}</span>
                                        {/if}
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>CPU Cores:</strong></td>
                                    <td>{$vdc.cpu_cores}</td>
                                </tr>
                                <tr>
                                    <td><strong>Memory:</strong></td>
                                    <td>{$vdc.memory_gb} GB</td>
                                </tr>
                                <tr>
                                    <td><strong>Allocation Type:</strong></td>
                                    <td>{$vdc.allocation_type}</td>
                                </tr>
                                <tr>
                                    <td><strong>Created:</strong></td>
                                    <td>{$vdc.created_at}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="fas fa-tachometer-alt"></i> Quick Stats</h3>
                        </div>
                        <div class="panel-body">
                            {if $usage}
                                <div class="row text-center">
                                    <div class="col-xs-6">
                                        <h3 class="no-margin">{$usage.total_uptime|default:0} hrs</h3>
                                        <p class="text-muted">Total Uptime</p>
                                    </div>
                                    <div class="col-xs-6">
                                        <h3 class="no-margin">{$usage.vms.running|default:0}/{$usage.vms.total|default:0}</h3>
                                        <p class="text-muted">Running VMs</p>
                                    </div>
                                </div>
                            {else}
                                <p class="text-center text-muted">Usage data unavailable</p>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        {* Usage Tab *}
        <div role="tabpanel" class="tab-pane" id="usage">
            {if $usage}
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fas fa-chart-line"></i> Cumulative Usage Totals</h3>
                    </div>
                    <div class="panel-body">
                        {if $usage.period}
                        <div class="alert alert-warning" style="margin-bottom: 20px;">
                            <h4><i class="fas fa-calendar-alt"></i> Date Range Comparison</h4>
                            <p><strong>Requested Period:</strong> {$usage.period.requested_start} - {$usage.period.requested_end}</p>
                            <p><strong>API Returned Period:</strong> {$usage.period.api_start} - {$usage.period.api_end}</p>
                            {if $usage.period.requested_start != $usage.period.api_start || $usage.period.requested_end != $usage.period.api_end}
                                <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> <strong>WARNING:</strong> API is ignoring the date range filter!</p>
                            {else}
                                <p class="text-success"><i class="fas fa-check-circle"></i> Date range filter is working correctly.</p>
                            {/if}
                        </div>
                        {/if}
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-striped">
                                    <tr>
                                        <td><strong>Total Uptime:</strong></td>
                                        <td class="text-right">{$usage.total_uptime|default:0} hours</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total CPU Usage Hours:</strong></td>
                                        <td class="text-right">{$usage.total_cpu_hours|default:0} cores</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Memory Usage Hours:</strong></td>
                                        <td class="text-right">{$usage.total_memory_tib|default:0} TiB</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-striped">
                                    <tr>
                                        <td><strong>Total VMs:</strong></td>
                                        <td class="text-right">{$usage.vms.total|default:0}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Running VMs:</strong></td>
                                        <td class="text-right">
                                            {if $usage.vms.running > 0}
                                                <span class="label label-success">{$usage.vms.running|default:0}</span>
                                            {else}
                                                <span class="label label-default">{$usage.vms.running|default:0}</span>
                                            {/if}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Stopped VMs:</strong></td>
                                        <td class="text-right">
                                            {assign var="stopped_vms" value=($usage.vms.total|default:0) - ($usage.vms.running|default:0)}
                                            {if $stopped_vms > 0}
                                                <span class="label label-warning">{$stopped_vms}</span>
                                            {else}
                                                <span class="label label-default">{$stopped_vms}</span>
                                            {/if}
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        {if $usage.storage_breakdown && count($usage.storage_breakdown) > 0}
                        <div class="row" style="margin-top: 20px;">
                            <div class="col-md-12">
                                <h4><i class="fas fa-hdd"></i> Storage Usage Breakdown</h4>
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Storage Policy</th>
                                            <th>Average Usage</th>
                                            <th>Capacity</th>
                                            <th>Utilization</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {foreach $usage.storage_breakdown as $storage}
                                        <tr>
                                            <td>{$storage.name}</td>
                                            <td>{$storage.usage_gb} GB</td>
                                            <td>{$storage.capacity_gb} GB</td>
                                            <td>{$storage.utilization}</td>
                                        </tr>
                                        {/foreach}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        {/if}
                    </div>
                </div>
            {else}
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Usage information is currently unavailable.
                </div>
            {/if}
        </div>
        
        {* Storage Tab *}
        <div role="tabpanel" class="tab-pane" id="storage">
            {if $storage_policies && count($storage_policies) > 0}
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fas fa-database"></i> Storage Policies</h3>
                    </div>
                    <div class="panel-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Policy Name</th>
                                    <th>Total Capacity</th>
                                    <th>Used</th>
                                    <th>Available</th>
                                    <th>Usage</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach $storage_policies as $policy}
                                <tr>
                                    <td>{$policy.name}</td>
                                    <td>{$policy.capacity} GB</td>
                                    <td>{$policy.used} GB</td>
                                    <td>{$policy.available} GB</td>
                                    <td>
                                        {assign var="percentage" value=0}
                                        {if $policy.capacity > 0}
                                            {assign var="percentage" value=($policy.used / $policy.capacity * 100)|round}
                                        {/if}
                                        <div class="progress" style="margin-bottom: 0;">
                                            <div class="progress-bar {if $percentage > 80}progress-bar-danger{elseif $percentage > 60}progress-bar-warning{else}progress-bar-success{/if}" 
                                                 style="width: {$percentage}%;">
                                                {$percentage}%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                </div>
            {else}
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No storage policies configured.
                </div>
            {/if}
        </div>
        
        {* Billing Tab *}
        <div role="tabpanel" class="tab-pane" id="billing">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fas fa-dollar-sign"></i> Billing Information</h3>
                </div>
                <div class="panel-body">
                    {* Show current service billing info from WHMCS *}
                    <div class="row">
                        <div class="col-md-6">
                            <h4>Service Details</h4>
                            <table class="table table-striped">
                                <tr>
                                    <td><strong>Billing Cycle:</strong></td>
                                    <td>{$billingcycle}</td>
                                </tr>
                                <tr>
                                    <td><strong>Amount:</strong></td>
                                    <td>{$amount}</td>
                                </tr>
                                <tr>
                                    <td><strong>Next Due Date:</strong></td>
                                    <td>{$nextduedate}</td>
                                </tr>
                                <tr>
                                    <td><strong>Payment Method:</strong></td>
                                    <td>{$paymentmethod}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h4>Allocation Type</h4>
                            <div class="alert alert-info">
                                <strong>{$vdc.allocation_type}</strong>
                                {if $vdc.allocation_type eq 'Pay As You Go'}
                                    <p class="small" style="margin-top: 10px;">You are charged based on actual resource usage. Usage is calculated monthly and added to your next invoice.</p>
                                {else}
                                    <p class="small" style="margin-top: 10px;">You have a fixed allocation of resources included in your plan.</p>
                                {/if}
                            </div>
                        </div>
                    </div>
                    
                    {if $usage && $vdc.allocation_type eq 'Pay As You Go' && $usage.pricing}
                    <hr>
                    <h4>Current Period Usage & Charges</h4>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> Usage charges will be calculated at the end of the billing period and added to your next invoice.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Usage Summary</h5>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Resource</th>
                                        <th>Usage</th>
                                        <th>Rate</th>
                                        <th>Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>CPU</td>
                                        <td>{$usage.total_cpu_hours|string_format:"%.1f"} core-hours</td>
                                        <td>${$usage.pricing.rates.cpu_per_hour}/hour</td>
                                        <td><strong>${$usage.pricing.costs.cpu|string_format:"%.2f"}</strong></td>
                                    </tr>
                                    <tr>
                                        <td>Memory</td>
                                        <td>{$usage.total_memory_gb_hours|string_format:"%.1f"} GB-hours</td>
                                        <td>${$usage.pricing.rates.memory_per_gb_hour}/GB/hour</td>
                                        <td><strong>${$usage.pricing.costs.memory|string_format:"%.2f"}</strong></td>
                                    </tr>
                                    <tr>
                                        <td>Storage</td>
                                        <td>
                                            {foreach $usage.storage_breakdown as $storage}
                                                {$storage.name}: {$storage.usage_gb} GB<br>
                                            {/foreach}
                                        </td>
                                        <td>${$usage.pricing.rates.storage_per_gb_hour}/GB/hour</td>
                                        <td><strong>${$usage.pricing.costs.storage|string_format:"%.2f"}</strong></td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="info">
                                        <td colspan="3" class="text-right"><strong>Total Estimated Charges:</strong></td>
                                        <td><strong class="text-primary">${$usage.pricing.costs.total|string_format:"%.2f"}</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5>Billing Period</h5>
                            <div class="well">
                                <p><strong>Period:</strong> {$usage.period.api_start} - {$usage.period.api_end}</p>
                                <p><strong>VM Uptime:</strong> {$usage.total_uptime} hours</p>
                                <p><strong>Active VMs:</strong> {$usage.vms.running} of {$usage.vms.total}</p>
                            </div>
                            
                            <div class="alert alert-info">
                                <h5><i class="fas fa-calculator"></i> How charges are calculated:</h5>
                                <ul class="small">
                                    <li><strong>CPU:</strong> Core-hours × ${$usage.pricing.rates.cpu_per_hour}</li>
                                    <li><strong>Memory:</strong> GB-hours × ${$usage.pricing.rates.memory_per_gb_hour}</li>
                                    <li><strong>Storage:</strong> GB × Hours × ${$usage.pricing.rates.storage_per_gb_hour}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    {/if}
                </div>
            </div>
        </div>
    </div>
    
    <div class="row" style="margin-top: 20px;">
        <div class="col-md-12">
            <div class="well well-sm">
                <p><i class="fas fa-info-circle"></i> <strong>Need help?</strong> For any issues or questions about your VDC, please contact our support team.</p>
            </div>
        </div>
    </div>
{/if}

<style>
.no-margin { margin: 0; }
.nav-tabs > li > a {
    padding: 10px 20px;
}
.progress {
    margin-bottom: 10px;
}
</style>

<script>
$(document).ready(function() {
    // Toggle password visibility
    $('.toggle-password').click(function() {
        var btn = $(this);
        var passwordField = btn.siblings('.password-field');
        var passwordInput = btn.siblings('input[type="hidden"]');
        var password = passwordInput.val();
        
        if (passwordField.text() === '••••••••') {
            passwordField.text(password);
            btn.html('<i class="fas fa-eye-slash"></i> Hide');
        } else {
            passwordField.text('••••••••');
            btn.html('<i class="fas fa-eye"></i> Show');
        }
    });
    
    // Copy password to clipboard
    $('.copy-password').click(function() {
        var btn = $(this);
        var passwordInput = btn.siblings('input[type="hidden"]');
        var password = passwordInput.val();
        
        // Create temporary input element
        var $temp = $("<input>");
        $("body").append($temp);
        $temp.val(password).select();
        
        // Copy to clipboard
        document.execCommand("copy");
        $temp.remove();
        
        // Show feedback
        var originalHtml = btn.html();
        btn.html('<i class="fas fa-check"></i> Copied!');
        btn.removeClass('btn-primary').addClass('btn-success');
        
        setTimeout(function() {
            btn.html(originalHtml);
            btn.removeClass('btn-success').addClass('btn-primary');
        }, 2000);
    });
});
</script>