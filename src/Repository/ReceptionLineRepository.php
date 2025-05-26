<?php

namespace App\Repository;

use App\Entity\Emergency\StockEmergency;
use App\Entity\Reception;
use App\Entity\ReceptionLine;
use App\Entity\Tracking\Pack;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
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
            ->leftJoin('line.pack', 'join_pack')
            ->leftJoin('join_pack.lastOngoingDrop', 'join_lastOngoingDrop')
            ->leftJoin('join_lastOngoingDrop.emplacement', 'join_locationLastOngoingDrop')
            ->leftJoin('join_pack.project', 'join_project')
            ->leftJoin('line.receptionReferenceArticles', 'join_receptionReferenceArticle')
            ->leftJoin('join_receptionReferenceArticle.referenceArticle', 'join_referenceArticle')
            ->leftJoin('join_receptionReferenceArticle.stockEmergencies', 'stock_emergency')
            ->andWhere('line.reception = :reception')
            ->addOrderBy('IF(join_pack.id IS NULL, 0, 1)') // show receptionLine without pack first
            ->addOrderBy('line.id')
            ->setParameter('reception', $reception);

        if (!empty($search)) {
            $queryBuilder
                ->andWhere($exprBuilder->orX(
                    'join_pack.code LIKE :search',
                    'join_locationLastOngoingDrop.label LIKE :search',
                    'join_project.code LIKE :search',
                    'join_referenceArticle.reference LIKE :search',
                    'join_receptionReferenceArticle.commande LIKE :search',
                ))
                ->setParameter('search', "%$search%");
        }

        if ($paginationMode === "references") {
            $total = QueryBuilderHelper::count($queryBuilder, 'join_receptionReferenceArticle');
            $queryBuilder
                ->addOrderBy('join_referenceArticle.barCode')
                ->setFirstResult($start)
                ->setMaxResults($length);
        }

        $result = Stream::from($queryBuilder->getQuery()->getResult());

        if ($paginationMode === "units") {
            $total = $result->count();
            $result->slice($start, $length);
        }

        return [
            "data" => $result->values(),
            "total" => $total ?? 0
        ];
    }

    public function getForSelectFromReception(?string $term,
                                              ?int    $reception,
                                              array $options = []): array {
        $reference = $options['reference'] ?? null;
        $orderNumber = $options['order-number'] ?? null;
        $includeEmpty = $options['include-empty'] ?? false;

        $queryBuilder = $this->createQueryBuilder("reception_line")
            ->distinct()
            ->select("IF(pack.id IS NOT NULL, pack.id, -1) AS id")
            ->addSelect("IF(pack.code IS NOT NULL, pack.code, '&nbsp;') AS text")
            ->leftJoin(Pack::class, "pack", Join::WITH, "reception_line.pack = pack")
            ->join("reception_line.reception",  "reception")
            ->andWhere("reception.id = :reception")
            ->setParameter("reception", $reception)
            ->setMaxResults(100);

        if (!$includeEmpty) {
            $queryBuilder->andWhere('pack.id IS NOT NULL');
        }

        if (!$includeEmpty || $term) {
            $queryBuilder
                ->andWhere("pack.code LIKE :term")
                ->setParameter("term", "%$term%");
        }

        if ($orderNumber) {
            $queryBuilder
                ->join("reception_line.receptionReferenceArticles",  "receptionReferenceArticle")
                ->andWhere("receptionReferenceArticle.commande = :orderNumber")
                ->setParameter("orderNumber", $orderNumber);
        }

        if ($reference) {
            if (!$orderNumber) {
                $queryBuilder
                    ->join("reception_line.receptionReferenceArticles",  "receptionReferenceArticle");
            }
            $queryBuilder
                ->join("receptionReferenceArticle.referenceArticle",  "referenceArticle")
                ->andWhere("referenceArticle.reference = :reference")
                ->setParameter("reference", $reference);
        }

        return $queryBuilder
            ->getQuery()
            ->getArrayResult();
    }
}
