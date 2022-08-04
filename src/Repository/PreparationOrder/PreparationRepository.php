<?php

namespace App\Repository\PreparationOrder;

use App\Entity\Article;
use App\Entity\FiltreSup;
use App\Entity\IOT\Sensor;
use App\Entity\LocationGroup;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Helper\QueryCounter;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Generator;
use Google\Service\AdMob\Date;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\StringHelper;

/**
 * @method Preparation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Preparation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Preparation[]    findAll()
 * @method Preparation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PreparationRepository extends EntityRepository
{
    const DtToDbLabels = [
        'Numéro' => 'numero',
        'Statut' => 'status',
        'Date' => 'date',
        'Opérateur' => 'user',
        'Type' => 'type'
    ];

    /**
     * @param Utilisateur $user
     * @param array $preparationIdsFilter
     * @return array
     */
    public function getMobilePreparations(Utilisateur $user,
                                          array       $preparationIdsFilter = [],
                                          ?int        $maxResult = 100)
    {
        $queryBuilder = $this->createQueryBuilder('p');
        $queryBuilder
            ->select('p.id AS id')
            ->addSelect('p.numero as number')
            ->addSelect('dest.label as destination')
            ->addSelect('(CASE WHEN triggeringSensorWrapper.id IS NOT NULL THEN triggeringSensorWrapper.name ELSE user.username END) as requester')
            ->addSelect('t.label as type')
            ->addSelect('d.commentaire as comment')
            ->join('p.statut', 's')
            ->join('p.demande', 'd')
            ->join('d.destination', 'dest')
            ->join('d.type', 't')
            ->leftJoin('d.utilisateur', 'user')
            ->leftJoin('d.triggeringSensorWrapper', 'triggeringSensorWrapper')
            ->andWhere('(s.nom = :toTreatStatusLabel OR (s.nom = :inProgressStatusLabel AND p.utilisateur = :user))')
            ->andWhere('t.id IN (:type)')
            ->andWhere('d.manual = false')
            ->orderBy('t.label', Criteria::ASC)
            ->setMaxResults($maxResult)
            ->setParameters([
                'toTreatStatusLabel' => Preparation::STATUT_A_TRAITER,
                'inProgressStatusLabel' => Preparation::STATUT_EN_COURS_DE_PREPARATION,
                'user' => $user,
                'type' => $user->getDeliveryTypeIds()
            ]);

        if (!empty($preparationIdsFilter)) {
            $queryBuilder
                ->andWhere('p.id IN (:preparationIdsFilter)')
                ->setParameter('preparationIdsFilter', $preparationIdsFilter, Connection::PARAM_STR_ARRAY);
        }

        return $queryBuilder
            ->getQuery()
            ->execute();
    }


    /**
     * @param ReferenceArticle $referenceArticle
     * @param DateTime $start
     * @param DateTime $end
     * @return Preparation[]
     */
    public function getValidatedWithReference(ReferenceArticle $referenceArticle, DateTime $start, DateTime $end)
    {
        $start->setTime(0, 0, 0);
        $end->setTime(23, 59, 59);
        return $this->createQueryBuilder('preparation')
            ->join('preparation.statut', 'statut')
            ->join('preparation.referenceLines', 'referenceLines')
            ->join('preparation.demande', 'request')
            ->andWhere('request.manual = false')
            ->andWhere('referenceLines.reference = :reference')
            ->andWhere('preparation.expectedAt BETWEEN :start and :end')
            ->andWhere('statut.code IN (:statuses)')
            ->setParameters([
                'start' => $start,
                'end' => $end,
                'statuses' => [Preparation::STATUT_VALIDATED, Preparation::STATUT_A_TRAITER],
                'reference' => $referenceArticle
            ])
            ->orderBy('preparation.expectedAt', 'ASC')
            ->getQuery()
            ->execute();
    }

    /**
     * @param array|null $params
     * @param array|null $filters
     * @return array
     * @throws Exception
     */
    public function findByParamsAndFilters(InputBag $params, $filters)
    {
        $qb = $this->createQueryBuilder("p");

        $countTotal = QueryCounter::count($qb, 'p');
        $qb
            ->where('p.planned IS NULL OR p.planned = 0')
            ->join('p.demande', 'request')
            ->andWhere('request.manual = false');
        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case FiltreSup::FIELD_TYPE:
                    $qb
                        ->leftJoin('request.type', 't')
                        ->andWhere('t.label = :type')
                        ->setParameter('type', $filter['value']);
                    break;
                case FiltreSup::FIELD_STATUT:
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('p.statut', 's')
                        ->andWhere('s.id in (:statut)')
                        ->setParameter('statut', $value);
                    break;
                case FiltreSup::FIELD_USERS:
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('p.utilisateur', 'u')
                        ->andWhere("u.id in (:userId)")
                        ->setParameter('userId', $value);
                    break;
                case FiltreSup::FIELD_DATE_MIN:
                    $qb
                        ->andWhere('p.date >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case FiltreSup::FIELD_DATE_MAX:
                    $qb
                        ->andWhere('p.date <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
                case FiltreSup::FIELD_DEMANDE:
                    $qb
                        ->andWhere('request.id = :id')
                        ->setParameter('id', $filter['value']);
                    break;
            }
        }

        //Filter search
        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->leftJoin('request.type', 't2')
                        ->leftJoin('p.utilisateur', 'p2')
                        ->leftJoin('p.statut', 's2')
                        ->andWhere('
						p.numero LIKE :value OR
						t2.label LIKE :value OR
						p2.username LIKE :value OR
						s2.nom LIKE :value
						')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = self::DtToDbLabels[$params->all('columns')[$params->all('order')[0]['column']]['data']];

                    if ($column === 'status') {
                        $qb
                            ->leftJoin('p.statut', 's3')
                            ->orderBy('s3.nom', $order);
                    } else if ($column === 'type') {
                        $qb
                            ->leftJoin('request.type', 't3')
                            ->orderBy('t3.label', $order);
                    } else if ($column === 'user') {
                        $qb
                            ->leftJoin('p.utilisateur', 'u3')
                            ->orderBy('u3.username', $order);
                    } else {
                        $qb
                            ->orderBy('p.' . $column, $order);
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryCounter::count($qb, 'p');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

        return [
            'data' => $qb->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return Generator
     */
    public function iterateByDates($dateMin, $dateMax): Generator
    {
        $dateMax = $dateMax->format('Y-m-d H:i:s');
        $dateMin = $dateMin->format('Y-m-d H:i:s');

        $iterator = $this->createQueryBuilder('preparation')
            ->join('preparation.demande', 'request')
            ->andWhere('request.manual = false')
            ->andWhere('preparation.date BETWEEN :dateMin AND :dateMax')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ])
            ->getQuery()
            ->iterate();

        foreach ($iterator as $item) {
            // $item [index => preparation]
            yield array_pop($item);
        }
    }

    public function getNumeroPrepaGroupByDemande(array $demandes)
    {
        $queryBuilder = $this->createQueryBuilder('preparation')
            ->select('demande.id AS demandeId')
            ->addSelect('preparation.numero AS numeroPreparation')
            ->join('preparation.demande', 'demande')
            ->andWhere('preparation.demande in (:demandes)')
            ->andWhere('demande.manual = false')
            ->setParameter('demandes', $demandes);

        $result = $queryBuilder->getQuery()->execute();
        return array_reduce($result, function (array $carry, $current) {

            $demandeId = $current['demandeId'];
            $numeroPreparation = $current['numeroPreparation'];
            if (!isset($carry[$demandeId])) {
                $carry[$demandeId] = [];
            }
            $carry[$demandeId][] = $numeroPreparation;
            return $carry;
        }, []);
    }

    public function countByNumero(string $numero)
    {
        $queryBuilder = $this
            ->createQueryBuilder('preparation')
            ->select('COUNT(preparation.id) AS counter')
            ->andWhere('preparation.numero = :numero')
            ->setParameter('numero', $numero . '%');

        $result = $queryBuilder
            ->getQuery()
            ->getResult();

        return !empty($result) ? ($result[0]['counter'] ?? 0) : 0;
    }

    /**
     * @param array|null $types
     * @param array|null $statuses
     * @return int|mixed|string
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countByTypesAndStatuses(?array $types, ?array $statuses): ?int
    {
        if (!empty($types) && !empty($statuses)) {
            $qb = $this->createQueryBuilder('preparationOrder')
                ->select('COUNT(preparationOrder)')
                ->leftJoin('preparationOrder.statut', 'status')
                ->leftJoin('preparationOrder.demande', 'request')
                ->leftJoin('request.type', 'type')
                ->where('status IN (:statuses)')
                ->andWhere('type IN (:types)')
                ->setParameter('statuses', $statuses)
                ->setParameter('types', $types);

            return $qb
                ->getQuery()
                ->getSingleScalarResult();
        } else {
            return [];
        }
    }

    /**
     * @param array $types
     * @param array $statuses
     * @return DateTime|null
     * @throws NonUniqueResultException
     */
    public function getOlderDateToTreat(array $types = [],
                                        array $statuses = []): ?DateTime
    {
        if (!empty($statuses)) {
            $res = $this
                ->createQueryBuilder('preparation')
                ->select('preparation.date AS date')
                ->innerJoin('preparation.statut', 'status')
                ->innerJoin('preparation.demande', 'request')
                ->innerJoin('request.type', 'type')
                ->andWhere('request.manual = false')
                ->andWhere('status IN (:statuses)')
                ->andWhere('type IN (:types)')
                ->andWhere('status.state IN (:treatedStates)')
                ->addOrderBy('preparation.date', 'ASC')
                ->setParameter('statuses', $statuses)
                ->setParameter('types', $types)
                ->setParameter('treatedStates', [Statut::PARTIAL, Statut::NOT_TREATED])
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            return $res['date'] ?? null;
        } else {
            return null;
        }
    }

    /**
     * @param LocationGroup $locationGroup
     * @return string
     */
    public function createArticleSensorPairingDataQueryUnion(Article $article): string
    {
        $entityManager = $this->getEntityManager();
        $createQueryBuilder = function () use ($entityManager) {
            return $entityManager->createQueryBuilder()
                ->from(Article::class, 'article')
                ->select('pairing.id AS pairingId')
                ->addSelect('sensorWrapper.name AS name')
                ->addSelect('(CASE WHEN sensorWrapper.deleted = false AND pairing.active = true AND (pairing.end IS NULL OR pairing.end > NOW()) THEN 1 ELSE 0 END) AS active')
                ->addSelect('preparation.numero AS entity')
                ->addSelect("'" . Sensor::PREPARATION . "' AS entityType")
                ->addSelect('preparation.id AS entityId')
                ->join('article.sensorMessages', 'sensorMessage')
                ->join('sensorMessage.pairings', 'pairing')
                ->join('pairing.preparationOrder', 'preparation')
                ->join('pairing.sensorWrapper', 'sensorWrapper')
                ->join('preparation.demande', 'request')
                ->andWhere('request.manual = false')
                ->where('article = :article')
                ->andWhere('pairing.article IS NULL');
        };

        $startQueryBuilder = $createQueryBuilder();
        $startQueryBuilder
            ->addSelect("pairing.start AS date")
            ->addSelect("'start' AS type")
            ->andWhere('pairing.start IS NOT NULL');

        $endQueryBuilder = $createQueryBuilder();
        $endQueryBuilder
            ->addSelect("pairing.end AS date")
            ->addSelect("'end' AS type")
            ->andWhere('pairing.end IS NOT NULL');

        $sqlAliases = [
            '/AS \w+_0/' => 'AS pairingId',
            '/AS \w+_1/' => 'AS name',
            '/AS \w+_2/' => 'AS active',
            '/AS \w+_3/' => 'AS entity',
            '/AS \w+_4/' => 'AS entityType',
            '/AS \w+_5/' => 'AS entityId',
            '/AS \w+_6/' => 'AS date',
            '/AS \w+_7/' => 'AS type',
            '/\?/' => $article->getId(),
        ];

        $startSQL = $startQueryBuilder->getQuery()->getSQL();
        $startSQL = StringHelper::multiplePregReplace($sqlAliases, $startSQL);

        $endSQL = $endQueryBuilder->getQuery()->getSQL();
        $endSQL = StringHelper::multiplePregReplace($sqlAliases, $endSQL);

        return "
            ($startSQL)
            UNION
            ($endSQL)
        ";
    }

    /**
     * @param string[] $statusCodes
     * @return Preparation[]
     */
    public function findByStatusCodesAndExpectedAt(array $filters, array $statusCodes, DateTime $start, DateTime $end): array
    {
        $startStr = $start->format('Y-m-d');
        $endStr = $end->format('Y-m-d');

        if (!empty($statusCodes)) {
            $queryBuilder = $this->createQueryBuilder('preparation')
                ->join('preparation.statut', 'status')
                ->join('preparation.demande', 'request')
                ->andWhere('request.manual = false')
                ->andWhere('status.code IN (:statusCodes)')
                ->andWhere('preparation.expectedAt BETWEEN :start AND :end')
                ->setParameter('statusCodes', $statusCodes)
                ->setParameter('start', $startStr)
                ->setParameter('end', $endStr);

            foreach ($filters as $filter) {
                if ($queryBuilder !== []) {
                    if ($filter['field'] === FiltreSup::FIELD_OPERATORS) {
                        $value = explode(',', $filter['value']);
                        $queryBuilder
                            ->join('preparation.utilisateur', 'filter_user')
                            ->andWhere('filter_user.id in (:users)')
                            ->setParameter('users', $value);
                    }
                    else if ($filter['field'] === FiltreSup::FIELD_TYPE) {
                        $queryBuilder
                            ->join('request.type', 'filter_type')
                            ->andWhere('filter_type.label = :type')
                            ->setParameter('type', $filter['value']);
                    }
                    else if ($filter['field'] === FiltreSup::FIELD_REQUEST_NUMBER) {
                        $queryBuilder
                            ->andWhere('preparation.numero = :numero')
                            ->setParameter('numero', $filter['value']);
                    }
                }
            }

            return $queryBuilder
                ->getQuery()
                ->getResult();
        }
        else {
            return [];
        }
    }

}
