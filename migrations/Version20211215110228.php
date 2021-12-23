<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\SpecificService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211215110228 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Affectation de l'utilisateur Société GT sur les referenceArticle anterieure à cette migration";
    }

    public function up(Schema $schema): void
    {
        if($_SERVER["APP_CLIENT"] === SpecificService::CLIENT_CEA_LETI) {
        // je recupere l'id de l'utilisateur Société GT
            $user = $this->connection->executeQuery("SELECT id FROM utilisateur WHERE username = 'Société GT'")->fetchOne();
            // j'attribue l'id de société GT à la colonne createdBy de toute les references
            $this->addSql(
            "UPDATE reference_article
            SET reference_article.created_by_id = $user
            WHERE 1");
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
