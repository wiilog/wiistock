<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231031093913 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // fetch all the natures and update allowed_forms (json ) by adding dispatch : all and displayed_on_forms to true
        $natures = $this->connection->fetchAllAssociative('SELECT * FROM nature');
        foreach ($natures as $nature) {
            $allowedFormsStr = $nature['allowed_forms'] ?? null;
            $allowedForms = $allowedFormsStr ? (json_decode($allowedFormsStr, true) ?: []) : [];
            $allowedForms['dispatch'] = 'all';
            $this->addSql('UPDATE nature SET displayed_on_forms = :displayedOnForms WHERE id = :id', [
                'displayedOnForms' => true,
                'id' => $nature['id']
            ]);
            $this->addSql('UPDATE nature SET allowed_forms = :allowedForms WHERE id = :id', [
                'allowedForms' => json_encode($allowedForms),
                'id' => $nature['id']
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
