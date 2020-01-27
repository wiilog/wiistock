<?php

namespace App\Repository;

use App\Entity\LigneArticle;
use App\Entity\LigneArticlePreparation;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method LigneArticlePreparation|null find($id, $lockMode = null, $lockVersion = null)
 * @method LigneArticlePreparation|null findOneBy(array $criteria, array $orderBy = null)
 * @method LigneArticlePreparation[]    findAll()
 * @method LigneArticlePreparation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LigneArticlePreparationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LigneArticlePreparation::class);
    }

    /**
     * @param ReferenceArticle $referenceArticle
     * @param Preparation $preparation
     * @return LigneArticlePreparation|null
     * @throws NonUniqueResultException
     */
    public function findOneByRefArticleAndPreparationAndToSplit($referenceArticle, Preparation $preparation)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT l
            FROM App\Entity\LigneArticlePreparation l
            WHERE l.reference = :referenceArticle AND l.preparation = :preparation AND l.toSplit = 1
            "
        )->setParameters([
            'referenceArticle' => $referenceArticle,
            'preparation' => $preparation
        ]);

        return $query->getOneOrNullResult();
    }

    /**
     * @param $referenceArticle
     * @param $preparation
     * @return LigneArticlePreparation
     * @throws NonUniqueResultException
     */
    public function findOneByRefArticleAndDemande($referenceArticle, $preparation)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT l
            FROM App\Entity\LigneArticlePreparation l
            WHERE l.reference = :referenceArticle AND l.preparation = :preparation
            "
        )->setParameters([
            'referenceArticle' => $referenceArticle,
            'preparation' => $preparation
        ]);

        return $query->getOneOrNullResult();
    }

    // /**
    //  * @return LigneArticlePreparation[] Returns an array of LigneArticlePreparation objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('l.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?LigneArticlePreparation
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
