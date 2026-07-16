<?php
namespace Sandy\WalmartSync\Model\Api;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Math\Random;
use Sandy\WalmartSync\Model\Config;

class Client
{
    const TOKEN_CACHE_KEY = 'sandy_walmartsync_access_token';

    private $config;
    private $json;
    private $cache;
    private $dateTime;
    private $random;
    private $encryptor;
    private $httpTransport;
    private $lastCorrelationId;

    public function __construct(
        Config $config,
        Json $json,
        CacheInterface $cache,
        DateTime $dateTime,
        Random $random,
        EncryptorInterface $encryptor,
        HttpTransport $httpTransport
    ) {
        $this->config = $config;
        $this->json = $json;
        $this->cache = $cache;
        $this->dateTime = $dateTime;
        $this->random = $random;
        $this->encryptor = $encryptor;
        $this->httpTransport = $httpTransport;
    }

    public function getAllItems($nextCursor = null, $limit = 50, $offset = null)
    {
        $query = ['limit' => max(1, min(200, (int)$limit))];
        if ($offset !== null) {
            $query['offset'] = max(0, (int)$offset);
        } elseif ($nextCursor !== null && $nextCursor !== '') {
            $query['nextCursor'] = $nextCursor;
        } else {
            $query['nextCursor'] = '*';
        }
        return $this->request('GET', '/v3/items', $query);
    }

    public function getInventory($sku, $shipNode = null)
    {
        $query = ['sku' => $sku];
        if ($shipNode) {
            $query['shipNode'] = $shipNode;
        }
        return $this->request('GET', '/v3/inventory', $query);
    }

    public function updateInventory($sku, $quantity, $shipNode = null)
    {
        if (!$this->config->isWriteEnabled()) {
            throw new LocalizedException(__('Walmart writes are disabled in Stores > Configuration > Sandy > Walmart Sync.'));
        }
        $query = ['sku' => $sku];
        if ($shipNode) {
            $query['shipNode'] = $shipNode;
        }
        $body = [
            'sku' => $sku,
            'quantity' => ['unit' => 'EACH', 'amount' => max(0, (int)floor($quantity))]
        ];
        return $this->request('PUT', '/v3/inventory', $query, $body);
    }

    public function getLastCorrelationId()
    {
        return $this->lastCorrelationId;
    }

    private function request($method, $path, array $query = [], array $body = null)
    {
        if (!$this->config->isEnabled()) {
            throw new LocalizedException(__('Walmart Sync is disabled.'));
        }
        $url = $this->config->getBaseUrl() . $path;
        if ($query) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        $correlationId = $this->random->getUniqueHash();
        $this->lastCorrelationId = $correlationId;
        $attempt = 0;
        $maxAttempts = $this->config->getRetryCount() + 1;
        do {
            $attempt++;
            $accessToken = $this->getAccessToken();
            $headers = [
                'WM_SEC.ACCESS_TOKEN' => $accessToken,
                'WM_QOS.CORRELATION_ID' => $correlationId,
                'WM_SVC.NAME' => 'Walmart Marketplace',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ];
            if ($this->config->getChannelType() !== '') {
                $headers['WM_CONSUMER.CHANNEL.TYPE'] = $this->config->getChannelType();
            }
            if ($this->config->isSandbox()) {
                $headers['WM_SANDBOX'] = 'v2';
            }
            if ($method !== 'GET' && $method !== 'PUT') {
                throw new LocalizedException(__('Unsupported Walmart HTTP method.'));
            }
            $payload = $body !== null ? $this->json->serialize($body) : null;
            $httpResponse = $this->httpTransport->request($method, $url, $headers, $payload, 60);
            $status = $httpResponse['status'];
            $responseBody = $httpResponse['body'];
            if ($status >= 200 && $status < 300) {
                $responseBody = trim($responseBody);
                return $responseBody === '' ? [] : $this->decode($responseBody);
            }
            if (($status === 429 || $status >= 500) && $attempt < $maxAttempts) {
                usleep((int)(250000 * pow(2, $attempt - 1)));
                continue;
            }
            throw new LocalizedException(__('Walmart API request failed with HTTP %1: %2', $status, $this->safeError($responseBody)));
        } while ($attempt < $maxAttempts);

        throw new LocalizedException(__('Walmart API request failed after retries.'));
    }

    private function getAccessToken()
    {
        $cacheKey = $this->getTokenCacheKey();
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            $tokenData = $this->decode($cached);
            if (!empty($tokenData['token']) && !empty($tokenData['expires_at']) && $tokenData['expires_at'] > $this->dateTime->gmtTimestamp() + 60) {
                try {
                    return $this->encryptor->decrypt($tokenData['token']);
                } catch (\Exception $exception) {
                    $this->cache->remove($cacheKey);
                }
            }
        }
        $clientId = $this->config->getClientId();
        $clientSecret = $this->config->getClientSecret();
        if ($clientId === '' || $clientSecret === '') {
            throw new LocalizedException(__('Walmart Client ID and Client Secret are required.'));
        }
        $correlationId = $this->random->getUniqueHash();
        $tokenHeaders = [
            'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
            'WM_QOS.CORRELATION_ID' => $correlationId,
            'WM_SVC.NAME' => 'Walmart Marketplace',
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
        if ($this->config->getChannelType() !== '') {
            $tokenHeaders['WM_CONSUMER.CHANNEL.TYPE'] = $this->config->getChannelType();
        }
        if ($this->config->isSandbox()) {
            $tokenHeaders['WM_SANDBOX'] = 'v2';
        }
        $tokenResponse = $this->httpTransport->request(
            'POST',
            $this->config->getBaseUrl() . '/v3/token',
            $tokenHeaders,
            'grant_type=client_credentials',
            30
        );
        $status = $tokenResponse['status'];
        if ($status < 200 || $status >= 300) {
            throw new LocalizedException(__('Walmart authentication failed with HTTP %1: %2', $status, $this->safeError($tokenResponse['body'])));
        }
        $data = $this->decode($tokenResponse['body']);
        $token = isset($data['access_token']) ? (string)$data['access_token'] : '';
        if ($token === '') {
            throw new LocalizedException(__('Walmart authentication response did not contain an access token.'));
        }
        $expiresIn = isset($data['expires_in']) ? (int)$data['expires_in'] : 900;
        $cacheData = ['token' => $this->encryptor->encrypt($token), 'expires_at' => $this->dateTime->gmtTimestamp() + $expiresIn];
        $this->cache->save($this->json->serialize($cacheData), $cacheKey, [], max(60, $expiresIn - 30));
        return $token;
    }

    private function getTokenCacheKey()
    {
        return self::TOKEN_CACHE_KEY . '_' . sha1($this->config->getClientId() . '|' . ($this->config->isSandbox() ? 'sandbox' : 'production'));
    }

    private function decode($value)
    {
        try {
            $decoded = $this->json->unserialize((string)$value);
            return is_array($decoded) ? $decoded : [];
        } catch (\InvalidArgumentException $exception) {
            throw new LocalizedException(__('Walmart returned invalid JSON.'));
        }
    }

    private function safeError($body)
    {
        $text = preg_replace('/(access[_ -]?token|client[_ -]?secret|authorization)[^,}\r\n]*/i', '$1=[redacted]', (string)$body);
        return mb_substr(trim(strip_tags($text)), 0, 1000);
    }
}
