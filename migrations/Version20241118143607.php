<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Setting;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use WiiCommon\Helper\Stream;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241118143607 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $checkboxSetting = $this->connection->executeQuery("SELECT value FROM setting WHERE label = :label", [
            "label" => Setting::KEEP_MODAL_OPEN_AND_CLEAR_AFTER_SUBMIT,
        ])->fetchOne();

        if($checkboxSetting) {
            $rolesSetting = $this->connection->executeQuery("SELECT value FROM setting WHERE label = :label", [
                "label" => Setting::KEEP_MODAL_OPEN_AND_CLEAR_AFTER_SUBMIT_FOR_ROLES,
            ])->fetchOne();

            if(!$rolesSetting) {
                $this->addSql("INSERT INTO setting (label, value) VALUES (:label, :value)", [
                    "label" => Setting::KEEP_MODAL_OPEN_AND_CLEAR_AFTER_SUBMIT_FOR_ROLES,
                    "value" => null,
                ]);
            }

            $roles = $this->connection->executeQuery("SELECT id FROM role WHERE label NOT LIKE 'aucun accès'")->fetchAllAssociative();
            $roleIds = Stream::from($roles)->map(fn($role) => $role['id'])->join(',');
            $this->addSql("UPDATE setting SET value = :value WHERE label = :label", [
                "label" => Setting::KEEP_MODAL_OPEN_AND_CLEAR_AFTER_SUBMIT_FOR_ROLES,
                "value" => $roleIds,
            ]);
        }
    }

    public function down(Schema $schema): void
    {

    }
}
