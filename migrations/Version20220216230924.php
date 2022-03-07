<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220216230924 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        if (!$schema->hasTable('setting')) {
            $duplicateSettings = $this->connection
                ->executeQuery('
                SELECT parametrage_global.label, parametrage_global.id
                FROM parametrage_global
                WHERE parametrage_global.label IN (SELECT parametrage_global.label FROM parametrage_global GROUP BY parametrage_global.label HAVING COUNT(parametrage_global.label) > 1)
                ORDER BY parametrage_global.id DESC
            ')
                ->fetchAllAssociative();
            $updated = [];
            $this->addSql('ALTER TABLE parametrage_global RENAME TO setting;');
            foreach ($duplicateSettings as $setting) {
                $id = $setting['id'];
                $label = $setting['label'];
                if (!in_array($label, $updated)) {
                    $updated[] = $label;
                }
                else {
                    $this->addSql('DELETE FROM setting WHERE setting.id = :id', ['id' => $id]);
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
