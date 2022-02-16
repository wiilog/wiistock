<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Setting;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220205174650 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update old settings with a new format';
    }

    public function up(Schema $schema): void {
        $arrivalEmergencyTriggeringFields = $this->connection
            ->executeQuery("SELECT value FROM parametrage_global WHERE label = 'ARRIVAL_EMERGENCY_TRIGGERING_FIELDS'")
            ->fetchFirstColumn();
        $value = $arrivalEmergencyTriggeringFields[0] ?? null;
        if ($value) {
            $decodedValue = json_decode($value, true);
            $splittableValue = $decodedValue && is_array($decodedValue) ? implode(',', $decodedValue) : $value;
            $this->addSql("UPDATE parametrage_global SET value = '$splittableValue' WHERE label = 'ARRIVAL_EMERGENCY_TRIGGERING_FIELDS'");
        }

        $icons = [
            Setting::EMERGENCY_ICON,
            Setting::CUSTOM_ICON,
            Setting::LABEL_LOGO,
        ];

        foreach ($icons as $icon) {
            $iconResult = $this->connection
                ->executeQuery("SELECT value FROM parametrage_global WHERE label = '$icon'")
                ->fetchFirstColumn();
            $iconValue = $iconResult[0] ?? null;
            if ($iconValue && !str_starts_with($iconValue, 'uploads/attachements')) {
                $this->addSql("UPDATE parametrage_global SET value = 'uploads/attachements/$iconValue' WHERE label = '$icon'");
            }
        }
    }

    public function down(Schema $schema): void
    {
    }
}
