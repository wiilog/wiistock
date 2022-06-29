<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220624131451 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        if(!$schema->getTable("transport_round_line")->hasColumn("rejected_at")) {
            $this->addSql("ALTER TABLE transport_round_line ADD rejected_at DATETIME DEFAULT NULL, ADD failed_at DATETIME DEFAULT NULL;");
        }

        $this->addSql("UPDATE transport_round_line
                            INNER JOIN transport_order ON transport_round_line.order_id = transport_order.id AND (transport_round_line.fulfilled_at OR transport_order.treated_at)
                            SET transport_round_line.rejected_at = transport_order.rejected_at, transport_round_line.failed_at = transport_order.failed_at");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
