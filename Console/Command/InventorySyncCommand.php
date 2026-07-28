<?php
namespace Sandy\WalmartSync\Console\Command;

use Sandy\WalmartSync\Model\Inventory\Operator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InventorySyncCommand extends Command
{
    private $operator;

    public function __construct(Operator $operator, $name = null)
    {
        $this->operator = $operator;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('walmart:inventory:sync')
            ->setDescription('Preview or synchronize eligible Magento inventory to Walmart')
            ->addOption('sku', null, InputOption::VALUE_OPTIONAL, 'One Walmart SKU')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Maximum records')
            ->addOption('execute', null, InputOption::VALUE_NONE, 'Actually send inventory updates')
            ->addOption('confirm', null, InputOption::VALUE_OPTIONAL, 'Required confirmation: SYNC:<sku> or SYNC-ALL');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sku = $input->getOption('sku');
        $execute = (bool)$input->getOption('execute');
        if ($execute) {
            $expected = $sku ? 'SYNC:' . $sku : 'SYNC-ALL';
            if ((string)$input->getOption('confirm') !== $expected) {
                $output->writeln(sprintf('<error>Execution refused. Use --confirm="%s" after reviewing the preview.</error>', $expected));
                return 2;
            }
        }
        $result = $this->operator->sync($sku, $input->getOption('limit'), $execute);
        foreach ($result['results'] as $row) {
            $quantity = $execute
                ? ($row['status'] === 'skipped' ? '-' : (isset($row['sent_quantity']) ? $row['sent_quantity'] : 'ERROR'))
                : $row['quantity'];
            $status = $execute ? $row['status'] : 'dry_run';
            $output->writeln(sprintf('%s: qty=%s eligible=%s [%s] %s', $row['walmart_sku'], $quantity, $row['eligible'] ? 'yes' : 'no', $status, $row['reason']));
        }
        if (!$execute) {
            $output->writeln('<info>Dry run only. No Walmart inventory was changed.</info>');
        }
        return $result['errors'] ? 1 : 0;
    }
}
