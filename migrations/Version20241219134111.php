<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241219134111 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $exports = $this->connection
            ->executeQuery(
                "SELECT id, column_to_export
                FROM export
                WHERE column_to_export IS NOT NULL AND JSON_LENGTH(column_to_export)"
            )
            ->iterateAssociative();

        foreach ($exports as $export) {
            $id = $export['id'];
            $columnToExport = json_decode($export['column_to_export'], true);
            $packParentKey = array_search("packParent", $columnToExport);
            if ($packParentKey !== false) {
                $columnToExport[$packParentKey] = "packGroup";
                $this->addSql("UPDATE export SET column_to_export = :new_value WHERE id = :export_id", [
                    'export_id' => $id,
                    'new_value' => json_encode($columnToExport),
                ]);
            }

        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
