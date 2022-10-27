<?php

namespace App\Repository;

use App\Entity\ReceptionLine;
use Doctrine\ORM\EntityRepository;

/**
 * @method ReceptionLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReceptionLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReceptionLine[]    findAll()
 * @method ReceptionLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceptionLineRepository extends EntityRepository {

    public function getForSelectFromReception(?string $term, ?int $reception) {
        return $this->createQueryBuilder("reception_line")
            ->select("reception_line.id AS id, pack.code AS text")
            ->join("reception_line.reception",  "reception")
            ->join("reception_line.pack", "pack")
            ->andWhere("pack.code LIKE :term")
            ->andWhere("reception.id = :reception")
            ->setParameters([
                "term" => "%$term%",
                "reception" => $reception
            ])
            ->setMaxResults(100)
            ->getQuery()
            ->getArrayResult();
    }

}
