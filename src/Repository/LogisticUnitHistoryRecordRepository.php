<?php

namespace App\Repository;

use App\Entity\OperationHistory\LogisticUnitHistoryRecord;
use App\Entity\Tracking\Pack;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method LogisticUnitHistoryRecord|null find($id, $lockMode = null, $lockVersion = null)
 * @method LogisticUnitHistoryRecord|null findOneBy(array $criteria, array $orderBy = null)
 * @method LogisticUnitHistoryRecord[]    findAll()
 * @method LogisticUnitHistoryRecord[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LogisticUnitHistoryRecordRepository extends EntityRepository {

    public function findByParamsAndFilters(InputBag $params, Pack $pack, array $options = []): array
    {
        $qb = $this->createQueryBuilder("logistic_unit_history_record")
            ->leftJoin('logistic_unit_history_record.pack', 'record_pack')
            ->andWhere('record_pack = :logisticUnit')
            ->addOrderBy('logistic_unit_history_record.date', 'DESC')
            ->addOrderBy('logistic_unit_history_record.id', 'DESC')
            ->setParameter('logisticUnit', $pack);


        $total = QueryBuilderHelper::count($qb, 'logistic_unit_history_record');

        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb
                        ->andWhere($exprBuilder->orX(
                            'logistic_unit_history_record.message LIKE :value',
                            'logistic_unit_history_record.type LIKE :value',
                            'record_location.label LIKE :value',
                            'record_user.username LIKE :value',
                        ))
                        ->leftJoin('logistic_unit_history_record.location', 'record_location')
                        ->leftJoin('logistic_unit_history_record.user', 'record_user')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            $filtered = QueryBuilderHelper::count($qb, 'logistic_unit_history_record');

            if ($params->getInt('start')) {
                $qb->setFirstResult($params->getInt('start'));
            }

            $pageLength = $params->getInt('length') ? $params->getInt('length') : 100;
            if ($pageLength) {
                $qb->setMaxResults($pageLength);
            }
        }


        return [
            'data' => $qb->getQuery()->getResult(),
            'count' => $filtered,
            'total' => $total
        ];
    }
}
