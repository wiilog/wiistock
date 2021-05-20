<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210505100718 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        if($schema->getTable("pack")->hasColumn("pack_group_id")) {
            $this->addSql('ALTER TABLE pack DROP FOREIGN KEY FK_97DE5E23D0DB9D56;');
            $this->addSql('ALTER TABLE pack DROP INDEX IDX_97DE5E23D0DB9D56;');
            $this->addSql('ALTER TABLE pack DROP pack_group_id;');
        }

        if($schema->getTable("tracking_movement")->hasColumn("pack_group_id")) {
            $this->addSql('ALTER TABLE tracking_movement DROP FOREIGN KEY FK_DCA0EFE8D0DB9D56;');
            $this->addSql('ALTER TABLE tracking_movement DROP INDEX IDX_DCA0EFE8D0DB9D56;');
            $this->addSql('ALTER TABLE tracking_movement DROP pack_group_id;');
        }

    }

    public function down(Schema $schema) : void
    {
    }
}
