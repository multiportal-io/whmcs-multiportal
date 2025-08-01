<?php

class ApiClient
{
    private $baseUrl;
    private $clientId;
    private $clientSecret;
    private $accessToken;
    private $sslVerify = true; // Default to secure SSL verification

    public function __construct($clientId, $clientSecret, $baseUrl = null, $sslVerify = true)
    {
        if (empty($clientId) || empty($clientSecret)) {
            throw new Exception('Client ID and Client Secret are required');
        }
        
        if (empty($baseUrl)) {
            throw new Exception('API Base URL is required. Please configure it in the server settings.');
        }
        
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->baseUrl = $baseUrl;
        $this->sslVerify = $sslVerify;
        
        // Only disable SSL verification in development environments
        if (!$this->sslVerify && php_sapi_name() !== 'cli') {
            error_log('WARNING: SSL verification is disabled. This should only be used in development environments.');
        }
        
        $this->authenticate();
    }

    private function authenticate()
    {
        $url = $this->baseUrl . '/access/get-token';

        $response = $this->postMultipart($url, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => '/'
        ]);

        if (isset($response['access_token'])) {
            $this->accessToken = $response['access_token'];
        } else {
            throw new Exception('Authentication failed: ' . json_encode($response));
        }
    }

    public function get($endpoint, $params = [])
    {
        $url = $this->baseUrl . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // SSL verification settings
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->sslVerify ? 2 : 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Log the request for debugging
        if (function_exists('logModuleCall')) {
            logModuleCall('multiportal', 'GET ' . $endpoint, ['url' => $url, 'params' => $params], $result, '', []);
        }

        if ($result === false) {
            throw new Exception('CURL error: ' . $error);
        }

        return json_decode($result, true);
    }

    public function post($endpoint, $data = [])
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        // SSL verification settings
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->sslVerify ? 2 : 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Log the request for debugging
        if (function_exists('logModuleCall')) {
            logModuleCall('multiportal', 'POST ' . $endpoint, ['url' => $url, 'data' => $data], $result, '', []);
        }

        if ($result === false) {
            throw new Exception('CURL error: ' . $error);
        }

        return json_decode($result, true);
    }


    public function put($endpoint, $data = [])
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        // SSL verification settings
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->sslVerify ? 2 : 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Log the request for debugging
        if (function_exists('logModuleCall')) {
            logModuleCall('multiportal', 'POST ' . $endpoint, ['url' => $url, 'data' => $data], $result, '', []);
        }

        if ($result === false) {
            throw new Exception('CURL error: ' . $error);
        }

        return json_decode($result, true);
    }

    public function delete($endpoint)
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        // SSL verification settings
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->sslVerify ? 2 : 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Log the request for debugging
        if (function_exists('logModuleCall')) {
            logModuleCall('multiportal', 'DELETE ' . $endpoint, ['url' => $url], $result, '', []);
        }

        if ($result === false) {
            throw new Exception('CURL error: ' . $error);
        }

        return json_decode($result, true);
    }

    private function postMultipart($url, $fields)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        // SSL verification settings
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->sslVerify ? 2 : 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Log the request for debugging
        if (function_exists('logModuleCall')) {
            logModuleCall('multiportal', 'POST AUTH', ['url' => $url, 'fields' => array_merge($fields, ['client_secret' => '***'])], $result, '', []);
        }

        if ($result === false) {
            throw new Exception('CURL error during authentication: ' . $error);
        }

        $decoded = json_decode($result, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from authentication: ' . $result);
        }

        return $decoded;
    }

    public function getDataCenters()
    {
        $response = $this->get('/data-center');

        if ($response['status'] === 'success') {
            return $response['data']['data'];
        }
        throw new Exception('Failed to fetch data centers: ' . json_encode($response));
    }

    public function getDataCenterOptions()
    {
        $providerVdcs = array();
        if ($dcs = $this->getDataCenters()) {
            foreach ($dcs as $i => $dc) {
                $providerVdcs[$dc['uuid']] = $dc['name'];
            }
        }
        return $providerVdcs;
    }
}
