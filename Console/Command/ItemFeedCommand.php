<?php
namespace Sandy\WalmartSync\Console\Command;

use Sandy\WalmartSync\Model\ItemFeed;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ItemFeedCommand extends Command
{
    private $itemFeed;

    public function __construct(ItemFeed $itemFeed, $name = null)
    {
        $this->itemFeed = $itemFeed;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('walmart:item:feed')
            ->setDescription('Validate or submit a Walmart new-item JSON feed')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Absolute path to Walmart-spec JSON feed')
            ->addOption('feed-type', null, InputOption::VALUE_OPTIONAL, 'MP_ITEM or MP_ITEM_MATCH', 'MP_ITEM')
            ->addOption('execute', null, InputOption::VALUE_NONE, 'Actually submit the feed to Walmart')
            ->addOption('confirm', null, InputOption::VALUE_OPTIONAL, 'Required confirmation: SUBMIT-WALMART-ITEMS')
            ->addOption('candidate-hash', null, InputOption::VALUE_OPTIONAL, 'Exact SHA-256 from the latest dry run');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $execute = (bool)$input->getOption('execute');
            if ($execute && (string)$input->getOption('confirm') !== 'SUBMIT-WALMART-ITEMS') {
                $output->writeln('<error>Execution refused. Use --confirm="SUBMIT-WALMART-ITEMS" after reviewing the dry run.</error>');
                return 2;
            }
            if ($execute && trim((string)$input->getOption('candidate-hash')) === '') {
                $output->writeln('<error>Execution refused. Provide --candidate-hash from the latest dry run.</error>');
                return 2;
            }

            $result = $execute
                ? $this->itemFeed->submit(
                    $input->getOption('file'),
                    $input->getOption('feed-type'),
                    $input->getOption('candidate-hash')
                )
                : $this->itemFeed->preview($input->getOption('file'), $input->getOption('feed-type'));

            $output->writeln(sprintf('Feed type: %s', $result['feed_type']));
            $output->writeln(sprintf('Items: %d', $result['item_count']));
            foreach (array_slice($result['skus'], 0, 25) as $sku) {
                $output->writeln('  ' . $sku);
            }
            if ($result['item_count'] > 25) {
                $output->writeln(sprintf('  ... %d more', $result['item_count'] - 25));
            }
            $output->writeln('Candidate hash: ' . $result['candidate_hash']);

            if (!$execute) {
                $output->writeln('<info>Dry run only. No item feed was sent to Walmart.</info>');
                return 0;
            }
            $output->writeln('<info>Walmart accepted the feed submission request.</info>');
            $output->writeln('Feed ID: ' . ($result['feed_id'] !== '' ? $result['feed_id'] : 'not returned'));
            $output->writeln('Check processing with walmart:item:feed:status before enabling inventory sync.');
            return 0;
        } catch (\Exception $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return 1;
        }
    }
}
