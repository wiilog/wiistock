<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211011123552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('RENAME TABLE litige TO dispute');
        $this->addSql('RENAME TABLE litige_pack TO dispute_pack');
        $this->addSql('RENAME TABLE litige_article TO dispute_article');
        $this->addSql('RENAME TABLE litige_utilisateur TO dispute_utilisateur');
        $this->addSql('ALTER TABLE attachment CHANGE litige_id dispute_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE dispute CHANGE declarant_id reporter_id INT DEFAULT NULL, CHANGE numero_litige number VARCHAR(64) NOT NULL');
        $this->addSql('ALTER TABLE dispute_pack CHANGE litige_id dispute_id INT NOT NULL');
        $this->addSql('ALTER TABLE dispute_article CHANGE litige_id dispute_id INT NOT NULL');
        $this->addSql('ALTER TABLE dispute_utilisateur CHANGE litige_id dispute_id INT NOT NULL');
        $this->addSql('ALTER TABLE dispute_history_record CHANGE dispute_id dispute_id INT NOT NULL');



        $usersWithDeclarant = $this->connection->executeQuery("
            SELECT id AS user_id,
                   columns_visible_for_litige
            FROM utilisateur
            WHERE columns_visible_for_litige LIKE '%\"declarant\"%'"
        );

        foreach ($usersWithDeclarant as $userWithDeclarant) {
            $user_id = $userWithDeclarant['user_id'];
            $columns_visible_for_litige = str_replace('"declarant"', '"reporter"', $userWithDeclarant['columns_visible_for_litige']);
            $this->addSql("UPDATE utilisateur SET columns_visible_for_litige = '$columns_visible_for_litige' WHERE id = $user_id");
        }


    }

    public function down(Schema $schema): void
    {
    }
}
