<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240425074505 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $settingsToDrop = ['COLLECT_REQUEST_TYPE',
            'COLLECT_REQUEST_REQUESTER',
            'COLLECT_REQUEST_OBJECT',
            'COLLECT_REQUEST_POINT_COLLECT',
            'COLLECT_REQUEST_DESTINATION',
            'COLLECT_REQUEST_ARTICLE_QUANTITY_TO_COLLECT'
        ];
        foreach ($settingsToDrop as $setting) {
            $this->addSql('DELETE FROM setting WHERE label = :setting', ['setting' => $setting]);
        }
    }

    public function down(Schema $schema): void
    {

    }
}
