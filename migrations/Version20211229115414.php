<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20211229115414 extends AbstractMigration {

    public function up(Schema $schema): void {
        $this->addSql("UPDATE parametrage_global SET label = 'FONT_FAMILY' WHERE label = 'FONT FAMILY'");
        $this->addSql("UPDATE parametrage_global SET label = 'BARCODE_TYPE' WHERE label = 'barcode type'");
        $this->addSql("UPDATE parametrage_global SET label = 'USES_UTF8' WHERE label = 'utilise utf8'");
        $this->addSql("UPDATE parametrage_global SET label = 'REQUESTER_IN_DELIVERY' WHERE label = 'DEMANDEUR DANS DL'");
        $this->addSql("UPDATE parametrage_global SET label = 'CREATE_PREPA_AFTER_DL' WHERE label = 'CREATION PREPA APRES DL'");
        $this->addSql("UPDATE parametrage_global SET label = 'CLOSE_AND_CLEAR_AFTER_NEW_MVT' WHERE label = 'CLOSE AND CLEAR AFTER NEW MVT'");
        $this->addSql("UPDATE parametrage_global SET label = 'DROP_OFF_LOCATION_IF_CUSTOMS' WHERE label = 'EMPLACEMENT DE DEPOSE SI CHAMP DOUANE COCHE'");
        $this->addSql("UPDATE parametrage_global SET label = 'DROP_OFF_LOCATION_IF_EMERGENCY' WHERE label = 'EMPLACEMENT DE DEPOSE SI CHAMP URGENCE COCHE'");
        $this->addSql("UPDATE parametrage_global SET label = 'DELIVERY_NOTE_LOGO' WHERE label = 'FILE DELIVERY NOTE'");
        $this->addSql("UPDATE parametrage_global SET label = 'WAYBILL_LOGO' WHERE label = 'FILE WAYBILL'");
        $this->addSql("UPDATE parametrage_global SET label = 'LABEL_LOGO' WHERE label = 'FILE FOR LOGO'");

        $this->addSql("INSERT INTO parametrage_global(label, value) VALUES
                               ('LABEL_WIDTH', (SELECT width FROM dimensions_etiquettes LIMIT 1)),
                               ('LABEL_HEIGHT', (SELECT height FROM dimensions_etiquettes LIMIT 1));
        ");

        $this->addSql("DROP TABLE dimensions_etiquettes");

    }

}
