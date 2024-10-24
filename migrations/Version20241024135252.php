<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fields\FixedFieldStandard;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241024135252 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
            $this->addSql("UPDATE fixed_field_by_type
                SET elements = '[]'
                WHERE entity_code = :entity_code
                AND field_code = :field_code",
            [
                'entity_code' => FixedFieldStandard::ENTITY_CODE_PRODUCTION,
                'field_code' => FixedFieldEnum::expectedAt->name,
            ]
            );

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
