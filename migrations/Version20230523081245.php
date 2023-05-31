<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\CategorieStatut;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\Statut;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230523081245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $statutStates = [
            Statut::DRAFT => ShippingRequest::STATUS_DRAFT,
            Statut::NOT_TREATED => ShippingRequest::STATUS_TO_TREAT,
            Statut::SCHEDULED => ShippingRequest::STATUS_SCHEDULED,
            Statut::SHIPPED => ShippingRequest::STATUS_SHIPPED,
        ];

        $categorieStatusId = $this->connection
            ->executeQuery("SELECT id FROM categorie_statut WHERE nom = '" . CategorieStatut::SHIPPING_REQUEST . "'")
            ->fetchFirstColumn();

        if (isset($categorieStatusId[0])) {
            foreach ($statutStates as $state => $statutName) {
                $this->addSql("UPDATE statut SET state = :state WHERE nom = :name AND categorie_id = :categorie ", [
                    'state' => $state,
                    'name' => $statutName,
                    'categorie' => $categorieStatusId[0],
                ]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
