<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use WiiCommon\Helper\Stream;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250306123740 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        if ($schema->getTable("translation_category")->hasColumn("label")
            && $schema->getTable("translation_source")->hasColumn("category_id")
            && $schema->getTable("translation")->hasColumn("source_id")) {

            $conn = $this->connection;
            $menuId = $conn->fetchOne('SELECT id FROM translation_category WHERE label = :menu_label', [
                'menu_label' => 'Références',
            ]);

            $query = $conn->executeQuery('SELECT id FROM translation_source WHERE category_id = :category_id', [
                'category_id' => $menuId,
            ])
            ->fetchAllAssociative();

            $translationSourceIds = Stream::from($query)->flatten()->toArray();

            foreach ($translationSourceIds as $translationSourceId) {
                $this->addSql('DELETE FROM translation where source_id = :source_id', ['source_id' => $translationSourceId]);
                $this->addSql('DELETE FROM translation_source where id = :source_id', ['source_id' => $translationSourceId]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
