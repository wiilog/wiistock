<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\ParametrageGlobal;
use App\Entity\Statut;
use App\Service\SpecificService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200922082719 extends AbstractMigration
{

    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $statusConforme = 'conforme';
        $statusReserve = 'reserve';
        $statusLitige = 'litige';

        $categoryStatusArrival = CategorieStatut::ARRIVAGE;
        $stateTreated = Statut::TREATED;
        $stateDispute = Statut::DISPUTE;

        $this->addSql("
            UPDATE statut
            INNER JOIN categorie_statut ON statut.categorie_id = categorie_statut.id
            SET statut.state = ${stateTreated}
            WHERE categorie_statut.nom = '${categoryStatusArrival}'
              AND statut.nom IN ('${statusConforme}', '${statusReserve}')
        ");

        $this->addSql("
            UPDATE statut
            INNER JOIN categorie_statut ON statut.categorie_id = categorie_statut.id
            SET statut.state = ${stateDispute}
            WHERE categorie_statut.nom = '${categoryStatusArrival}'
              AND statut.nom = '${statusLitige}'
        ");

        $defaultStatusLabel = 'DEFAULT_STATUT_ARRIVAGE';
        $params = $this->connection
            ->executeQuery("SELECT parametrage_global.value AS value FROM parametrage_global WHERE label = '${defaultStatusLabel}'")
            ->fetchAll();
        if (!empty($params)) {
            $defaultStatusValue = $params[0]['value'];
            $this->addSql("
                UPDATE statut
                SET statut.default_for_category = 1
                WHERE statut.id = '${defaultStatusValue}'
            ");
        }

        $arrivageTypeCategory = CategoryType::ARRIVAGE;
        $standardTypeArrival = "
            SELECT type.id AS id
            FROM type
            INNER JOIN category_type on type.category_id = category_type.id
            WHERE category_type.label = '${arrivageTypeCategory}' LIMIT 1
        ";

        $arrivageStatusCategory = CategorieStatut::ARRIVAGE;
        $categoryStatusArrival = "
            SELECT categorie_statut.id AS id
            FROM categorie_statut
            WHERE categorie_statut.nom = '${arrivageStatusCategory}' LIMIT 1
        ";
        $this->addSql("
            UPDATE statut
            SET type_id = (${standardTypeArrival})
            WHERE type_id IS NULL
              AND statut.categorie_id = (${categoryStatusArrival})
        ");

        if ($_SERVER['APP_CLIENT'] === 'emerson') {
            $params = $this->connection
                ->executeQuery("
                    SELECT arrivage.id
                    FROM arrivage
                    INNER JOIN statut ON statut.id = arrivage.statut_id
                    WHERE statut.nom = '${statusLitige}'
                      AND statut.categorie_id = (${categoryStatusArrival})
                ")
                ->fetchAll();
            if (empty($params)) {
                $this->addSql("
                    DELETE FROM statut
                    WHERE statut.nom = '${statusLitige}'
                      AND statut.categorie_id = (${categoryStatusArrival})
                ");
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
