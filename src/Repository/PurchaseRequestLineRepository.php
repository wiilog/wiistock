<?php

namespace App\Repository;

use App\Entity\PurchaseRequest;
use App\Entity\PurchaseRequestLine;
use App\Entity\ReferenceArticle;
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
            ->addSelect('join_supplier.nom AS supplierName')
            ->join('purchaseRequestLine.reference', 'join_referenceArticle')
            ->join('purchaseRequestLine.purchaseRequest', 'join_purchaseRequest')
            ->leftjoin('purchaseRequestLine.supplier', 'join_supplier')
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

    public function findByReferenceArticleAndPurchaseStatus(ReferenceArticle $referenceArticle, array $statusStates, ?PurchaseRequest $ignored = null) {
        $queryBuilder = $this->createQueryBuilder('purchase_request_line');
        $query = $queryBuilder
            ->join('purchase_request_line.reference', 'reference_article')
            ->join('purchase_request_line.purchaseRequest', 'purchase_request')
            ->join('purchase_request.status', 'status')
            ->where('reference_article = :ref')
            ->andWhere('status.state IN (:statuses)')
            ->setParameters([
                'ref' => $referenceArticle,
                'statuses' => $statusStates
            ]);

        if ($ignored) {
            $query
                ->andWhere('purchase_request != :purchaseRequest')
                ->setParameter('purchaseRequest', $ignored);
        }
        return $query
            ->getQuery()
            ->getResult();
    }
}
