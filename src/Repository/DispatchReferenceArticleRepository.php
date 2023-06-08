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

    public function getForMobile(array $dispatchIds): array {
        if (empty($dispatchIds)) {
            return [];
        }
        return $this->createQueryBuilder('dispatch_reference_article')
            ->select('reference_article.reference AS reference')
            ->addSelect('dispatch_reference_article.quantity AS quantity')
            ->addSelect('dispatch_pack.id AS dispatchPackId')
            ->addSelect('JSON_UNQUOTE(JSON_EXTRACT(reference_article.description, \'$."outFormatEquipment"\'))) AS outFormatEquipment')
            ->addSelect('JSON_UNQUOTE(JSON_EXTRACT(reference_article.description, \'$."manufacturerCode"\'))) AS manufacturerCode')
            ->addSelect('JSON_UNQUOTE(JSON_EXTRACT(reference_article.description, \'$."width"\'))) AS width')
            ->addSelect('JSON_UNQUOTE(JSON_EXTRACT(reference_article.description, \'$."height"\'))) AS height')
            ->addSelect('JSON_UNQUOTE(JSON_EXTRACT(reference_article.description, \'$."length"\'))) AS length')
            ->addSelect('JSON_UNQUOTE(JSON_EXTRACT(reference_article.description, \'$."weight"\'))) AS weight')
            ->addSelect('JSON_UNQUOTE(JSON_EXTRACT(reference_article.description, \'$."volume"\'))) AS volume')
            ->addSelect('JSON_UNQUOTE(JSON_EXTRACT(reference_article.description, \'$."associatedDocumentTypes"\'))) AS associatedDocumentTypes')
            ->addSelect('dispatch_reference_article.sealingNumber AS sealingNumber')
            ->addSelect('dispatch_reference_article.serialNumber AS serialNumber')
            ->addSelect('dispatch_reference_article.batchNumber AS batchNumber')
            ->addSelect('dispatch_reference_article.ADR AS adr')
            ->addSelect('dispatch_reference_article.comment AS comment')
            ->join('dispatch_reference_article.referenceArticle', 'reference_article')
            ->join('dispatch_reference_article.dispatchPack', 'dispatch_pack')
            ->andWhere('dispatch_reference_article.dispatch IN (:dispatchIds)')
            ->setParameter('dispatchIds', $dispatchIds)
            ->getQuery()
            ->getResult();
    }
}
