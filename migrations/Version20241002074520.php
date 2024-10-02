<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241002074520 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Delete unused setting';
    }

    public function up(Schema $schema): void{
        $this->addSql('DELETE FROM setting WHERE label = "ALLOW_CHANGE_NATURE_ON_MVT"');
    }

    public function down(Schema $schema): void{}
}
