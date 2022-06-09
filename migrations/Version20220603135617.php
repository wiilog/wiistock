<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\CategorieStatut;
use App\Entity\Transport\TransportOrder;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220603135617 extends AbstractMigration {

    public function up(Schema $schema): void {
        $statuses = [
            "Affecté" => TransportOrder::STATUS_ASSIGNED,
            "Terminé" => TransportOrder::STATUS_FINISHED,
            "Annulé" => TransportOrder::STATUS_CANCELLED,
            "Non livré" => TransportOrder::STATUS_NOT_DELIVERED,
            "Non collecté" => TransportOrder::STATUS_NOT_COLLECTED,
            "Sous-traité" => TransportOrder::STATUS_SUBCONTRACTED,
        ];

        foreach ([CategorieStatut::TRANSPORT_ORDER_DELIVERY, CategorieStatut::TRANSPORT_ORDER_COLLECT] as $category) {
            foreach ($statuses as $previous => $new) {
                $this->addSql("UPDATE statut INNER JOIN categorie_statut ON statut.categorie_id = categorie_statut.id SET statut.code = '$new', statut.nom = '$new' WHERE categorie_statut.nom = '$category' AND code = '$previous'");
            }
        }
    }

}
