<?php

namespace A5sys\DoctrineMigrationToolsBundle\Command;

use Doctrine\Bundle\MigrationsBundle\Command\DoctrineCommand;
use Doctrine\DBAL\Sharding\PoolingShardConnection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\Bundle\MigrationsBundle\Command\Helper;

/**
 * Command for generate migration classes by comparing your your mapping information to a file that contains the last definition
 *
 */
class MigrationsDiffFileDoctrineCommand extends DiffFileCommand
{
    protected function configure() : void
    {
        parent::configure();

        $this
            ->setName('doctrine:migrations:diff-file')
            ->addOption('db', null, InputOption::VALUE_REQUIRED, 'The database connection to use for this command.')
            ->addOption('em', null, InputOption::VALUE_OPTIONAL, 'The entity manager to use for this command.')
            ->addOption('shard', null, InputOption::VALUE_REQUIRED, 'The shard connection to use for this command.');
    }

    public function initialize(InputInterface $input, OutputInterface $output) : void
    {
        /** @var Application $application */
        $application = $this->getApplication();

        Helper\DoctrineCommandHelper::setApplicationHelper($application, $input);

        $configuration = $this->getMigrationConfiguration($input, $output);
        DoctrineCommand::configureMigrations($application->getKernel()->getContainer(), $configuration);

        parent::initialize($input, $output);
    }

    public function execute(InputInterface $input, OutputInterface $output) : ?int
    {
        // EM and DB options cannot be set at same time
        if ($input->getOption('em') !== null && $input->getOption('db') !== null) {
            throw new InvalidArgumentException('Cannot set both "em" and "db" for command execution.');
        }

        return parent::execute($input, $output);
    }
}
