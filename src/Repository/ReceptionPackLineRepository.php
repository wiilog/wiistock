<?php

namespace App\Repository;

use App\Entity\Reception;
use App\Entity\ReceptionPackLine;
use Doctrine\ORM\EntityRepository;

/**
 * @method ReceptionPackLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReceptionPackLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReceptionPackLine[]    findAll()
 * @method ReceptionPackLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceptionPackLineRepository extends EntityRepository
{
    /**
     * @param Reception $reception
     * @return ReceptionPackLine[]|null
     */
    public function findByReception($reception)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\ReceptionPackLine a
            WHERE a.reception = :reception'
        )->setParameter('reception', $reception);;
        return $query->execute();
    }
}
