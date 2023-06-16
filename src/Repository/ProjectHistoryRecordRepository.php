<?php

namespace App\Repository;

use App\Entity\ProjectHistoryRecord;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;

/**
 * @method ProjectHistoryRecord|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProjectHistoryRecord|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProjectHistoryRecord[]    findAll()
 * @method ProjectHistoryRecord[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProjectHistoryRecordRepository extends EntityRepository {

    public function findLineForProjectHistory($pack, $params){
        $qb = $this->createQueryBuilder('project_history_record');

        $qb->select('project_history_record')
            ->leftJoin('project_history_record.pack', 'pack')
            ->where('pack.id = :pack')
            ->setParameters([
                'pack' => $pack,
            ]);

        $countTotal = QueryBuilderHelper::count($qb, "project_history_record");

        //Filter search
        if (!empty($params)) {
            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];
                    if ($column === 'project') {
                        $qb
                            ->leftJoin('project_history_record.project', 'order_project')
                            ->orderBy('order_project.code', $order);
                    } else if ($column === 'createdAt') {
                        $qb
                            ->orderBy('project_history_record.createdAt', $order);
                    }
                }
            }
        }

        $countFiltererd = QueryBuilderHelper::count($qb, "project_history_record");

        return [
            'data' => $qb->getQuery()->getResult(),
            'filtered' => $countFiltererd,
            'total' => $countTotal
        ];
    }
}
