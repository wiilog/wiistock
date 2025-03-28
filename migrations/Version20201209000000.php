<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use WiiCommon\Helper\Stream;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

//TODO WIIS-12585: renommer le fichier en "MigrationCleaner"
//      la prochaine fois que cette migration est
//      exécutée pour qu'on puisse la différentier
//      des autres
final class Version20201209000000 extends AbstractMigration {


    public function __construct(Connection $connection, LoggerInterface $logger) {
        parent::__construct($connection, $logger);
    }

    public function getDescription(): string {
        return "Cleans up removed migrations";
    }

    public function up(Schema $schema): void {
        $output = new ConsoleOutput();

        $migrationDirectory = getcwd() . '/migrations';

        $currentMigrationName = (new \ReflectionClass($this))->getShortName();

        $migrations = Stream::from(scandir($migrationDirectory))
            ->filter(fn($file) => str_starts_with($file, "Version"))
            ->sort(fn($e1, $e2) => strnatcmp($e1, $e2));

        if ($migrations->count() > 1
            && $migrations->toArray()[0] !== "$currentMigrationName.php") {
            $output->writeln("There are undeleted migrations, migrations cleaner can't be run");
        }

        $this->addSql("TRUNCATE TABLE migration_versions");
    }

    public function down(Schema $schema): void {
        // this down() migration is auto-generated, please modify it to your needs

    }

}
