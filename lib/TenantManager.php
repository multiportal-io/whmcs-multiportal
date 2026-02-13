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
        $response = $this->api->get('/tenant/' . $tenantId . '/user', ['pageSize' => 100]);

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        return [];
    }

    /**
     * Find a user in a tenant by username or email.
     *
     * @return array|null User data or null if not found
     */
    public function findUserInTenant($tenantId, $username = null, $email = null)
    {
        // GET /tenant/{id}/user — note: singular "user", not "users"
        $response = $this->api->get('/tenant/' . $tenantId . '/user', ['pageSize' => 100]);

        if (function_exists('logModuleCall')) {
            logModuleCall('multiportal', 'findUserInTenant_raw', [
                'tenantId' => $tenantId,
                'looking_for' => ['username' => $username, 'email' => $email],
            ], json_encode($response), 'Raw API response for user list', []);
        }

        if (!isset($response['status']) || $response['status'] !== 'success' || empty($response['data'])) {
            return null;
        }

        // API returns: { status: success, data: { data: [...users], count, page, pageSize } }
        $userList = isset($response['data']['data']) ? $response['data']['data'] : [];

        if (empty($userList)) {
            return null;
        }

        foreach ($userList as $user) {
            if (!is_array($user)) {
                continue;
            }
            if ($username && isset($user['username']) && $user['username'] === $username) {
                return $user;
            }
            if ($email && isset($user['email']) && strtolower($user['email']) === strtolower($email)) {
                return $user;
            }
        }

        if (function_exists('logModuleCall')) {
            logModuleCall('multiportal', 'findUserInTenant_notfound', [
                'looking_for' => ['username' => $username, 'email' => $email],
                'user_count' => count($userList),
                'usernames_in_list' => array_column($userList, 'username'),
                'emails_in_list' => array_column($userList, 'email'),
            ], '', 'User not found in list', []);
        }

        return null;
    }

    /**
     * Update a user's password (and optionally other fields) in a tenant.
     *
     * The API requires ALL fields on PUT (not just changed ones), so we
     * fetch the current user first and merge in the updates.
     */
    public function updateUser($tenantId, $userId, $data)
    {
        // Fetch current user data — PUT requires all fields
        $current = $this->api->get('/tenant/' . $tenantId . '/user/' . $userId);

        if (!isset($current['status']) || $current['status'] !== 'success' || empty($current['data'])) {
            throw new Exception('Failed to fetch user for update: ' . json_encode($current));
        }

        $userData = $current['data'];
        $payload = [
            'username'        => isset($data['username']) ? $data['username'] : $userData['username'],
            'email'           => isset($data['email']) ? $data['email'] : $userData['email'],
            'first_name'      => isset($data['first_name']) ? $data['first_name'] : $userData['first_name'],
            'last_name'       => isset($data['last_name']) ? $data['last_name'] : $userData['last_name'],
            'role'            => isset($data['role']) ? $data['role'] : $userData['role'],
            'status'          => isset($data['status']) ? $data['status'] : $userData['status'],
        ];

        // Only include password fields if provided
        if (!empty($data['password'])) {
            $payload['password'] = $data['password'];
            $payload['confirmPassword'] = isset($data['confirmPassword']) ? $data['confirmPassword'] : $data['password'];
        }

        $response = $this->api->put('/tenant/' . $tenantId . '/user/' . $userId, $payload);

        if ($response['status'] === 'success') {
            return $response['data'];
        }

        throw new Exception('Failed to update user: ' . json_encode($response));
    }
}
