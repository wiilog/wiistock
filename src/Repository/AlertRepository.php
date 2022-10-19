<?php

namespace App\Repository;

use App\Entity\Alert;
use App\Entity\Article;
use App\Entity\FiltreSup;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Helper\QueryBuilderHelper;
use DateTime;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;

/**
 * @method Alert|null find($id, $lockMode = null, $lockVersion = null)
 * @method Alert|null findOneBy(array $criteria, array $orderBy = null)
 * @method Alert[]    findAll()
 * @method Alert[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AlertRepository extends EntityRepository {

    public function findForReference($reference, $types) {
        if(!is_array($types)) {
            $types = [$types];
        }

        return $this->createQueryBuilder("a")
            ->where("a.reference = :reference")
            ->andWhere("a.type IN (:types)")
            ->setParameter("reference", $reference)
            ->setParameter("types", $types)
            ->getQuery()
            ->getResult();
    }

    public function findForArticle($article, int $type) {
        return $this->createQueryBuilder("a")
            ->where("a.article = :article")
            ->andWhere("a.type = :type")
            ->setParameter("article", $article)
            ->setParameter("type", $type)
            ->getQuery()
            ->getResult();
    }

    public function getAlertDataByParams(InputBag $params, array $filters, Utilisateur $user) {
        $queryBuilder = $this->createQueryBuilder("a");
        $exprBuilder = $queryBuilder->expr();

        $queryBuilder
            ->leftJoin("a.reference", "reference")
            ->leftJoin("a.article", "article");
        $visibilityGroup = $user->getVisibilityGroups();
        if (!$visibilityGroup->isEmpty()) {
            $queryBuilder
                ->leftJoin('article.articleFournisseur', 'join_article_supplierArticle')
                ->leftJoin('join_article_supplierArticle.referenceArticle', 'join_article_reference')
                ->leftJoin('reference.visibilityGroup', 'visibility_group')
                ->leftJoin('join_article_reference.visibilityGroup', 'join_article_reference_visibility_group')
                ->andWhere($exprBuilder->orX(
                    'visibility_group.id IN (:userVisibilityGroups)',
                    'join_article_reference_visibility_group.id IN (:userVisibilityGroups)',
                ))
                ->setParameter('userVisibilityGroups', Stream::from(
                    $visibilityGroup->toArray()
                )->map(fn(VisibilityGroup $visibilityGroup) => $visibilityGroup->getId())->toArray());
        }

        $total = QueryBuilderHelper::count($queryBuilder, "a");

        foreach($filters as $filter) {
            switch ($filter['field']) {
                case 'dateMin':
                    $queryBuilder->andWhere('a.date >= :dateMin')
                        ->setParameter('dateMin', $filter['value']. ' 00:00:00');
                    break;
                case 'dateMax':
                    $queryBuilder->andWhere('a.date <= :dateMax')
                        ->setParameter('dateMax', $filter['value']. ' 23:59:59');
                    break;
                case 'multipleTypes':
                    $types = explode(',', $filter['value']);
                    $types = Stream::from($types)
                        ->map(fn(string $type) => strtok($type, ':'))
                        ->toArray();
                    $queryBuilder
                        ->leftJoin('reference.type', 'filter_multipleTypes_reference_type')
                        ->leftJoin('article.articleFournisseur', 'filter_multipleTypes_article_supplierArticle')
                        ->leftJoin('filter_multipleTypes_article_supplierArticle.referenceArticle', 'filter_multipleTypes_article_reference')
                        ->leftJoin('filter_multipleTypes_article_reference.type', 'filter_multipleTypes_article_type')
                        ->andWhere($exprBuilder->orX(
                            'filter_multipleTypes_reference_type.id IN (:filter_multipleTypes_value)',
                            'filter_multipleTypes_article_type.id IN (:filter_multipleTypes_value)'
                        ))
                        ->setParameter('filter_multipleTypes_value', $types);
                    break;
                case 'alert':
                    $value = Alert::TYPE_LABELS_IDS[$filter['value']];
                    $queryBuilder->andWhere('a.type = :alert')
                        ->setParameter('alert', $value);
                    break;
                case FiltreSup::FIELD_MANAGERS:
                    $value = explode(',', $filter['value']);

                    $or = $queryBuilder->expr()->orX();
                    foreach($value as $user) {
                        $id = explode(":", $user)[0];
                        $or->add(":user_$id MEMBER OF reference.managers");
                        $or->add(":user_$id MEMBER OF articlera.managers");
                        $queryBuilder->setParameter("user_$id", $id);
                    }

                    $queryBuilder->andWhere($or)
                        ->leftJoin("article.articleFournisseur", "articleaf")
                        ->leftJoin("articleaf.referenceArticle", "articlera");
                    break;
            }
        }

        // prise en compte des paramètres issus du datatable
        if(!empty($params)) {
            if(!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if(!empty($search)) {
                    $queryBuilder
                        ->andWhere($queryBuilder->expr()->orX(
                            'reference.reference LIKE :value',
                            'reference.libelle LIKE :value',
                            'article.reference LIKE :value',
                            'article.label LIKE :value'
                        ))
                        ->setParameter('value', '%' . str_replace('_', '\_', $search) . '%');
                }
            }

            if(!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if(!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];

                    switch($column) {
                        case "label":
                            $queryBuilder->addSelect("COALESCE(article.label, reference.libelle) AS HIDDEN label")
                                ->orderBy("label", $order);
                            break;
                        case "reference":
                            $queryBuilder->addSelect("COALESCE(article.reference, reference.reference) AS HIDDEN stref")
                                ->orderBy("stref", $order);
                            break;
                        case "code":
                            $queryBuilder->addSelect("COALESCE(article.barCode, reference.barCode) AS HIDDEN code")
                                ->orderBy("code", $order);
                            break;
                        case "quantity":
                            $queryBuilder->orderBy('quantity', $order);
                            break;
                        case "quantityType":
                            $queryBuilder->orderBy("reference.typeQuantite", $order);
                            break;
                        case "securityThreshold":
                            $queryBuilder->orderBy('reference.limitSecurity', $order);
                            break;
                        case "warningThreshold":
                            $queryBuilder->orderBy('reference.limitWarning', $order);
                            break;
                        case "expiry":
                            $queryBuilder->orderBy('article.expiryDate', $order);
                            break;
                        default:
                            $queryBuilder->orderBy('a.' . $column, $order);
                            break;
                    }
                }
            }
        }

        $queryBuilder->groupBy('a.id')
            ->addSelect('COALESCE(reference.quantiteDisponible, article.quantite) AS quantity');

        $countFiltered = QueryBuilderHelper::count($queryBuilder, "a");

        if(!empty($params)) {
            if ($params->getInt('start')) $queryBuilder->setFirstResult($params->getInt('start'));
            if ($params->getInt('length')) $queryBuilder->setMaxResults($params->getInt('length'));
        }

        return [
            'data' => $queryBuilder->getQuery()->getResult(AbstractQuery::HYDRATE_OBJECT),
            'count' => $countFiltered,
            'total' => $total
        ];
    }

    public function countAll(): int {
        return $this->createQueryBuilder("a")
            ->select("COUNT(a)")
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAllActiveByParams(array $params): int {
        $qb = $this->createQueryBuilder("alert")
            ->select("COUNT(alert)")
            ->leftJoin("alert.reference","reference")
            ->leftJoin("reference.statut","refStatus")
            ->where("reference IS NULL OR refStatus.nom = :active")
            ->setParameter("active", ReferenceArticle::STATUT_ACTIF);

        if (isset($params['managers']) && !empty($params['managers'])) {
            $qb
                ->join('reference.managers', 'managers')
                ->andWhere('managers.id IN (:managers)')
                ->setParameter('managers', $params['managers']);
        }

        if (isset($params['referenceTypes']) && !empty($params['referenceTypes'])) {
            $qb
                ->join('reference.type', 'type')
                ->andWhere('type.id IN (:referenceTypes)')
                ->setParameter('referenceTypes', $params['referenceTypes']);
        }

        if (isset($params['user'])) {
            $user = $params['user'];
            $visibilityGroup = $user->getVisibilityGroups();
            if (!$visibilityGroup->isEmpty()) {
                $qb
                    ->leftJoin('reference.visibilityGroup', 'visibility_group')
                    ->andWhere('visibility_group.id IN (:userVisibilityGroups)')
                    ->setParameter('userVisibilityGroups', Stream::from(
                        $visibilityGroup->toArray()
                    )->map(fn(VisibilityGroup $visibilityGroup) => $visibilityGroup->getId())->toArray());
            }
        }
        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findNoLongerExpired() {
        $since = new DateTime("now");

        $qb = $this->createQueryBuilder("a");

        return $qb->join("a.article", "ar")
            ->join("ar.statut", "s")
            ->where("ar.id IS NOT NULL")
            ->andWhere("a.type = " . Alert::EXPIRY)
            ->andWhere($qb->expr()->orX(
                "ar.expiryDate > :since OR ar.expiryDate IS NULL",
                "s.nom IN (:inactives)"
            ))
            ->setParameter("since", $since)
            ->setParameter("inactives", [Article::STATUT_INACTIF])
            ->getQuery()
            ->getResult();
    }

    public function findByDates($dateMin, $dateMax) {

        $qb = $this->createQueryBuilder("alert");

        $qb
            ->where("alert.date BETWEEN :dateMin AND :dateMax")
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);

        return $qb
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string[] $statusCodeFilter
     */
    public function iterateBetween(DateTime $start,
                                   DateTime $end,
                                   Utilisateur $user,
                                   array $statusCodeFilter = []): iterable {
        $qb = $this->createQueryBuilder('alert');
        $exprBuilder = $qb->expr();
        $queryBuilder = $this->createQueryBuilder('alert');
        $queryBuilder
            ->leftJoin('alert.reference', 'join_reference')
            ->leftJoin('alert.article', 'join_article')
            ->leftJoin('join_article.articleFournisseur', 'join_article_supplierArticle')
            ->leftJoin('join_article_supplierArticle.referenceArticle', 'join_article_reference')
            ->andWhere($exprBuilder->between('alert.date',':start',':end'))
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        $visibilityGroup = $user->getVisibilityGroups();
        if (!$visibilityGroup->isEmpty()) {
            $queryBuilder
                ->leftJoin('join_reference.visibilityGroup', 'visibility_group')
                ->leftJoin('join_article_reference.visibilityGroup', 'join_article_reference_visibility_group')
                ->andWhere($exprBuilder->orX(
                    'visibility_group.id IN (:userVisibilityGroups)',
                    'join_article_reference_visibility_group.id IN (:userVisibilityGroups)',
                ))
                ->setParameter('userVisibilityGroups', Stream::from(
                    $visibilityGroup->toArray()
                )->map(fn(VisibilityGroup $visibilityGroup) => $visibilityGroup->getId())->toArray());
        }

        if (!empty($statusCodeFilter)) {
            $queryBuilder
                ->leftJoin('join_reference.statut', 'join_reference_status')
                ->leftJoin('join_article_reference.statut', 'join_article_reference_status')
                ->andWhere($exprBuilder->orX(
                    'join_reference_status.code IN (:statusCodes)',
                    'join_article_reference_status.code IN (:statusCodes)'
                ))
                ->setParameter('statusCodes', $statusCodeFilter);
        }

        return $queryBuilder
            ->getQuery()
            ->toIterable();
    }

}
