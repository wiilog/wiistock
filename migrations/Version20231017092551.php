<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\CategoryType;
use App\Entity\Type;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231017092551 extends AbstractMigration
{

    public function __construct(Connection $connection, LoggerInterface $logger) {
        parent::__construct($connection, $logger);
    }

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $categoryTypeId = $this->connection->executeQuery("SELECT id FROM category_type WHERE label = :category_type", [
            "category_type" => CategoryType::IMPORT,
        ])->fetchFirstColumn()[0];

        $typeId = $this->connection->executeQuery("SELECT id FROM type WHERE label = :type AND category_id = :category_type", [
            "type" => Type::LABEL_UNIQUE_IMPORT,
            "category_type" => $categoryTypeId,
        ])->fetchFirstColumn()[0];

        $this->addSql("UPDATE import SET type_id = :type_id", [
            "type_id" => $typeId,
        ]);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
