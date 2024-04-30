<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240415122537 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Edit receivers filter field in reception ';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE filtre_sup SET field ='receivers' WHERE field = 'utilisateurs' AND page='reception'");


    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
