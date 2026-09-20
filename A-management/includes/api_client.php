<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

function apiRequest(string $route, string $method = 'GET', array $body = [], bool $multipart = false, array $query = []): array
{
    $url = API_BASE . '?route=' . rawurlencode($route);
    foreach ($query as $key => $value) {
        $url .= '&' . rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
    }
    if (!empty($_SESSION['api_token'])) {
        $url .= '&token=' . rawurlencode($_SESSION['api_token']);
    }
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];

    if (!empty($_SESSION['api_token'])) {
        $headers[] = 'Authorization: Bearer ' . $_SESSION['api_token'];
    }

    if ($method === 'POST' && !$multipart) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    } elseif ($method === 'POST' && $multipart) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['success' => false, 'message' => 'API connection failed: ' . $err, 'http' => $status];
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['success' => false, 'message' => 'Invalid API response.', 'http' => $status, 'raw' => $raw];
    }

    $json['http'] = $status;

    return $json;
}

function apiGet(string $route, array $query = []): array
{
    return apiRequest($route, 'GET', [], false, $query);
}

function apiPost(string $route, array $body = [], bool $multipart = false): array
{
    return apiRequest($route, 'POST', $body, $multipart);
}

function apiMultipart(string $route, array $fields = [], array $files = []): array
{
    $post = [];
    foreach ($fields as $k => $v) {
        $post[$k] = $v;
    }
    foreach ($files as $field => $fileInfo) {
        if (is_array($fileInfo) && !empty($fileInfo['tmp_name']) && is_uploaded_file($fileInfo['tmp_name'])) {
            $post[$field] = new CURLFile($fileInfo['tmp_name'], $fileInfo['type'] ?? 'application/octet-stream', $fileInfo['name'] ?? basename($fileInfo['tmp_name']));
        }
    }
    return apiRequest($route, 'POST', $post, true);
}
