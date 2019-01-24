# DoctrineMigrationToolsBundle

Tools for the doctrine migration bundle

# Installation

    composer require a5sys/doctrine-migration-tools-bundle

# Generate versions from schema file for the doctrine-migration
The diff command of doctrine generate the version file from the diff between your database and the current schema.

This command generate the version from the diff between the schema stored in a file and your current schema.

So you only have to run the command before doing a new version of your app.

    php bin/console doctrine:migrations:diff-file

A version file will be generated (if required) and your current schema will be dumped in a file. (in /app/DoctrineMigrations/SchemaVersion)

You can automatically check that the migrations have been generated using the check option

    php bin/console doctrine:migrations:diff-file --check

The exit code 1 will be used if a migration should have been generated
