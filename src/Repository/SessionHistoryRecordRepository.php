<?php

namespace App\Repository;

use App\Entity\SessionHistoryRecord;
use App\Entity\Type;
use App\Entity\UserSession;
use App\Helper\QueryBuilderHelper;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpFoundation\ParameterBag;

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
    public function findSessionHistoryRecordToClose(?Type $type): array
    {
        $now = (new DateTime())->getTimestamp();
        $queryBuilder = $this->createQueryBuilder('session_history_record');
        $queryBuilder
            ->leftJoin(UserSession::class, 'user_session', Join::WITH, 'user_session.id = session_history_record.sessionId')
            ->andWhere('session_history_record.closedAt IS NULL AND user_session.lifetime < :now')
            ->orWhere('user_session.id IS NULL AND session_history_record.closedAt IS NULL')
            ->andWhere('session_history_record.type = :type')
            ->setParameter('now', $now)
            ->setParameter('type', $type);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findByParams(ParameterBag $params): array{
        $data = [];
        $queryBuilder = $this->createQueryBuilder('session_history_record');
        $data["recordsTotal"] = QueryBuilderHelper::count($queryBuilder, 'session_history_record');


        $queryBuilder
            ->leftJoin('session_history_record.type', 'type')
            ->leftJoin('session_history_record.user', 'user');

        if (!empty($params->all('search'))) {
            $search = $params->all('search')['value'];
            if (!empty($search)) {
                $queryBuilder
                    ->orWhere('type.label LIKE :value')
                    ->orWhere('user.username LIKE :value')
                    ->orWhere('user.email LIKE :value')
                    ->orWhere('session_history_record.sessionId LIKE :value')
                    ->orWhere('session_history_record.openedAt LIKE :value')
                    ->orWhere('session_history_record.closedAt LIKE :value')
                    ->setParameter('value', '%' . $search . '%');
            }
        }

        if (!empty($params->all('order'))) {
            $order = $params->all('order')[0]['dir'];
            if (!empty($order)) {
                $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];
                switch ($column) {
                    case 'user':
                        $queryBuilder
                            ->orderBy('user.username', $order);
                        break;
                    case 'userEmail':
                        $queryBuilder
                            ->orderBy('user.email', $order);
                        break;
                    case 'type':
                        $queryBuilder
                            ->orderBy('type.label', $order);
                        break;
                    case 'closedAt':
                        $queryBuilder
                            ->orderBy('session_history_record.closedAt', $order);
                        break;
                    case 'sessionId':
                        $queryBuilder
                            ->orderBy('session_history_record.sessionId', $order);
                        break;
                    case 'openedAt':
                    default:
                        $queryBuilder
                            ->orderBy('session_history_record.openedAt', $order);
                        break;
                }
            }
        }

        $data["recordsFiltered"] = QueryBuilderHelper::count($queryBuilder, 'session_history_record');

        if ($params->getInt('start')) $queryBuilder->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $queryBuilder->setMaxResults($params->getInt('length'));

        $data["data"] = $queryBuilder->getQuery()->getResult();

        return $data;
    }

    public function getActiveLicenceCount(): int
    {
        $queryBuilder = $this->createQueryBuilder('session_history_record')
            ->andWhere('session_history_record.closedAt IS NULL');

        return QueryBuilderHelper::count($queryBuilder, 'session_history_record');
    }
}
