<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Setting;
use App\Service\SpecificService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220704140723 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $directDelivery = Setting::DIRECT_DELIVERY;
        $createDeliveryOnly = Setting::CREATE_DELIVERY_ONLY;
        $createPreparationAfterDelivery = Setting::CREATE_PREPA_AFTER_DL;
        $this->addSql("INSERT INTO setting (label, value) VALUES ('$directDelivery', 0)");
        $this->addSql("INSERT INTO setting (label, value) VALUES ('$createDeliveryOnly', 0)");

        if($_SERVER["APP_CLIENT"] !== SpecificService::CLIENT_ARCELOR) {
            $createPreparationAfterDeliverySetting = $this->connection
                ->executeQuery("SELECT * FROM setting WHERE label = '$createPreparationAfterDelivery' AND value = 0")
                ->fetchFirstColumn();

            if(!empty($createPreparationAfterDeliverySetting)) {
                $this->addSql("UPDATE setting SET value = 1 WHERE label = '$createDeliveryOnly'");
            }
        } else {
            $this->addSql("UPDATE setting SET value = 0 WHERE label = '$createPreparationAfterDelivery'");
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
