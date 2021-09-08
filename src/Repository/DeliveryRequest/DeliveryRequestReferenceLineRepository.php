<?php

namespace App\Repository\DeliveryRequest;

use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

class DeliveryRequestReferenceLineRepository extends EntityRepository
{

    public function getQuantity($id)
    {
        return $this->createQueryBuilder('line')
            ->andWhere('line.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * @param $referenceArticle
     * @param $request
     * @return DeliveryRequestReferenceLine
     * @throws NonUniqueResultException
     */
    public function findOneByRefArticleAndDemande($referenceArticle, $request, $splitFilter = false)
    {
        $queryBuilder = $this->createQueryBuilder('line')
            ->andWhere('line.reference = :referenceArticle AND line.request = :request')
            ->setParameters([
                'referenceArticle' => $referenceArticle,
                'request' => $request
            ]);

        if ($splitFilter) {
            $queryBuilder
                ->andWhere('line.toSplit = 1');
        }

        return $queryBuilder
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param $demandes
     * @param bool $needAssoc
     * @return DeliveryRequestReferenceLine[]
     */
    public function findByDemandes($demandes, $needAssoc = false)
    {
        $queryBuilder = $this->createQueryBuilder('line');

        if ($needAssoc) {
            $queryBuilder->addSelect('demande.id AS demandeId');
        }

        $queryBuilder
            ->join('line.request' , 'demande')
            ->where('line.request IN (:demandes)')
            ->setParameter('demandes', $demandes );

        $result = $queryBuilder
            ->getQuery()
            ->execute();

        if ($needAssoc) {
            $result = array_reduce($result, function(array $carry, $current) {
                $ligneArticle =  $current[0];
                $demandeId = $current['demandeId'];

                if (!isset($carry[$demandeId])) {
                    $carry[$demandeId] = [];
                }

                $carry[$demandeId][] = $ligneArticle;
                return $carry;
            }, []);
        }
        return $result;
    }

    public function countByRefArticleDemande($referenceArticle, $request)
    {
        return $this->createQueryBuilder('line')
            ->select('COUNT(line)')
            ->where('line.reference = :referenceArticle AND line.request = :request')
            ->setMaxResults(1)
            ->setParameters([
                'referenceArticle' => $referenceArticle,
                'request' => $request
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }
}
