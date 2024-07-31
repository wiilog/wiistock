<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240730093606 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('
            CREATE TABLE free_field_management_rule (
                id INT AUTO_INCREMENT NOT NULL,
                type_id INT NOT NULL,
                free_field_id INT NOT NULL,
                required_create TINYINT(1) NOT NULL,
                required_edit TINYINT(1) NOT NULL,
                displayed_create TINYINT(1) NOT NULL,
                displayed_edit TINYINT(1) NOT NULL,
                PRIMARY KEY(id)
            )');

        //get all free fields
        $freeFields = $this->connection->fetchAllAssociative('SELECT * FROM free_field');
        foreach ($freeFields as $freeField) {
            if (isset($freeField['type_id']) && isset($freeField['id'])) {
                $this->addSql(
                    '
                    INSERT INTO free_field_management_rule (
                            type_id,
                            free_field_id,
                            required_create,
                            required_edit,
                            displayed_create,
                            displayed_edit
                        )
                        VALUES (
                            :type_id,
                            :free_field_id,
                            :required_create,
                            :required_edit,
                            :displayed_create,
                            :displayed_edit
                        )
                    ',
                    [
                        "type_id" => $freeField['type_id'],
                        "free_field_id" => $freeField['id'],
                        "required_create" => $freeField['required_create'] ?? false,
                        "required_edit" => $freeField['required_edit'] ?? false,
                        "displayed_create" => $freeField['displayed_create'] ?? false,
                        "displayed_edit" => $freeField['displayed_edit'] ?? false,
                    ]
                );
            }
        }
    }

    public function down(Schema $schema): void {}
}
