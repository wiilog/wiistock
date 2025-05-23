<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Controller\FieldModesController;
use App\Entity\Utilisateur;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250519132841 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $result = $this->connection
            ->executeQuery('SELECT id AS user_id, field_modes_by_page FROM utilisateur')
            ->iterateAssociative();
        foreach ($result as $row) {
            $userId = $row['user_id'];
            $fieldModesByPageStr = $row['field_modes_by_page'];
            $fieldModesByPage = $fieldModesByPageStr ? json_decode($fieldModesByPageStr, true) : Utilisateur::DEFAULT_FIELDS_MODES;
            $packIndexFieldModes = $fieldModesByPage[FieldModesController::PAGE_PACK_LIST] ?? Utilisateur::DEFAULT_PACK_LIST_FIELDS_MODES;
            $arrivalPacksFieldModes = $fieldModesByPage['arrivalPack'] ?? Utilisateur::DEFAULT_ARRIVAL_PACK_FIELDS_MODES;

            if (isset($packIndexFieldModes['location'])
                || isset($arrivalPacksFieldModes['lastLocation'])) {
                if (isset($packIndexFieldModes['location'])) {
                    $packIndexFieldModes['lastLocation'] = $packIndexFieldModes['location'];
                    unset($packIndexFieldModes['location']);
                    $fieldModesByPage[FieldModesController::PAGE_PACK_LIST] = $packIndexFieldModes;
                }

                if (isset($arrivalPacksFieldModes['lastLocation'])) {
                    $arrivalPacksFieldModes['ongoingLocation'] = $arrivalPacksFieldModes['lastLocation'];
                    unset($arrivalPacksFieldModes['lastLocation']);
                    $fieldModesByPage['arrivalPack'] = $arrivalPacksFieldModes;
                }

                $this->addSql('UPDATE utilisateur SET field_modes_by_page = :field_modes_by_page WHERE id = :user_id', [
                    'field_modes_by_page' => json_encode($fieldModesByPage),
                    'user_id' => $userId,
                ]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
