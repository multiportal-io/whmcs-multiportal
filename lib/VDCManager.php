<?php

require_once __DIR__ . '/ApiClient.php';

class VDCManager
{
    private $api;

    public function __construct(ApiClient $apiClient)
    {
        $this->api = $apiClient;
    }

    public $allocationTypes = [
        1 => 'Allocation',
        2 => 'PAYG',
    ];

    public function getAllocationTypeByName($name)
    {
        $name = strtolower($name);
        foreach ($this->allocationTypes as $key => $value) {
            if (strtolower($value) === $name) {
                return $key;
            }
        }
        return null;
    }

    public function createVDC($name, $dataCenterId, $tenantId, $cpu, $ram, $enabled = true, $allocationType = 1)
    {
        $data = [
            'data_center_id' => $dataCenterId,
            'company_id' => $tenantId,
            'vdc_name' => $name,
            'allocation_type' => $allocationType, // 1: Reserved, 2: Burstable (if supported)
            'memory_in_gb' => $ram,
            'core_count' => $cpu,
            'is_enabled' => $enabled ? 1 : 0
        ];

        $response = $this->api->post('/virtual-data-center', $data);

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        throw new Exception('Failed to create VDC: ' . json_encode($response));
    }

    public function updateVDC($vdcId, $data)
    {
        $response = $this->api->put('/virtual-data-center/' . $vdcId, $data);

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        throw new Exception('Failed to update VDC: ' . json_encode($response));
    }

    public function deleteVDC($vdcId)
    {
        $response = $this->api->delete('/virtual-data-center/' . $vdcId);

        if ($response['status'] === 'success') {
            return true;
        }

        throw new Exception('Failed to delete Virtual Data Center: ' . json_encode($response));
    }

    public function listVDCs($tenantId)
    {
        return $this->api->get('/virtual-data-center', ['query' => $tenantId]);
    }

    public function getVDCById($vdcId)
    {
        $response = $this->api->get('/virtual-data-center/' . $vdcId);

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        return null;
    }



    public function getStoragePoliciesByDataCenter($dataCenterId)
    {
        $response = $this->api->get('/data-center/' . $dataCenterId . '/storage-policy');

        if ($response === null) {
            throw new Exception('Failed to get storage policies: Invalid response from API (null). Please check API connectivity and SSL settings.');
        }

        if (!isset($response['status'])) {
            throw new Exception('Failed to get storage policies: Invalid response format. Response: ' . json_encode($response));
        }

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        $errorMessage = isset($response['message']) ? $response['message'] : 'Unknown error';
        throw new Exception('Failed to get storage policies: ' . $errorMessage);
    }

    public function getStoragePolicyById($storagePolicyId)
    {
        $response = $this->api->get('/storage-policy/' . $storagePolicyId);

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        throw new Exception('Failed to get storage policy: ' . json_encode($response));
    }

    public function getStorageTierOptions($dataCenterId)
    {
        $response = $this->getStoragePoliciesByDataCenter($dataCenterId);
        $options = [];

        if (!empty($response['data']) && is_array($response['data'])) {
            foreach ($response['data'] as $policy) {
                if (!empty($policy['uuid']) && !empty($policy['name'])) {
                    $options[$policy['uuid']] = $policy['name'];
                }
            }
        }

        return $options;
    }


    public function getStoragePolicy($vdcId)
    {
        $response = $this->api->get('/virtual-data-center/' . $vdcId . '/storage-policy');

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        throw new Exception('Failed to get storage policy: ' . json_encode($response));
    }

    /**
     * Add a storage policy to a VDC
     * 
     */
    public function addStoragePolicy($vdcId, $data)
    {

        $response = $this->api->post('/virtual-data-center/' . $vdcId . '/storage-policy', $data);

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        throw new Exception('Failed to add storage policy: ' . json_encode($response));
    }

    public function updateStoragePolicy($vdcId, $storagePolicyId, $data)
    {
        $response = $this->api->put('/virtual-data-center/' . $vdcId . '/storage-policy/' . $storagePolicyId, $data);

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        throw new Exception('Failed to update storage policy: ' . json_encode($response));
    }

    public function deleteStoragePolicy($vdcId, $storagePolicyId)
    {
        $response = $this->api->delete('/virtual-data-center/' . $vdcId . '/storage-policy/' . $storagePolicyId);

        if ($response['status'] === 'success') {
            return true;
        }

        throw new Exception('Failed to delete storage policy: ' . json_encode($response));
    }

    /**
     * Get VDC usage statistics
     * 
     * @param string $vdcId
     * @param array $params Optional parameters
     * @return array
     * @throws Exception
     */
    public function getVDCUsage($vdcId, $params = [])
    {
        // The API expects date_range in format: "YYYY/MM/DD HH:MM:SS - YYYY/MM/DD HH:MM:SS"
        if (!isset($params['date_range'])) {
            $dateFrom = date('Y/m/d 00:00:00', strtotime('first day of this month'));
            $dateTo = date('Y/m/d 23:59:59');
            $params = ['date_range' => $dateFrom . ' - ' . $dateTo];
        }
        
        $response = $this->api->get('/virtual-data-center/' . $vdcId . '/usage', $params);

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        throw new Exception('Failed to get VDC usage: ' . json_encode($response));
    }
}
