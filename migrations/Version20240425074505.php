<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240425074505 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // create new datatable kiosk
        $this->addSql('CREATE TABLE kiosk (id INT AUTO_INCREMENT NOT NULL, picking_type_id INT NOT NULL, picking_location_id INT NOT NULL, requester_id INT NOT NULL, token VARCHAR(255) DEFAULT NULL, expire_at DATETIME DEFAULT NULL, name VARCHAR(255) NOT NULL, subject VARCHAR(255) NOT NULL, quantity_to_pick INT DEFAULT 1 NOT NULL, destination VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_F31717305F37A13B (token), UNIQUE INDEX UNIQ_F31717305E237E06 (name), INDEX IDX_F3171730116E1520 (picking_type_id), INDEX IDX_F3171730DD441368 (picking_location_id), INDEX IDX_F3171730ED442CF4 (requester_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
                    ALTER TABLE kiosk ADD CONSTRAINT FK_F3171730116E1520 FOREIGN KEY (picking_type_id) REFERENCES type (id);
                    ALTER TABLE kiosk ADD CONSTRAINT FK_F3171730DD441368 FOREIGN KEY (picking_location_id) REFERENCES emplacement (id);
                    ALTER TABLE kiosk ADD CONSTRAINT FK_F3171730ED442CF4 FOREIGN KEY (requester_id) REFERENCES utilisateur (id);
                    ALTER TABLE delivery_request_article_line CHANGE notes notes LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`;
                    ALTER TABLE delivery_request_reference_line CHANGE notes notes LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`;');

        $requestType = $this->connection->fetchAssociative('SELECT id FROM type WHERE label = :label', ['label' => 'COLLECT_REQUEST_TYPE']);
        $requester = $this->connection->fetchAssociative('SELECT id FROM utilisateur WHERE username = :username', ['username' => 'COLLECT_REQUEST_REQUESTER']);
        $pointCollect = $this->connection->fetchAssociative('SELECT id FROM emplacement WHERE label = :label', ['label' => 'COLLECT_REQUEST_POINT_COLLECT']);

        $object = $this->connection->fetchAssociative('SELECT value FROM setting WHERE label = :label', ['label' => 'COLLECT_REQUEST_OBJECT']);
        $destination = $this->connection->fetchAssociative('SELECT value FROM setting WHERE label = :label', ['label' => 'COLLECT_REQUEST_DESTINATION']);
        $quantityToCollect = $this->connection->fetchAssociative('SELECT value FROM setting WHERE label = :label', ['label' => 'COLLECT_REQUEST_ARTICLE_QUANTITY_TO_COLLECT']);

        // insert data into kiosk with data from settings table
        $this->addSql('INSERT INTO kiosk (name, subject, quantity_to_pick, destination, requester_id, picking_location_id, picking_type_id)
                                VALUES (:name, :subject, :quantity_to_pick, :destination, :requester_id, :picking_location_id, :picking_type_id)', [
            'name' => 'Kiosk',
            'subject' => $object,
            'quantity_to_pick' => $quantityToCollect,
            'destination' => $destination,
            'requester_id' => $requester != '',
            'picking_location_id' => $pointCollect != '',
            'picking_type_id' => $requestType != '',
        ]);

        // delete old datatable kioskToken
        $this->addSql('DROP TABLE kiosk_token');

        // delete old settings
        $settingsToDrop = ['COLLECT_REQUEST_TYPE',
            'COLLECT_REQUEST_REQUESTER',
            'COLLECT_REQUEST_OBJECT',
            'COLLECT_REQUEST_POINT_COLLECT',
            'COLLECT_REQUEST_DESTINATION',
            'COLLECT_REQUEST_ARTICLE_QUANTITY_TO_COLLECT'
        ];
        foreach ($settingsToDrop as $setting) {
            $this->addSql('DELETE FROM setting WHERE label = :setting', ['setting' => $setting]);
        }
    }

    public function down(Schema $schema): void
    {

    }
}
