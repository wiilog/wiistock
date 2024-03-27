<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Language;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240322112907 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        ["id" => $id, "slug" => $slug] = $this->connection
            ->executeQuery("SELECT id, slug FROM language WHERE selected = 1 LIMIT 1")
            ->fetchAssociative();

        $this->addSql("UPDATE language SET selected = 0 WHERE id = :id", [
            "id" => $id,
        ]);

        $this->addSql("UPDATE language SET selected = 1 WHERE slug = :slug", [
            "slug" => $slug === Language::FRENCH_DEFAULT_SLUG
                ? Language::FRENCH_SLUG
                : Language::ENGLISH_SLUG,
        ]);

        $this->addSql("UPDATE language SET selectable = 0 WHERE slug IN (:frenchDefaultSlug, :englishDefaultSlug)", [
            "frenchDefaultSlug" => Language::FRENCH_DEFAULT_SLUG,
            "englishDefaultSlug" => Language::ENGLISH_DEFAULT_SLUG,
        ]);

        $this->addSql("UPDATE language SET selectable = 1 WHERE slug IN (:frenchSlug, :englishSlug)", [
            "frenchSlug" => Language::FRENCH_SLUG,
            "englishSlug" => Language::ENGLISH_SLUG,
        ]);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
