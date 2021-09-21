<?php

namespace App\Repository\PreparationOrder;

use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use Doctrine\ORM\EntityRepository;

/**
 * @method PreparationOrderReferenceLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method PreparationOrderReferenceLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method PreparationOrderReferenceLine[]    findAll()
 * @method PreparationOrderReferenceLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PreparationOrderReferenceLineRepository extends EntityRepository
{
    public function findOneByRefArticleAndDemande($referenceArticle, $preparation): ?PreparationOrderReferenceLine
    {
        return $this->createQueryBuilder('line')
            ->andWhere('line.reference = :referenceArticle AND line.preparation = :preparation')
            ->setMaxResults(1)
            ->setParameters([
                'referenceArticle' => $referenceArticle,
                'preparation' => $preparation
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }
}
