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
            ->setDescription('Preview or execute zero inventory for one SKU, all SKUs, or a guarded scope')
            ->addOption('sku', null, InputOption::VALUE_OPTIONAL, 'One Walmart SKU; omit only for zero-all')
            ->addOption('scope', null, InputOption::VALUE_OPTIONAL, 'Guarded scope: published-unmatched')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Maximum records (dry-run or controlled batch)')
            ->addOption('execute', null, InputOption::VALUE_NONE, 'Actually send inventory updates')
            ->addOption('candidate-hash', null, InputOption::VALUE_OPTIONAL, 'Exact hash printed by the reviewed published-unmatched dry run')
            ->addOption('confirm', null, InputOption::VALUE_OPTIONAL, 'Required confirmation: ZERO:<sku>, ZERO-PUBLISHED-UNMATCHED, or ZERO-ALL');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sku = $input->getOption('sku');
        $scope = $input->getOption('scope');
        $execute = (bool)$input->getOption('execute');
        if ($scope && $scope !== 'published-unmatched') {
            $output->writeln('<error>Unknown scope. Supported value: published-unmatched.</error>');
            return 2;
        }
        if ($scope && $sku) {
            $output->writeln('<error>--scope cannot be combined with --sku.</error>');
            return 2;
        }
        if ($execute) {
            $expected = $scope === 'published-unmatched'
                ? 'ZERO-PUBLISHED-UNMATCHED'
                : ($sku ? 'ZERO:' . $sku : 'ZERO-ALL');
            if ((string)$input->getOption('confirm') !== $expected) {
                $output->writeln(sprintf('<error>Execution refused. Use --confirm="%s" after reviewing the dry run.</error>', $expected));
                return 2;
            }
            if (!$sku && $input->getOption('limit')) {
                $output->writeln('<error>Bulk zero execution cannot use --limit. Use it only for a dry-run sample.</error>');
                return 2;
            }
            if ($scope === 'published-unmatched' && !$input->getOption('candidate-hash')) {
                $output->writeln('<error>Execution refused. Provide --candidate-hash from the complete reviewed dry run.</error>');
                return 2;
            }
        }
        $result = $this->operator->zero(
            $sku,
            $input->getOption('limit'),
            $execute,
            $scope,
            $input->getOption('candidate-hash')
        );
        foreach ($result['results'] as $row) {
            $output->writeln(sprintf('%s: %s -> 0 [%s]', $row['walmart_sku'], $row['previous_qty'] === null ? 'unknown' : $row['previous_qty'], $row['status']));
        }
        if (!$execute) {
            $output->writeln('<info>Dry run only. No Walmart inventory was changed.</info>');
            $output->writeln(sprintf('<info>Candidates: %d; scope: %s.</info>', $result['total'], $scope ?: ($sku ? 'single-sku' : 'all')));
            $output->writeln('<info>Candidate hash: ' . $result['candidate_hash'] . '</info>');
        } elseif ($result['backup']) {
            $output->writeln('<info>Mandatory backup: ' . $result['backup']['path'] . '</info>');
            $output->writeln('<info>Candidate hash: ' . $result['backup']['candidate_hash'] . '</info>');
        }
        return $result['errors'] ? 1 : 0;
    }
}
