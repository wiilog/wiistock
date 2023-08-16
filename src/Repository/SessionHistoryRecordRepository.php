<?php

namespace App\Repository;

use App\Entity\SessionHistoryRecord;
use App\Entity\UserSession;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;

/**
 * @extends EntityRepository<SessionHistoryRecord>
 *
 * @method SessionHistoryRecord|null find($id, $lockMode = null, $lockVersion = null)
 * @method SessionHistoryRecord|null findOneBy(array $criteria, array $orderBy = null)
 * @method SessionHistoryRecord[]    findAll()
 * @method SessionHistoryRecord[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SessionHistoryRecordRepository extends EntityRepository
{
    public function findSessionHistoryRecordToClose(): array
    {
        $now = (new DateTime())->getTimestamp();
        $queryBuilder = $this->createQueryBuilder('session_history_record');
        $queryBuilder
            ->leftJoin(UserSession::class, 'user_session', Join::WITH, 'user_session.id = session_history_record.sessionId')
            ->andWhere('session_history_record.closedAt IS NULL AND user_session.lifetime < :now')
            ->orWhere('user_session.id IS NULL AND session_history_record.closedAt IS NULL')
            ->setParameter('now', $now);

        return $queryBuilder->getQuery()->getResult();
    }
}
