<?php

namespace App\Repository;

use App\Entity\Pack;
use App\Entity\Reception;
use App\Entity\ReceptionLine;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;

/**
 * @method ReceptionLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReceptionLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReceptionLine[]    findAll()
 * @method ReceptionLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceptionLineRepository extends EntityRepository {
    public function getByReception(Reception $reception, InputBag $params): array {
        $queryBuilder = $this->createQueryBuilder('line');
        $exprBuilder = $queryBuilder->expr();

        $countTotal = QueryBuilderHelper::count($queryBuilder, 'line');
        $queryBuilder
            ->select('line.id AS id')
            ->addSelect('join_pack.id AS packId')
            ->addSelect('join_pack.code AS packCode')
            ->addSelect('join_referenceArticle.reference AS reference')
            ->addSelect('join_referenceArticle.libelle AS label')
            ->addSelect('join_referenceArticle.barCode AS barCode')
            ->addSelect('join_referenceArticle.isUrgent AS emergency')
            ->addSelect("GROUP_CONCAT(join_article.label SEPARATOR ', ') AS articles")
            ->addSelect('join_attachment.fullPath AS logo')
            ->leftJoin('line.pack', 'join_pack')
            ->leftJoin('line.receptionReferenceArticles', 'join_receptionReferenceArticle')
            ->leftJoin('join_receptionReferenceArticle.referenceArticle', 'join_referenceArticle')
            ->leftJoin('join_receptionReferenceArticle.articles', 'join_article')
            ->leftJoin('join_referenceArticle.type', 'join_type')
            ->leftJoin('join_type.logo', 'join_attachment')
            ->andWhere('line.reception = :reception')
            ->addOrderBy('IF(join_pack.id IS NULL, 0, 1)') // show receptionLine without pack first
            ->addOrderBy('line.id')
            ->addGroupBy('id')
            ->addGroupBy('packId')
            ->addGroupBy('packCode')
            ->addGroupBy('reference')
            ->addGroupBy('label')
            ->addGroupBy('barCode')
            ->addGroupBy('emergency')
            ->addGroupBy('logo')
            ->setParameter('reception', $reception);

        if (!empty($search)) {
            $queryBuilder
                ->leftJoin('join_pack.project', 'join_project')
                ->leftJoin('join_pack.lastDrop', 'join_lastDrop')
                ->leftJoin('join_lastDrop.emplacement', 'join_locationLastDrop')
                ->andWhere($exprBuilder->orX(
                    'join_pack.code LIKE :search',
                    'join_locationLastDrop.label LIKE :search',
                    'join_project.code LIKE :search',
                    'join_referenceArticle.reference LIKE :search',
                    'join_receptionReferenceArticle.commande LIKE :search',
                ))
                ->setParameter('search', "%$search%");
        }

        $countFiltered = QueryBuilderHelper::count($queryBuilder, 'line');

        if ($params->getInt('start')) $queryBuilder->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $queryBuilder->setMaxResults($params->getInt('length'));

        $queryResult = $queryBuilder->getQuery()->getResult();

        return [
            'total' => $countTotal,
            'count' => $countFiltered,
            'data' => $queryResult,
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
