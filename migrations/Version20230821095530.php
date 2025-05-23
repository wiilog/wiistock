<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Type\CategoryType;
use App\Entity\Type\Type;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230821095530 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $categoryId = $this->connection
            ->executeQuery("SELECT id FROM category_type WHERE label = :label", [
                "label" => CategoryType::TRANSFER_REQUEST,
            ])->fetchOne();

        $this->addSql("UPDATE type SET notifications_enabled = 1 WHERE category_id = :categoryId AND label = :label", [
            "categoryId" => $categoryId,
            "label" => Type::LABEL_STANDARD,
        ]);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
