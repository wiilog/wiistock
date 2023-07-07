<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\DeliveryRequest\Demande;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230503075915 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'set default visible columns for all demandes';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable('demande')->hasColumn('visible_columns')) {
            $this->addSql('ALTER TABLE demande ADD visible_columns JSON DEFAULT NULL');
        }

        $this->addSql('UPDATE demande SET visible_columns = :defaultVisibleColumns WHERE visible_columns IS NULL', ['defaultVisibleColumns' => json_encode(Demande::DEFAULT_VISIBLE_COLUMNS)]);
    }

    public function down(Schema $schema): void
    {
    }
}
