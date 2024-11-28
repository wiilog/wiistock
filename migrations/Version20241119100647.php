<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\CategorieStatut;
use App\Entity\Statut;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241119100647 extends AbstractMigration
{

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // get valid roles
        $roles = $this->connection->executeQuery("SELECT role.id AS id FROM role WHERE role.label NOT LIKE :roleLabel", [
            'roleLabel' => '%aucun accès%',
        ])->fetchAllAssociative();

        // Get the ID of the "Dispatch" category
        $categorieStatutDispatchId = $this->connection->executeQuery("SELECT categorie_statut.id as categorie_statut_id FROM categorie_statut WHERE categorie_statut.nom = :categorieStatutDispatch LIMIT 1", [
            'categorieStatutDispatch' => CategorieStatut::DISPATCH,
        ])->fetchOne();

        // Get statuts in "Brouillon" state and corresponding to the "Dispatch" category
        $statuts = $this->connection->executeQuery("SELECT statut.id AS id FROM statut WHERE statut.state = :state AND statut.categorie_id = :categorie", [
            'state' => Statut::DRAFT,
            'categorie' => $categorieStatutDispatchId ?? 0,
        ])->fetchAllAssociative();

        // Add all roles to each statut
        foreach ($statuts as $statut) {
            foreach ($roles as $role) {
                $this->addSql("INSERT INTO statut_role (statut_id, role_id) VALUES (:statut_id, :role_id)", [
                    'statut_id' => $statut["id"],
                    'role_id' => $role["id"],
                ]);
            }
        }
    }




    public function down(Schema $schema): void
    {

    }
}
