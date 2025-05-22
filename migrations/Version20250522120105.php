<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Controller\FieldModesController;
use App\Entity\Utilisateur;
use App\Service\FieldModesService;
use App\Service\SpecificService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250522120105 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pack ADD last_movement_id INT DEFAULT NULL');
        $this->addSql("
            UPDATE pack
            SET last_movement_id = (
                SELECT last_movement.id
                FROM tracking_movement last_movement
                         INNER JOIN statut type ON type.id = last_movement.type_id
                WHERE type.code IN ('prise', 'depose')
                  AND last_movement.pack_id = pack.id
                ORDER BY last_movement.datetime DESC,
                         last_movement.order_index DESC,
                         last_movement.id DESC
                LIMIT 1
            )
            WHERE 1
        ");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
