<?php

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Utility: Set a custom field value for this service (e.g., VDC UUID)
 */
function setCustomFieldValue($serviceId, $fieldName, $value)
{
    $customField = Capsule::table('tblcustomfields')
        ->where('type', 'product')
        ->where('fieldname', $fieldName)
        ->first();

    if (!$customField) {
        throw new Exception("Custom field '$fieldName' not found.");
    }

    $existing = Capsule::table('tblcustomfieldsvalues')
        ->where('relid', $serviceId)
        ->where('fieldid', $customField->id)
        ->first();

    if ($existing) {
        Capsule::table('tblcustomfieldsvalues')
            ->where('id', $existing->id)
            ->update(['value' => $value]);
    } else {
        Capsule::table('tblcustomfieldsvalues')
            ->insert([
                'relid' => $serviceId,
                'fieldid' => $customField->id,
                'value' => $value,
            ]);
    }
}

function setClientCustomFieldValue($clientId, $fieldName, $value)
{
    $customField = Capsule::table('tblcustomfields')
        ->where('type', 'client')
        ->where('fieldname', $fieldName)
        ->first();

    if (!$customField) {
        throw new Exception("Client custom field '$fieldName' not found.");
    }

    $existing = Capsule::table('tblcustomfieldsvalues')
        ->where('relid', $clientId)
        ->where('fieldid', $customField->id)
        ->first();

    if ($existing) {
        Capsule::table('tblcustomfieldsvalues')
            ->where('id', $existing->id)
            ->update(['value' => $value]);
    } else {
        Capsule::table('tblcustomfieldsvalues')
            ->insert([
                'relid' => $clientId,
                'fieldid' => $customField->id,
                'value' => $value,
            ]);
    }
}
