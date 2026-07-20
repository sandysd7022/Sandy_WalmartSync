<?php
namespace Sandy\WalmartSync\Console\Command;

use Sandy\WalmartSync\Model\CatalogReconciler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CatalogReconcileCommand extends Command
{
    private $reconciler;

    public function __construct(CatalogReconciler $reconciler, $name = null)
    {
        $this->reconciler = $reconciler;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('walmart:catalog:reconcile')
            ->setDescription('Report unique local Walmart SKUs, mappings, publication states, and exemption states');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $result = $this->reconciler->execute();
        $output->writeln(sprintf('<info>Unique Walmart SKUs: %d</info>', $result['total']));
        $output->writeln(sprintf('Matched: %d; unmatched: %d; unverified custom-option mappings: %d', $result['matched'], $result['unmatched'], $result['mapping_unverified']));
        $this->writeGroup($output, 'Mapping types', $result['mapping_types']);
        $this->writeGroup($output, 'Exemption statuses', $result['exemption_statuses']);
        $this->writeGroup($output, 'Published statuses', $result['published_statuses']);
        $output->writeln('<comment>Read-only report. No Walmart or Magento data was changed.</comment>');
        return 0;
    }

    private function writeGroup(OutputInterface $output, $label, array $values)
    {
        $parts = [];
        foreach ($values as $key => $count) {
            $parts[] = $key . '=' . $count;
        }
        $output->writeln($label . ': ' . implode(', ', $parts));
    }
}
