<?php

require_once __DIR__ . '/ApiClient.php';

class TenantManager
{
    private $api;

    public function __construct(ApiClient $apiClient)
    {
        $this->api = $apiClient;
    }

    public function findTenantById($id)
    {
        $response = $this->api->get('/tenant/' . $id);

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        return null;
    }
    
    public function findTenantByName($name)
    {
        $response = $this->api->get('/tenant', ['query' => $name]);

        if (!empty($response['data']['items'])) {
            foreach ($response['data']['items'] as $tenant) {
                if (trim(strtolower($tenant['name'])) === trim(strtolower($name))) {
                    return $tenant;
                }
            }
        }

        return null;
    }

    public function createTenant($name, $resellerId, $address = '', $contact = '', $phone = '')
    {
        $tenant = $this->findTenantByName($name);
        
        if ($tenant) {
            return $tenant; // Already exists
        }

        $data = [
            'name' => $name,
            'reseller_id' => $resellerId,
            'address' => $address,
            'primary_contact_person' => $contact,
            'contact_number' => $phone
        ];

        $response = $this->api->post('/tenant', $data);

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        throw new Exception('Failed to create tenant: ' . json_encode($response));
    }
    
    public function createUser($tenantId, $username, $password, $email, $firstName = '', $lastName = '', $role = 'Tenant Administrator')
    {
        $data = [
            'username' => $username,
            'password' => $password,
            'confirmPassword' => $password,
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'role' => $role,
            'status' => 'active'
        ];
        
        // Log the request for debugging
        error_log('MultiPortal createUser request: ' . json_encode([
            'endpoint' => '/tenant/' . $tenantId . '/user',
            'data' => array_merge($data, ['password' => '***', 'confirmPassword' => '***'])
        ]));
        
        $response = $this->api->post('/tenant/' . $tenantId . '/user', $data);
        
        // Log the response for debugging
        error_log('MultiPortal createUser response: ' . json_encode($response));
        
        if ($response['status'] === 'success') {
            return $response['data'];
        }
        
        throw new Exception('Failed to create user: ' . json_encode($response));
    }
    
    public function getUsersByTenant($tenantId)
    {
        $response = $this->api->get('/tenant/' . $tenantId . '/users');
        
        if ($response['status'] === 'success') {
            return $response['data'];
        }
        
        return [];
    }
}
