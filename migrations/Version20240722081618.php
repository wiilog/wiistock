<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Utilisateur;
use App\Service\FieldModesService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use WiiCommon\Helper\Stream;


final class Version20240722081618 extends AbstractMigration {
    public function getDescription(): string {
        return '';
    }

    public function up(Schema $schema): void {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE utilisateur ADD field_modes_by_page JSON NOT NULL');

        // for each user tacke the visible_comlumns, format it to the new format and store it in field_modes_by_page
        $this->addSql('UPDATE utilisateur SET field_modes_by_page = visible_columns');
        // get the user visible_columns
        $users = $this->connection->fetchAllAssociative('SELECT id, visible_columns FROM utilisateur');

        foreach ($users as $user) {
            $visibleColumns = $user['visible_columns'];
            $fieldModesByPage = [];
            if (empty($visibleColumns)) {
                $fieldModesByPage = Utilisateur::DEFAULT_FIELDS_MODES;
            } else {
                $visibleColumns = json_decode($visibleColumns, true);
                foreach ($visibleColumns as $page => $columns) {
                    $fieldModesByPage[$page] = Stream::from($columns)
                        ->keymap(static fn($column) => [
                            $column,
                            [FieldModesService::FIELD_MODE_VISIBLE],
                        ])
                        ->toArray();
                }
            }
            $this->addSql('UPDATE utilisateur SET field_modes_by_page = :fieldModesByPage WHERE id = :id', [
                'fieldModesByPage' => json_encode($fieldModesByPage),
                'id' => $user['id'],
            ]);
        }
    }

    public function down(Schema $schema): void {}
}
