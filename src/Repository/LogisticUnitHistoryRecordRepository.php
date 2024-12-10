<?php

namespace App\Repository;

use App\Entity\OperationHistory\LogisticUnitHistoryRecord;
use App\Entity\Tracking\Pack;
use App\Helper\QueryBuilderHelper;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Exception;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;

/**
 * @method LogisticUnitHistoryRecord|null find($id, $lockMode = null, $lockVersion = null)
 * @method LogisticUnitHistoryRecord|null findOneBy(array $criteria, array $orderBy = null)
 * @method LogisticUnitHistoryRecord[]    findAll()
 * @method LogisticUnitHistoryRecord[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LogisticUnitHistoryRecordRepository extends EntityRepository {

    // only record of current pack, parent and grandparent
    private const MAX_PARENT_RECORD_LEVEL = 2;

    public function findByParamsAndFilters(InputBag $params, Pack $logisticUnit, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder("logistic_unit_history_record")
            ->addSelect("COUNT_OVER(logistic_unit_history_record.id) AS __query_count")
            ->join('logistic_unit_history_record.pack', 'record_pack')
            ->addOrderBy('logistic_unit_history_record.date', 'DESC')
            ->addOrderBy('logistic_unit_history_record.id', 'DESC');

        $this->joinWithPack(
            $logisticUnit,
            $queryBuilder,
            "record_pack",
            "logistic_unit_history_record",
            self::MAX_PARENT_RECORD_LEVEL
        );

        $total = QueryBuilderHelper::count($queryBuilder, 'logistic_unit_history_record');

        if (!empty($params->all('search'))) {
            $search = $params->all('search')['value'];
            if (!empty($search)) {
                $exprBuilder = $queryBuilder->expr();
                $queryBuilder
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

            if ($params->getInt('start')) {
                $queryBuilder->setFirstResult($params->getInt('start'));
            }

            $pageLength = $params->getInt('length') ? $params->getInt('length') : 100;
            if ($pageLength) {
                $queryBuilder->setMaxResults($pageLength);
            }
        }

        $queryResult = $queryBuilder
            ->getQuery()
            ->getResult();

        return [
            'data' => Stream::from($queryResult)
                ->map(static fn(array $item) => $item[0])
                ->toArray(),
            'count' => $queryResult[0]['__query_count'] ?? 0,
            'total' => $total
        ];
    }

    /**
     * @param Pack $logisticUnit
     * @param "first"|"last" $mode
     * @return Pack|null
     */
    public function findOneRecord(Pack   $logisticUnit,
                                  string $mode): ?LogisticUnitHistoryRecord {

        $direction = match($mode) {
            "first" => Order::Ascending,
            "last"  => Order::Descending,
            default => throw new Exception("Invalid mode"),
        };

        $queryBuilder = $this->createQueryBuilder("logistic_unit_history_record")
            ->join('logistic_unit_history_record.pack', 'record_pack')
            ->addOrderBy('logistic_unit_history_record.date', $direction->value)
            ->addOrderBy('logistic_unit_history_record.id', $direction->value)
            ->setMaxResults(1);

        $this->joinWithPack(
            $logisticUnit,
            $queryBuilder,
            "record_pack",
            "logistic_unit_history_record",
            self::MAX_PARENT_RECORD_LEVEL
        );

        return $queryBuilder
            ->getQuery()
            ->getOneOrNullResult();
    }


    private function joinWithPack(Pack         $logisticUnit,
                                  QueryBuilder $queryBuilder,
                                  string       $packAlias,
                                  string       $recordAlias,
                                  int          $maxLevel): void {

        $exprBuilder = $queryBuilder->expr();
        $currentLogisticUnit = $logisticUnit;

        // condition for logistic unit record
        $orXPackRecord = $exprBuilder->orX("$packAlias = :main_pack");

        // add condition for logistic unit forebears (logistic unit parent & grandparent)
        for ($currentLevel = 0; $currentLevel < $maxLevel; $currentLevel++) {
            $currentPackSplit = $currentLogisticUnit->getSplitFrom();
            $currentLogisticUnit = $currentPackSplit?->getFrom();
            if ($currentLogisticUnit) {
                $packParentLevelName = "pack_parent_level{$currentLevel}";
                $packParentLevelDateSplitName = "pack_parent_level{$currentLevel}_date_split";
                $orXPackRecord->add(
                    $exprBuilder->andX("$packAlias = :$packParentLevelName", "$recordAlias.date <= :$packParentLevelDateSplitName")
                );
                $queryBuilder
                    ->setParameter($packParentLevelName, $currentLogisticUnit)
                    ->setParameter($packParentLevelDateSplitName, $currentPackSplit->getSplittingAt());
            }
            else {
                // quit loop if current logistic unit has no forebears
                break;
            }
        }

        $queryBuilder
            ->andWhere($orXPackRecord)
            ->setParameter('main_pack', $logisticUnit);
    }
}
