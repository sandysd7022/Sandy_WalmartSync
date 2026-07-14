<?php
namespace Sandy\WalmartSync\Console\Command;

use Sandy\WalmartSync\Model\Inventory\Operator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class InventoryPreviewCommand extends Command
{
    private $operator;

    public function __construct(Operator $operator, $name = null)
    {
        $this->operator = $operator;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('walmart:inventory:preview')
            ->setDescription('Preview Walmart inventory eligibility without sending changes')
            ->addOption('sku', null, InputOption::VALUE_OPTIONAL, 'One Walmart SKU')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Maximum records');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $rows = [];
        foreach ($this->operator->preview($input->getOption('sku'), $input->getOption('limit')) as $result) {
            $rows[] = [$result['walmart_sku'], $result['magento_sku'], $result['eligible'] ? 'YES' : 'NO', $result['quantity'], $result['reason']];
        }
        $table = new Table($output);
        $table->setHeaders(['Walmart SKU', 'Magento SKU', 'Eligible', 'Calculated Qty', 'Reason'])->setRows($rows)->render();
        $output->writeln('<info>Dry run only. No Walmart inventory was changed.</info>');
        return 0;
    }
}
