<?php
namespace Sandy\WalmartSync\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

class IngredientImageGenerator
{
    const RELATIVE_DIRECTORY = 'walmart/ingredient-labels';
    const MAX_INGREDIENT_LENGTH = 5000;

    private $directoryList;
    private $storeManager;

    public function __construct(DirectoryList $directoryList, StoreManagerInterface $storeManager)
    {
        $this->directoryList = $directoryList;
        $this->storeManager = $storeManager;
    }

    public function generate($sku, $ingredients)
    {
        $ingredients = $this->cleanText($ingredients);
        if ($ingredients === '') {
            throw new LocalizedException(__(
                'SKU %1 is excluded from Walmart item creation because its Ingredients attribute is empty.',
                $sku
            ));
        }
        $this->assertLength($ingredients, self::MAX_INGREDIENT_LENGTH, 'ingredients', $sku);
        if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor') || !function_exists('imagepng')) {
            throw new LocalizedException(__(
                'The PHP GD extension is required to create the Walmart ingredient-label image for SKU %1.',
                $sku
            ));
        }

        $safeSku = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim((string)$sku));
        $hash = substr(hash('sha256', $ingredients), 0, 16);
        $fileName = $safeSku . '-' . $hash . '.png';
        $relativePath = self::RELATIVE_DIRECTORY . '/' . $fileName;
        $directory = $this->directoryList->getPath(DirectoryList::MEDIA) . '/' . self::RELATIVE_DIRECTORY;
        $absolutePath = $directory . '/' . $fileName;
        if (!is_file($absolutePath)) {
            $this->createImage($directory, $absolutePath, $ingredients);
        }

        $mediaUrl = rtrim(
            $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA),
            '/'
        );
        return $mediaUrl . '/' . $relativePath;
    }

    private function createImage($directory, $absolutePath, $ingredients)
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new LocalizedException(__('Unable to create the Walmart ingredient-label image directory.'));
        }

        $ingredientLines = $this->wrapText($ingredients, 132);
        $width = 1400;
        $padding = 55;
        $lineHeight = 22;
        $height = $padding * 2 + 45 + count($ingredientLines) * $lineHeight;
        $height = max(360, $height);
        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            throw new LocalizedException(__('Unable to allocate the Walmart ingredient-label image.'));
        }
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 20, 20, 20);
        imagefilledrectangle($image, 0, 0, $width, $height, $white);
        imagerectangle($image, 12, 12, $width - 13, $height - 13, $black);

        $y = $padding;
        imagestring($image, 5, $padding, $y, 'INGREDIENTS', $black);
        $y += 42;
        foreach ($ingredientLines as $line) {
            imagestring($image, 5, $padding, $y, $line, $black);
            $y += $lineHeight;
        }
        $temporaryPath = $absolutePath . '.tmp-' . uniqid('', true);
        $written = imagepng($image, $temporaryPath, 6);
        imagedestroy($image);
        if (!$written) {
            throw new LocalizedException(__('Unable to write the Walmart ingredient-label image.'));
        }
        if (!rename($temporaryPath, $absolutePath)) {
            if (is_file($absolutePath)) {
                unlink($temporaryPath);
                return;
            }
            unlink($temporaryPath);
            throw new LocalizedException(__('Unable to publish the Walmart ingredient-label image.'));
        }
    }

    private function cleanText($value)
    {
        $value = (string)$value;
        $value = preg_replace('/<\s*br\s*\/?>/i', "\n", $value);
        $value = preg_replace('/<\/(?:p|div|li)>/i', "\n", $value);
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8');
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[\t ]+/u', ' ', $value);
        $value = preg_replace('/\n{3,}/', "\n\n", $value);
        return trim($value);
    }

    private function wrapText($value, $width)
    {
        if (function_exists('iconv')) {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($ascii !== false) {
                $value = $ascii;
            }
        }
        $lines = [];
        foreach (explode("\n", $value) as $paragraph) {
            $wrapped = wordwrap(trim($paragraph), $width, "\n", true);
            foreach (explode("\n", $wrapped) as $line) {
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }
        return $lines ?: [''];
    }

    private function assertLength($value, $maximum, $label, $sku)
    {
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        if ($length > $maximum) {
            throw new LocalizedException(__(
                'SKU %1 %2 exceeds Walmart\'s %3-character limit.',
                $sku,
                $label,
                $maximum
            ));
        }
    }
}
