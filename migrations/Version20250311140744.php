<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\SpecificService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250311140744 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clear table pack_sensor_message';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $appClient = $_SERVER['APP_CLIENT'] ?? '';
        if ($appClient === SpecificService::CLIENT_DIOT) {
            $this->addSql('DROP TABLE pack_sensor_message');
        }

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
