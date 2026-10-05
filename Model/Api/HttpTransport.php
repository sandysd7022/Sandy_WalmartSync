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

    public function requestMultipartJson($method, $url, array $headers, $json, $filename = 'item-feed.json', $timeout = 60)
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'walmart_item_');
        if ($temporaryFile === false || file_put_contents($temporaryFile, (string)$json) === false) {
            throw new \RuntimeException('Unable to create the temporary Walmart item-feed file.');
        }

        try {
            $handle = curl_init();
            if ($handle === false) {
                throw new \RuntimeException('Unable to initialize cURL.');
            }
            $headerLines = [];
            foreach ($headers as $name => $value) {
                if (strtolower((string)$name) === 'content-type') {
                    continue;
                }
                $headerLines[] = $name . ': ' . $value;
            }
            $options = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_HTTPHEADER => $headerLines,
                CURLOPT_CONNECTTIMEOUT => min(15, (int)$timeout),
                CURLOPT_TIMEOUT => (int)$timeout,
                CURLOPT_HEADER => false,
                CURLOPT_POSTFIELDS => [
                    'file' => new \CURLFile($temporaryFile, 'application/json', basename((string)$filename))
                ]
            ];
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
        } finally {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }
    }
}
