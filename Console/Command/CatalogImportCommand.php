<?php
namespace Sandy\WalmartSync\Console\Command;

use Sandy\WalmartSync\Model\CatalogImporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CatalogImportCommand extends Command
{
    private $importer;

    public function __construct(CatalogImporter $importer, $name = null)
    {
        $this->importer = $importer;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('walmart:catalog:import')
            ->setDescription('Import the existing Walmart catalog without changing inventory')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Maximum number of SKUs to import');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $result = $this->importer->execute($input->getOption('limit'));
        $output->writeln(sprintf(
            '<info>Processed: %d; unique SKUs: %d; Walmart expected: %d; pages: %d; repeated records: %d; stale local rows removed: %d; errors: %d. No Walmart data was changed.</info>',
            $result['imported'],
            $result['unique'],
            $result['expected'],
            $result['pages'],
            $result['repeated'],
            $result['removed'],
            $result['errors']
        ));
        return $result['errors'] ? 1 : 0;
    }
}
