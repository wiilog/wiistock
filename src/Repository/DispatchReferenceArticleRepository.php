<?php

namespace App\Repository;

use App\Entity\DispatchReferenceArticle;
use Doctrine\ORM\EntityRepository;

/**
 * @method DispatchReferenceArticle|null find($id, $lockMode = null, $lockVersion = null)
 * @method DispatchReferenceArticle|null findOneBy(array $criteria, array $orderBy = null)
 * @method DispatchReferenceArticle[]    findAll()
 * @method DispatchReferenceArticle[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DispatchReferenceArticleRepository extends EntityRepository
{

    public function save(DispatchReferenceArticle $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DispatchReferenceArticle $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return DispatchReferenceArticle[] Returns an array of DispatchReferenceArticle objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('d.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?DispatchReferenceArticle
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
