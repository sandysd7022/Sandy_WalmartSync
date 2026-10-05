<?php
namespace Sandy\WalmartSync\Console\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use Sandy\WalmartSync\Model\SimpleItemFeedBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ItemFeedGenerateCommand extends Command
{
    private $builder;
    private $directoryList;

    public function __construct(SimpleItemFeedBuilder $builder, DirectoryList $directoryList, $name = null)
    {
        $this->builder = $builder;
        $this->directoryList = $directoryList;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('walmart:item:generate-simple')
            ->setDescription('Generate a guarded one-SKU Walmart Gummy Candy MP_ITEM JSON file from Magento')
            ->addOption('sku', null, InputOption::VALUE_REQUIRED, 'Magento simple-product SKU')
            ->addOption('ingredients', null, InputOption::VALUE_OPTIONAL, 'Actual package ingredient text override')
            ->addOption('ingredient-image-url', null, InputOption::VALUE_OPTIONAL, 'Optional public ingredient-label image URL override')
            ->addOption('country', null, InputOption::VALUE_OPTIONAL, 'Country of origin override')
            ->addOption('brand', null, InputOption::VALUE_OPTIONAL, 'Brand override')
            ->addOption('manufacturer', null, InputOption::VALUE_OPTIONAL, 'Manufacturer override')
            ->addOption('description', null, InputOption::VALUE_OPTIONAL, 'Description override')
            ->addOption('main-image-url', null, InputOption::VALUE_OPTIONAL, 'Public main-image URL override')
            ->addOption('flavor', null, InputOption::VALUE_REQUIRED, 'Flavor, for example Assorted Fruit')
            ->addOption('feature-1', null, InputOption::VALUE_REQUIRED, 'First factual key feature')
            ->addOption('feature-2', null, InputOption::VALUE_REQUIRED, 'Second factual key feature')
            ->addOption('feature-3', null, InputOption::VALUE_REQUIRED, 'Third factual key feature')
            ->addOption('count-per-pack', null, InputOption::VALUE_OPTIONAL, 'Walmart count per pack', 1)
            ->addOption('multipack-quantity', null, InputOption::VALUE_OPTIONAL, 'Individually packaged units', 1)
            ->addOption('net-content-measure', null, InputOption::VALUE_REQUIRED, 'Total net-content number')
            ->addOption('net-content-unit', null, InputOption::VALUE_OPTIONAL, 'Walmart net-content unit', 'Ounce')
            ->addOption('size', null, InputOption::VALUE_OPTIONAL, 'Customer-facing size')
            ->addOption('color-category', null, InputOption::VALUE_OPTIONAL, 'Walmart color category', 'Multicolor')
            ->addOption('food-condition', null, InputOption::VALUE_OPTIONAL, 'Walmart food condition', 'Shelf-Stable')
            ->addOption('food-form', null, InputOption::VALUE_OPTIONAL, 'Walmart food form override; otherwise derived from product name')
            ->addOption('occasion', null, InputOption::VALUE_OPTIONAL, 'Occasion', 'Everyday')
            ->addOption('texture', null, InputOption::VALUE_OPTIONAL, 'Texture', 'Gummy')
            ->addOption('spec-version', null, InputOption::VALUE_OPTIONAL, 'Walmart MP_ITEM spec version', SimpleItemFeedBuilder::DEFAULT_SPEC_VERSION)
            ->addOption('output', null, InputOption::VALUE_OPTIONAL, 'Absolute output JSON path')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Replace an existing output file');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $sku = trim((string)$input->getOption('sku'));
            $path = trim((string)$input->getOption('output'));
            if ($path === '') {
                $safeSku = preg_replace('/[^A-Za-z0-9._-]+/', '_', $sku);
                $path = $this->directoryList->getPath(DirectoryList::VAR_DIR)
                    . '/export/walmart_sync/walmart-mp-item-' . $safeSku . '.json';
            }
            if (!$this->isAbsolutePath($path)) {
                throw new \RuntimeException('Use an absolute --output path.');
            }
            if (is_file($path) && !$input->getOption('force')) {
                throw new \RuntimeException('Output file already exists. Use --force only after reviewing it.');
            }

            $payload = $this->builder->build($sku, [
                'ingredient_image_url' => $input->getOption('ingredient-image-url'),
                'ingredients' => $input->getOption('ingredients'),
                'country' => $input->getOption('country'),
                'brand' => $input->getOption('brand'),
                'manufacturer' => $input->getOption('manufacturer'),
                'description' => $input->getOption('description'),
                'main_image_url' => $input->getOption('main-image-url'),
                'flavor' => $input->getOption('flavor'),
                'feature_1' => $input->getOption('feature-1'),
                'feature_2' => $input->getOption('feature-2'),
                'feature_3' => $input->getOption('feature-3'),
                'count_per_pack' => $input->getOption('count-per-pack'),
                'multipack_quantity' => $input->getOption('multipack-quantity'),
                'net_content_measure' => $input->getOption('net-content-measure'),
                'net_content_unit' => $input->getOption('net-content-unit'),
                'size' => $input->getOption('size'),
                'color_category' => $input->getOption('color-category'),
                'food_condition' => $input->getOption('food-condition'),
                'food_form' => $input->getOption('food-form'),
                'occasion' => $input->getOption('occasion'),
                'texture' => $input->getOption('texture'),
                'spec_version' => $input->getOption('spec-version')
            ]);
            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new \RuntimeException('Unable to encode the Walmart item feed.');
            }
            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new \RuntimeException('Unable to create the output directory.');
            }
            $temporary = $path . '.tmp';
            if (file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false || !rename($temporary, $path)) {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
                throw new \RuntimeException('Unable to write the Walmart item feed.');
            }

            $output->writeln('<info>Created one standalone Walmart simple-item feed.</info>');
            $output->writeln('SKU: ' . $sku);
            $output->writeln('File: ' . $path);
            $output->writeln('Ingredient image: ' . $payload['MPItem'][0]['Visible'][SimpleItemFeedBuilder::PRODUCT_TYPE_GUMMY_CANDY]['ingredientListImage']);
            $output->writeln('Magento custom options and variant fields were not included.');
            $output->writeln('No Walmart API was called and no inventory was changed.');
            $output->writeln('Next: preview the file with walmart:item:feed --feed-type=MP_ITEM.');
            return 0;
        } catch (\Exception $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return 1;
        }
    }

    private function isAbsolutePath($path)
    {
        return strpos($path, '/') === 0 || (bool)preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }
}
