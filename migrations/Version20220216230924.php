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
            $this->addSql('ALTER TABLE parametrage_global RENAME TO setting;');
            $duplicateSettings = $this->connection
                ->executeQuery('
                SELECT setting.label, setting.id
                FROM setting
                WHERE setting.label IN (SELECT setting.label FROM setting GROUP BY setting.label HAVING COUNT(setting.label) > 1)
                ORDER BY setting.id DESC
            ')
                ->fetchAllAssociative();
            $updated = [];
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
