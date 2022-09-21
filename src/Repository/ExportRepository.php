<?php

namespace App\Repository;

use App\Entity\Export;
use App\Entity\Import;
use App\Entity\Type;
use Doctrine\ORM\EntityRepository;

class ExportRepository extends EntityRepository
{
    public function findScheduledExports() {
        return $this->createQueryBuilder("export")
            ->join("export.type", "type")
            ->join("export.status", "status")
            ->where("type.label = :type")
            ->andWhere("status.code = :status")
            ->setParameter("type", Type::LABEL_SCHEDULED_EXPORT)
            ->setParameter("status", Export::STATUS_SCHEDULED)
            ->getQuery()
            ->getResult();
    }
}
