<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201007185031 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {

        $trackingMovements = $this->connection
            ->executeQuery('
                SELECT mouvement_traca.*,
                       pack.code
                FROM mouvement_traca
                INNER JOIN pack ON mouvement_traca.pack_id = pack.id
                WHERE mouvement_traca.arrivage_id IS NOT NULL AND pack.arrivage_id IS NULL
            ')
            ->fetchAll();

        foreach ($trackingMovements as $tracking) {
            $code = $tracking['code'];
            $arrivageId = $tracking['arrivage_id'];
            $trackingId = $tracking['id'];

            $packId = $this->connection
                ->executeQuery("
                    SELECT pack.id
                    FROM pack
                    WHERE arrivage_id = {$arrivageId}
                      AND code LIKE '{$code}%'
                    LIMIT 1
                ")
                ->fetchColumn();

            $uniqueIdMobile = isset($code['unique_id_for_mobile']) ? "'{$code['unique_id_for_mobile']}'" : 'NULL';
            $comment = isset($code['commentaire']) ? str_replace("\\", "\\\\", $code['commentaire']) : null;
            $comment = isset($code['commentaire']) ? str_replace("'", "''", $comment) : null;
            $comment = isset($code['commentaire']) ? "'{$comment}'" : 'NULL';

            $this->addSql("INSERT INTO mouvement_traca
                (
                    emplacement_id,
                    type_id,
                    operateur_id,
                    unique_id_for_mobile,
                    datetime,
                    commentaire,
                    finished,
                    arrivage_id,
                    pack_id,
                    free_fields,
                    quantity)
            VALUES (
                {$tracking['emplacement_id']},
                {$tracking['type_id']},
                {$tracking['operateur_id']},
                {$uniqueIdMobile},
                '{$tracking['datetime']}',
                {$comment},
                {$tracking['finished']},
                {$tracking['arrivage_id']},
                {$packId},
                '{$tracking['free_fields']}',
                {$tracking['quantity']}
            )");

            $this->addSql("DELETE FROM mouvement_traca WHERE id = {$trackingId}");
        }

        $this->addSql('ALTER TABLE mouvement_traca RENAME TO tracking_movement');
        $this->addSql('ALTER TABLE pack ADD article_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE pack ADD reference_article_id INT DEFAULT NULL');

        $this->addSql('
            UPDATE pack
            SET article_id = (SELECT tracking_movement.article_id FROM tracking_movement WHERE tracking_movement.pack_id = pack.id LIMIT 1)
        ');
        $this->addSql('
            UPDATE pack
            SET reference_article_id = (SELECT tracking_movement.reference_article_id FROM tracking_movement WHERE tracking_movement.pack_id = pack.id LIMIT 1)
        ');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
