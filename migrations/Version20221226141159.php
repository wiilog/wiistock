<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\SpecificService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221226141159 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $label = SpecificService::ARRIVAGE_SPECIFIQUE_SED_MVT_DEPOSE;

        $this->addSql("UPDATE emplacement SET label = :label WHERE label = 'CHARIOT COLIS'", ['label' => $label]);
    }

    public function down(Schema $schema): void
    {

    }
}
