<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211012101734 extends AbstractMigration
{
    private const REGEX_TYPE_CHANGE = '/Changement du type : ([^\n]+) -> ([^\n]+).\n?/';
    private const REGEX_TYPE_NEW = '/Type à la création -> ([^\n]+)\n?/';
    private const REGEX_STATUS_CHANGE = '/Changement du statut : ([^\n]+) -> ([^\n]+).\n?/';
    private const REGEX_STATUS_NEW = '/Statut à la création -> ([^\n]+)\n?/';

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dispute_history_record ADD type_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE dispute_history_record ADD status_id INT DEFAULT NULL');

        $disputeIterator = $this->connection->iterateAssociative('
            SELECT dispute.*, IF(dispute_article.article_id IS NOT NULL, 1, 0) AS is_reception
            FROM dispute
            LEFT JOIN dispute_article ON dispute.id = dispute_article.dispute_id
        ');

        foreach ($disputeIterator as $dispute) {
            $disputeId = $dispute['id'];
            $isReception = $dispute['is_reception'] == 1;

            $disputeHistory = $this->connection
                ->executeQuery("
                    SELECT dispute_history_record.*
                    FROM dispute_history_record
                    WHERE dispute_history_record.dispute_id = $disputeId
                    ORDER BY dispute_history_record.date ASC
                ")
                ->fetchAllAssociative();
            if (empty($disputeHistory)) {
                $userId = $dispute['reporter_id'];
                $typeId = $dispute['type_id'];
                $statusId = $dispute['status_id'];
                $creationDate = $dispute['creation_date'];

                if ($userId) {
                    $this->addSql("
                        INSERT INTO dispute_history_record
                            (user_id, dispute_id, date, type_id, status_id)
                        VALUES
                            ($userId, $disputeId, '$creationDate', $typeId, $statusId);
                    ");
                }
            }
            else {
                [$lastStatusLabel, $lastTypeLabel] = $this->getInitialStatusAndType($dispute, $disputeHistory);
                if (!$lastStatusLabel) {
                    $lastStatusLabel = 'NULL';
                }
                if (!$lastTypeLabel) {
                    $lastTypeLabel = 'NULL';
                }

                foreach ($disputeHistory as $record) {
                    $recordId = $record['id'];
                    $comment = $record['comment'];
                    [, $newStatus] = $this->extractStatusFromComment($comment);
                    [, $newType] = $this->extractTypeFromComment($comment);

                    if ($newStatus) {
                        $lastStatusLabel = $newStatus;
                    }

                    if ($newType) {
                        $lastTypeLabel = $newType;
                    }

                    $categoryStatus = $isReception ? CategorieStatut::LITIGE_RECEPT : CategorieStatut::DISPUTE_ARR;
                    $categoryType = CategoryType::DISPUTE;

                    $newStatusIdRes = $this->connection
                        ->executeQuery("
                            SELECT statut.id AS id
                            FROM statut
                                INNER JOIN categorie_statut ON categorie_statut.id = statut.categorie_id
                            WHERE categorie_statut.nom = '$categoryStatus'
                              AND (statut.nom = '$lastStatusLabel' OR statut.id = '$lastStatusLabel')
                            LIMIT 1
                        ")
                        ->fetchFirstColumn();
                    $newTypeIdRes = $this->connection
                        ->executeQuery("
                            SELECT type.id AS id
                            FROM type
                                INNER JOIN category_type ON category_type.id = type.category_id
                            WHERE (type.label = '$lastTypeLabel' OR type.id = '$lastTypeLabel')
                              AND category_type.label = '$categoryType'
                            LIMIT 1
                        ")
                        ->fetchFirstColumn();

                    $newStatusId = $newStatusIdRes[0] ?? null;
                    $newTypeId = $newTypeIdRes[0] ?? null;
                    $newStatusIdStr = $newStatusId ?: 'NULL';
                    $newTypeIdStr = $newTypeId ?: 'NULL';

                    $clearedComment = $this->clearComment($comment, (bool) $newStatusId, (bool) $newTypeId) ?: '';
                    $clearedStrComment = $clearedComment ? ("'" . str_replace("'", "\'", $clearedComment) . "'") : 'NULL';

                    $this->addSql("
                        UPDATE dispute_history_record
                        SET status_id = $newStatusIdStr,
                            type_id = $newTypeIdStr,
                            comment = $clearedStrComment
                        WHERE id = $recordId
                    ");
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
    }

    private function getInitialStatusAndType(array $dispute, $disputeHistory): array {
        $firstTypeId = null;
        $firstStatusId = null;
        $loopIndex = 0;
        foreach ($disputeHistory as $record) {
            $comment = $record['comment'];
            [$oldStatus, $newStatus] = $this->extractStatusFromComment($comment);
            if (empty($firstStatusId)) {
                if ($loopIndex === 0 && $newStatus && !$oldStatus) {
                    $firstStatusId = $newStatus;
                }
                else if ($loopIndex > 0 && $newStatus && $oldStatus) {
                    $firstStatusId = $oldStatus;
                }
            }
            [$oldType, $newType] = $this->extractTypeFromComment($comment);
            if (empty($firstTypeId)) {
                if ($loopIndex === 0 && $newType && !$oldType) {
                    $firstTypeId = $newType;
                }
                else if ($loopIndex > 0 && $newType && $oldType) {
                    $firstTypeId = $oldType;
                }
            }

            if ($firstStatusId && $firstTypeId) {
                break;
            }
            $loopIndex++;
        }

        if (empty($firstStatusId)) {
            $firstStatusId = $dispute['status_id'];
        }
        if (empty($firstTypeId)) {
            $firstTypeId = $dispute['type_id'];
        }

        return [$firstStatusId, $firstTypeId];
    }

    private function extractStatusFromComment(?string $comment): array {
        if ($comment) {
            preg_match(self::REGEX_STATUS_CHANGE, $comment, $changeMatches);
            preg_match(self::REGEX_STATUS_NEW, $comment, $newMatches);
        }
        return !empty($changeMatches)
            ? [$changeMatches[1], $changeMatches[2]]
            : (!empty($newMatches)
                ? [null, $newMatches[1]]
                : [null, null]);
    }

    private function extractTypeFromComment(?string $comment): array {
        if ($comment) {
            preg_match(self::REGEX_TYPE_CHANGE, $comment, $changeMatches);
            preg_match(self::REGEX_TYPE_NEW, $comment, $newMatches);
        }
        return !empty($changeMatches)
            ? [$changeMatches[1], $changeMatches[2]]
            : (!empty($newMatches)
                ? [null, $newMatches[1]]
                : [null, null]);
    }

    private function clearComment(?string $comment, bool $removeStatus, bool $removeType): ?string {
        $regexToCheck = [
            $removeStatus ? self::REGEX_STATUS_CHANGE : null,
            $removeStatus ? self::REGEX_STATUS_NEW : null,
            $removeType ? self::REGEX_TYPE_CHANGE : null,
            $removeType ? self::REGEX_TYPE_NEW : null
        ];
        if (!empty($comment)) {
            foreach ($regexToCheck as $reg) {
                if ($reg) {
                    preg_match($reg, $comment, $matches);
                    if (!empty($matches)) {
                        $match = $matches[0];
                        $comment = str_replace($match, '', $comment);
                    }
                }
            }
        }
        return $comment;
    }
}
