<?php

namespace App\Repository;

use App\Entity\Reception;
use App\Entity\ReceptionLine;
use Doctrine\ORM\EntityRepository;
use WiiCommon\Helper\Stream;

/**
 * @method ReceptionLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReceptionLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReceptionLine[]    findAll()
 * @method ReceptionLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceptionLineRepository extends EntityRepository {
    public function getByReception(Reception $reception, array $params): array {
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 5;
        $paginationMode = $params['paginationMode'] ?? null;

        $query = $this->createQueryBuilder('line')
            ->select('line.id AS id')
            ->addSelect('join_pack.id AS packId')
            ->addSelect('join_pack.code AS packCode')
            ->addSelect('join_locationLastDrop.label AS packLocation')
            ->addSelect('join_project.code AS packProject')
            ->addSelect('join_nature.label AS packNature')
            ->addSelect('join_nature.color AS packColor')
            ->addSelect('join_receptionReferenceArticle.id AS referenceId')
            ->addSelect('join_referenceArticle.reference AS reference')
            ->addSelect('join_receptionReferenceArticle.commande AS orderNumber')
            ->addSelect('join_receptionReferenceArticle.quantiteAR AS quantityToReceive')
            ->addSelect('join_receptionReferenceArticle.quantite AS receivedQuantity')
            ->leftJoin('line.pack', 'join_pack')
            ->leftJoin('join_pack.nature', 'join_nature')
            ->leftJoin('join_pack.lastDrop', 'join_lastDrop')
            ->leftJoin('join_lastDrop.emplacement', 'join_locationLastDrop')
            ->leftJoin('join_pack.project', 'join_project')
            ->leftJoin('line.receptionReferenceArticles', 'join_receptionReferenceArticle')
            ->leftJoin('join_receptionReferenceArticle.referenceArticle', 'join_referenceArticle')
            ->andWhere('line.reception = :reception')
            ->setParameter('reception', $reception);


        if ($paginationMode === "references") {
            $query
                ->setFirstResult($start)
                ->setMaxResults($length);
            $queryResult = $query->getQuery()->getResult();
            $total = count($queryResult);
        }
        else {
            $queryResult = $query->getQuery()->getResult();
        }

        $result = Stream::from($queryResult)
            ->keymap(fn(array $row) => [$row["id"], $row], true)
            ->map(function(array $references, $key) {
                if (!empty($references)) {
                    $packId = $references[0]["packId"] ?? null;
                    $packCode = $references[0]["packCode"] ?? null;
                    $packLocation = $references[0]["packLocation"] ?? null;
                    $packProject = $references[0]["packProject"] ?? null;
                    $packNature = $references[0]["packNature"] ?? null;
                    $packColor = $references[0]["packColor"] ?? null;
                }
                return [
                    'pack' => isset($packId)
                        ? [
                            "lineId" => $key,
                            "packId" => $packId,
                            "code" => $packCode ?? null,
                            "location" => $packLocation ?? null,
                            "project" => $packProject ?? null,
                            "nature" => $packNature ?? null,
                            "color" => $packColor ?? null,
                        ]
                        : null,
                    'references' => Stream::from($references)
                        ->filter(fn(array $reference) => (
                            isset($reference["reference"])
                                ? [
                                    "id" => $reference["referenceId"],
                                    "reference" => $reference["reference"],
                                    "orderNumber" => $reference["orderNumber"],
                                    "quantityToReceive" => $reference["quantityToReceive"],
                                    "receivedQuantity" => $reference["receivedQuantity"]
                                ]
                                : null
                        ))
                        ->toArray()
                ];
            });

        if ($paginationMode === "units") {
            $total = $result->count();
            $result->slice($start, $length);
        }

        return [
            "data" => $result->values(),
            "total" => $total ?? 0
        ];
    }
}
