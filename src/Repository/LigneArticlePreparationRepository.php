<?php

namespace App\Repository;

use App\Entity\LigneArticlePreparation;
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
}
