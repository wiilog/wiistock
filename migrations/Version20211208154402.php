<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\FiltreSup;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211208154402 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(" DELETE
                            FROM filtre_sup
                            WHERE page = ('". FiltreSup::PAGE_PACK ."')
                            AND field = ('". FiltreSup::FIELD_TYPE ."');
                            ");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
