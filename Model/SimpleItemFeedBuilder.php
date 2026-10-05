<?php
namespace Sandy\WalmartSync\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

class SimpleItemFeedBuilder
{
    const DEFAULT_SPEC_VERSION = '5.0.20260703-18_22_27-api';
    const PRODUCT_TYPE_GUMMY_CANDY = 'Gummy Candy';
    const PRODUCT_TYPE_MARSHMALLOWS = 'Marshmallows';
    const PRODUCT_TYPE_LICORICE_CANDY = 'Licorice Candy';

    private $productRepository;
    private $storeManager;
    private $ingredientImageGenerator;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager,
        IngredientImageGenerator $ingredientImageGenerator
    ) {
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->ingredientImageGenerator = $ingredientImageGenerator;
    }

    public function build($sku, array $options)
    {
        $sku = trim((string)$sku);
        if ($sku === '') {
            throw new LocalizedException(__('A Magento SKU is required.'));
        }
        $product = $this->productRepository->get($sku, false, null, true);
        if ((string)$product->getTypeId() !== 'simple') {
            throw new LocalizedException(__('SKU %1 is not a Magento simple product.', $sku));
        }

        $productType = $this->option($options, 'product_type', self::PRODUCT_TYPE_GUMMY_CANDY);
        if (!in_array($productType, [
            self::PRODUCT_TYPE_GUMMY_CANDY,
            self::PRODUCT_TYPE_MARSHMALLOWS,
            self::PRODUCT_TYPE_LICORICE_CANDY
        ], true)) {
            throw new LocalizedException(__(
                'This guarded builder supports only Walmart product types Gummy Candy, Marshmallows, and Licorice Candy.'
            ));
        }

        $identifier = $this->firstProductValue($product, ['upc', 'barcode_value']);
        if (!preg_match('/^\d{12,14}$/', $identifier)) {
            throw new LocalizedException(__(
                'SKU %1 needs an exact 12, 13, or 14 digit UPC/EAN/GTIN. Scientific notation is not accepted.',
                $sku
            ));
        }
        $identifierType = strlen($identifier) === 12 ? 'UPC' : (strlen($identifier) === 13 ? 'EAN' : 'GTIN');

        $price = (float)$product->getFinalPrice();
        $shippingWeight = (float)$product->getWeight();
        if ($price <= 0 || $shippingWeight <= 0) {
            throw new LocalizedException(__('SKU %1 needs a positive price and shipping weight.', $sku));
        }

        $brand = $this->option($options, 'brand', $this->firstProductValue($product, ['jet_brand', 'brand']));
        $manufacturer = $this->option($options, 'manufacturer', $brand);
        $country = $this->normalizeCountry($this->option(
            $options,
            'country',
            $this->firstProductValue($product, ['country_of_manufactu', 'country_of_manufacture', 'countryf_manufacture'])
        ));
        $ingredients = $this->cleanFeedText($this->option(
            $options,
            'ingredients',
            $this->firstProductValue($product, ['ingredients'])
        ));
        if ($ingredients === '') {
            throw new LocalizedException(__(
                'SKU %1 is excluded from Walmart item creation because its Ingredients attribute is empty.',
                $sku
            ));
        }
        $this->assertTextLength($ingredients, 5000, 'ingredients', $sku);
        $ingredientImage = $this->option($options, 'ingredient_image_url');
        if ($ingredientImage === '') {
            $ingredientImage = $this->ingredientImageGenerator->generate($sku, $ingredients);
        } else {
            $ingredientImage = $this->requiredUrl($ingredientImage, 'ingredient image');
        }
        $mainImage = $this->option($options, 'main_image_url', $this->productImageUrl($product));
        $mainImage = $this->requiredUrl($mainImage, 'main image');

        $name = trim((string)$product->getName());
        $description = trim($this->option(
            $options,
            'description',
            $this->firstProductValue($product, ['description', 'short_description'])
        ));
        $features = [
            $this->option($options, 'feature_1'),
            $this->option($options, 'feature_2'),
            $this->option($options, 'feature_3')
        ];
        foreach (['name' => $name, 'description' => $description, 'brand' => $brand, 'manufacturer' => $manufacturer] as $label => $value) {
            if ($value === '') {
                throw new LocalizedException(__('SKU %1 needs a Walmart %2.', $sku, $label));
            }
        }
        foreach ($features as $index => $feature) {
            if (trim($feature) === '') {
                throw new LocalizedException(__('Provide --feature-%1 for SKU %2.', $index + 1, $sku));
            }
        }

        $countPerPack = $this->positiveInteger($this->option($options, 'count_per_pack', 1), 'count per pack');
        $multipackQuantity = $this->positiveInteger($this->option($options, 'multipack_quantity', 1), 'multipack quantity');
        $netContentMeasure = $this->positiveNumber($this->option($options, 'net_content_measure'), 'net content measure');
        $netContentUnit = $this->option($options, 'net_content_unit', 'Ounce');
        $flavor = $this->option($options, 'flavor');
        $foodForm = $this->option($options, 'food_form', $this->resolveFoodForm($name));
        $size = $this->option($options, 'size', $netContentMeasure . ' ' . $netContentUnit);
        if ($flavor === '') {
            throw new LocalizedException(__('Provide --flavor for SKU %1.', $sku));
        }
        if ($foodForm === '') {
            throw new LocalizedException(__(
                'Food Form could not be determined safely from the product name for SKU %1. Provide --food-form.',
                $sku
            ));
        }

        $visible = [
            'productName' => $name,
            'brand' => $brand,
            'condition' => 'New',
            'shortDescription' => $description,
            'keyFeatures' => array_values($features),
            'mainImageUrl' => $mainImage,
            'countPerPack' => $countPerPack,
            'multipackQuantity' => $multipackQuantity,
            'isProp65WarningRequired' => 'No',
            'colorCategory' => [$this->option($options, 'color_category', 'Multicolor')],
            'flavor' => $flavor,
            'food_condition' => [$this->option($options, 'food_condition', 'Shelf-Stable')],
            'foodForm' => [$foodForm],
            'ingredientListImage' => $ingredientImage,
            'ingredients' => $ingredients,
            'manufacturer' => $manufacturer,
            'netContent' => [
                'productNetContentUnit' => $netContentUnit,
                'productNetContentMeasure' => $netContentMeasure
            ],
            'occasion' => [$this->option($options, 'occasion', 'Everyday')],
            'size' => $size,
            'texture' => [$this->option($options, 'texture', $this->defaultTexture($productType, $name))]
        ];
        $orderable = [
            'sku' => $this->option($options, 'marketplace_sku', $sku),
            'productIdentifiers' => [
                'productId' => $identifier,
                'productIdType' => $identifierType
            ],
            'price' => round($price, 2),
            'ShippingWeight' => $shippingWeight,
            'country_of_origin_substantial_transformation' => $country
        ];
        $startDate = $this->option($options, 'start_date');
        if ($startDate !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $startDate)) {
                throw new LocalizedException(__('Walmart start date must be UTC in YYYY-MM-DDTHH:MM:SSZ format.'));
            }
            $orderable['startDate'] = $startDate;
        }
        $endDate = $this->option($options, 'end_date');
        if ($endDate !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $endDate)) {
                throw new LocalizedException(__('Walmart end date must be UTC in YYYY-MM-DDTHH:MM:SSZ format.'));
            }
            if ($startDate !== '' && strtotime($endDate) <= strtotime($startDate)) {
                throw new LocalizedException(__('Walmart end date must be later than the start date.'));
            }
            $orderable['endDate'] = $endDate;
        }

        return [
            'MPItemFeedHeader' => [
                'businessUnit' => 'WALMART_US',
                'locale' => 'en',
                'version' => $this->option($options, 'spec_version', self::DEFAULT_SPEC_VERSION)
            ],
            'MPItem' => [[
                'Orderable' => $orderable,
                'Visible' => [
                    $productType => $visible
                ]
            ]]
        ];
    }

    /**
     * Resolve a factual candy form from the product name without inventing a
     * shape. More-specific phrases must be checked before generic gummy text.
     */
    public function resolveFoodForm($productName)
    {
        $name = strtolower(trim(preg_replace('/\s+/u', ' ', (string)$productName)));
        if ($name === '') {
            return '';
        }
        $forms = [
            '/\bgum\s*drops?\b/' => 'Gumdrops',
            '/\bgumm(?:y|i)\s+bears?\b/' => 'Gummy Bears',
            '/\b(?:gumm(?:y|i)\s+)?(?:worms?|crawlers?)\b/' => 'Gummy Worms',
            '/\bgumm(?:y|i)\s+rings?\b/' => 'Gummy Rings',
            '/\bmarshmallows?\b/' => 'Marshmallows',
            '/\bfruit\s+slices?\b/' => 'Fruit Slices',
            '/\bjelly\s+beans?\b/' => 'Jelly Beans',
            '/\b(?:sour\s+)?belts?\b/' => 'Belts',
            '/\b(?:laces?|strings?)\b/' => 'Strings',
            '/\bsticks?\b/' => 'Sticks',
            '/\bchews?\b/' => 'Chews',
            '/\bbites?\b/' => 'Bites',
            '/\bdrops?\b/' => 'Drops',
            '/\b(?:licorice|liquorice|salmiak)\b/' => 'Licorice',
            '/\bgumm(?:y|i|ies)\b/' => 'Gummies'
        ];
        foreach ($forms as $pattern => $form) {
            if (preg_match($pattern, $name)) {
                return $form;
            }
        }
        return '';
    }

    private function defaultTexture($productType, $productName)
    {
        $name = strtolower((string)$productName);
        if ($productType === self::PRODUCT_TYPE_MARSHMALLOWS || preg_match('/\bmarshmallows?\b/', $name)) {
            return 'Marshmallow';
        }
        if ($productType === self::PRODUCT_TYPE_LICORICE_CANDY) {
            return 'Chewy';
        }
        return 'Gummy';
    }

    private function option(array $options, $key, $default = '')
    {
        return isset($options[$key]) && trim((string)$options[$key]) !== ''
            ? trim((string)$options[$key])
            : (string)$default;
    }

    private function firstProductValue($product, array $codes)
    {
        foreach ($codes as $code) {
            $value = $product->getData($code);
            $attribute = $product->getResource()->getAttribute($code);
            if ($attribute && $attribute->usesSource() && $value !== null && $value !== '') {
                $text = $attribute->getSource()->getOptionText($value);
                if (is_scalar($text) && trim((string)$text) !== '') {
                    return trim((string)$text);
                }
            }
            if (is_scalar($value) && trim((string)$value) !== '') {
                return trim((string)$value);
            }
        }
        return '';
    }

    private function normalizeCountry($country)
    {
        $country = trim((string)$country);
        if (in_array(strtoupper($country), ['US', 'USA', 'UNITED STATES OF AMERICA'], true)) {
            return 'United States';
        }
        if ($country === '') {
            throw new LocalizedException(__('A Walmart country of origin is required. Use --country if Magento is blank.'));
        }
        return $country;
    }

    private function productImageUrl($product)
    {
        $image = trim((string)$product->getData('image'));
        if ($image === '' || $image === 'no_selection') {
            return '';
        }
        $base = rtrim($this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA), '/');
        return $base . '/catalog/product/' . ltrim($image, '/');
    }

    private function requiredUrl($value, $label)
    {
        $value = trim((string)$value);
        if (!filter_var($value, FILTER_VALIDATE_URL) || !preg_match('/\.(?:jpe?g|png|bmp)(?:\?.*)?$/i', $value)) {
            throw new LocalizedException(__('A public JPG, JPEG, PNG, or BMP URL is required for the %1.', $label));
        }
        return $value;
    }

    private function cleanFeedText($value)
    {
        $value = preg_replace('/<\s*br\s*\/?>/i', "\n", (string)$value);
        $value = preg_replace('/<\/(?:p|div|li)>/i', "\n", $value);
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value);
        return trim($value);
    }

    private function positiveInteger($value, $label)
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 1) {
            throw new LocalizedException(__('A positive integer is required for %1.', $label));
        }
        return (int)$value;
    }

    private function assertTextLength($value, $maximum, $label, $sku)
    {
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        if ($length > $maximum) {
            throw new LocalizedException(__('SKU %1 %2 exceeds Walmart\'s %3-character limit.', $sku, $label, $maximum));
        }
    }

    private function positiveNumber($value, $label)
    {
        if (!is_numeric($value) || (float)$value <= 0) {
            throw new LocalizedException(__('A positive number is required for %1.', $label));
        }
        return (float)$value;
    }
}
