<?php

namespace App\Repository;

use App\Entity\Nature;
use Doctrine\ORM\EntityRepository;

/**
 * @method Nature|null find($id, $lockMode = null, $lockVersion = null)
 * @method Nature|null findOneBy(array $criteria, array $orderBy = null)
 * @method Nature[]    findAll()
 * @method Nature[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NatureRepository extends EntityRepository
{

    public function getAllowedNaturesIdByLocation() {
        return $this->createQueryBuilder('nature')
            ->select('nature.id AS nature_id')
            ->addSelect('location.id AS location_id')
            ->join('nature.emplacements', 'location')
            ->getQuery()
            ->getResult();
    }

    public function countUsedById($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT COUNT(n)
            FROM App\Entity\Nature n
            LEFT JOIN n.packs pack
            WHERE pack.nature = :id
           "
        )->setParameter('id', $id);

        return $query->getSingleScalarResult();
    }

    public function countAll()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT COUNT(n)
            FROM App\Entity\Nature n
           "
        );

        return $query->getSingleScalarResult();
    }

    public function findAllLabels() {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT n.label as label
            FROM App\Entity\Nature n
           "
        );
        return array_map(function(array $nature) {
            return $nature['label'];
        }, $query->getResult());
    }
}
