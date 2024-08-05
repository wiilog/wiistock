<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240802144224 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void {
        $this->addSql("UPDATE categorie_cl SET label = 'demande transport livraison' WHERE label = 'transport livraison'");
        $this->addSql("UPDATE categorie_cl SET label = 'demande transport collecte' WHERE label = 'transport collecte'");
    }

    public function down(Schema $schema): void {}
}
