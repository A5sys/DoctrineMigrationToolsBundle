<?php

namespace A5sys\DoctrineMigrationToolsBundle\Command;

use Doctrine\DBAL\Migrations\Configuration\Configuration;
use Doctrine\DBAL\Version as DbalVersion;
use Doctrine\DBAL\Migrations\Provider\OrmSchemaProvider;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Command to generate a version file for doctrine migration using a file containing the schema definition
 *
 */
class DiffFileCommand extends \Doctrine\DBAL\Migrations\Tools\Console\Command\DiffCommand
{
    protected function configure()
    {
        parent::configure();

        $this->addOption('check', null, InputOption::VALUE_NONE, 'Check that all migrations have been created.');
    }

    /**
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return type
     * @throws \InvalidArgumentException
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $isDbalOld = (DbalVersion::compare('2.2.0') > 0);
        $configuration = $this->getMigrationConfiguration($input, $output);

        $conn = $configuration->getConnection();
        $platform = $conn->getDatabasePlatform();

        if ($filterExpr = $input->getOption('filter-expression')) {
            if ($isDbalOld) {
                throw new \InvalidArgumentException('The "--filter-expression" option can only be used as of Doctrine DBAL 2.2');
            }

            $conn->getConfiguration()
                ->setFilterSchemaAssetsExpression($filterExpr);
        }

        $checkOnly = $input->getOption('check');

        $fromSchema = $this->getLastSchemaDefinition($configuration);
        $toSchema = $this->getSchemaProvider()->createSchema();

        //Not using value from options, because filters can be set from config.yml
        if (!$isDbalOld && $filterExpr = $conn->getConfiguration()->getFilterSchemaAssetsExpression()) {
            foreach ($toSchema->getTables() as $table) {
                $tableName = $table->getName();
                if (!preg_match($filterExpr, $this->resolveTableName($tableName))) {
                    $toSchema->dropTable($tableName);
                }
            }
        }

        $up = $this->buildCodeFromSql($configuration, $fromSchema->getMigrateToSql($toSchema, $platform));
        $down = $this->buildCodeFromSql($configuration, $fromSchema->getMigrateFromSql($toSchema, $platform));

        if (!$up && !$down) {
            $output->writeln('No changes detected in your mapping information.');

            return;
        }

        if ($checkOnly) {
            $output->writeln('<error>Changes detected in your mapping information!</error>');
            exit(1);
        }

        $version = date('YmdHis');
        $path = $this->generateMigration($configuration, $input, $version, $up, $down);

        $this->saveCurrentSchema($configuration, $toSchema, $version);

        $output->writeln(sprintf('Generated new migration class to "<info>%s</info>" from schema differences.', $path));
    }

    /**
     * Get the most recent schema
     *
     * @param type $configuration
     */
    protected function getLastSchemaDefinition($configuration)
    {
        $migrationDirectoryHelper = new \Doctrine\DBAL\Migrations\Tools\Console\Helper\MigrationDirectoryHelper($configuration);
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

            $lastSchema = unserialize($content);
        }

        return $lastSchema;
    }

    /**
     * Save the schema to a file
     * @param type $configuration
     * @param type $schema
     * @param type $version
     */
    protected function saveCurrentSchema($configuration, $schema, $version)
    {
        $migrationDirectoryHelper = new \Doctrine\DBAL\Migrations\Tools\Console\Helper\MigrationDirectoryHelper($configuration);
        $dir = $migrationDirectoryHelper->getMigrationDirectory().'/SchemaVersion';

        $filepath = $dir.'/Schema'.$version;

        file_put_contents($filepath, serialize($schema));
    }

    /**
     *
     * @return type
     */
    protected function getSchemaProvider()
    {
        if (!$this->schemaProvider) {
            $this->schemaProvider = new OrmSchemaProvider($this->getHelper('entityManager')->getEntityManager());
        }

        return $this->schemaProvider;
    }

    /**
     * Resolve a table name from its fully qualified name. The `$name` argument
     * comes from Doctrine\DBAL\Schema\Table#getName which can sometimes return
     * a namespaced name with the form `{namespace}.{tableName}`. This extracts
     * the table name from that.
     *
     * @param   string $name
     * @return  string
     */
    protected function resolveTableName($name)
    {
        $pos = strpos($name, '.');

        return false === $pos ? $name : substr($name, $pos + 1);
    }

    /**
     *
     * @param Configuration $configuration
     * @param array $sql
     * @param type $formatted
     * @param type $lineLength
     * @return type
     * @throws \InvalidArgumentException
     */
    private function buildCodeFromSql(Configuration $configuration, array $sql, $formatted = false, $lineLength = 120)
    {
        $currentPlatform = $configuration->getConnection()->getDatabasePlatform()->getName();
        $code = [];
        foreach ($sql as $query) {
            if (stripos($query, $configuration->getMigrationsTableName()) !== false) {
                continue;
            }

            if ($formatted) {
                if (!class_exists('\SqlFormatter')) {
                    throw new \InvalidArgumentException(
                        'The "--formatted" option can only be used if the sql formatter is installed.'.'Please run "composer require jdorn/sql-formatter".'
                    );
                }

                $maxLength = $lineLength - 18 - 8; // max - php code length - indentation

                if (strlen($query) > $maxLength) {
                    $query = \SqlFormatter::format($query, false);
                }
            }

            $code[] = sprintf("\$this->addSql(%s);", var_export($query, true));
        }

        if (!empty($code)) {
            array_unshift(
                $code,
                sprintf(
                    "\$this->abortIf(\$this->connection->getDatabasePlatform()->getName() != %s, %s);",
                    var_export($currentPlatform, true),
                    var_export(sprintf("Migration can only be executed safely on '%s'.", $currentPlatform), true)
                ),
                ""
            );
        }

        return implode("\n", $code);
    }
}
