<?php

namespace App\Repository;

use App\Entity\SessionHistoryRecord;
use App\Entity\Type\Type;
use App\Entity\UserSession;
use App\Helper\QueryBuilderHelper;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpFoundation\ParameterBag;
use WiiCommon\Helper\Stream;

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
    public function findSessionHistoryRecordToClose(?Type $type, ?int $sessionLifetime): array
    {
        $now = (new DateTime())->getTimestamp();
        $queryBuilder = $this->createQueryBuilder('session_history_record');
        $exprBuilder = $queryBuilder->expr();
        $queryBuilder
            ->leftJoin(UserSession::class, 'user_session', Join::WITH, 'user_session.id = session_history_record.sessionId')
            ->andWhere('session_history_record.closedAt IS NULL')
            ->andWhere($exprBuilder->orX(
                '(UNIX_TIMESTAMP(session_history_record.openedAt) + :sessionLifetime) < :now',
                'user_session.id IS NULL',
                'user_session.lifetime < :now',
            ))
            ->andWhere('session_history_record.type = :type')
            ->setParameter('now', $now)
            ->setParameter('type', $type)
            ->setParameter('sessionLifetime', $sessionLifetime ? $sessionLifetime * 60 : 3600);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findByParams(ParameterBag $params): array {
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

    public function getActiveLicenceCount(): int {
        $queryBuilder = $this->createQueryBuilder('session_history_record')
            ->andWhere('session_history_record.closedAt IS NULL');

        return QueryBuilderHelper::count($queryBuilder, 'session_history_record');
    }

    public function iterateAll(): iterable {
        return $this->createQueryBuilder("session_history_record")
            ->getQuery()
            ->toIterable();
    }

    public function countOpenedSessions(): int {
        $wiilogDomains = Stream::explode(",", $_SERVER['WIILOG_DOMAINS'] ?? "")
            ->filter()
            ->toArray();

        $queryBuilder = $this->createQueryBuilder("session_history_record")
            ->leftJoin('session_history_record.user', 'user')
            ->andWhere('session_history_record.closedAt IS NULL');

        foreach ($wiilogDomains as $index => $domain) {
            $parameterName = "domain$index";
            $queryBuilder
                ->andWhere("user.email NOT LIKE :$parameterName")
                ->setParameter($parameterName, "%@$domain");
        }

        return $queryBuilder
            ->select('COUNT(session_history_record.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveUsers(DateTime $from, DateTime $to, array $excludedDomains): int {
        $queryBuilder = $this->createQueryBuilder("session_history_record");
        $expressionBuilder = $queryBuilder->expr();

        $queryBuilder
            ->select("COUNT(DISTINCT session_history_record.user)")
            ->andWhere(
                $expressionBuilder->orX(
                    $expressionBuilder->orX(
                        $expressionBuilder->between("session_history_record.openedAt", ":from", ":to"),
                        $expressionBuilder->between("session_history_record.closedAt", ":from", ":to"),
                    ),
                    $expressionBuilder->andX(
                        $expressionBuilder->lt("session_history_record.openedAt", ":from"),
                        $expressionBuilder->gt("session_history_record.closedAt", ":to"),
                    ),
                )
            )
            ->join("session_history_record.user", "user")
            ->setParameter("from", $from)
            ->setParameter("to", $to);

        foreach ($excludedDomains as $index => $domain) {
            $parameterName = "domain$index";
            $queryBuilder
                ->andWhere("user.email NOT LIKE :$parameterName")
                ->setParameter($parameterName, "%@$domain");
        }

        return $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }
}
