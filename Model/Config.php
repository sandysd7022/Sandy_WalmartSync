<?php
namespace Sandy\WalmartSync\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class Config
{
    const XML_PATH = 'sandy_walmartsync/';

    private $scopeConfig;
    private $encryptor;

    public function __construct(ScopeConfigInterface $scopeConfig, EncryptorInterface $encryptor)
    {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    public function isEnabled()
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH . 'general/enabled');
    }

    public function isWriteEnabled()
    {
        return $this->isEnabled() && $this->scopeConfig->isSetFlag(self::XML_PATH . 'safety/write_enabled');
    }

    public function isCronEnabled()
    {
        return $this->isWriteEnabled() && $this->scopeConfig->isSetFlag(self::XML_PATH . 'safety/cron_enabled');
    }

    public function getBaseUrl()
    {
        if ($this->isSandbox()) {
            return 'https://sandbox.walmartapis.com';
        }
        return rtrim((string)$this->scopeConfig->getValue(self::XML_PATH . 'general/api_base_url'), '/');
    }

    public function isSandbox()
    {
        return $this->scopeConfig->getValue(self::XML_PATH . 'general/environment') === 'sandbox';
    }

    public function getClientId()
    {
        return $this->decryptValue($this->scopeConfig->getValue(self::XML_PATH . 'general/client_id'));
    }

    public function getClientSecret()
    {
        return $this->decryptValue($this->scopeConfig->getValue(self::XML_PATH . 'general/client_secret'));
    }

    public function getChannelType()
    {
        return trim((string)$this->scopeConfig->getValue(self::XML_PATH . 'general/channel_type'));
    }

    public function getShipNode()
    {
        return trim((string)$this->scopeConfig->getValue(self::XML_PATH . 'general/ship_node'));
    }

    public function getBatchSize()
    {
        return max(1, min(200, (int)$this->scopeConfig->getValue(self::XML_PATH . 'general/batch_size')));
    }

    public function getRetryCount()
    {
        return max(0, min(5, (int)$this->scopeConfig->getValue(self::XML_PATH . 'general/retry_count')));
    }

    public function getInventoryBuffer()
    {
        return max(0, (float)$this->scopeConfig->getValue(self::XML_PATH . 'general/inventory_buffer'));
    }

    private function decryptValue($value)
    {
        $value = (string)$value;
        if ($value === '') {
            return '';
        }
        try {
            $decrypted = (string)$this->encryptor->decrypt($value);
            return $decrypted !== '' ? $decrypted : $value;
        } catch (\Exception $exception) {
            return $value;
        }
    }
}
