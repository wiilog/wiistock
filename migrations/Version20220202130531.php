<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\FiltreRef;
use App\Entity\ReferenceArticle;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220202130531 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $activeOnlyFixedField = 'active_only';
        $statusFixedField = FiltreRef::FIXED_FIELD_STATUS;
        $activeReferenceStatus = ReferenceArticle::STATUT_ACTIF;

        $usersWithActiveReferencesFilter = $this->connection
            ->executeQuery("SELECT utilisateur_id AS id FROM filtre_ref WHERE champ_fixe = '$activeOnlyFixedField'  AND value = 'actif'")
            ->fetchAllAssociative();

        foreach ($usersWithActiveReferencesFilter as $user) {
            $userId = $user['id'];
            $this->addSql("INSERT INTO filtre_ref (utilisateur_id, champ_fixe, value) VALUES ($userId, '$statusFixedField', '$activeReferenceStatus')");
        }

        $this->addSql("DELETE FROM filtre_ref WHERE champ_fixe = '$activeOnlyFixedField'");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
