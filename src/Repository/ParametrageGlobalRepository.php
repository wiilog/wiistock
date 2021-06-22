<?php

namespace App\Repository;

use App\Entity\ParametrageGlobal;
use Doctrine\ORM\EntityRepository;

/**
 * @method ParametrageGlobal|null find($id, $lockMode = null, $lockVersion = null)
 * @method ParametrageGlobal|null findOneBy(array $criteria, array $orderBy = null)
 * @method ParametrageGlobal[]    findAll()
 * @method ParametrageGlobal[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParametrageGlobalRepository extends EntityRepository
{
    public function getOneParamByLabel($label) {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT pg.value
            FROM App\Entity\ParametrageGlobal pg
            WHERE pg.label LIKE :label
            "
        )->setParameter('label', $label);

        $result = $query->getOneOrNullResult();

        return $result ? $result['value'] : null;
    }
}
