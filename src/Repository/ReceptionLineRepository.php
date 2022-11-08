<?php

namespace App\Repository;

use App\Entity\Reception;
use App\Entity\ReceptionLine;
use App\Helper\QueryBuilderHelper;
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
        $search = $params['search'] ?? null;
        $paginationMode = $params['paginationMode'] ?? null;

        $queryBuilder = $this->createQueryBuilder('line');
        $exprBuilder = $queryBuilder->expr();

        $queryBuilder
            ->select('line.id AS id')
            ->addSelect('join_pack.id AS packId')
            ->addSelect('join_pack.code AS packCode')
            ->addSelect('join_locationLastDrop.label AS packLocation')
            ->addSelect('join_project.code AS packProject')
            ->addSelect('join_nature.label AS packNature')
            ->addSelect('join_nature.color AS packColor')
            ->addSelect('join_receptionReferenceArticle.id AS referenceId')
            ->addSelect('join_referenceArticle.reference AS reference')
            ->addSelect('join_referenceArticle.typeQuantite AS quantityType')
            ->addSelect('join_referenceArticle.barCode AS barCode')
            ->addSelect('join_receptionReferenceArticle.commande AS orderNumber')
            ->addSelect('join_receptionReferenceArticle.quantiteAR AS quantityToReceive')
            ->addSelect('join_receptionReferenceArticle.quantite AS receivedQuantity')
            ->addSelect('join_receptionReferenceArticle.emergencyTriggered AS emergency')
            ->addSelect('join_receptionReferenceArticle.emergencyComment AS comment')
            ->leftJoin('line.pack', 'join_pack')
            ->leftJoin('join_pack.nature', 'join_nature')
            ->leftJoin('join_pack.lastDrop', 'join_lastDrop')
            ->leftJoin('join_lastDrop.emplacement', 'join_locationLastDrop')
            ->leftJoin('join_pack.project', 'join_project')
            ->leftJoin('line.receptionReferenceArticles', 'join_receptionReferenceArticle')
            ->leftJoin('join_receptionReferenceArticle.referenceArticle', 'join_referenceArticle')
            ->andWhere('line.reception = :reception')
            ->addOrderBy('IF(join_pack.id IS NULL, 0, 1)') // show receptionLine without pack first
            ->addOrderBy('line.id')
            ->setParameter('reception', $reception);

        if (!empty($search)) {
            $queryBuilder
                ->andWhere($exprBuilder->orX(
                    'join_pack.code LIKE :search',
                    'join_locationLastDrop.label LIKE :search',
                    'join_project.code LIKE :search',
                    'join_referenceArticle.reference LIKE :search',
                    'join_receptionReferenceArticle.commande LIKE :search',
                ))
                ->setParameter('search', "%$search%");
        }

        if ($paginationMode === "references") {
            $total = QueryBuilderHelper::count($queryBuilder, 'join_receptionReferenceArticle');
            $queryBuilder
                ->setFirstResult($start)
                ->setMaxResults($length);
        }

        $queryResult = $queryBuilder->getQuery()->getResult();

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
                    "id" => $key,
                    "pack" => isset($packId)
                        ? [
                            "id" => $packId,
                            "code" => $packCode ?? null,
                            "location" => $packLocation ?? null,
                            "project" => $packProject ?? null,
                            "nature" => $packNature ?? null,
                            "color" => $packColor ?? null,
                        ]
                        : null,
                    "references" => Stream::from($references)
                        ->filterMap(fn(array $reference) => (
                            isset($reference["reference"])
                                ? [
                                    "id" => $reference["referenceId"],
                                    "reference" => $reference["reference"],
                                    "orderNumber" => $reference["orderNumber"],
                                    "quantityToReceive" => $reference["quantityToReceive"],
                                    "receivedQuantity" => $reference["receivedQuantity"],
                                    "emergency" => $reference["emergency"],
                                    "comment" => $reference["comment"],
                                    "quantityType" => $reference["quantityType"],
                                    "barCode" => $reference["barCode"],
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
