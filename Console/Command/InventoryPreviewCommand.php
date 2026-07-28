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
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Maximum records')
            ->addOption('date', null, InputOption::VALUE_OPTIONAL, 'Test date in YYYY-MM-DD format; preview only');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $rows = [];
        $date = $input->getOption('date');
        if ($date !== null) {
            $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', (string)$date);
            $dateErrors = \DateTimeImmutable::getLastErrors();
            if (
                !$parsedDate ||
                ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) ||
                $parsedDate->format('Y-m-d') !== (string)$date
            ) {
                $output->writeln('<error>Invalid --date. Use a real calendar date in YYYY-MM-DD format.</error>');
                return 2;
            }
        }
        foreach ($this->operator->preview($input->getOption('sku'), $input->getOption('limit'), $date) as $result) {
            $rows[] = [
                $result['walmart_sku'],
                $result['magento_sku'],
                $result['eligible'] ? 'YES' : 'NO',
                strtoupper($result['sync_action']),
                $result['is_meltable'] ? 'YES' : 'NO',
                $result['seasonal_status'],
                $result['quantity'],
                $result['reason']
            ];
        }
        $table = new Table($output);
        $table->setHeaders(['Walmart SKU', 'Magento SKU', 'Ready', 'Action', 'Meltable', 'Season', 'Calculated Qty', 'Reason'])->setRows($rows)->render();
        if ($date !== null) {
            $output->writeln(sprintf('<comment>Seasonal rules previewed as of %s. The server clock was not changed.</comment>', $date));
        }
        $output->writeln('<info>Dry run only. No Walmart inventory was changed.</info>');
        return 0;
    }
}
