<?php
namespace Sandy\WalmartSync\Console\Command;

use Sandy\WalmartSync\Model\Inventory\Operator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InventoryZeroCommand extends Command
{
    private $operator;

    public function __construct(Operator $operator, $name = null)
    {
        $this->operator = $operator;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('walmart:inventory:zero')
            ->setDescription('Preview or execute zero inventory for one or all known Walmart SKUs')
            ->addOption('sku', null, InputOption::VALUE_OPTIONAL, 'One Walmart SKU; omit only for zero-all')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Maximum records (dry-run or controlled batch)')
            ->addOption('execute', null, InputOption::VALUE_NONE, 'Actually send inventory updates')
            ->addOption('confirm', null, InputOption::VALUE_OPTIONAL, 'Required confirmation: ZERO:<sku> or ZERO-ALL');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sku = $input->getOption('sku');
        $execute = (bool)$input->getOption('execute');
        if ($execute) {
            $expected = $sku ? 'ZERO:' . $sku : 'ZERO-ALL';
            if ((string)$input->getOption('confirm') !== $expected) {
                $output->writeln(sprintf('<error>Execution refused. Use --confirm="%s" after reviewing the dry run.</error>', $expected));
                return 2;
            }
            if (!$sku && $input->getOption('limit')) {
                $output->writeln('<error>Zero-all execution cannot use --limit. Use --sku for a controlled one-product test.</error>');
                return 2;
            }
        }
        $result = $this->operator->zero($sku, $input->getOption('limit'), $execute);
        foreach ($result['results'] as $row) {
            $output->writeln(sprintf('%s: %s -> 0 [%s]', $row['walmart_sku'], $row['previous_qty'] === null ? 'unknown' : $row['previous_qty'], $row['status']));
        }
        if (!$execute) {
            $output->writeln('<info>Dry run only. No Walmart inventory was changed.</info>');
        } elseif ($result['backup']) {
            $output->writeln('<info>Mandatory backup: ' . $result['backup']['path'] . '</info>');
        }
        return $result['errors'] ? 1 : 0;
    }
}
