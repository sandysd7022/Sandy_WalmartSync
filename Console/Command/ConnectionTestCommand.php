<?php
namespace Sandy\WalmartSync\Console\Command;

use Sandy\WalmartSync\Model\Api\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConnectionTestCommand extends Command
{
    private $client;

    public function __construct(Client $client, $name = null)
    {
        $this->client = $client;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('walmart:connection:test')
            ->setDescription('Test Walmart authentication and read-only Items access');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->client->getAllItems(null, 1);
        $output->writeln('<info>Walmart authentication and read-only Items access succeeded.</info>');
        $output->writeln('<comment>No Walmart data was changed.</comment>');
        return 0;
    }
}
