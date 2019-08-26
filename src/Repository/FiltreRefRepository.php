<?php

namespace App\Repository;

use App\Entity\FiltreRef;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method FiltreRef|null find($id, $lockMode = null, $lockVersion = null)
 * @method FiltreRef|null findOneBy(array $criteria, array $orderBy = null)
 * @method FiltreRef[]    findAll()
 * @method FiltreRef[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FiltreRefRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, FiltreRef::class);
    }

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
     * @param $userId
     * @return FiltreRef
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findByUserAndStatut($userId) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT f
            FROM App\Entity\FiltreRef f
            WHERE f.utilisateur=:userId AND f.champFixe = 'Statut'"
        )->setParameter('userId' , $userId);
        return $query->getOneOrNullResult();
    }

    public function findByUserAndNoStatut($userId) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT f
            FROM App\Entity\FiltreRef f
            WHERE f.utilisateur=:userId AND f.champFixe != 'Statut'"
        )->setParameter('userId' , $userId);
        return $query->getOneOrNullResult();
    }
}
