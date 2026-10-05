<?php
namespace Sandy\WalmartSync\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Sandy\WalmartSync\Model\Api\Client;
use Sandy\WalmartSync\Model\ResourceModel\ItemCandidate\CollectionFactory as CandidateCollectionFactory;

class ItemCreationManager
{
    const WALMART_CATEGORY_FOOD = 'Food & Beverages';

    private $config;
    private $productCollectionFactory;
    private $productRepository;
    private $candidateFactory;
    private $candidateCollectionFactory;
    private $resourceConnection;
    private $builder;
    private $itemFeed;
    private $client;
    private $json;
    private $varDirectory;
    private $storeManager;

    public function __construct(
        Config $config,
        ProductCollectionFactory $productCollectionFactory,
        ProductRepositoryInterface $productRepository,
        ItemCandidateFactory $candidateFactory,
        CandidateCollectionFactory $candidateCollectionFactory,
        ResourceConnection $resourceConnection,
        SimpleItemFeedBuilder $builder,
        ItemFeed $itemFeed,
        Client $client,
        Json $json,
        Filesystem $filesystem,
        StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productRepository = $productRepository;
        $this->candidateFactory = $candidateFactory;
        $this->candidateCollectionFactory = $candidateCollectionFactory;
        $this->resourceConnection = $resourceConnection;
        $this->builder = $builder;
        $this->itemFeed = $itemFeed;
        $this->client = $client;
        $this->json = $json;
        $this->varDirectory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $this->storeManager = $storeManager;
    }

    public function refreshCandidates()
    {
        $categoryId = $this->config->getNewItemCategoryId();
        $connection = $this->resourceConnection->getConnection();
        $candidateTable = $this->resourceConnection->getTableName('sandy_walmartsync_item_candidate');
        $knownTable = $this->resourceConnection->getTableName('sandy_walmartsync_sku');
        $lastImport = $connection->fetchOne($connection->select()->from($knownTable, ['last_imported_at' => 'MAX(last_imported_at)']));
        $maximumAge = $this->config->getNewItemCatalogMaxAgeHours() * 3600;
        if (!$lastImport || strtotime((string)$lastImport) < time() - $maximumAge) {
            throw new LocalizedException(__(
                'The local Walmart catalog is missing or older than %1 hours. Run a complete Walmart catalog import before discovering new items.',
                $this->config->getNewItemCatalogMaxAgeHours()
            ));
        }
        $connection->update($candidateTable, ['in_scope' => 0]);

        $collection = $this->productCollectionFactory->create();
        $store = $this->storeManager->getDefaultStoreView();
        if ($store) {
            $collection->setStoreId((int)$store->getId());
        }
        $collection->addAttributeToSelect([
            'name', 'description', 'short_description', 'main_cat', 'walmart_sku', 'walmart_item_id',
            'jet_brand', 'package_qty', 'total_package_weight'
        ]);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->addAttributeToFilter('type_id', 'simple');
        $collection->addCategoriesFilter(['eq' => $categoryId]);

        $counts = ['discovered' => 0, 'ready' => 0, 'blocked' => 0, 'existing' => 0];
        foreach ($collection as $product) {
            $counts['discovered']++;
            $sku = trim((string)$product->getSku());
            $walmartSku = trim((string)$product->getData('walmart_sku')) ?: $sku;
            $mainCategory = $this->attributeText($product, 'main_cat');
            $brand = $this->attributeText($product, 'jet_brand');
            $packageQty = is_scalar($product->getData('package_qty')) ? trim((string)$product->getData('package_qty')) : '';
            $totalPackageWeight = $this->attributeText($product, 'total_package_weight');
            $productType = $this->productTypeForMainCategory($mainCategory);
            $classificationError = $productType === ''
                ? 'Unsupported main_cat. Supported values are Gummies, Marshmallow, and Licorice.'
                : $this->mainCategoryMismatchError($product, $mainCategory);
            $supported = $productType !== '' && $classificationError === '';
            $alreadyExists = trim((string)$product->getData('walmart_item_id')) !== '' || (bool)$connection->fetchOne(
                $connection->select()->from($knownTable, ['entity_id'])->where('walmart_sku = ?', $walmartSku)->limit(1)
            );
            if (!$alreadyExists) {
                $alreadyExists = (bool)$connection->fetchOne(
                    $connection->select()->from($knownTable, ['entity_id'])->where('magento_sku = ?', $sku)->limit(1)
                );
            }

            $candidate = $this->candidateFactory->create();
            $candidate->load((int)$product->getId(), 'product_id');
            $locked = in_array((string)$candidate->getData('creation_status'), ['submitted', 'inprogress', 'success'], true);
            $candidate->addData([
                'product_id' => (int)$product->getId(),
                'magento_sku' => $sku,
                'walmart_sku' => $walmartSku,
                'product_name' => (string)$product->getName(),
                'main_category' => $mainCategory,
                'walmart_product_type' => $productType ?: null,
                'walmart_category' => $productType !== '' ? self::WALMART_CATEGORY_FOOD : null,
                'brand' => $brand,
                'package_qty' => $packageQty,
                'total_package_weight' => $totalPackageWeight,
                'in_scope' => 1,
                'already_in_walmart' => $alreadyExists ? 1 : 0
            ]);
            if (!$locked) {
                $status = $alreadyExists ? 'already_exists' : ($supported ? 'candidate' : 'blocked');
                $error = $alreadyExists
                    ? 'SKU already exists in the latest imported Walmart catalog.'
                    : ($supported ? null : $classificationError);
                $candidate->addData([
                    'validation_status' => 'not_validated',
                    'validation_error' => $error,
                    'payload_hash' => null,
                    'payload_json' => null,
                    'creation_status' => $status,
                    'last_error' => $error
                ]);
            }
            $candidate->save();
            if ($alreadyExists) {
                $counts['existing']++;
            } elseif ($supported) {
                $counts['ready']++;
            } else {
                $counts['blocked']++;
            }
        }
        return $counts;
    }

    public function validate(array $ids)
    {
        $result = ['valid' => 0, 'blocked' => 0];
        foreach ($this->selected($ids) as $candidate) {
            try {
                $this->assertCandidateCanValidate($candidate);
                $product = $this->getProduct($candidate);
                $this->assertProductScope($product);
                $productType = $this->productTypeForMainCategory($this->attributeText($product, 'main_cat'));
                $brand = $this->attributeText($product, 'jet_brand');
                if ($brand === '') {
                    throw new LocalizedException(__('Brand (jet_brand) is required.'));
                }
                $packageQty = $this->positiveInteger($product->getData('package_qty'), 'Package qty (package_qty)');
                $totalPackageWeight = $this->attributeText($product, 'total_package_weight');
                list($measure, $unit) = $this->parseNetContent($totalPackageWeight);
                $feature1 = $this->featureText(
                    $product->getData('walmart_feature_1'),
                    $product->getData('short_description'),
                    $product->getName()
                );
                $feature2 = $this->featureText(
                    $product->getData('walmart_feature_2'),
                    $product->getData('description'),
                    $brand . ' ' . $product->getName()
                );
                $feature3 = $this->featureText(
                    $product->getData('walmart_feature_3'),
                    'Package quantity: ' . $packageQty . '. Total package weight: ' . $totalPackageWeight . '.'
                );
                $flavor = $this->deriveFlavor($product);
                if ($flavor === '') {
                    throw new LocalizedException(__(
                        'Flavor could not be determined safely from walmart_flavor, flavor, product name, or short description.'
                    ));
                }
                $foodForm = $this->builder->resolveFoodForm($product->getName());
                if ($foodForm === '') {
                    throw new LocalizedException(__(
                        'Food Form could not be determined safely from the product name.'
                    ));
                }
                $payload = $this->builder->build((string)$candidate->getData('magento_sku'), [
                    'marketplace_sku' => (string)$candidate->getData('walmart_sku'),
                    'product_type' => $productType,
                    'brand' => $brand,
                    'manufacturer' => $brand,
                    'start_date' => $this->config->getNewItemHoldStartDate(),
                    'end_date' => $this->config->getNewItemHoldEndDate(),
                    'flavor' => $flavor,
                    'food_form' => $foodForm,
                    'feature_1' => $feature1,
                    'feature_2' => $feature2,
                    'feature_3' => $feature3,
                    'count_per_pack' => 1,
                    'multipack_quantity' => $packageQty,
                    'net_content_measure' => $measure,
                    'net_content_unit' => $unit,
                    'size' => $packageQty . ' x ' . $this->formatNumber($measure / $packageQty) . ' ' . $unit
                        . ' (' . $this->formatNumber($measure) . ' ' . $unit . ' total)'
                ]);
                $json = $this->json->serialize($payload);
                $visible = $payload['MPItem'][0]['Visible'][$productType];
                $candidate->addData([
                    'validation_status' => 'valid',
                    'validation_error' => null,
                    'payload_hash' => hash('sha256', $json),
                    'payload_json' => $json,
                    'ingredient_image_url' => $visible['ingredientListImage'],
                    'flavor' => $flavor,
                    'food_form' => $foodForm,
                    'creation_status' => 'validated',
                    'last_error' => null
                ])->save();
                $result['valid']++;
            } catch (\Exception $exception) {
                $message = mb_substr($exception->getMessage(), 0, 60000);
                $candidate->addData([
                    'validation_status' => 'invalid',
                    'validation_error' => $message,
                    'payload_hash' => null,
                    'payload_json' => null,
                    'creation_status' => 'blocked',
                    'last_error' => $message
                ])->save();
                $result['blocked']++;
            }
        }
        return $result;
    }

    public function submitUnpublished(array $ids)
    {
        $items = [];
        $candidates = [];
        foreach ($this->selected($ids) as $candidate) {
            if ((string)$candidate->getData('validation_status') !== 'valid' || (string)$candidate->getData('creation_status') !== 'validated') {
                throw new LocalizedException(__('Every selected row must be validated and not previously submitted.'));
            }
            $this->assertProductScope($this->getProduct($candidate));
            $this->assertCatalogStillDoesNotContain($candidate);
            $payload = $this->decodePayload($candidate);
            $item = $payload['MPItem'][0];
            $expected = $this->config->getNewItemHoldStartDate();
            $expectedEnd = $this->config->getNewItemHoldEndDate();
            if (!isset($item['Orderable']['startDate']) || $item['Orderable']['startDate'] !== $expected) {
                throw new LocalizedException(__('SKU %1 does not contain the configured unpublished hold date.', $candidate->getData('walmart_sku')));
            }
            if (
                !isset($item['Orderable']['endDate']) ||
                $item['Orderable']['endDate'] !== $expectedEnd ||
                strtotime($expectedEnd) <= strtotime($expected)
            ) {
                throw new LocalizedException(__('SKU %1 does not contain a valid hold end date later than its start date.', $candidate->getData('walmart_sku')));
            }
            $items[] = $item;
            $candidates[] = $candidate;
        }
        $submission = $this->submitItems($items, 'create-held');
        foreach ($candidates as $candidate) {
            $candidate->addData([
                'creation_status' => 'submitted',
                'creation_feed_id' => $submission['feed_id'],
                'publish_status' => 'held_unpublished',
                'submitted_at' => gmdate('Y-m-d H:i:s'),
                'last_error' => null
            ])->save();
        }
        return ['count' => count($candidates), 'feed_id' => $submission['feed_id']];
    }

    public function publish(array $ids)
    {
        $items = [];
        $candidates = [];
        $now = gmdate('Y-m-d\TH:i:s\Z');
        foreach ($this->selected($ids) as $candidate) {
            if ((string)$candidate->getData('creation_status') !== 'success' || (string)$candidate->getData('publish_status') !== 'held_unpublished') {
                throw new LocalizedException(__('Every selected row must have a successful held-item creation and must not already be published/submitted.'));
            }
            $this->assertProductScope($this->getProduct($candidate));
            $payload = $this->decodePayload($candidate);
            $item = $payload['MPItem'][0];
            $item['Orderable']['startDate'] = $now;
            $items[] = $item;
            $candidates[] = $candidate;
        }
        $submission = $this->submitItems($items, 'publish');
        foreach ($candidates as $candidate) {
            $candidate->addData([
                'publish_status' => 'publish_submitted',
                'publish_feed_id' => $submission['feed_id'],
                'publish_requested_at' => gmdate('Y-m-d H:i:s'),
                'last_error' => null
            ])->save();
        }
        return ['count' => count($candidates), 'feed_id' => $submission['feed_id']];
    }

    public function refreshStatus(array $ids)
    {
        $groups = [];
        foreach ($this->selected($ids) as $candidate) {
            $feedId = (string)$candidate->getData('publish_feed_id');
            $kind = 'publish';
            if ($feedId === '') {
                $feedId = (string)$candidate->getData('creation_feed_id');
                $kind = 'creation';
            }
            if ($feedId !== '') {
                $groups[$kind . '|' . $feedId][] = $candidate;
            }
        }
        $updated = 0;
        foreach ($groups as $key => $candidates) {
            list($kind, $feedId) = explode('|', $key, 2);
            $response = $this->client->getFeedStatus($feedId);
            $details = isset($response['itemDetails']['itemIngestionStatus']) && is_array($response['itemDetails']['itemIngestionStatus'])
                ? $response['itemDetails']['itemIngestionStatus'] : [];
            $bySku = [];
            foreach ($details as $detail) {
                if (is_array($detail) && isset($detail['sku'])) {
                    $bySku[strtolower((string)$detail['sku'])] = $detail;
                }
            }
            foreach ($candidates as $candidate) {
                $detail = isset($bySku[strtolower((string)$candidate->getData('walmart_sku'))])
                    ? $bySku[strtolower((string)$candidate->getData('walmart_sku'))] : [];
                $status = strtoupper(isset($detail['ingestionStatus']) ? (string)$detail['ingestionStatus'] : (string)($response['feedStatus'] ?? 'INPROGRESS'));
                $error = $this->ingestionError($detail);
                if ($kind === 'creation') {
                    $candidate->setData('creation_status', $status === 'SUCCESS' ? 'success' : ($status === 'INPROGRESS' ? 'inprogress' : 'failed'));
                    if ($status === 'SUCCESS') {
                        $candidate->setData('item_id', $detail['itemid'] ?? null);
                        $candidate->setData('wpid', $detail['wpid'] ?? null);
                    }
                } else {
                    $candidate->setData('publish_status', $status === 'SUCCESS' ? 'publish_accepted' : ($status === 'INPROGRESS' ? 'publish_inprogress' : 'publish_failed'));
                }
                $candidate->setData('last_error', $error ?: null);
                $candidate->setData('processed_at', gmdate('Y-m-d H:i:s'));
                $candidate->save();
                $updated++;
            }
        }
        return $updated;
    }

    private function submitItems(array $items, $label)
    {
        if (!$items) {
            throw new LocalizedException(__('No eligible item payloads were selected.'));
        }
        if (count($items) > $this->config->getItemFeedMaxItems()) {
            throw new LocalizedException(__('Selected %1 items; configured feed maximum is %2.', count($items), $this->config->getItemFeedMaxItems()));
        }
        $payload = [
            'MPItemFeedHeader' => [
                'businessUnit' => 'WALMART_US',
                'locale' => 'en',
                'version' => SimpleItemFeedBuilder::DEFAULT_SPEC_VERSION
            ],
            'MPItem' => $items
        ];
        $relative = 'export/walmart_sync/grid-' . $label . '-' . gmdate('Ymd-His') . '-' . substr(hash('sha256', $this->json->serialize($items)), 0, 12) . '.json';
        $this->varDirectory->writeFile($relative, $this->json->serialize($payload));
        $absolute = $this->varDirectory->getAbsolutePath($relative);
        $preview = $this->itemFeed->preview($absolute, 'MP_ITEM');
        $submission = $this->itemFeed->submit($absolute, 'MP_ITEM', $preview['candidate_hash']);
        if (empty($submission['feed_id'])) {
            throw new LocalizedException(__('Walmart accepted the request but did not return a feed ID. No candidate state was advanced; check the operation log before retrying.'));
        }
        return $submission;
    }

    private function selected(array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            throw new LocalizedException(__('Select at least one candidate row.'));
        }
        $collection = $this->candidateCollectionFactory->create();
        $collection->addFieldToFilter('entity_id', ['in' => $ids]);
        if ($collection->getSize() !== count($ids)) {
            throw new LocalizedException(__('One or more selected candidate rows no longer exist.'));
        }
        return $collection;
    }

    private function assertCandidateCanValidate($candidate)
    {
        if (!(int)$candidate->getData('in_scope')) {
            throw new LocalizedException(__('Product is no longer an enabled simple product in the configured Collections category.'));
        }
        if ((int)$candidate->getData('already_in_walmart')) {
            throw new LocalizedException(__('SKU already exists in the latest imported Walmart catalog.'));
        }
        if (!in_array((string)$candidate->getData('walmart_product_type'), [
            SimpleItemFeedBuilder::PRODUCT_TYPE_GUMMY_CANDY,
            SimpleItemFeedBuilder::PRODUCT_TYPE_MARSHMALLOWS,
            SimpleItemFeedBuilder::PRODUCT_TYPE_LICORICE_CANDY
        ], true)) {
            throw new LocalizedException(__('Unsupported main_cat. Supported values are Gummies, Marshmallow, and Licorice.'));
        }
        if (in_array((string)$candidate->getData('creation_status'), ['submitted', 'inprogress', 'success'], true)) {
            throw new LocalizedException(__('This item has already been submitted to Walmart.'));
        }
    }

    private function getProduct($candidate)
    {
        $store = $this->storeManager->getDefaultStoreView();
        $storeId = $store ? (int)$store->getId() : null;
        return $this->productRepository->getById((int)$candidate->getData('product_id'), false, $storeId, true);
    }

    private function assertProductScope($product)
    {
        if ((string)$product->getTypeId() !== 'simple' || (int)$product->getStatus() !== Status::STATUS_ENABLED) {
            throw new LocalizedException(__('SKU %1 is no longer an enabled simple product.', $product->getSku()));
        }
        if (!in_array($this->config->getNewItemCategoryId(), array_map('intval', $product->getCategoryIds()), true)) {
            throw new LocalizedException(__('SKU %1 is no longer directly assigned to the configured Collections category.', $product->getSku()));
        }
        $mainCategory = $this->attributeText($product, 'main_cat');
        if ($this->productTypeForMainCategory($mainCategory) === '') {
            throw new LocalizedException(__('SKU %1 has an unsupported main_cat value.', $product->getSku()));
        }
        $classificationError = $this->mainCategoryMismatchError($product, $mainCategory);
        if ($classificationError !== '') {
            throw new LocalizedException(__($classificationError));
        }
    }

    private function productTypeForMainCategory($mainCategory)
    {
        switch ($this->normalize($mainCategory)) {
            case 'gummy':
            case 'gummies':
                return SimpleItemFeedBuilder::PRODUCT_TYPE_GUMMY_CANDY;
            case 'marshmallow':
            case 'marshmallows':
                return SimpleItemFeedBuilder::PRODUCT_TYPE_MARSHMALLOWS;
            case 'licorice':
            case 'liquorice':
                return SimpleItemFeedBuilder::PRODUCT_TYPE_LICORICE_CANDY;
            default:
                return '';
        }
    }

    private function mainCategoryMismatchError($product, $mainCategory)
    {
        $mainCategory = $this->normalize($mainCategory);
        $source = strtolower(html_entity_decode(strip_tags(
            (string)$product->getName() . ' ' .
            (string)$product->getData('short_description') . ' ' .
            (string)$product->getData('description')
        ), ENT_QUOTES, 'UTF-8'));
        $hasLicorice = (bool)preg_match('/\b(?:licorice|liquorice|salmiak)\b/', $source);
        $hasMarshmallow = (bool)preg_match('/\bmarshmallows?\b/', $source);
        $hasGummy = (bool)preg_match('/\b(?:gummy|gummi|gummies|gum\s*drops?)\b/', $source);

        // The imported Walmart report establishes that Salty Licorice/Salmiak
        // marshmallows such as SD0922 belong to Licorice Candy, not Marshmallows.
        if ($mainCategory !== 'licorice' && $mainCategory !== 'liquorice' && $hasLicorice) {
            return 'Strict main_cat mismatch: the product name/description contains Licorice or Salmiak, so main_cat must be Licorice.';
        }
        if (!in_array($mainCategory, ['marshmallow', 'marshmallows'], true) && $hasMarshmallow && !$hasLicorice && !$hasGummy) {
            return 'Strict main_cat mismatch: the product name/description indicates Marshmallow, so main_cat must be Marshmallow.';
        }
        if (in_array($mainCategory, ['gummy', 'gummies'], true) && !$hasGummy) {
            return 'Strict main_cat mismatch: main_cat Gummies requires Gummy, Gummi, Gummies, or Gumdrop wording in the product name/description.';
        }
        if (in_array($mainCategory, ['marshmallow', 'marshmallows'], true) && !$hasMarshmallow) {
            return 'Strict main_cat mismatch: main_cat Marshmallow requires Marshmallow wording in the product name/description.';
        }
        if (in_array($mainCategory, ['licorice', 'liquorice'], true) && !$hasLicorice) {
            return 'Strict main_cat mismatch: main_cat Licorice requires Licorice, Liquorice, or Salmiak wording in the product name/description.';
        }
        return '';
    }

    private function assertCatalogStillDoesNotContain($candidate)
    {
        $connection = $this->resourceConnection->getConnection();
        $knownTable = $this->resourceConnection->getTableName('sandy_walmartsync_sku');
        $lastImport = $connection->fetchOne($connection->select()->from($knownTable, ['last_imported_at' => 'MAX(last_imported_at)']));
        if (!$lastImport || strtotime((string)$lastImport) < time() - ($this->config->getNewItemCatalogMaxAgeHours() * 3600)) {
            throw new LocalizedException(__('The imported Walmart catalog became stale. Refresh it before submission.'));
        }
        $exists = (bool)$connection->fetchOne(
            $connection->select()->from($knownTable, ['entity_id'])
                ->where('walmart_sku = ?', (string)$candidate->getData('walmart_sku'))->limit(1)
        );
        if (!$exists) {
            $exists = (bool)$connection->fetchOne(
                $connection->select()->from($knownTable, ['entity_id'])
                    ->where('magento_sku = ?', (string)$candidate->getData('magento_sku'))->limit(1)
            );
        }
        if ($exists) {
            throw new LocalizedException(__('SKU %1 now exists in the imported Walmart catalog. Refresh candidates instead of creating it.', $candidate->getData('walmart_sku')));
        }
    }

    private function decodePayload($candidate)
    {
        try {
            $payload = $this->json->unserialize((string)$candidate->getData('payload_json'));
        } catch (\Exception $exception) {
            throw new LocalizedException(__('Stored payload for SKU %1 is invalid; validate it again.', $candidate->getData('walmart_sku')));
        }
        if (!isset($payload['MPItem'][0]) || hash('sha256', $this->json->serialize($payload)) !== (string)$candidate->getData('payload_hash')) {
            throw new LocalizedException(__('Stored reviewed payload for SKU %1 failed its integrity check.', $candidate->getData('walmart_sku')));
        }
        return $payload;
    }

    private function attributeText($product, $code)
    {
        $value = $product->getData($code);
        $attribute = $product->getResource()->getAttribute($code);
        if ($attribute && $attribute->usesSource() && $value !== null && $value !== '') {
            $text = $attribute->getSource()->getOptionText($value);
            if (is_scalar($text)) {
                return trim((string)$text);
            }
        }
        return is_scalar($value) ? trim((string)$value) : '';
    }

    private function normalize($value)
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string)$value)));
    }

    private function positiveInteger($value, $label)
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 1) {
            throw new LocalizedException(__('%1 must be a positive integer.', $label));
        }
        return (int)$value;
    }

    private function parseNetContent($value)
    {
        if (!preg_match('/^\s*(\d+(?:\.\d+)?)\s*(oz|ounce|ounces|lb|lbs|pound|pounds)\s*$/i', (string)$value, $matches)) {
            throw new LocalizedException(__('Per Package Weight (total_package_weight) must look like "28 Oz" or "1.5 Lbs".'));
        }
        $unit = strtolower($matches[2]);
        return [(float)$matches[1], in_array($unit, ['oz', 'ounce', 'ounces'], true) ? 'Ounce' : 'Pound'];
    }

    private function formatNumber($value)
    {
        return rtrim(rtrim(number_format((float)$value, 4, '.', ''), '0'), '.');
    }

    private function featureText(...$candidates)
    {
        foreach ($candidates as $candidate) {
            $text = preg_replace('/<\s*br\s*\/?>/i', ' ', (string)$candidate);
            $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
            $text = trim(preg_replace('/\s+/u', ' ', $text));
            if ($text !== '') {
                if ((function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text)) > 300) {
                    $text = function_exists('mb_substr') ? mb_substr($text, 0, 297, 'UTF-8') : substr($text, 0, 297);
                    $text = rtrim($text, " \t\n\r\0\x0B,.;:-") . '...';
                }
                return $text;
            }
        }
        return '';
    }

    private function deriveFlavor($product)
    {
        foreach (['walmart_flavor', 'flavor'] as $attributeCode) {
            $value = $this->attributeText($product, $attributeCode);
            if ($value !== '') {
                return $this->featureText($value);
            }
        }

        $source = strtolower($this->featureText(
            (string)$product->getName() . ' ' . (string)$product->getData('short_description')
        ));
        $flavors = [
            'assorted fruit' => 'Assorted Fruit',
            'passion fruit' => 'Passion Fruit',
            'green apple' => 'Green Apple',
            'blue raspberry' => 'Blue Raspberry',
            'salty licorice' => 'Salty Licorice',
            'salmiak' => 'Salty Licorice',
            'strawberry' => 'Strawberry',
            'raspberry' => 'Raspberry',
            'blueberry' => 'Blueberry',
            'blackberry' => 'Blackberry',
            'watermelon' => 'Watermelon',
            'pineapple' => 'Pineapple',
            'tangerine' => 'Tangerine',
            'chocolate' => 'Chocolate',
            'licorice' => 'Licorice',
            'caramel' => 'Caramel',
            'vanilla' => 'Vanilla',
            'coconut' => 'Coconut',
            'banana' => 'Banana',
            'orange' => 'Orange',
            'lemon' => 'Lemon',
            'lime' => 'Lime',
            'cherry' => 'Cherry',
            'peach' => 'Peach',
            'mango' => 'Mango',
            'grape' => 'Grape',
            'apple' => 'Apple',
            'berry' => 'Berry',
            'cola' => 'Cola',
            'mint' => 'Mint',
            'fruit' => 'Fruit'
        ];
        $matches = [];
        foreach ($flavors as $needle => $label) {
            if (preg_match('/(?<![a-z])' . preg_quote($needle, '/') . '(?![a-z])/', $source)) {
                $matches[$label] = $label;
            }
        }
        // Prefer a specific compound match over its component, for example
        // Blue Raspberry rather than both Blue Raspberry and Raspberry.
        foreach (['Assorted Fruit' => 'Fruit', 'Passion Fruit' => 'Fruit', 'Green Apple' => 'Apple', 'Blue Raspberry' => 'Raspberry'] as $specific => $generic) {
            if (isset($matches[$specific])) {
                unset($matches[$generic]);
            }
        }
        if (isset($matches['Salty Licorice'])) {
            unset($matches['Licorice']);
        }
        if (count($matches) > 1 && isset($matches['Fruit'])) {
            unset($matches['Fruit']);
        }
        if (preg_match('/\b(assorted|mix|mixed|variety|multi[ -]?flavor)\b/', $source)) {
            return 'Assorted';
        }
        if (count($matches) === 1) {
            return (string)reset($matches);
        }
        if (count($matches) > 1) {
            return 'Assorted';
        }
        return '';
    }

    private function ingestionError(array $detail)
    {
        if (empty($detail['ingestionErrors'])) {
            return '';
        }
        $encoded = $this->json->serialize($detail['ingestionErrors']);
        return mb_substr($encoded, 0, 60000);
    }
}
