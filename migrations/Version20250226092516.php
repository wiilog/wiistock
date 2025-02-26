<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250226092516 extends AbstractMigration {
    public function getDescription(): string {
        return '';
    }

    public function up(Schema $schema): void{
        // The discriminator value "deliveryrequesttemplate" is invalid. It must be one of "requesttemplate", "collectrequesttemplate", "handlingrequesttemplate", "deliveryrequesttemplatetriggeraction".
        // change all the values to deliveryrequesttemplatetriggeraction in column dicr where value = deliveryrequesttemplate on table request_tyemplate
        $this->addSql('UPDATE request_template SET request_template.discr = "deliveryrequesttemplatetriggeraction" WHERE request_template.discr = "deliveryrequesttemplate"');


        $this->addSql('RENAME TABLE delivery_request_template TO delivery_request_template_trigger_action');



    }

    public function down(Schema $schema): void {}
}
