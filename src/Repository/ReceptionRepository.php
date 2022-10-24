<?php

namespace App\Repository;

use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Helper\QueryBuilderHelper;
use App\Service\VisibleColumnService;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;

/**
 * @method Reception|null find($id, $lockMode = null, $lockVersion = null)
 * @method Reception|null findOneBy(array $criteria, array $orderBy = null)
 * @method Reception[]    findAll()
 * @method Reception[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceptionRepository extends EntityRepository
{

    private const DtToDbLabels = [
        'Date' => 'date',
        'DateFin' => 'dateFinReception',
        'Commentaire' => 'commentaire',
        'Statut' => 'statut',
        'Fournisseur' => 'fournisseur',
        'emergency' => 'emergency',
        'storageLocation' => 'storageLocation',
        'urgence' => 'emergencyTriggered',
        'dateAttendue' =>'dateAttendue'
    ];

    public function getLastNumberByDate(string $date): ?string {
        $result = $this->createQueryBuilder('reception')
            ->select('reception.number AS number')
            ->where('reception.number LIKE :value')
            ->orderBy('reception.date', 'DESC')
            ->addOrderBy('reception.number', 'DESC')
            ->addOrderBy('reception.id', 'DESC')
            ->setParameter('value', Reception::NUMBER_PREFIX . $date . '%')
            ->getQuery()
            ->execute();
        return $result ? $result[0]['number'] : null;
    }

	public function countByUser(Utilisateur $user): int
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT COUNT(r)
            FROM App\Entity\Reception r
            WHERE r.utilisateur = :user"
		)->setParameter('user', $user);

		return $query->getSingleScalarResult();
	}

    /**
     * @param ReferenceArticle $referenceArticle
     * @param DateTime $start
     * @param DateTime $end
     * @return Reception[]
     */
    public function getAwaitingWithReference(ReferenceArticle $referenceArticle, DateTime $start, DateTime $end) {
        $start->setTime(0, 0, 0);
        $end->setTime(23, 59, 59);
        return $this->createQueryBuilder('reception')
            ->join('reception.statut', 'statut')
            ->join('reception.receptionReferenceArticles', 'referenceLines')
            ->where('referenceLines.referenceArticle = :reference')
            ->andWhere('reception.dateAttendue BETWEEN :start and :end')
            ->andWhere('statut.code = :validated')
            ->setParameters([
                'start' => $start,
                'end' => $end,
                'validated' => Reception::STATUT_EN_ATTENTE,
                'reference' => $referenceArticle
            ])
            ->orderBy('reception.dateAttendue', 'ASC')
            ->getQuery()
            ->execute();
    }

    public function getByDates(DateTime $dateMin, DateTime $dateMax): array {
        $queryBuilder = $this->createQueryBuilder('reception')
            ->select('reception.id AS id')
            ->addSelect('article.id AS articleId')
            ->addSelect('referenceArticle.id AS referenceArticleId')
            ->addSelect('reception.number')
            ->addSelect('reception.orderNumber')
            ->addSelect('provider.nom AS providerName')
            ->addSelect('user.username AS userUsername')
            ->addSelect('status.nom AS statusName')
            ->addSelect('reception.date')
            ->addSelect('reception.dateFinReception')
            ->addSelect('reception.commentaire')
            ->addSelect('receptionReferenceArticle.quantiteAR AS receptionRefArticleQuantiteAR')
            ->addSelect('receptionReferenceArticle.quantite AS receptionRefArticleQuantite')
            ->addSelect('referenceArticle.reference AS referenceArticleReference')
            ->addSelect('referenceArticle.typeQuantite AS referenceArticleTypeQuantite')
            ->addSelect('referenceArticle.libelle AS referenceArticleLibelle')
            ->addSelect('referenceArticle.quantiteStock AS referenceArticleQuantiteStock')
            ->addSelect('referenceArticleType.label AS referenceArticleTypeLabel')
            ->addSelect('referenceArticle.barCode AS referenceArticleBarcode')
            ->addSelect('articleReferenceArticle.reference AS articleReference')
            ->addSelect('article.label AS articleLabel')
            ->addSelect('article.quantite AS articleQuantity')
            ->addSelect('articleType.label AS articleTypeLabel')
            ->addSelect('articleReferenceArticle.barCode AS articleReferenceArticleBarcode')
            ->addSelect('article.barCode as articleBarcode')
            ->addSelect('reception.manualUrgent AS receptionEmergency')
            ->addSelect('reception.urgentArticles AS referenceEmergency')
            ->addSelect('join_storageLocation.label AS storageLocation')

            ->where('reception.date BETWEEN :dateMin AND :dateMax')

            ->leftJoin('reception.fournisseur', 'provider')
            ->leftJoin('reception.utilisateur', 'user')
            ->leftJoin('reception.statut', 'status')
            ->leftJoin('reception.storageLocation', 'join_storageLocation')
            ->leftJoin('reception.receptionReferenceArticles', 'receptionReferenceArticle')
            ->leftJoin('receptionReferenceArticle.referenceArticle', 'referenceArticle')
            ->leftJoin('referenceArticle.type', 'referenceArticleType')
            ->leftJoin('receptionReferenceArticle.articles', 'article')
            ->leftJoin('article.type', 'articleType')
            ->leftJoin('article.articleFournisseur', 'articleFournisseur')
            ->leftJoin('articleFournisseur.referenceArticle', 'articleReferenceArticle')

            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function findByParamAndFilters(InputBag $params, $filters, Utilisateur $user, VisibleColumnService $visibleColumnService)
    {
        $qb = $this->createQueryBuilder("reception");

        $countTotal = QueryBuilderHelper::count($qb, 'reception');

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statut':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('reception.statut', 's')
                        ->andWhere('s.id in (:statut)')
                        ->setParameter('statut', $value);
                    break;
                case 'purchaseRequest':
                    $value = $filter['value'];
                    $qb
                        ->join('reception.purchaseRequestLines', 'purchaseRequestLines')
                        ->join('purchaseRequestLines.purchaseRequest', 'purchaseRequestLines_purchaseRequest')
                        ->andWhere('purchaseRequestLines_purchaseRequest.id = :purchaseRequest')
                        ->setParameter('purchaseRequest', $value);
                    break;
                case 'commandList':
                    $value = Stream::from(explode(',', $filter['value']))
                        ->map(fn($v) => explode(':', $v)[0])
                        ->toArray();

                    $ors = $qb->expr()->orX();
                    foreach($value as $command) {
                        $ors->add("(:number{$ors->count()}) IN reception.orderNumber");
                        $qb->setParameter("number{$ors->count()}", $command);
                    }

                    $qb->andWhere($ors);
                    break;
                case 'utilisateurs':
                    $values = array_map(function ($value) {
                        return explode(":", $value)[0];
                    }, explode(',', $filter['value']));
                    $qb
                        ->join('reception.demandes', 'filter_request')
                        ->join('filter_request.utilisateur', 'filter_request_user');

                    $exprBuilder = $qb->expr();
                    $OROperands = [];
                    foreach ($values as $index => $receiver) {
                        $OROperands[] = "filter_request_user.id = :user$index";
                        $qb->setParameter("user$index", $receiver);
                    }
                    $qb->andWhere('(' . $exprBuilder->orX(...$OROperands) . ')');
                    break;
                case 'providers':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('reception.fournisseur', 'f')
                        ->andWhere("f.id in (:fournisseur)")
                        ->setParameter('fournisseur', $value);
                    break;
                case 'dateMin':
                    $qb->andWhere('reception.date >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . ' 00:00:00');
                    break;
                case 'dateMax':
                    $qb->andWhere('reception.date <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . ' 23:59:59');
                    break;
                case 'expectedDate':
                    $dateExpectedMin = ($filter['value'] . ' 00:00:00 ');
                    $dateExpectedMax = ($filter['value'] . ' 23:59:59 ');
                    $qb->andWhere('reception.dateAttendue BETWEEN :dateExpectedMin AND :dateExpectedMax')
                        ->setParameter('dateExpectedMin', $dateExpectedMin)
                        ->setParameter('dateExpectedMax', $dateExpectedMax);
                    break;
                case 'emergency':
                    $valueFilter = ((int)($filter['value'] ?? 0));
                    if ($valueFilter) {
                        $qb->andWhere('reception.urgentArticles = true OR reception.manualUrgent = true');
                    }
                    break;
            }
        }
        //Filter search
        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $conditions = [
                        "Date" => "DATE_FORMAT(reception.date, '%d/%m/%Y') LIKE :search_value",
                        "number" => "reception.number LIKE :search_value",
                        "dateAttendue" => "DATE_FORMAT(reception.dateAttendue, '%d/%m/%Y') LIKE :search_value",
                        "DateFin" => "DATE_FORMAT(reception.dateFinReception, '%d/%m/%Y') LIKE :search_value",
                        "orderNumber" => "reception.orderNumber LIKE :search_value",
                        "receiver" => null,
                        "Fournisseur" => "search_provider.nom LIKE :search_value",
                        "Statut" => "search_status.nom LIKE :search_value",
                        "storageLocation" => "search_storage_location.label LIKE :search_value",
                        "Commentaire" => "reception.commentaire LIKE :search_value",
                        "deliveries" => null,
                    ];

                    $visibleColumnService->bindSearchableColumns($conditions, 'reception', $qb, $user, $search);

                    $qb
						->leftJoin('reception.statut', 'search_status')
						->leftJoin('reception.fournisseur', 'search_provider')
                        ->leftJoin('reception.demandes', 'search_request')
                        ->leftJoin('search_request.utilisateur', 'search_request_user')
                        ->leftJoin('reception.storageLocation', 'search_storage_location');
                }
            }

            if (!empty($params->all('order')))
            {
                foreach ($params->all('order') as $sort) {
                    $order = $sort['dir'];
                    if (!empty($order))
                    {
                        $columnName = $params->all('columns')[$sort['column']]['data'];
                        $column = self::DtToDbLabels[$columnName] ?? $columnName;
                        if ($column === 'statut') {
                            $qb
                                ->leftJoin('reception.statut', 's2')
                                ->addOrderBy('s2.nom', $order);
                        } else if ($column === 'fournisseur') {
                            $qb
                                ->leftJoin('reception.fournisseur', 'u2')
                                ->addOrderBy('u2.nom', $order);
                        } else if ($column === 'storageLocation') {
                            $qb
                                ->leftJoin('reception.storageLocation', 'join_storageLocation')
                                ->addOrderBy('join_storageLocation.label', $order);
                        } else if (property_exists(Reception::class, $column)) {
                            $qb
                                ->addOrderBy('reception.' . $column, $order);
                        }
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryBuilderHelper::count($qb, 'reception');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

        $query = $qb->getQuery();

        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

}
