<?php
namespace Sandy\WalmartSync\Console\Command;

use Sandy\WalmartSync\Model\Exemption\Importer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExemptionImportCommand extends Command
{
    private $importer;

    public function __construct(Importer $importer, $name = null)
    {
        $this->importer = $importer;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('walmart:exemption:import')
            ->setDescription('Preview or import per-SKU Walmart return-exemption statuses from CSV')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'CSV file containing SKU and optionally Status')
            ->addOption('default-status', null, InputOption::VALUE_OPTIONAL, 'Status used when the file has no Status column')
            ->addOption('execute', null, InputOption::VALUE_NONE, 'Actually update Magento local statuses')
            ->addOption('confirm', null, InputOption::VALUE_OPTIONAL, 'Required confirmation: IMPORT-EXEMPTIONS');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $execute = (bool)$input->getOption('execute');
        if ($execute && (string)$input->getOption('confirm') !== 'IMPORT-EXEMPTIONS') {
            $output->writeln('<error>Execution refused. Use --confirm="IMPORT-EXEMPTIONS" after reviewing the dry run.</error>');
            return 2;
        }
        try {
            $result = $this->importer->execute($input->getOption('file'), $input->getOption('default-status'), $execute);
        } catch (\Exception $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return 1;
        }
        foreach ($result['errors'] as $error) {
            $output->writeln('<error>' . $error . '</error>');
        }
        if ($result['errors']) {
            $output->writeln('<error>No statuses were updated because the file contains errors.</error>');
            return 1;
        }
        if ($execute) {
            $output->writeln(sprintf('<info>Updated exemption status for %d Walmart SKUs.</info>', $result['updated']));
        } else {
            $output->writeln(sprintf('<info>Dry run passed for %d Walmart SKUs. No status was changed.</info>', $result['processed']));
        }
        $output->writeln('<comment>No Walmart API data was changed.</comment>');
        return 0;
    }
}
