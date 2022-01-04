<?php

namespace App\Repository;

use App\Entity\FiltreRef;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityRepository;

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

    public function findOneByUserAndChampFixe(Utilisateur $user, string $fixedField) {
        return $this->createQueryBuilder('reference_filter')
            ->andWhere('reference_filter.utilisateur = :user')
            ->andWhere('reference_filter.champFixe = :fixed_field')
            ->setMaxResults(1)
            ->setParameter('user', $user)
            ->setParameter('fixed_field', $fixedField)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByUserExceptFixedField(Utilisateur $user, string $fixedField) {

        return $this->createQueryBuilder('reference_filter')
            ->where('reference_filter.utilisateur = :user')
            ->andWhere('reference_filter.champFixe != :fixed_field OR reference_filter.champFixe IS NULL')
            ->setParameter('user', $user)
            ->setParameter('fixed_field', $fixedField)
            ->getQuery()
            ->execute();
    }
}
