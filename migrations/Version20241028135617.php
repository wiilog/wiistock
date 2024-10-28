<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Type;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241028135617 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update createDropMovementById field with Type::CREATE_DROP_MOVEMENT_BY_ID_MANUFACTURING_ORDER_VALUE';
    }

    public function up(Schema $schema): void
    {

        $conn = $this->connection;
        $categoryId = $conn->fetchOne('SELECT id FROM category_type WHERE label = :category_label', [
            'category_label' => 'production',
        ]);

        $this->addSql(
            'UPDATE type SET create_drop_movement_by_id = :createdDropMovementById WHERE category_id = :category_id',
            [
                'createdDropMovementById' => Type::CREATE_DROP_MOVEMENT_BY_ID_MANUFACTURING_ORDER_VALUE,
                'category_id' => $categoryId,
            ]
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
