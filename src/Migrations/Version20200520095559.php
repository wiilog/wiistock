<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200520095559 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'On enlève "Référence" du champ recherche_for_article des utilisateurs';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $oldData = $this->connection
            ->executeQuery('
                SELECT id AS user_id, recherche_for_article
                FROM utilisateur
                WHERE recherche_for_article IS NOT NULL
            ')
            ->fetchAll();

        foreach ($oldData as $data) {
            $userId = $data['user_id'];
            $rechercheForArticleStr = $data['recherche_for_article'];
            if (!empty($rechercheForArticleStr)) {
                $rechercheForArticleJson = json_decode($rechercheForArticleStr, true);

                $newRechercheForArticle = array_filter($rechercheForArticleJson, function ($field) {
                    return $field !== 'Référence';
                });

                $newRechercheForArticleStr = json_encode($newRechercheForArticle);

                $this->addSql("
                    UPDATE `utilisateur`
                    SET recherche_for_article = '$newRechercheForArticleStr'
                    WHERE id = ${userId}"
                );
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

    }
}
