<?php
namespace Sandy\WalmartSync\Console\Command;

use Sandy\WalmartSync\Model\Inventory\Backup;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InventoryBackupCommand extends Command
{
    private $backup;

    public function __construct(Backup $backup, $name = null)
    {
        $this->backup = $backup;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('walmart:inventory:backup')
            ->setDescription('Export current Walmart inventory before any write operation')
            ->addOption('sku', null, InputOption::VALUE_OPTIONAL, 'One Walmart SKU')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Maximum records');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $result = $this->backup->execute($input->getOption('sku'), $input->getOption('limit'));
        $output->writeln(sprintf('<info>Backup: %s</info>', $result['path']));
        $output->writeln(sprintf('Captured: %d; errors: %d; total: %d', $result['captured'], $result['errors'], $result['total']));
        return $result['errors'] ? 1 : 0;
    }
}
