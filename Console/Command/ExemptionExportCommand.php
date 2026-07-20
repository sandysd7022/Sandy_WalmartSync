<?php
namespace Sandy\WalmartSync\Console\Command;

use Sandy\WalmartSync\Model\Exemption\Exporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExemptionExportCommand extends Command
{
    private $exporter;

    public function __construct(Exporter $exporter, $name = null)
    {
        $this->exporter = $exporter;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('walmart:exemption:export')
            ->setDescription('Export Walmart return-exemption request and internal review CSV files')
            ->addOption('file', null, InputOption::VALUE_OPTIONAL, 'Output CSV path; defaults to var/export/walmart-return-exemption-request.csv')
            ->addOption('scope', null, InputOption::VALUE_OPTIONAL, 'all or new', 'all')
            ->addOption('reason', null, InputOption::VALUE_OPTIONAL, 'Walmart exemption reason', 'Other')
            ->addOption('notes', null, InputOption::VALUE_OPTIONAL, 'Walmart request notes', 'perishable');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $result = $this->exporter->execute(
                $input->getOption('file'),
                $input->getOption('scope'),
                $input->getOption('reason'),
                $input->getOption('notes')
            );
        } catch (\Exception $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return 1;
        }
        $output->writeln('<info>Walmart request CSV: ' . $result['path'] . '</info>');
        $output->writeln('<info>Internal review CSV: ' . $result['review_path'] . '</info>');
        $output->writeln(sprintf(
            'Request rows: %d; master rows: %d; missing product URLs: %d; excluded previous rows: %d',
            $result['request_rows'],
            $result['review_rows'],
            $result['missing_urls'],
            $result['excluded_previous']
        ));
        if ($result['missing_urls']) {
            $output->writeln('<comment>Do not submit yet. Add and validate every missing Walmart Product URL shown in the review CSV.</comment>');
        }
        $output->writeln('<comment>No Walmart or Magento data was changed.</comment>');
        return 0;
    }
}
