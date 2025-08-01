<?php

require_once __DIR__ . '/ApiClient.php';

class ResellerManager
{
    private $api;

    public function __construct(ApiClient $apiClient)
    {
        $this->api = $apiClient;
    }

    public function findResellerByName($name)
    {
        $response = $this->api->get('/reseller', ['query' => $name]);

        if (!empty($response['data']['data']['items'])) {
            foreach ($response['data']['data']['items'] as $reseller) {
                if (trim(strtolower($reseller['name'])) === trim(strtolower($name))) {
                    return $reseller;
                }
            }
        }

        return null;
    }
    public function findResellerByID($id)
    {
        $response = $this->api->get('/reseller/' . $id);

        if (!empty($response['data']['uuid'])) {
            return $response['data']['uuid'];
        }

        return null;
    }


    public function createReseller($name, $address = '', $contact = '', $phone = '')
    {
        $reseller = $this->findResellerByName($name);

        if ($reseller) {
            return $reseller; // Already exists
        }

        $data = [
            'name' => $name,
            'address' => $address,
            'primary_contact_person' => $contact,
            'contact_number' => $phone
        ];

        $response = $this->api->post('/reseller', $data);

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        throw new Exception('Failed to create reseller: ' . json_encode($response));
    }
}
