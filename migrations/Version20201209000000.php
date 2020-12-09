<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Helper\Stream;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201209000000 extends AbstractMigration {

    public function getDescription(): string {
        return "Cleans up removed migrations";
    }

    public function up(Schema $schema): void {
        $output = new ConsoleOutput();

        $migrations = Stream::from(scandir("../migrations/"))
            ->filter(function($file) {
                return str_starts_with($file, "Version");
            })
            ->count();

        if($migrations > 1) {
            $output->writeln("There are undeleted migrations, migrations cleaner can't be run");
        }

        $this->addSql("TRUNCATE TABLE migration_versions");
    }

    public function down(Schema $schema): void {
        // this down() migration is auto-generated, please modify it to your needs

    }

}
