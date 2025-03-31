<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\MouvementStock;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Service\FieldModesService;
use App\Service\SleepingStockPlanService;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr\Join;
use Exception;
use Generator;
use RuntimeException;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;

/**
 * @method MouvementStock|null find($id, $lockMode = null, $lockVersion = null)
 * @method MouvementStock|null findOneBy(array $criteria, array $orderBy = null)
 * @method MouvementStock[]    findAll()
 * @method MouvementStock[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MouvementStockRepository extends EntityRepository {
    private const DtToDbLabels = [
        'date' => 'date',
        'refArticle' => 'refArticle',
        'quantite' => 'quantity',
        'origine' => 'emplacementFrom',
        'destination' => 'emplacementTo',
        'type' => 'type',
        'operateur' => 'user',
        'barCode' => 'barCode',
        'unitPrice' => 'unitPrice',
    ];

    public function countByLocation(Emplacement $location): int {
        return $this->createQueryBuilder('stock_movement')
            ->select('COUNT(stock_movement.id)')
            ->andWhere('
                stock_movement.emplacementFrom = :location
                OR stock_movement.emplacementTo = :location
            ')
            ->setParameter('location', $location)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAll()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(m)
            FROM App\Entity\MouvementStock m"
        );
        return $query->getSingleScalarResult();
    }

    /**
     * @param Preparation $preparation
     * @return MouvementStock[]
     */
    public function findByPreparation($preparation)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT m
            FROM App\Entity\MouvementStock m
            WHERE m.preparationOrder = :preparation"
        )->setParameter('preparation', $preparation);

        return $query->execute();
    }

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return Generator
     */
    public function iterateByDates(DateTime $dateMin, DateTime $dateMax): Generator
    {
        $dateMax = $dateMax->format('Y-m-d H:i:s');
        $dateMin = $dateMin->format('Y-m-d H:i:s');

        $iterator = $this->createQueryBuilder('mouvementStock')
            ->select('mouvementStock.date as date')
            ->addSelect('preparation.numero as preparationOrder')
            ->addSelect('livraison.numero as livraisonOrder')
            ->addSelect('collecte.numero as collectOrder')
            ->addSelect('reception.orderNumber as receptionOrder')
            ->addSelect('article.barCode as articleBarCode')
            ->addSelect('(CASE WHEN refArticle.id IS NOT NULL THEN refArticle.reference ELSE article_referenceArticle.reference END) as refArticleRef')
            ->addSelect('(CASE WHEN refArticle.id IS NOT NULL THEN refArticle.barCode ELSE article_referenceArticle.barCode END) as refArticleBarCode')
            ->addSelect('mouvementStock.quantity as quantity')
            ->addSelect('emplacementFrom.label as originEmpl')
            ->addSelect('destination.label as destinationEmpl')
            ->addSelect('mouvementStock.type as type')
            ->addSelect('user.username as operator')
            ->addSelect('mouvementStock.unitPrice AS unitPrice')
            ->addSelect('mouvementStock.comment AS comment')
            ->leftJoin('mouvementStock.preparationOrder','preparation')
            ->leftJoin('mouvementStock.livraisonOrder','livraison')
            ->leftJoin('mouvementStock.collecteOrder','collecte')
            ->leftJoin('mouvementStock.receptionOrder','reception')
            ->leftJoin('mouvementStock.article','article')
            ->leftJoin('mouvementStock.refArticle','refArticle')
            ->leftJoin('article.articleFournisseur','article_articleFournisseur')
            ->leftJoin('article_articleFournisseur.referenceArticle','article_referenceArticle')
            ->leftJoin('mouvementStock.emplacementFrom','emplacementFrom')
            ->leftJoin('mouvementStock.emplacementTo','destination')
            ->leftJoin('mouvementStock.user','user')
            ->where('mouvementStock.date BETWEEN :dateMin AND :dateMax')
            ->setParameter('dateMin' , $dateMin)
            ->setParameter('dateMax' , $dateMax)
            ->getQuery()
            ->iterate(null, Query::HYDRATE_ARRAY);

        foreach($iterator as $item) {
            // $item [index => movement]
            yield array_pop($item);
        }

    }

    /**
     * @param string[] $types
     */
    public function countByTypes(array $types)
    {
        return $this->createQueryBuilder('stock_movement')
            ->select('COUNT(stock_movement)')
            ->andWhere('stock_movement.type IN (:types)')
            ->setParameter('types', $types)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTotalEntryPriceRefArticle($dateDebut = '', $dateFin = '')
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('SUM(m.quantity * ra.prixUnitaire)')
            ->from('App\Entity\MouvementStock', 'm')
            ->join('m.refArticle', 'ra');

        if ($dateDebut == '' && $dateFin == '') {
            $qb
                ->where('m.type = :entreeInv')
                ->setParameter('entreeInv', MouvementStock::TYPE_INVENTAIRE_ENTREE);
        } else if (!empty($dateDebut) && $dateFin == '') {
            $qb
                ->where('m.type = :entreeInv AND m.date > :dateDebut')
                ->setParameters([
                    'entreeInv' => MouvementStock::TYPE_INVENTAIRE_ENTREE,
                    'dateDebut' => $dateDebut
                ]);
        } else if (!empty($dateDebut) && !empty($dateFin)) {
            $qb
                ->where('m.type = :entreeInv AND m.date BETWEEN :dateDebut AND :dateFin')
                ->setParameters([
                    'entreeInv' => MouvementStock::TYPE_INVENTAIRE_ENTREE,
                    'dateDebut' => $dateDebut,
                    'dateFin' => $dateFin
                ]);
        }
        $query = $qb->getQuery();
        return $query->getSingleScalarResult();
    }

    public function countTotalExitPriceRefArticle($dateDebut = '', $dateFin = '')
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('SUM(m.quantity * ra.prixUnitaire)')
            ->from('App\Entity\MouvementStock', 'm')
            ->join('m.refArticle', 'ra');

        if ($dateDebut == '' && $dateFin == '') {
            $qb
                ->where('m.type = :sortieInv')
                ->setParameter('sortieInv', MouvementStock::TYPE_INVENTAIRE_SORTIE);
        } else if (!empty($dateDebut) && $dateFin == '') {
            $qb
                ->where('m.type = :sortieInv AND m.date > :dateDebut')
                ->setParameters(['sortieInv' => MouvementStock::TYPE_INVENTAIRE_SORTIE,
                    'dateDebut' => $dateDebut]);
        } else if (!empty($dateDebut) && !empty($dateFin)) {
            $qb
                ->where('m.type = :sortieInv AND m.date BETWEEN :dateDebut AND :dateFin')
                ->setParameters(['sortieInv' => MouvementStock::TYPE_INVENTAIRE_SORTIE,
                    'dateDebut' => $dateDebut,
                    'dateFin' => $dateFin]);
        }
        $query = $qb->getQuery();
        return $query->getSingleScalarResult();
    }

    public function countTotalEntryPriceArticle($dateDebut = '', $dateFin = '')
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('SUM(m.quantity * a.prixUnitaire)')
            ->from('App\Entity\MouvementStock', 'm')
            ->join('m.article', 'a');

        if ($dateDebut == '' && $dateFin == '') {
            $qb
                ->where('m.type = :entreeInv')
                ->setParameter('entreeInv', MouvementStock::TYPE_INVENTAIRE_ENTREE);
        } else if (!empty($dateDebut) && $dateFin == '') {
            $qb
                ->where('m.type = :entreeInv AND m.date > :dateDebut')
                ->setParameters(['entreeInv' => MouvementStock::TYPE_INVENTAIRE_ENTREE,
                    'dateDebut' => $dateDebut]);
        } else if (!empty($dateDebut) && !empty($dateFin)) {
            $qb
                ->where('m.type = :entreeInv AND m.date BETWEEN :dateDebut AND :dateFin')
                ->setParameters(['entreeInv' => MouvementStock::TYPE_INVENTAIRE_ENTREE,
                    'dateDebut' => $dateDebut,
                    'dateFin' => $dateFin]);
        }
        $query = $qb->getQuery();
        return $query->getSingleScalarResult();
    }

    public function countTotalExitPriceArticle($dateDebut = '', $dateFin = '')
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('SUM(m.quantity * a.prixUnitaire)')
            ->from('App\Entity\MouvementStock', 'm')
            ->join('m.article', 'a');

        if ($dateDebut == '' && $dateFin == '') {
            $qb
                ->where('m.type = :sortieInv')
                ->setParameter('sortieInv', MouvementStock::TYPE_INVENTAIRE_SORTIE);
        } else if (!empty($dateDebut) && $dateFin == '') {
            $qb
                ->where('m.type = :sortieInv AND m.date > :dateDebut')
                ->setParameters(['sortieInv' => MouvementStock::TYPE_INVENTAIRE_SORTIE,
                    'dateDebut' => $dateDebut]);
        } else if (!empty($dateDebut) && !empty($dateFin)) {
            $qb
                ->where('m.type = :sortieInv AND m.date BETWEEN :dateDebut AND :dateFin')
                ->setParameters(['sortieInv' => MouvementStock::TYPE_INVENTAIRE_SORTIE,
                    'dateDebut' => $dateDebut,
                    'dateFin' => $dateFin]);
        }
        $query = $qb->getQuery();
        return $query->getSingleScalarResult();
    }


    /**
     * @param ReferenceArticle $referenceArticle
     * @return MouvementStock[]
     */
    public function findByRef(ReferenceArticle $referenceArticle)
    {
        $queryBuilder = $this->createQueryBuilder('mouvementStock');

        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
            $queryBuilder->andWhere('mouvementStock.refArticle = :refArticle');
        }
        else if ($referenceArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE) {
            $queryBuilder
                ->join('mouvementStock.article', 'article')
                ->join('article.articleFournisseur', 'articleFournisseur')
                ->andWhere('articleFournisseur.referenceArticle = :refArticle');
        }

        $queryBuilder->setParameter('refArticle', $referenceArticle);

        return $queryBuilder
            ->getQuery()
            ->execute();
    }

    /**
     * @param $idArt
     * @param $idPrep
     * @return MouvementStock | null
     * @throws NonUniqueResultException
     */
    public function findByArtAndPrepa($idArt, $idPrep)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT m
            FROM App\Entity\MouvementStock m
            WHERE m.article = :id AND m.preparationOrder = :idP"
        )->setParameters([
            'id' => $idArt,
            'idP' => $idPrep
        ]);

        return $query->getOneOrNullResult();
    }

    /**
     * @param array|null $params
     * @param array|null $filters
     * @return array
     * @throws Exception
     */
    public function findByParamsAndFilters(InputBag          $params,
                                           array             $filters,
                                           FieldModesService $fieldModesService,
                                           Utilisateur       $user,)
    {
        $queryBuilder = $this->createQueryBuilder('stock_movement');
        $exprBuilder = $queryBuilder->expr();
        $visibilityGroup = $user->getVisibilityGroups();
        if (!$visibilityGroup->isEmpty()) {
            $queryBuilder
                ->leftJoin('stock_movement.refArticle', 'join_refArticle')
                ->leftJoin('stock_movement.article', 'join_article')
                ->leftJoin('join_article.articleFournisseur', 'join_article_supplierArticle')
                ->leftJoin('join_article_supplierArticle.referenceArticle', 'join_article_refArticle')
                ->leftJoin('join_refArticle.visibilityGroup', 'visibility_group')
                ->leftJoin('join_article_refArticle.visibilityGroup', 'join_article_reference_visibility_group')
                ->andWhere($exprBuilder->orX(
                    'visibility_group.id IN (:userVisibilityGroups)',
                    'join_article_reference_visibility_group.id IN (:userVisibilityGroups)',
                ))
                ->setParameter('userVisibilityGroups', Stream::from(
                    $visibilityGroup->toArray()
                )->map(fn(VisibilityGroup $visibilityGroup) => $visibilityGroup->getId())->toArray());
        }

        $countTotal = $this->countAll();
        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statut':
                    $types = explode(',', $filter['value']);
                    $typeIds = array_map(function ($type) {
                        $splitted = explode(':', $type);
                        return $splitted[1] ?? $type;
                    }, $types);
                    $queryBuilder
                        ->andWhere('stock_movement.type in (:typeIds)')
                        ->setParameter('typeIds', $typeIds, Connection::PARAM_STR_ARRAY);
                    break;
                case 'emplacement':
                    $value = explode(':', $filter['value']);
                    $queryBuilder
                        ->leftJoin('stock_movement.emplacementFrom', 'ef')
                        ->leftJoin('stock_movement.emplacementTo', 'et')
                        ->andWhere('ef.label = :location OR et.label = :location')
                        ->setParameter('location', $value[1] ?? $filter['value']);
                    break;
                case 'utilisateurs':
                    $value = explode(',', $filter['value']);
                    $queryBuilder
                        ->join('stock_movement.user', 'u')
                        ->andWhere("u.id in (:userId)")
                        ->setParameter('userId', $value);
                    break;
                case 'dateMin':
                    $queryBuilder->andWhere('stock_movement.date >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $queryBuilder->andWhere('stock_movement.date <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
            }
        }
        //Filter search
        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $conditions = [
                        "date" => "DATE_FORMAT(stock_movement.date, '%d/%m/%Y') LIKE :search_value",
                        "from" => null,
                        "barCode" => "(search_article.barCode LIKE :search_value OR search_reference_article.barCode LIKE :search_value)",
                        "refArticle" => "(search_reference_article.reference LIKE :search_value OR search_article_reference_article.reference LIKE :search_value)",
                        "quantity" => null,
                        "origin" => "search_location_origin.label LIKE :search_value",
                        "destination" => "search_location_destination.label LIKE :search_value",
                        "type" => "stock_movement.type LIKE :search_value",
                        "operator" => "search_operator.username LIKE :search_value",
                        "unitPrice" => null,
                        "comment" => null,
                    ];

                    $fieldModesService->bindSearchableColumns($conditions, 'stockMovement', $queryBuilder, $user, $search);
                    $queryBuilder
                        ->leftJoin('stock_movement.refArticle', 'search_reference_article')
                        ->leftJoin('stock_movement.article', 'search_article')
                        ->leftJoin('search_article.articleFournisseur', 'search_supplier_article')
                        ->leftJoin('search_supplier_article.referenceArticle', 'search_article_reference_article')
                        ->leftJoin('stock_movement.emplacementFrom', 'search_location_origin')
                        ->leftJoin('stock_movement.emplacementTo', 'search_location_destination')
                        ->leftJoin('stock_movement.user', 'search_operator');
                }
            }
            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = self::DtToDbLabels[$params->all('columns')[$params->all('order')[0]['column']]['data']];

                    if ($column === 'refArticle') {
                        $queryBuilder
                            ->leftJoin('stock_movement.refArticle', 'ra2')
                            ->orderBy('ra2.reference', $order);
                    } else if ($column === 'emplacementFrom') {
                        $queryBuilder
                            ->leftJoin('stock_movement.emplacementFrom', 'ef2')
                            ->orderBy('ef2.label', $order);
                    } else if ($column === 'emplacementTo') {
                        $queryBuilder
                            ->leftJoin('stock_movement.emplacementTo', 'et2')
                            ->orderBy('et2.label', $order);
                    } else if ($column === 'user') {
                        $queryBuilder
                            ->leftJoin('stock_movement.user', 'u2')
                            ->orderBy('u2.username', $order);
                    } else if ($column === 'barCode') {
                        $queryBuilder

                            ->leftJoin('stock_movement.article','articleSort')
                            ->leftJoin('stock_movement.refArticle', 'raSort')
                            ->addOrderBy('raSort.barCode', $order)
                            ->addOrderBy('articleSort.barCode', $order);
                    } else {
                        $queryBuilder
                            ->orderBy('stock_movement.' . $column, $order)
                            ->addOrderBy('stock_movement.id', $order);
                    }
                }
            }
        }
        $queryBuilder
            ->select('count(stock_movement)');
        // compte éléments filtrés
        $countFiltered = $queryBuilder->getQuery()->getSingleScalarResult();
        $queryBuilder
            ->select('stock_movement');

        if ($params->getInt('start')) $queryBuilder->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $queryBuilder->setMaxResults($params->getInt('length'));

        $query = $queryBuilder->getQuery();

        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    public function getMaxMovementDateForReferenceArticleQuery(string $referenceArticleAlias, ?string $movementAlias = null ): ?string {
        $movementAlias ??= "movement_" . uniqid();
        return $this->createQueryBuilder($movementAlias)
            ->select("MAX($movementAlias.date)")
            ->where("$movementAlias.refArticle = $referenceArticleAlias")
            ->getDQL();
    }


    /**
     * @return array{
     *   "countTotal": int,
     *   "referenceArticles": array<
     *     array{
     *       "entity": string,
     *       "id": int,
     *       "reference": string,
     *       "label": string,
     *       "quantityStock": int,
     *       "lastMovementDate": DateTime,
     *       "maxStorageTime": int,
     *       "maxStorageDate": DateTime,
     *       "isSleeping": bool
     *     }
     *   >
     * }
     */
    public function findForSleepingStock(Utilisateur              $user,
                                         int                      $maxResults,
                                         SleepingStockPlanService $sleepingStockPlanService,
                                         ?Type                    $type = null): array {
        $queryBuilder = $this->createQueryBuilder('movement');

        $expr = $queryBuilder->expr();

        $queryBuilder->distinct()
            ->select("movement.id AS id")
            ->addSelect("reference_article.id AS referenceArticleId")
            ->addSelect("article.id AS articleId")
            ->addSelect("COUNT_OVER(movement.id) AS __query_count")
            ->addSelect("reference_article.reference AS referenceReference")
            ->addSelect("article_reference_article.reference AS articleReference")
            ->addSelect("reference_article.libelle AS referenceLabel")
            ->addSelect("article.label AS articleLabel")
            ->addSelect("reference_article.barCode AS referenceBarCode")
            ->addSelect("article.barCode AS articleBarCode")
            ->addSelect("reference_article.quantiteDisponible AS referenceQuantityStock")
            ->addSelect("article.quantite AS articleQuantityStock")
            ->addSelect("movement.date AS lastMovementDate")
            ->addSelect("sleeping_stock_plan.maxStorageTime AS maxStorageTime")
            ->addSelect("DATE_ADD(movement.date, sleeping_stock_plan.maxStorageTime, 'second') AS maxStorageDate")
            ->andWhere(
                $expr->orX(
                    $expr->isMemberOf(":user", "reference_article.managers"),
                    $expr->isMemberOf(":user", "article_reference_article.managers"),
                )
            )
            ->leftJoin(ReferenceArticle::class, 'reference_article', Join::WITH, 'reference_article.lastMovement = movement')
            ->leftJoin(Article::class, 'article', Join::WITH, 'article.lastMovement = movement')
            ->leftJoin("article.articleFournisseur", "articles_fournisseur")
            ->leftJoin("articles_fournisseur.referenceArticle", "article_reference_article")
            ->setParameter("user", $user)
            ->setParameter("quantityTypeReference", ReferenceArticle::QUANTITY_TYPE_REFERENCE)
        ;

        $sleepingStockPlanService->findSleepingStock(
            $queryBuilder,
            "sleeping_stock_plan",
            "type",
            "movement",
            "reference_article",
            "article",
            $type
        );;

        $queryResult = $queryBuilder
            ->setMaxResults($maxResults)
            ->getQuery()
            ->getResult();

        $countTotal = $queryResult[0]["__query_count"] ?? 0;

        $now = new DateTime();
        $data = Stream::from($queryResult)
            ->map(function ($item) use ($now) {
                $maxStorageDate = new DateTime($item["maxStorageDate"]);
                return [
                    "entity" => match (true) {
                        isset($item["referenceArticleId"]) => ReferenceArticle::class,
                        isset($item["articleId"]) => Article::class,
                        default => throw new RuntimeException("Unknown entity, invalid id"),
                    },
                    "id" => $item["referenceArticleId"] ?? $item["articleId"],
                    "reference" => $item["referenceReference"] ?? $item["articleReference"],
                    "label" => $item["referenceLabel"] ?? $item["articleLabel"],
                    "barCode" => $item["referenceBarCode"] ?? $item["articleBarCode"],
                    "quantityStock" => $item["referenceQuantityStock"] ?? $item["articleQuantityStock"],
                    "lastMovementDate" => $item["lastMovementDate"],
                    "maxStorageTime" => $item["maxStorageTime"],
                    "maxStorageDate" => $maxStorageDate,
                    "isSleeping" => $now > $maxStorageDate,
                ];
            })
            ->toArray();

        return [
            "countTotal" => $countTotal,
            "referenceArticles" => $data,
        ];
    }
}
