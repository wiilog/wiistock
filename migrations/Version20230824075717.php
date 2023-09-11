<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Setting;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230824075717 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE setting SET label = :label WHERE label = 'CLOSE_AND_CLEAR_AFTER_NEW_MVT'", [
            'label' => Setting::CLEAR_AND_KEEP_MODAL_AFTER_NEW_MVT,
        ]);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
