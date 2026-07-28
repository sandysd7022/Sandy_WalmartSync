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

    public function isMeltableRestrictionEnabled()
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH . 'seasonal/enabled');
    }

    public function getMeltableCategoryIds()
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_PATH . 'seasonal/category_ids');
        $ids = array_filter(array_map('intval', explode(',', $value)));
        return array_values(array_unique($ids));
    }

    public function getMeltableZeroStart()
    {
        return $this->normalizeMonthDay(
            $this->scopeConfig->getValue(self::XML_PATH . 'seasonal/zero_start'),
            '05-01'
        );
    }

    public function getMeltableZeroEnd()
    {
        return $this->normalizeMonthDay(
            $this->scopeConfig->getValue(self::XML_PATH . 'seasonal/zero_end'),
            '11-30'
        );
    }

    public function getSeasonalTimezone()
    {
        $value = trim((string)$this->scopeConfig->getValue(self::XML_PATH . 'seasonal/timezone'));
        if ($value === '') {
            return 'America/New_York';
        }
        try {
            new \DateTimeZone($value);
            return $value;
        } catch (\Exception $exception) {
            return 'America/New_York';
        }
    }

    private function normalizeMonthDay($value, $default)
    {
        $value = trim((string)$value);
        if (!preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/', $value)) {
            return $default;
        }
        list($month, $day) = array_map('intval', explode('-', $value));
        return checkdate($month, $day, 2000) ? $value : $default;
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
