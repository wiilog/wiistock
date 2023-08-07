<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Setting;
use App\Service\SpecificService;
use App\Service\UniqueNumberService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230807123125 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        if ($_SERVER["APP_CLIENT"] === SpecificService::CLIENT_ARKEMA_SERQUIGNY ) {
            // cretate seting
            $this->addSql('INSERT INTO `setting` (label , value) VALUES (:label, :value)', [
                'label' => Setting::DISPATCH_NUMBER_FORMAT,
                'value' => UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT,
            ]);
        }
    }

    public function down(Schema $schema): void
    {}
}
