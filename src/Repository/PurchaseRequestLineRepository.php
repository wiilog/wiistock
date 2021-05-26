<?php

namespace App\Repository;

use App\Entity\PurchaseRequestLine;
use DateTime;
use Doctrine\ORM\EntityRepository;

/**
 * @method PurchaseRequestLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method PurchaseRequestLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method PurchaseRequestLine[]    findAll()
 * @method PurchaseRequestLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PurchaseRequestLineRepository extends EntityRepository {

    public function iterateByPurchaseRequest(DateTime $dateMin,
                                             DateTime $dateMax): iterable {
        return $this->createQueryBuilder('purchaseRequestLine')
            ->select('join_purchaseRequest.id AS purchaseRequestId')
            ->addSelect('join_referenceArticle.reference AS reference')
            ->addSelect('join_referenceArticle.barCode AS barcode')
            ->addSelect('join_referenceArticle.libelle AS label')
            ->join('purchaseRequestLine.reference', 'join_referenceArticle')
            ->join('purchaseRequestLine.purchaseRequest', 'join_purchaseRequest')
            ->where("join_purchaseRequest.creationDate BETWEEN :dateMin AND :dateMax")
            ->orderBy('join_purchaseRequest.creationDate', 'DESC')
            ->addOrderBy('join_purchaseRequest.id', 'DESC')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ])
            ->getQuery()
            ->toIterable();
    }
}
