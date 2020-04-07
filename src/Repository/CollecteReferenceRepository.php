<?php

namespace App\Repository;

use App\Entity\Collecte;
use App\Entity\CollecteReference;
use Doctrine\ORM\EntityRepository;

/**
 * @method CollecteReference|null find($id, $lockMode = null, $lockVersion = null)
 * @method CollecteReference|null findOneBy(array $criteria, array $orderBy = null)
 * @method CollecteReference[]    findAll()
 * @method CollecteReference[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CollecteReferenceRepository extends EntityRepository
{

	/**
	 * @param Collecte|int $collecte
	 * @return CollecteReference[]|null
	 */
    public function findByCollecte($collecte)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT cr
          FROM App\Entity\CollecteReference cr
          WHERE cr.collecte = :collecte"
        )->setParameter('collecte',  $collecte);

        return $query->execute();
    }

    public function countByCollecteAndRA($collecte, $refArticle)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(cr)
          FROM App\Entity\CollecteReference cr
          WHERE cr.collecte = :collecte AND cr.referenceArticle = :refArticle"
        )->setParameters([
            'collecte' => $collecte,
            'refArticle' => $refArticle
        ]);
        return $query->getSingleScalarResult();
    }

    public function getByCollecteAndRA($collecte, $refArticle)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT cr
          FROM App\Entity\CollecteReference cr
          WHERE cr.collecte = :collecte AND cr.referenceArticle = :refArticle"
        )->setParameters([
            'collecte' => $collecte,
            'refArticle' => $refArticle
        ]);
        return $query->getOneOrNullResult();
    }
}

