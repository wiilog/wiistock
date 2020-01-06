<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20191231132604 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'creates and fills new reception_reference_article_id in article from the old association with reception';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql("ALTER TABLE article ADD reception_reference_article_id int(11)");
        $this->addSql("UPDATE article as a SET a.reception_reference_article_id =
                            (
                                SELECT id FROM reception_reference_article as rra
                                WHERE rra.reception_id = a.reception_id AND rra.reference_article_id =
                                (
                                    SELECT ra.id
                                    FROM (SELECT * FROM article) as a_second
                                    INNER JOIN article_fournisseur as af ON a_second.article_fournisseur_id = af.id
                                    INNER JOIN reference_article ra ON af.reference_article_id = ra.id
                                    WHERE a_second.id = a.id AND ra.type_quantite = 'article'
                                ) LIMIT 1
                            )
                            WHERE a.reception_id IS NOT NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql("ALTER TABLE article ADD reception_id int(11)");
        $this->addSql("UPDATE article as a SET a.reception_id =
                            (
                                SELECT r.id
                                FROM reception_reference_article as rra
                                INNER JOIN reception r on rra.reception_id = r.id
                                WHERE rra.id = a.reception_reference_article_id
                            )
                            WHERE a.reception_reference_article_id IS NOT NULL"
        );
        $this->addSql("ALTER TABLE article DROP COLUMN reception_reference_article_id");
    }
}
