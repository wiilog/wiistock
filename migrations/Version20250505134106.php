<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250505134106 extends AbstractMigration {
    public function getDescription(): string {
        return '';
    }

    public function up(Schema $schema): void {
        $newValues = [
            'provider' => 'supplier',
            'commande' => 'orderNumber',
        ];

        $arrivalEmergencyTriggeringFields = $this->connection
            ->executeQuery("SELECT value FROM setting WHERE label = 'ARRIVAL_EMERGENCY_TRIGGERING_FIELDS'")
            ->fetchFirstColumn();
        $oldValue = $arrivalEmergencyTriggeringFields[0] ?? null;
        if ($oldValue) {
            $explodedValue = explode(',', $oldValue);
            $newValue = '';
            if(count($explodedValue) === 1) {
                $newValue = $newValues[$explodedValue[0]] ?? $explodedValue[0];
            } else {
                foreach($explodedValue as $value) {
                    $newValue .= ($newValues[$value] ?? $value) . ',';
                }
            }

            $this->addSql("UPDATE setting SET value = '$newValue' WHERE label = 'ARRIVAL_EMERGENCY_TRIGGERING_FIELDS'");
        }
    }

    public function down(Schema $schema): void {}
}
