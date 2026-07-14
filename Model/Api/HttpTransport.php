<?php
namespace Sandy\WalmartSync\Model\Api;

class HttpTransport
{
    public function request($method, $url, array $headers, $body = null, $timeout = 60)
    {
        $handle = curl_init();
        if ($handle === false) {
            throw new \RuntimeException('Unable to initialize cURL.');
        }
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_CONNECTTIMEOUT => min(15, (int)$timeout),
            CURLOPT_TIMEOUT => (int)$timeout,
            CURLOPT_HEADER => false
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($handle, $options);
        $responseBody = curl_exec($handle);
        if ($responseBody === false) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new \RuntimeException('Walmart HTTP transport failed: ' . $error);
        }
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        return ['status' => $status, 'body' => (string)$responseBody];
    }
}
