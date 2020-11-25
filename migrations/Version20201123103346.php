<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Helper\FormatHelper;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201123103346 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $sqlRequests = [
            'arrivage' => [
                'comment' => 'commentaire'
            ],
            'collecte' => [
                'comment' => 'commentaire'
            ],
            'demande' => [
                'comment' => 'commentaire'
            ],
            'reception' => [
                'comment' => 'commentaire'
            ],
            'reference_article' => [
                'comment' => 'commentaire'
            ],
            'transfer_request' => [
                'comment' => 'comment'
            ]
        ];

        foreach ($sqlRequests as $table => $comment) {
            $commentColumn = $comment['comment'];
            $this->addSql("ALTER TABLE ${table} ADD COLUMN cleaned_comment TEXT");

            $results = $this->connection
                ->executeQuery("
                    SELECT id, ${commentColumn} AS comment
                    FROM ${table}
                    WHERE ${commentColumn} IS NOT NULL
                ")
                ->fetchAll();

            if (!empty($results)) {
                foreach ($results as $row) {
                    $currentId = $row['id'];
                    $currentComment = $row['comment'];
                    $newCleanedComment = FormatHelper::sqlString(strip_tags($currentComment));
                    $newCleanedComment = empty($newCleanedComment) ? 'NULL' : "'${newCleanedComment}'";
                    $this->addSql("
                        UPDATE ${table}
                        SET cleaned_comment = ${newCleanedComment}
                        WHERE id = ${currentId}
                    ");
                }
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
