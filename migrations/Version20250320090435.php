<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\SpecificService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250320090435 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $appClient = $_SERVER['APP_CLIENT'] ?? '';
        if ($appClient === SpecificService::CLIENT_PAELLA) {
            $packIdsToClear = $this->connection->executeQuery("SELECT id FROM pack WHERE code IN ('GAREKIT CAISSE2', 'GAREKIT CAISSE1')")->fetchAllAssociative();

            foreach ($packIdsToClear as $packId) {
                $this->addSql("UPDATE pack SET group_id = NULL WHERE group_id = '{$packId['id']}'");
                $this->addSql("DELETE FROM logistic_unit_history_record WHERE pack_id = '{$packId['id']}'");
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
