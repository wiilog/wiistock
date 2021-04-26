<?php

namespace App\Repository;

use App\Entity\Group;
use App\Helper\QueryCounter;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;

/**
 * @method Group|null find($id, $lockMode = null, $lockVersion = null)
 * @method Group|null findOneBy(array $criteria, array $orderBy = null)
 * @method Group[]    findAll()
 * @method Group[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupRepository extends EntityRepository {

    public function findByParamsAndFilters($params, $filters) {
        $qb = $this->createQueryBuilder("grp");

        $countTotal = QueryCounter::count($qb, "grp");

        $countFiltered = QueryCounter::count($qb, "grp");

        if ($params) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

        $query = $qb->getQuery();
        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    public function getByDates(DateTime $dateMin, DateTime $dateMax) {
        $iterator =  $this->createQueryBuilder("grp")
            ->distinct()
            ->select("grp.code AS code")
            ->addSelect("nat.label AS nature")
            ->addSelect("COUNT(children.id) AS packs")
            ->addSelect("grp.weight AS weight")
            ->addSelect("grp.volume AS volume")
            ->addSelect("movement.datetime AS lastMvtDate")
            ->addSelect("movement.id AS fromTo")
            ->addSelect("emplacement.label AS location")
            ->leftJoin("grp.lastTracking", "movement")
            ->leftJoin("movement.emplacement","emplacement")
            ->leftJoin("grp.nature","nat")
            ->leftJoin("grp.packs","children")
            ->where("movement.datetime BETWEEN :dateMin AND :dateMax")
            ->groupBy("grp")
            ->setParameter("dateMin", $dateMin)
            ->setParameter("dateMax", $dateMax)
            ->getQuery()
            ->iterate(null, Query::HYDRATE_ARRAY);

        foreach($iterator as $item) {
            // $item [index => article array]
            yield array_pop($item);
        }
    }

}
