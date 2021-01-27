<?php

namespace App\Repository;

use App\Entity\LigneArticlePreparation;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method LigneArticlePreparation|null find($id, $lockMode = null, $lockVersion = null)
 * @method LigneArticlePreparation|null findOneBy(array $criteria, array $orderBy = null)
 * @method LigneArticlePreparation[]    findAll()
 * @method LigneArticlePreparation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LigneArticlePreparationRepository extends EntityRepository
{
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
