<?php
namespace Sandy\WalmartSync\Console\Command;

use Sandy\WalmartSync\Model\SkuStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SkuConfigureCommand extends Command
{
    private $storage;

    public function __construct(SkuStorage $storage, $name = null)
    {
        $this->storage = $storage;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('walmart:sku:configure')
            ->setDescription('Verify a Walmart SKU mapping and record its exemption status without changing Walmart')
            ->addOption('sku', null, InputOption::VALUE_REQUIRED, 'Walmart SKU')
            ->addOption('mapping-verified', null, InputOption::VALUE_OPTIONAL, 'yes or no')
            ->addOption('exemption', null, InputOption::VALUE_OPTIONAL, 'unknown, previously_requested, pending, approved, or rejected');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sku = trim((string)$input->getOption('sku'));
        $record = $this->storage->getByWalmartSku($sku);
        if (!$record) {
            $output->writeln('<error>Walmart SKU was not found. Import the catalog first.</error>');
            return 2;
        }
        $update = [];
        $verified = $input->getOption('mapping-verified');
        if ($verified !== null) {
            $normalized = strtolower(trim((string)$verified));
            if (!in_array($normalized, ['yes', 'no'], true)) {
                $output->writeln('<error>--mapping-verified must be yes or no.</error>');
                return 2;
            }
            if ($normalized === 'yes' && empty($record['product_id'])) {
                $output->writeln('<error>Mapping cannot be verified because no Magento product is matched.</error>');
                return 2;
            }
            $update['mapping_verified'] = $normalized === 'yes' ? 1 : 0;
        }
        $exemption = $input->getOption('exemption');
        if ($exemption !== null) {
            $exemption = strtolower(trim((string)$exemption));
            $allowed = ['unknown', 'previously_requested', 'pending', 'approved', 'rejected'];
            if (!in_array($exemption, $allowed, true)) {
                $output->writeln('<error>Invalid exemption status.</error>');
                return 2;
            }
            $update['sku_exemption_status'] = $exemption;
        }
        if (!$update) {
            $output->writeln('<error>Provide --mapping-verified and/or --exemption.</error>');
            return 2;
        }
        $this->storage->configure($sku, $update);
        $output->writeln(sprintf('<info>Updated local controls for %s. No Walmart data was changed.</info>', $sku));
        return 0;
    }
}
