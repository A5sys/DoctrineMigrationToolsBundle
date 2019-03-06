<?php

namespace A5sys\DoctrineMigrationToolsBundle\Command;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\Migrations\Generator\DiffGenerator;
use Doctrine\Migrations\Generator\Exception\NoChangesDetected;
use Doctrine\Migrations\Provider\SchemaProviderInterface;
use Doctrine\Migrations\Tools\Console\Helper\MigrationDirectoryHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Doctrine\Migrations\Provider\OrmSchemaProvider;

/**
 * Command to generate a version file for doctrine migration using a file containing the schema definition
 *
 */
class DiffFileCommand extends \Doctrine\Migrations\Tools\Console\Command\DiffCommand
{
    private $schemaManager;

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('migrations:diff-file')
            ->setAliases(['diff-file'])
            ->setDescription('Generate a migration by comparing your current entities to your file mapping information.');
        $this->addOption('check', null, InputOption::VALUE_NONE, 'Check that all migrations have been created.');
    }

    public function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $checkOnly = $input->getOption('check');

        $configuration = $this->getMigrationConfiguration($input, $output);
        $lastSchema = $this->getLastSchemaDefinition($configuration);
        $this->generateSchemaManager($lastSchema);

        $toSchema = $this->createToSchema();

        $versionNumber = $this->configuration->generateVersionNumber();

        $noChanges = false;

        try {
            $return = parent::execute($input, $output);
        } catch (NoChangesDetected $ex) {
            if ($checkOnly === false) {
                throw $ex;
            }

            $noChanges = true;
        }

        if ($checkOnly) {
            if ($noChanges === false) {
                $output->writeln('<error>Changes detected in your mapping information!</error>');
                exit(1);
            } else {
                $output->writeln('<info>NO Changes detected in your mapping information!</info>');
                exit(0);
            }
        }

        $this->saveCurrentSchema($configuration, $toSchema, $versionNumber);

        return $return;
    }

    protected function createMigrationDiffGenerator() : DiffGenerator
    {
        return new DiffGenerator(
            $this->connection->getConfiguration(),
            $this->schemaManager,
            $this->getSchemaProvider(),
            $this->connection->getDatabasePlatform(),
            $this->dependencyFactory->getMigrationGenerator(),
            $this->dependencyFactory->getMigrationSqlGenerator()
        );
    }

    private function generateSchemaManager(Schema $lastSchema): void
    {
        $this->schemaManager = new class($lastSchema) extends AbstractSchemaManager {
            private $lastSchema = null;

            public function _getPortableTableColumnDefinition($tableColumn)
            {

            }
            public function __construct($lastSchema)
            {
                $this->lastSchema = $lastSchema;
            }

            public function createSchema(): Schema
            {
                return $this->lastSchema;
            }
        };
    }

    private function getSchemaProvider() : SchemaProviderInterface
    {
        if ($this->schemaProvider === null) {
            $this->schemaProvider = new OrmSchemaProvider(
                $this->getHelper('entityManager')->getEntityManager()
            );
        }

        return $this->schemaProvider;
    }

    /**
     * Get the most recent schema
     *
     * @param type $configuration
     */
    protected function getLastSchemaDefinition($configuration): Schema
    {
        $migrationDirectoryHelper = new MigrationDirectoryHelper($configuration);
        $dir = $migrationDirectoryHelper->getMigrationDirectory().'/SchemaVersion';

        //create the directory if required
        $fs = new Filesystem();
        $fs->mkdir($dir);

        //get the files containing the schema
        $finder = new Finder();
        $finder->in($dir);
        $finder->sortByName();

        $filesIterator = $finder->getIterator();
        $filesArray = iterator_to_array($filesIterator);

        if (count($filesArray) === 0) {
            $lastSchema = new \Doctrine\DBAL\Schema\Schema();
        } else {
            //get last entry
            $lastSchemaFile = end($filesArray);
            $content = $lastSchemaFile->getContents();

            /** @var Schema $lastSchema */
            $lastSchema = unserialize($content);

            $this->updateSchameTypes($lastSchema);
        }

        return $lastSchema;
    }

    /**
     * Save the schema to a file
     * @param type $configuration
     * @param type $schema
     * @param type $version
     */
    private function saveCurrentSchema($configuration, $schema, $version)
    {
        $migrationDirectoryHelper = new MigrationDirectoryHelper($configuration);
        $dir = $migrationDirectoryHelper->getMigrationDirectory().'/SchemaVersion';

        $filepath = $dir.'/Schema'.$version;

        file_put_contents($filepath, serialize($schema));
    }

    private function createToSchema() : Schema
    {
        return  $this->getSchemaProvider()->createSchema();
    }

    private function updateSchameTypes(Schema $schema): void
    {
        $typeCodeMap = array_flip(Type::getTypesMap());

        $tables = $schema->getTables();
        foreach ($tables as $table) {
            foreach ($table->getColumns() as $column) {
                $type = $column->getType();
                $typeClass = get_class($type);
                $typeCode = $typeCodeMap[$typeClass];
                $currentType = Type::getType($typeCode);
                $column->setType($currentType);
            }
        }
    }
}
