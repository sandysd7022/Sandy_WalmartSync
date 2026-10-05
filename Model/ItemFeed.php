<?php
namespace Sandy\WalmartSync\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Sandy\WalmartSync\Model\Api\Client;

class ItemFeed
{
    const MAX_FILE_BYTES = 10485760;

    private $client;
    private $config;
    private $json;
    private $logger;

    public function __construct(Client $client, Config $config, Json $json, OperationLogger $logger)
    {
        $this->client = $client;
        $this->config = $config;
        $this->json = $json;
        $this->logger = $logger;
    }

    public function preview($file, $feedType)
    {
        $feedType = $this->normalizeFeedType($feedType);
        $file = $this->resolveFile($file);
        $raw = file_get_contents($file);
        if ($raw === false) {
            throw new LocalizedException(__('Unable to read item feed file.'));
        }
        if (strlen($raw) > self::MAX_FILE_BYTES) {
            throw new LocalizedException(__('Item feed file exceeds the 10 MB local safety limit.'));
        }
        try {
            $payload = $this->json->unserialize($raw);
        } catch (\InvalidArgumentException $exception) {
            throw new LocalizedException(__('Item feed is not valid JSON.'));
        }
        if (!is_array($payload)) {
            throw new LocalizedException(__('Item feed JSON must contain an object.'));
        }

        $feed = isset($payload['payload']) && is_array($payload['payload'])
            ? $payload['payload']
            : $payload;
        $header = isset($feed['MPItemFeedHeader']) && is_array($feed['MPItemFeedHeader'])
            ? $feed['MPItemFeedHeader']
            : [];
        $items = isset($feed['MPItem']) && is_array($feed['MPItem']) ? $feed['MPItem'] : [];
        if (!$header || !$items || !$this->isList($items)) {
            throw new LocalizedException(__(
                'Expected MPItemFeedHeader and a non-empty MPItem array in the Walmart item feed.'
            ));
        }
        if (isset($header['feedType']) && strtoupper((string)$header['feedType']) !== $feedType) {
            throw new LocalizedException(__(
                'Feed header type %1 does not match requested type %2.',
                $header['feedType'],
                $feedType
            ));
        }
        if ($feedType === 'MP_ITEM') {
            foreach (['businessUnit', 'locale', 'version'] as $field) {
                if (!isset($header[$field]) || trim((string)$header[$field]) === '') {
                    throw new LocalizedException(__('MP_ITEM header is missing required field %1.', $field));
                }
            }
        }
        $count = count($items);
        if ($count > $this->config->getItemFeedMaxItems()) {
            throw new LocalizedException(__(
                'Feed has %1 items; the configured local maximum is %2.',
                $count,
                $this->config->getItemFeedMaxItems()
            ));
        }

        $skus = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw new LocalizedException(__('Feed item %1 is not an object.', $index + 1));
            }
            $sku = $this->findSku($item);
            if ($sku === '') {
                throw new LocalizedException(__('Feed item %1 does not contain a SKU.', $index + 1));
            }
            $key = strtolower($sku);
            if (isset($skus[$key])) {
                throw new LocalizedException(__('Duplicate SKU in item feed: %1', $sku));
            }
            $skus[$key] = $sku;
            if ($feedType === 'MP_ITEM') {
                $this->validateFullItem($item, $index + 1);
            }
        }

        return [
            'file' => $file,
            'feed_type' => $feedType,
            'item_count' => $count,
            'skus' => array_values($skus),
            'candidate_hash' => hash('sha256', $raw),
            'payload' => $payload
        ];
    }

    public function submit($file, $feedType, $candidateHash)
    {
        $preview = $this->preview($file, $feedType);
        if (!hash_equals($preview['candidate_hash'], strtolower(trim((string)$candidateHash)))) {
            throw new LocalizedException(__(
                'Candidate set changed or was not confirmed. Run the dry run again and use its exact hash.'
            ));
        }
        if (!$this->config->isItemFeedWriteEnabled()) {
            throw new LocalizedException(__(
                'Item feed submission is disabled in Stores > Configuration > Sandy > Walmart Sync.'
            ));
        }
        try {
            $response = $this->client->submitItemFeed($preview['feed_type'], $preview['payload']);
            $feedId = $this->findValue($response, ['feedId', 'feed_id']);
            $this->logger->log(
                'item_feed_submit',
                'success',
                null,
                null,
                null,
                $preview['item_count'],
                sprintf(
                    'Submitted %d Walmart item(s); type=%s; feedId=%s; hash=%s.',
                    $preview['item_count'],
                    $preview['feed_type'],
                    $feedId !== '' ? $feedId : 'not_returned',
                    $preview['candidate_hash']
                ),
                $this->client->getLastCorrelationId()
            );
            $preview['response'] = $response;
            $preview['feed_id'] = $feedId;
            unset($preview['payload']);
            return $preview;
        } catch (\Exception $exception) {
            $this->logger->log(
                'item_feed_submit',
                'error',
                null,
                null,
                null,
                $preview['item_count'],
                $exception->getMessage(),
                $this->client->getLastCorrelationId()
            );
            throw $exception;
        }
    }

    private function normalizeFeedType($feedType)
    {
        $feedType = strtoupper(trim((string)$feedType));
        if (!in_array($feedType, ['MP_ITEM', 'MP_ITEM_MATCH'], true)) {
            throw new LocalizedException(__('Use feed type MP_ITEM or MP_ITEM_MATCH.'));
        }
        return $feedType;
    }

    private function resolveFile($file)
    {
        $file = trim((string)$file);
        $real = $file !== '' ? realpath($file) : false;
        if ($real === false || !is_file($real) || !is_readable($real)) {
            throw new LocalizedException(__('A readable --file path is required.'));
        }
        return $real;
    }

    private function isList(array $items)
    {
        return array_keys($items) === range(0, count($items) - 1);
    }

    private function findSku(array $node, $depth = 0)
    {
        if ($depth > 6) {
            return '';
        }
        foreach ($node as $key => $value) {
            if (strtolower((string)$key) === 'sku' && is_scalar($value)) {
                return trim((string)$value);
            }
        }
        foreach ($node as $value) {
            if (is_array($value)) {
                $sku = $this->findSku($value, $depth + 1);
                if ($sku !== '') {
                    return $sku;
                }
            }
        }
        return '';
    }

    private function findValue(array $node, array $keys, $depth = 0)
    {
        if ($depth > 6) {
            return '';
        }
        foreach ($node as $key => $value) {
            if (in_array((string)$key, $keys, true) && is_scalar($value)) {
                return trim((string)$value);
            }
        }
        foreach ($node as $value) {
            if (is_array($value)) {
                $found = $this->findValue($value, $keys, $depth + 1);
                if ($found !== '') {
                    return $found;
                }
            }
        }
        return '';
    }

    private function validateFullItem(array $item, $position)
    {
        $orderable = isset($item['Orderable']) && is_array($item['Orderable']) ? $item['Orderable'] : [];
        $visible = isset($item['Visible']) && is_array($item['Visible']) ? $item['Visible'] : [];
        if (!$orderable || !$visible) {
            throw new LocalizedException(__('MP_ITEM %1 must contain Orderable and Visible objects.', $position));
        }
        foreach (['sku', 'productIdentifiers', 'price', 'ShippingWeight', 'country_of_origin_substantial_transformation'] as $field) {
            if (!array_key_exists($field, $orderable) || $orderable[$field] === '' || $orderable[$field] === null) {
                throw new LocalizedException(__('MP_ITEM %1 Orderable is missing required field %2.', $position, $field));
            }
        }
        $identifiers = is_array($orderable['productIdentifiers']) ? $orderable['productIdentifiers'] : [];
        $productId = isset($identifiers['productId']) ? trim((string)$identifiers['productId']) : '';
        $productIdType = isset($identifiers['productIdType']) ? strtoupper(trim((string)$identifiers['productIdType'])) : '';
        if (!preg_match('/^\d{12,14}$/', $productId) || !in_array($productIdType, ['UPC', 'EAN', 'GTIN'], true)) {
            throw new LocalizedException(__(
                'MP_ITEM %1 needs an exact 12, 13, or 14 digit UPC/EAN/GTIN; scientific notation is invalid.',
                $position
            ));
        }

        $productTypes = array_keys($visible);
        if (count($productTypes) !== 1 || !is_array($visible[$productTypes[0]])) {
            throw new LocalizedException(__('MP_ITEM %1 Visible must contain exactly one product-type object.', $position));
        }
        $productType = trim((string)$productTypes[0]);
        if (!in_array($productType, ['Gummy Candy', 'Marshmallows', 'Licorice Candy'], true)) {
            return;
        }
        $content = $visible[$productType];
        $required = [
            'productName', 'brand', 'condition', 'shortDescription', 'keyFeatures', 'mainImageUrl',
            'countPerPack', 'multipackQuantity', 'isProp65WarningRequired', 'colorCategory', 'flavor',
            'food_condition', 'foodForm', 'ingredientListImage', 'ingredients', 'manufacturer', 'netContent',
            'occasion', 'size', 'texture'
        ];
        foreach ($required as $field) {
            if (!array_key_exists($field, $content) || $content[$field] === '' || $content[$field] === null || $content[$field] === []) {
                throw new LocalizedException(__('%1 item %2 is missing required field %3.', $productType, $position, $field));
            }
        }
        if (!is_array($content['keyFeatures']) || count($content['keyFeatures']) < 3) {
            throw new LocalizedException(__('%1 item %2 requires at least three key features.', $productType, $position));
        }
        foreach (['mainImageUrl', 'ingredientListImage'] as $field) {
            $url = trim((string)$content[$field]);
            if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/\.(?:jpe?g|png|bmp)(?:\?.*)?$/i', $url)) {
                throw new LocalizedException(__('%1 item %2 has an invalid %3.', $productType, $position, $field));
            }
        }
        if (!is_string($content['ingredients']) || trim($content['ingredients']) === '' || $this->textLength($content['ingredients']) > 5000) {
            throw new LocalizedException(__(
                '%1 item %2 requires actual package ingredient text up to 5000 characters.',
                $productType,
                $position
            ));
        }
        $netContent = is_array($content['netContent']) ? $content['netContent'] : [];
        if (
            !isset($netContent['productNetContentUnit']) || trim((string)$netContent['productNetContentUnit']) === '' ||
            !isset($netContent['productNetContentMeasure']) || !is_numeric($netContent['productNetContentMeasure']) ||
            (float)$netContent['productNetContentMeasure'] <= 0
        ) {
            throw new LocalizedException(__('%1 item %2 has invalid net content.', $productType, $position));
        }
    }

    private function textLength($value)
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
