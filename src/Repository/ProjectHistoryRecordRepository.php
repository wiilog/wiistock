<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\ProjectHistoryRecord;
use Doctrine\ORM\EntityRepository;

/**
 * @method ProjectHistoryRecord|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProjectHistoryRecord|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProjectHistoryRecord[]    findAll()
 * @method ProjectHistoryRecord[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProjectHistoryRecordRepository extends EntityRepository {

    public function countProject(Project $project): int {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->from(ProjectHistoryRecord::class, 'record')
            ->select('COUNT(record)')
            ->andWhere('record.project = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->getSingleScalarResult();
    }

}
