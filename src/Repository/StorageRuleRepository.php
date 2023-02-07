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
}
