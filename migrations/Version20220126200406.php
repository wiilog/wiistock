<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\ReferenceArticle;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220126200406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->addSql('ALTER TABLE role ADD quantity_type VARCHAR(64) NOT NULL');

        $typeQuantityReference = ReferenceArticle::QUANTITY_TYPE_REFERENCE;
        $this->addSql("
            UPDATE role
            INNER JOIN parametre_role ON role.id = parametre_role.role_id
            SET quantity_type = '$typeQuantityReference'
            WHERE parametre_role.value = 'par référence'
        ");

        $typeQuantityArticle = ReferenceArticle::QUANTITY_TYPE_ARTICLE;
        $this->addSql("
            UPDATE role
            INNER JOIN parametre_role ON role.id = parametre_role.role_id
            SET quantity_type = '$typeQuantityArticle'
            WHERE parametre_role.value = 'par article'
        ");

        if ($schema->hasTable('parametre_role')) {
            if ($schema->getTable('parametre_role')->hasForeignKey('FK_44CD190C6358FF62')) {
                $this->addSql('ALTER TABLE parametre_role DROP FOREIGN KEY FK_44CD190C6358FF62');
            }
            $this->addSql('DROP TABLE parametre_role');
        }

        if ($schema->hasTable('parametre')) {
            $this->addSql('DROP TABLE parametre');
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
