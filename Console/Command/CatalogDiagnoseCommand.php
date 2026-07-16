<?php
namespace Sandy\WalmartSync\Console\Command;

use Sandy\WalmartSync\Model\Api\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CatalogDiagnoseCommand extends Command
{
    private $client;

    public function __construct(Client $client, $name = null)
    {
        $this->client = $client;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('walmart:catalog:diagnose')
            ->setDescription('Show only the Walmart catalog response structure; values and credentials are hidden');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $response = $this->client->getAllItems(null, 1);
        $output->writeln('<info>Walmart catalog response structure (values hidden):</info>');
        $this->writeShape($output, $response, 'root');
        $output->writeln('<comment>No credentials, product values, or Walmart data changes are included.</comment>');
        return 0;
    }

    private function writeShape(OutputInterface $output, $value, $path, $depth = 0)
    {
        if ($depth > 8) {
            $output->writeln($path . ': depth limit');
            return;
        }
        if (!is_array($value)) {
            $output->writeln($path . ': ' . gettype($value) . ' (value hidden)');
            return;
        }
        if (!$value) {
            $output->writeln($path . ': empty array');
            return;
        }
        if ($this->isList($value)) {
            $output->writeln(sprintf('%s: list (%d entries; first entry shape follows)', $path, count($value)));
            $this->writeShape($output, $value[0], $path . '[0]', $depth + 1);
            return;
        }
        $output->writeln(sprintf('%s: object (%d keys)', $path, count($value)));
        foreach ($value as $key => $child) {
            $this->writeShape($output, $child, $path . '.' . $key, $depth + 1);
        }
    }

    private function isList(array $value)
    {
        return $value && array_keys($value) === range(0, count($value) - 1);
    }
}
