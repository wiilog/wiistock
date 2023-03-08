<?php

namespace App\Repository;

use App\Entity\StorageRule;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<StorageRule>
 *
 * @method StorageRule|null find($id, $lockMode = null, $lockVersion = null)
 * @method StorageRule|null findOneBy(array $criteria, array $orderBy = null)
 * @method StorageRule[]    findAll()
 * @method StorageRule[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StorageRuleRepository extends EntityRepository
{
    public function clearTable(): void {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "DELETE
            FROM App\Entity\StorageRule sr
           "
        );
        $query->execute();
    }

    public function findOneByReferenceAndLocation(string $reference, string $location): StorageRule|null {
        return $this->createQueryBuilder("storage_rule")
            ->leftJoin("storage_rule.location", "location")
            ->leftJoin("storage_rule.referenceArticle", "reference_article")
            ->andWhere("reference_article.reference = :reference AND location.label = :location")
            ->setParameter("reference", "$reference")
            ->setParameter("location", $location)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function iterateAll(): iterable {
        $qb = $this->createQueryBuilder('storage_rule')
            ->select('reference.reference')
            ->addSelect('location.label AS locationLabel')
            ->addSelect('storage_rule.securityQuantity AS securityQuantity')
            ->addSelect('storage_rule.conditioningQuantity AS conditioningQuantity')
            ->addSelect('zone.name AS zoneName')
            ->leftjoin('storage_rule.referenceArticle', 'reference')
            ->leftjoin('storage_rule.location', 'location')
            ->leftJoin('location.zone', 'zone');

        return $qb
            ->getQuery()
            ->toIterable();
    }
}
