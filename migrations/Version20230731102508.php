<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230731102508 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $duplicateSettings = $this->connection
            ->executeQuery('
                SELECT setting.id, setting.label
                FROM setting
                WHERE setting.label IN (
                    SELECT s1.label
                    FROM setting s1
                    GROUP BY s1.label
                    HAVING COUNT(s1.label) > 1
                )
                ORDER BY setting.id
            ')
            ->iterateAssociative();

        $toTreatLabels = [];

        foreach ($duplicateSettings as $setting) {
            $id = $setting['id'];
            $label = $setting['label'];
            if (!in_array($label, $toTreatLabels)) {
                $toTreatLabels[] = $label;
            }
            else {
                $this->addSql('DELETE FROM setting WHERE setting.id = :id', [
                    "id" => $id
                ]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
