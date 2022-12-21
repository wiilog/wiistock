<?php

namespace App\Repository\DeliveryRequest;

use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use WiiCommon\Helper\Stream;

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
    public function findOneByRefArticleAndDemande($referenceArticle, $request)
    {
        $queryBuilder = $this->createQueryBuilder('line')
            ->andWhere('line.reference = :referenceArticle AND line.request = :request')
            ->setParameters([
                'referenceArticle' => $referenceArticle,
                'request' => $request
            ]);

        return $queryBuilder
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByRequests(array $requests): array
    {
        $queryBuilder = $this->createQueryBuilder('line')
            ->select('line')
            ->addSelect('request.id AS requestId');

        $result = $queryBuilder
            ->join('line.request' , 'request')
            ->where('request IN (:requests)')
            ->setParameter('requests', $requests)
            ->getQuery()
            ->execute();

        return Stream::from($result)
            ->keymap(fn(array $current) => [$current['requestId'], $current[0]], true)
            ->toArray();
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
