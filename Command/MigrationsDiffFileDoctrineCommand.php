<?php

namespace A5sys\DoctrineMigrationToolsBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Command\Proxy\DoctrineCommandHelper;
use Doctrine\Bundle\MigrationsBundle\Command\DoctrineCommand;
use Doctrine\DBAL\Sharding\PoolingShardConnection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for generate migration classes by comparing your your mapping information to a file that contains the last definition
 *
 */
class MigrationsDiffFileDoctrineCommand extends DiffFileCommand
{
    /**
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @throws \LogicException
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineCommandHelper::setApplicationEntityManager($this->getApplication(), $input->getOption('em'));

        if ($input->getOption('shard')) {
            $connection = $this->getApplication()->getHelperSet()->get('db')->getConnection();
            if (!$connection instanceof PoolingShardConnection) {
                throw new \LogicException(sprintf("Connection of EntityManager '%s' must implements shards configuration.", $input->getOption('em')));
            }

            $connection->connect($input->getOption('shard'));
        }

        $configuration = $this->getMigrationConfiguration($input, $output);

        DoctrineCommand::configureMigrations($this->getApplication()->getKernel()->getContainer(), $configuration);

        parent::execute($input, $output);
    }

    /**
     *
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:migrations:diff-file')
            ->addOption('em', null, InputOption::VALUE_OPTIONAL, 'The entity manager to use for this command.')
            ->addOption('shard', null, InputOption::VALUE_REQUIRED, 'The shard connection to use for this command.')
        ;
    }
}
