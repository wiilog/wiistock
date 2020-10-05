<?php

namespace App\Repository;

use App\Entity\FiltreRef;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method FiltreRef|null find($id, $lockMode = null, $lockVersion = null)
 * @method FiltreRef|null findOneBy(array $criteria, array $orderBy = null)
 * @method FiltreRef[]    findAll()
 * @method FiltreRef[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FiltreRefRepository extends EntityRepository
{

    public function countByChampAndUser($field, $userId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT (f)
            FROM App\Entity\FiltreRef f
            WHERE (f.champLibre = :clId OR f.champFixe = :clId)
            AND f.utilisateur = :userId"
        )->setParameters(['clId' => $field, 'userId' => $userId]);

        return $query->getSingleScalarResult();
    }

    public function getFieldsAndValuesByUser($userId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
//            "SELECT f.champFixe, cl.id champLibre, f.value, cl.typage, f.operator
            "SELECT f.champFixe, cl.id champLibre, f.value, cl.typage
            FROM App\Entity\FiltreRef f
            LEFT JOIN f.champLibre cl
            WHERE f.utilisateur = :userId
            "
        )->setParameter('userId', $userId);

        return $query->execute();
    }

    public function countByChampLibre($cl)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            'SELECT COUNT (f)
            FROM App\Entity\FiltreRef f
            WHERE f.champLibre = :cl
            '
        )->setParameter('cl', $cl);
        return $query->getSingleScalarResult();
    }

    public function deleteByChampLibre($cl)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            'DELETE
            FROM App\Entity\FiltreRef f
            WHERE f.champLibre = :cl
            '
        )->setParameter('cl', $cl);
        return $query->execute();
    }

    /**
     * @param Utilisateur $user
	 * @param string $champFixe
     * @return FiltreRef|null
     */
    public function findOneByUserAndChampFixe($user, $champFixe) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT f
            FROM App\Entity\FiltreRef f
            WHERE f.utilisateur =:user AND f.champFixe = :cf"
        )->setParameters(['user' => $user, 'cf' => $champFixe]);

        $result = $query->execute();
        return $result ? $result[0] : null;
    }

    public function findByUserExceptChampFixe($user, $champFixe) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        	/** @lang DQL */
            "SELECT f
            FROM App\Entity\FiltreRef f
            WHERE f.utilisateur =:user AND (f.champFixe != :cf or f.champFixe is null)"
        )->setParameters(['user' => $user, 'cf' => $champFixe]);
        return $query->execute();
    }
}
