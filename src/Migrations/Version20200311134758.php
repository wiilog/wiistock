<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200311134758 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Renomme un parametrage de champ fixe et migration des anciens numeroBl => numeroCommandeList';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE arrivage ADD numero_commande_list JSON NOT NULL');
        $this->addSql("
            UPDATE `fields_param`
            SET `field_label` = 'n° commande / BL',
                `field_code` = 'numeroCommandeList'
            WHERE `fields_param`.`field_label` = 'n° commande/ BL'
              AND `fields_param`.`field_code` = 'numeroBL'
              AND `fields_param`.`entity_code` = 'arrivage';");


        $oldArrivageNumeroBL = $this->connection
            ->executeQuery('
                SELECT arrivage.id AS id,
                       arrivage.numero_bl AS numeroBL
                FROM arrivage
                WHERE arrivage.numero_bl IS NOT NULL
                  AND arrivage.numero_bl <> \'\'
            ',
            [])
            ->fetchAll();

        $this->addSql("UPDATE `arrivage` SET `numero_commande_list` = '[]'");

        foreach ($oldArrivageNumeroBL as $arrivage) {
            if (!empty($arrivage['numeroBL'])) {
                $numeroCommandeList = json_encode([$arrivage['numeroBL']]);
                $arrivageId = $arrivage['id'];
                $this->addSql("
                    UPDATE `arrivage`
                    SET `numero_commande_list` = '$numeroCommandeList'
                    WHERE `arrivage`.id = ${arrivageId}");
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
    }
}
