<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use http\Client\Curl\User;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200426153530 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Transforme les columns idden en columns isible pour les littiges';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE utilisateur ADD columns_visible_for_litige JSON DEFAULT NULL COMMENT \'(DC2Type:json_array)\'');

        $this->addSql("UPDATE `utilisateur` SET `columns_visible_for_litige` = '[]'");

        $columns = [
            "actions",
            "type",
            "arrivalNumber",
            "receptionNumber",
            "buyers",
            "numCommandeBl",
            "command",
            "provider",
            "references",
            "lastHistorique",
            "creationDate",
            "updateDate",
            "status"
        ];

        $oldData = $this->connection
            ->executeQuery('SELECT user_id, value FROM column_hidden')
            ->fetchAll();

        foreach ($oldData as $oldDatum) {
            $userId = $oldDatum['user_id'];
            $oldHiddenColumn = json_decode($oldDatum['value']);
            $newVisibleColumns = [];

            foreach ($columns as $index => $name) {
                if (!in_array($index, $oldHiddenColumn)) {
                    $newVisibleColumns[] = $name;
                }
            }

            $newVisibleColumnsStr = json_encode($newVisibleColumns);

            $this->addSql("
                UPDATE `utilisateur`
                SET columns_visible_for_litige = '$newVisibleColumnsStr'
                WHERE id = $userId"
            );
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

    }
}
