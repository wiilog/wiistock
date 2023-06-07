<?php

namespace App\Repository;

use App\Entity\Dispatch;
use App\Entity\DispatchPack;
use App\Entity\DispatchReferenceArticle;
use Doctrine\ORM\EntityRepository;
use WiiCommon\Helper\Stream;

/**
 * @method DispatchPack|null find($id, $lockMode = null, $lockVersion = null)
 * @method DispatchPack|null findOneBy(array $criteria, array $orderBy = null)
 * @method DispatchPack[]    findAll()
 * @method DispatchPack[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DispatchPackRepository extends EntityRepository {

    /**
     * @param int[] $dispatchIds
     * @return array
     */
    public function getMobilePacksFromDispatches(array $dispatchIds) {
        $queryBuilder = $this->createQueryBuilder('dispatch_pack');
        $queryBuilder
            ->select('dispatch_pack.id AS id')
            ->addSelect('pack.code AS code')
            ->addSelect('pack.comment AS comment')
            ->addSelect('nature.id AS natureId')
            ->addSelect('dispatch_pack.quantity AS quantity')
            ->addSelect('dispatch.id AS dispatchId')
            ->addSelect('packLastLocation.label AS lastLocation')
            ->addSelect('dispatch_pack.treated AS already_treated')
            ->addSelect('MIN(referenceArticle.reference) as reference')
            ->join('dispatch_pack.pack', 'pack')
            ->join('dispatch_pack.dispatch', 'dispatch')
            ->leftJoin('pack.nature', 'nature')
            ->leftJoin('dispatch_pack.dispatchReferenceArticles', 'dispatch_reference_articles')
            ->leftJoin('dispatch_reference_articles.referenceArticle', 'referenceArticle')
            ->leftJoin('pack.lastTracking', 'packLastTracking')
            ->leftJoin('packLastTracking.emplacement', 'packLastLocation')
            ->where('dispatch.id IN (:dispatchIds)')
            ->addGroupBy('dispatch_pack.id')
            ->addGroupBy('pack.code')
            ->addGroupBy('pack.comment')
            ->addGroupBy('nature.id')
            ->addGroupBy('dispatch_pack.quantity')
            ->addGroupBy('dispatch.id')
            ->addGroupBy('packLastLocation.label')
            ->addGroupBy('dispatch_pack.treated')
            ->setParameter('dispatchIds', $dispatchIds);
        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function getByDispatch(Dispatch $dispatch, array $params): array {
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 5;
        $search = $params['search'] ?? null;

        $queryBuilder = $this->createQueryBuilder('dispatch_pack');
        $exprBuilder = $queryBuilder->expr();

        $queryBuilder
            ->select('dispatch_pack.id AS id')

            ->addSelect('join_pack.id AS packId')
            ->addSelect('join_pack.code AS packCode')

            ->addSelect('join_locationLastDrop.label AS packLocation')
            ->addSelect('join_nature.label AS packNature')
            ->addSelect('join_nature.color AS packColor')

            ->addSelect('join_dispatchReferenceArticle.id AS dispatchReferenceArticleId')
            ->addSelect('join_referenceArticle.reference AS reference')
            ->addSelect('join_referenceArticle.barCode AS barCode')
            ->addSelect('join_dispatchReferenceArticle.quantity AS quantity')
            ->addSelect('join_dispatchReferenceArticle.batchNumber AS batchNumber')
            ->addSelect('join_dispatchReferenceArticle.sealingNumber AS sealingNumber')
            ->addSelect('join_dispatchReferenceArticle.serialNumber AS serialNumber')
            ->addSelect('join_dispatchReferenceArticle.comment AS comment')
            ->addSelect('join_referenceArticle.description AS description')
            ->addSelect('join_dispatchReferenceArticle.ADR AS ADR')

            ->leftJoin('dispatch_pack.pack', 'join_pack')
            ->leftJoin('join_pack.nature', 'join_nature')
            ->leftJoin('join_pack.lastDrop', 'join_lastDrop')
            ->leftJoin('join_lastDrop.emplacement', 'join_locationLastDrop')

            ->leftJoin('dispatch_pack.dispatchReferenceArticles', 'join_dispatchReferenceArticle')
            ->leftJoin('join_dispatchReferenceArticle.referenceArticle', 'join_referenceArticle')


            ->andWhere('dispatch_pack.dispatch = :dispatch')
            ->addOrderBy('IF(join_pack.id IS NULL, 0, 1)')
            ->addOrderBy('dispatch_pack.id')
            ->setParameter('dispatch', $dispatch);

        if (!empty($search)) {
            $queryBuilder
                ->andWhere($exprBuilder->orX(
                    'join_pack.code LIKE :search',
                    'join_locationLastDrop.label LIKE :search',
                    'join_project.code LIKE :search',
                    'join_referenceArticle.reference LIKE :search',
                ))
                ->setParameter('search', "%$search%");
        }

        $queryResult = $queryBuilder->getQuery()->getResult();

        $resultGroupedByLogisticUnit = Stream::from($queryResult)
            ->keymap(fn(array $row) => [$row["id"], $row], true);

        // Number of logistic units
        $logisticUnitCount = $resultGroupedByLogisticUnit->count();

        $resultGroupedByLogisticUnit->slice($start, $length);

        // get all attachments linked to references after slice the first array
        $referenceIds = Stream::from($resultGroupedByLogisticUnit)
            ->flatMap(fn (array $rows) => $rows)
            ->map(fn (array $reference) => $reference['dispatchReferenceArticleId'])
            ->filter()
            ->unique();
        if (!empty($referenceIds)) {
            $attachmentsResult = $this->getEntityManager()->createQueryBuilder()
                ->select('attachment.fullPath')
                ->addSelect('attachment.fileName')
                ->addSelect('attachment.originalName')
                ->addSelect('dispatchReferenceArticle.id AS dispatchReferenceArticleId')
                ->from(DispatchReferenceArticle::class, 'dispatchReferenceArticle')
                ->join('dispatchReferenceArticle.attachments', 'attachment')
                ->andWhere('dispatchReferenceArticle.id IN (:dispatchReferenceArticleIds)')
                ->setParameter('dispatchReferenceArticleIds', $referenceIds)
                ->getQuery()
                ->getResult();
            $attachmentsByReference = Stream::from($attachmentsResult)
                ->keymap(fn(array $row) => [$row['dispatchReferenceArticleId'], [
                    "fullPath" => $row["fullPath"],
                    "fileName" => $row["fileName"],
                    "originalName" => $row["originalName"],
                ]], true)
                ->toArray();
        }
        else {
            $attachmentsByReference = [];
        }

        $resultGroupedByLogisticUnit
            ->map(function(array $references, int $key) use ($attachmentsByReference) {
                if (!empty($references)) {
                    $packId = $references[0]["packId"] ?? null;
                    $packCode = $references[0]["packCode"] ?? null;
                    $packLocation = $references[0]["packLocation"] ?? null;
                    $packNature = $references[0]["packNature"] ?? null;
                    $packColor = $references[0]["packColor"] ?? null;
                }

                return [
                    "id" => $key,
                    "pack" => isset($packId)
                        ? [
                            "id" => $packId,
                            "code" => $packCode ?? null,
                            "location" => $packLocation ?? null,
                            "nature" => $packNature ?? null,
                            "color" => $packColor ?? null,
                        ]
                        : null,
                    "references" => Stream::from($references)
                        ->filterMap(fn(array $reference) => (
                            isset($reference["reference"])
                                ? [
                                    "id" => $reference["dispatchReferenceArticleId"],
                                    "reference" => $reference["reference"],
                                    "quantity" => $reference["quantity"],
                                    "batchNumber" => $reference["batchNumber"],
                                    "serialNumber" => $reference["serialNumber"],
                                    "sealingNumber" => $reference["sealingNumber"],
                                    "manufacturerCode" => $reference["description"] ? $reference["description"]["manufacturerCode"] : '',
                                    "length" => $reference["description"] ? $reference["description"]["length"] ?? '' : '',
                                    "width" => $reference["description"] ? $reference["description"]["width"] ?? '' : '',
                                    "heigth" => $reference["description"] ? $reference["description"]["height"] ?? '' : '',
                                    "volume" => $reference["description"] ? $reference["description"]["volume"] ?? '' : '',
                                    "weight" => $reference["description"] ? $reference["description"]["weight"] ?? '' : '',
                                    "ADR" => $reference["ADR"] ? 'Oui' : 'Non',
                                    "outFormatEquipment" => $reference["description"] && isset($reference["description"]["outFormatEquipment"]) ? 'Oui' : 'Non',
                                    "associatedDocumentTypes" => $reference["description"] ? $reference["description"]["associatedDocumentTypes"] ?? '' : '',
                                    "comment" => $reference["comment"],
                                    "attachments" => $attachmentsByReference[$reference["dispatchReferenceArticleId"]] ?? [],
                                ]
                                : null
                        ))
                        ->toArray()
                ];
            });

        return [
            "data" => $resultGroupedByLogisticUnit->values(),
            "total" => $logisticUnitCount ?? 0
        ];
    }
}
