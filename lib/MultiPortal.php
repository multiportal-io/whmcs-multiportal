<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/ApiClient.php';
require_once __DIR__ . '/ResellerManager.php';
require_once __DIR__ . '/TenantManager.php';
require_once __DIR__ . '/VDCManager.php';

class MultiPortal
{
    protected $params;
    protected $client;
    protected $service;
    protected $api;
    protected $resellerMgr;
    protected $tenantMgr;
    protected $vdcMgr;

    public function __construct($params)
    {
        $this->params = $params;
        $this->client = $this->getClient($params['userid']);
        $this->service = $this->getService($params['serviceid']);

        $this->api = new ApiClient(
            $params['serverusername'],
            $params['serverpassword'],
            $params['configoption1'] ?? 'https://matt.multiportal.io/api/v1'
        );

        $this->resellerMgr = new ResellerManager($this->api);
        $this->tenantMgr = new TenantManager($this->api);
        $this->vdcMgr = new VDCManager($this->api);
    }

    public function createAccount()
    {
        $clientName = $this->client->companyname ?: ($this->client->firstname . ' ' . $this->client->lastname);
        $contact = $this->client->firstname . ' ' . $this->client->lastname;

        $reseller = $this->resellerMgr->createReseller(
            $clientName,
            $this->client->address1,
            $contact,
            $this->client->phonenumber
        );

        $tenant = $this->tenantMgr->createTenant(
            $clientName . '-tenant',
            $reseller['uuid'],
            $this->client->address1,
            $contact,
            $this->client->phonenumber
        );

        $vdc = $this->vdcMgr->createVDC(
            'VDC - ' . $this->service->id,
            $this->params['configoption4'], // data_center_id
            $tenant['id'],
            (int) $this->params['configoption2'], // CPU
            (int) $this->params['configoption3']  // RAM
        );

        return 'success';
    }

    protected function getClient($clientId)
    {
        return Capsule::table('tblclients')->where('id', $clientId)->first();
    }

    protected function getService($serviceId)
    {
        return Capsule::table('tblhosting')->where('id', $serviceId)->first();
    }

    public function getCustomFieldValue($fieldId, $relId)
    {
        $value = Capsule::table('tblcustomfieldsvalues')
            ->where('fieldid', $fieldId)
            ->where('relid', $relId)
            ->value('value');

        return $value;
    }
}
