<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\SpecificService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200114101637 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function getDescription() : string
    {
        return 'Is used to delete the duplicates from the articleFournisseur table. It takes care of the foreign keys in the article table.
        it also deletes empty collect orders and copies the mail field into the username field for the utilisateur table.';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        // Update article first
        $this->addSql("
            UPDATE article a
            INNER JOIN article_fournisseur af ON af.id = a.article_fournisseur_id
            SET a.article_fournisseur_id =
            (
                     SELECT MIN(af_second.id)
                     FROM article_fournisseur AS af_second
                     WHERE
                af_second.label LIKE af.label AND
                af_second.reference LIKE af.reference AND
                af_second.fournisseur_id LIKE af.fournisseur_id AND
                af_second.reference_article_id LIKE af.reference_article_id
            )
        ");

        // Then delete unused duplicates
        $this->addSql("
            DELETE a
            FROM article_fournisseur AS a
            LEFT OUTER JOIN
            (
                 SELECT
                 MIN(af.id) as IdToKeep
                 FROM
                 (SELECT * FROM article_fournisseur) AS af
                 GROUP BY
                 af.reference_article_id,
                 af.fournisseur_id,
                 af.reference,
                 af.label
            ) AS tableToKeep ON tableToKeep.IdToKeep = a.id
            WHERE tableToKeep.IdToKeep IS NULL
        ");

        $this->addSql("
            DELETE oc
            FROM ordre_collecte oc
            WHERE
            (
                SELECT COUNT(*)
                FROM article_ordre_collecte aoc
                WHERE aoc.ordre_collecte_id = oc.id
            ) = 0
            AND
            (
                SELECT COUNT(*)
                FROM ordre_collecte_reference ocr
                WHERE ocr.ordre_collecte_id = oc.id
            ) = 0
        ");
        $specificService = $this->container->get('wiistock.specific_service');
        $isCea = $specificService->isCurrentClientNameFunction(SpecificService::CLIENT_CEA_LETI);
        if ($isCea) {
            $this->addSql(
                "UPDATE utilisateur u SET u.username = u.email"
            );
        }
    }

    public function down(Schema $schema) : void
    {

    }
}
