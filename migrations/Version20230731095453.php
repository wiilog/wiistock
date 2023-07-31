<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Setting;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230731095453 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }


    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $dispatchWaybillSettings = [
            Setting::DISPATCH_WAYBILL_CARRIER,
            Setting::DISPATCH_WAYBILL_CONSIGNER,
            Setting::DISPATCH_WAYBILL_CONTACT_NAME,
            Setting::DISPATCH_WAYBILL_CONTACT_PHONE_OR_MAIL,
            Setting::DISPATCH_WAYBILL_LOCATION_FROM,
            Setting::DISPATCH_WAYBILL_LOCATION_TO,
            Setting::DISPATCH_WAYBILL_RECEIVER
        ];
        $deliveryWaybillSettings = [
            Setting::DELIVERY_WAYBILL_CARRIER,
            Setting::DELIVERY_WAYBILL_CONSIGNER,
            Setting::DELIVERY_WAYBILL_CONTACT_NAME,
            Setting::DELIVERY_WAYBILL_CONTACT_PHONE_OR_MAIL,
            Setting::DELIVERY_WAYBILL_LOCATION_FROM,
            Setting::DELIVERY_WAYBILL_LOCATION_TO,
            Setting::DELIVERY_WAYBILL_RECEIVER
        ];

        foreach ($deliveryWaybillSettings as $counter => $deliveryWaybillSetting){
            $value = $this->connection
                ->executeQuery("
                    SELECT value
                    FROM setting
                    WHERE setting.label = :labelDispatch
                ", [
                   "labelDispatch" => $dispatchWaybillSettings[$counter]
                ])->fetchFirstColumn();
            $valueExisting = $this->connection
                ->executeQuery("
                    SELECT COUNT(setting.id) AS counter
                    FROM setting
                    WHERE setting.label = :labelDelivery
                ", [
                    "labelDelivery" => $deliveryWaybillSetting
                ])
                ->fetchFirstColumn();

            $counter = $valueExisting[0] ?? 0;
            if ($counter == 0) {
                $this->addSql("INSERT INTO setting (label, value) VALUES (:deliveryWaybillSetting, :value)", [
                    "deliveryWaybillSetting" => $deliveryWaybillSetting,
                    "value" => $value[0] ?? null,
                ]);
            }
        }

        $this->addSql("DELETE FROM setting WHERE setting.label = ':deliveryWaybillSetting'");
    }

    public function down(Schema $schema): void
    {
    }
}
