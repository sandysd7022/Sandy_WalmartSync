<?php
namespace Sandy\WalmartSync\Console\Command;

use Sandy\WalmartSync\Model\Api\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ItemFeedStatusCommand extends Command
{
    private $client;

    public function __construct(Client $client, $name = null)
    {
        $this->client = $client;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('walmart:item:feed:status')
            ->setDescription('Read Walmart processing status for an item feed')
            ->addOption('feed-id', null, InputOption::VALUE_REQUIRED, 'Walmart feed ID returned by submission');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $response = $this->client->getFeedStatus($input->getOption('feed-id'));
            $encoded = json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $output->writeln($encoded !== false ? $encoded : 'Unable to encode Walmart feed status.');
            return 0;
        } catch (\Exception $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return 1;
        }
    }
}
