<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210302092306 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE dispatch_receiver (dispatch_id INT NOT NULL, utilisateur_id INT NOT NULL)');
        $this->addSql('
                            INSERT INTO
                                dispatch_receiver (
                                                    dispatch_id,
                                                    utilisateur_id
                                                    )
                                                    SELECT
                                                        id AS dispatch_id,
                                                        receiver_id AS utilisateur_id
                                                    FROM
                                                        dispatch
                                                    WHERE
                                                        receiver_id IS NOT NULL
                                                    ');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
