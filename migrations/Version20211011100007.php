<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211011100007 extends AbstractMigration
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
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('RENAME TABLE litige_historic TO dispute_history_record');
        $this->addSql('ALTER TABLE dispute_history_record DROP FOREIGN KEY FK_169F2D151ACCC76A');
        $this->addSql('DROP INDEX IDX_169F2D151ACCC76A ON dispute_history_record');
        $this->addSql('ALTER TABLE dispute_history_record CHANGE litige_id dispute_id INT NOT NULL');

        $this->addSql('RENAME TABLE litige TO dispute');
        $this->addSql('RENAME TABLE litige_pack TO dispute_pack');
        $this->addSql('RENAME TABLE litige_article TO dispute_article');
        $this->addSql('RENAME TABLE litige_utilisateur TO dispute_utilisateur');
        $this->addSql('ALTER TABLE attachment CHANGE litige_id dispute_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE dispute CHANGE declarant_id reporter_id INT DEFAULT NULL, CHANGE numero_litige number VARCHAR(64) NOT NULL');
        $this->addSql('ALTER TABLE dispute_pack CHANGE litige_id dispute_id INT NOT NULL');
        $this->addSql('ALTER TABLE dispute_article CHANGE litige_id dispute_id INT NOT NULL');
        $this->addSql('ALTER TABLE dispute_utilisateur CHANGE litige_id dispute_id INT NOT NULL');
        $this->addSql('ALTER TABLE dispute_history_record CHANGE dispute_id dispute_id INT NOT NULL');



        $usersWithDeclarant = $this->connection->executeQuery("
            SELECT id AS user_id,
                   columns_visible_for_litige
            FROM utilisateur
            WHERE columns_visible_for_litige LIKE '%\"declarant\"%'"
        );

        foreach ($usersWithDeclarant as $userWithDeclarant) {
            $user_id = $userWithDeclarant['user_id'];
            $columns_visible_for_litige = str_replace('"declarant"', '"reporter"', $userWithDeclarant['columns_visible_for_litige']);
            $this->addSql("UPDATE utilisateur SET columns_visible_for_litige = '$columns_visible_for_litige' WHERE id = $user_id");
        }

        $this->addSql('ALTER TABLE dispute ADD last_history_record_id INT DEFAULT NULL');
        $this->addSql('
            UPDATE dispute
                INNER JOIN dispute_history_record ON dispute.id = dispute_history_record.dispute_id
            SET last_history_record_id = (
                SELECT id
                FROM dispute_history_record
                WHERE dispute_history_record.dispute_id = dispute.id
                ORDER BY dispute_history_record.date DESC
                LIMIT 1
            )
            WHERE 1=1
        ');

        if (!$schema->hasTable('dispute_history_record')) {
            $this->addSql('
                CREATE TABLE dispute_history_record (
                    id INT AUTO_INCREMENT NOT NULL,
                    user_id INT DEFAULT NULL,
                    dispute_id INT NOT NULL,
                    date DATETIME NOT NULL,
                    comment LONGTEXT DEFAULT NULL,
                    status_label VARCHAR(255) DEFAULT NULL,
                    type_label VARCHAR(255) DEFAULT NULL,
                    PRIMARY KEY(id))
            ');
        }
        else {
            if (!$schema->getTable('dispute_history_record')->hasColumn('type_label')) {
                $this->addSql('ALTER TABLE dispute_history_record ADD type_label VARCHAR(255) DEFAULT NULL');
            }
            if (!$schema->getTable('dispute_history_record')->hasColumn('status_label')) {
                $this->addSql('ALTER TABLE dispute_history_record ADD status_label VARCHAR(255) DEFAULT NULL');
            }
        }

        $disputeIterator = $this->connection->iterateAssociative('
            SELECT litige.*,
                   statut.nom AS status_label,
                   type.label AS type_label
            FROM litige
                LEFT JOIN statut ON litige.status_id = statut.id
                LEFT JOIN type ON litige.type_id = type.id
        ');

        foreach ($disputeIterator as $dispute) {
            $disputeId = $dispute['id'];

            $disputeHistory = $this->connection
                ->executeQuery("
                    SELECT litige_historic.*
                    FROM litige_historic
                    WHERE litige_historic.litige_id = :disputeId
                    ORDER BY litige_historic.date ASC
                ", ['disputeId' => $disputeId])
                ->fetchAllAssociative();
            if (empty($disputeHistory)) {
                $userId = $dispute['reporter_id'];
                if ($userId) {
                    $this->addSql("
                        INSERT INTO dispute_history_record
                            (user_id, dispute_id, date, type_label, status_label)
                        VALUES
                            (:user_id, :dispute_id, :creation_date, :type_label, :status_label);
                    ", [
                        'user_id' => $userId,
                        'dispute_id' => $disputeId,
                        'creation_date' => $dispute['creation_date'],
                        'type_label' => $dispute['type_label'],
                        'status_label' => $dispute['status_label']
                    ]);
                }
            }
            else {
                [$lastStatusLabel, $lastTypeLabel] = $this->getInitialStatusAndType($dispute, $disputeHistory);

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

                    $clearedComment = $this->clearComment($comment, !empty($lastStatusLabel), !empty($lastTypeLabel)) ?: '';

                    $this->addSql("
                        UPDATE dispute_history_record
                        SET status_label = :status_label,
                            type_label = :type_label,
                            comment = :comment
                        WHERE id = $recordId
                    ", [
                        'status_label' => $lastStatusLabel,
                        'type_label' => $lastTypeLabel,
                        'comment' => $clearedComment
                    ]);
                }
            }
        }
    }

    private function getInitialStatusAndType(array $dispute, $disputeHistory): array {
        $firstTypeLabel = null;
        $firstStatusLabel = null;
        $loopIndex = 0;
        foreach ($disputeHistory as $record) {
            $comment = $record['comment'];
            [$oldStatus, $newStatus] = $this->extractStatusFromComment($comment);
            if (empty($firstStatusLabel)) {
                if ($loopIndex === 0 && $newStatus && !$oldStatus) {
                    $firstStatusLabel = $newStatus;
                }
                else if ($loopIndex > 0 && $newStatus && $oldStatus) {
                    $firstStatusLabel = $oldStatus;
                }
            }
            [$oldType, $newType] = $this->extractTypeFromComment($comment);
            if (empty($firstTypeLabel)) {
                if ($loopIndex === 0 && $newType && !$oldType) {
                    $firstTypeLabel = $newType;
                }
                else if ($loopIndex > 0 && $newType && $oldType) {
                    $firstTypeLabel = $oldType;
                }
            }

            if ($firstStatusLabel && $firstTypeLabel) {
                break;
            }
            $loopIndex++;
        }

        if (empty($firstStatusLabel)) {
            $firstStatusLabel = $dispute['status_label'];
        }
        if (empty($firstTypeLabel)) {
            $firstTypeLabel = $dispute['type_label'];
        }

        return [$firstStatusLabel, $firstTypeLabel];
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

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
