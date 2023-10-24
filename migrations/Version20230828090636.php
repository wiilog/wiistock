<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\FixedFieldStandard;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230828090636 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        if(!$schema->getTable('article')->hasColumn('manufactured_at')
            && $schema->getTable('article')->hasColumn('manifacturing_date')) {
            $this->addSql("ALTER TABLE article RENAME COLUMN manifacturing_date TO manufactured_at");
        }

        $this->addSql("UPDATE fields_param SET field_code = :fieldCode WHERE field_code = 'manufactureDate'", [
            "fieldCode" => FixedFieldStandard::FIELD_CODE_ARTICLE_MANUFACTURED_AT
        ]);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
