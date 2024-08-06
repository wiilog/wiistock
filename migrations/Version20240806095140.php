<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\FieldModesService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240806095140 extends AbstractMigration
{
    public function getDescription(): string {
        return '';
    }

    public function up(Schema $schema): void {
        $requests = $this->connection->fetchAllAssociative('SELECT id, visible_columns FROM demande');
        foreach ($requests as $request) {
            $visibleColumns = $request['visible_columns'];
            $fieldModes = [];
            if (!empty($visibleColumns)) {
                $visibleColumns = json_decode($visibleColumns, true);
                foreach ($visibleColumns as $columns) {
                    if (is_string($columns)) {
                        $fieldModes[$columns] = [FieldModesService::FIELD_MODE_VISIBLE];
                    }
                }
            }
            $this->addSql('UPDATE demande SET visible_columns = :fieldModes WHERE id = :id', [
                'fieldModes' => json_encode($fieldModes),
                'id' => $request['id'],
            ]);
        }
    }

    public function down(Schema $schema): void {}
}
