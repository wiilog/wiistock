<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\FieldsParam;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221019141245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable('fields_param')->hasColumn('modal_type')) {
            $this->addSql("ALTER TABLE fields_param ADD modal_type VARCHAR(255) DEFAULT NULL");
        }
        $this->addSql("UPDATE fields_param SET modal_type = '" . FieldsParam::MODAL_TYPE_FREE . "' WHERE elements IS NOT NULL");
        $this->addSql("UPDATE fields_param SET modal_type = '" . FieldsParam::MODAL_TYPE_USER . "' WHERE field_code = 'receivers'");
        $this->addSql("UPDATE fields_param SET elements = '[]' WHERE modal_type = '" . FieldsParam::MODAL_TYPE_USER . "' AND elements is NULL");
    }

    public function down(Schema $schema): void
    {
    }
}
