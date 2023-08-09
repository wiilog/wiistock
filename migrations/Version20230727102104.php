<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Reserve;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230727102104 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        if($schema->hasTable('reserve')) {
            if($schema->getTable('reserve')->hasColumn('kind')) {
                $this->addSql("UPDATE reserve SET kind = :line WHERE kind = :quality", [
                    'line' => Reserve::KIND_LINE,
                    'quality' => 'quality'
                ]);
            } else if($schema->getTable('reserve')->hasColumn('type')) {
                $this->addSql("UPDATE reserve SET type = :line WHERE type = :quality", [
                    'line' => Reserve::KIND_LINE,
                    'quality' => 'quality'
                ]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
