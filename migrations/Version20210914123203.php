<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Type\CategoryType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210914123203 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $categoryTypeId = $this->connection
            ->executeQuery("SELECT id FROM category_type WHERE label = '" . CategoryType::ARTICLE . "'")
            ->fetchFirstColumn();
        $this->addSql("ALTER TABLE type ADD color VARCHAR(255)");
        $this->addSql("UPDATE type SET color = '#3353D7' WHERE category_id = {$categoryTypeId[0]}");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
