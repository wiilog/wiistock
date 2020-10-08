<?php

namespace App\Repository;

use App\Entity\Arrivage;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Helper\QueryCounter;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Exception;

/**
 * @method Arrivage|null find($id, $lockMode = null, $lockVersion = null)
 * @method Arrivage|null findOneBy(array $criteria, array $orderBy = null)
 * @method Arrivage[]    findAll()
 * @method Arrivage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArrivageRepository extends EntityRepository
{
    private const DtToDbLabels = [
        'date' => 'date',
        'arrivalNumber' => 'arrivalNumber',
        'carrier' => 'carrier',
        'driver' => 'driver',
        'trackingCarrierNumber' => 'trackingCarrierNumber',
        'orderNumber' => 'orderNumber',
        'provider' => 'provider',
        'receiver' => 'receiver',
        'buyers' => 'buyers',
        'nbUm' => 'nbUm',
        'status' => 'status',
        'user' => 'user',
        'type' => 'type',
        'custom' => 'custom',
        'frozen' => 'frozen',
        'emergency' => 'emergency',
        'projectNumber' => 'projectNumber',
        'businessUnit' => 'businessUnit'
    ];

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return Arrivage[]|null
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countByDates($dateMin, $dateMax)
    {
		return $this->createQueryBuilderByDates($dateMin, $dateMax)
            ->select('COUNT(arrivage)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param DateTime $date
     * @return Arrivage[]|null
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countByDate(DateTime $date)
    {
		return $this->createQueryBuilder('arrivage')
            ->select('COUNT(arrivage)')
            ->where('arrivage.date = :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return Arrivage[]|null
     */
    public function findByDates($dateMin, $dateMax)
    {
		return $this->createQueryBuilderByDates($dateMin, $dateMax)
            ->getQuery()
            ->execute();
    }

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return Arrivage[]|null
     */
    public function getByDates($dateMin, $dateMax)
    {
		return $this->createQueryBuilderByDates($dateMin, $dateMax)
            ->select('arrivage.id')
            ->addSelect('arrivage.numeroArrivage')
            ->addSelect('recipient.username AS recipientUsername')
            ->addSelect('user.username AS userUsername')
            ->addSelect('fournisseur.nom AS fournisseurName')
            ->addSelect('transporteur.label AS transporteurLabel')
            ->addSelect('chauffeur.nom AS chauffeurSurname')
            ->addSelect('chauffeur.prenom AS chauffeurFirstname')
            ->addSelect('arrivalType.label AS type')
            ->addSelect('arrivage.noTracking')
            ->addSelect('arrivage.numeroCommandeList')
            ->addSelect('arrivage.duty')
            ->addSelect('arrivage.frozen')
            ->addSelect('status.nom AS statusName')
            ->addSelect('arrivage.commentaire')
            ->addSelect('arrivage.date')
            ->addSelect('arrivage.projectNumber AS projectNumber')
            ->addSelect('arrivage.businessUnit AS businessUnit')
            ->addSelect('arrivage.freeFields AS freeFields')
            ->leftJoin('arrivage.destinataire', 'recipient')
            ->leftJoin('arrivage.fournisseur', 'fournisseur')
            ->leftJoin('arrivage.transporteur', 'transporteur')
            ->leftJoin('arrivage.chauffeur', 'chauffeur')
            ->leftJoin('arrivage.statut', 'status')
            ->leftJoin('arrivage.utilisateur', 'user')
            ->leftJoin('arrivage.type', 'arrivalType')
            ->getQuery()
            ->execute();
    }

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return QueryBuilder
     */
    public function createQueryBuilderByDates($dateMin, $dateMax): QueryBuilder
    {
        return $this->createQueryBuilder('arrivage')
            ->where('arrivage.date BETWEEN :dateMin AND :dateMax')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);
    }

    public function countByFournisseur($fournisseurId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(a)
			FROM App\Entity\Arrivage a
			WHERE a.fournisseur = :fournisseurId"
        )->setParameter('fournisseurId', $fournisseurId);

        return $query->getSingleScalarResult();
    }

    public function countByChauffeur($chauffeur)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(a)
			FROM App\Entity\Arrivage a
			WHERE a.chauffeur = :chauffeur"
        )->setParameter('chauffeur', $chauffeur);

        return $query->getSingleScalarResult();
    }

	/**
	 * @param Arrivage $arrivage
	 * @return int
	 * @throws NonUniqueResultException
	 * @throws NoResultException
	 */
    public function countColisByArrivage($arrivage)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(c)
			FROM App\Entity\Pack c
			WHERE c.arrivage = :arrivage"
        )->setParameter('arrivage', $arrivage->getId());

        return $query->getSingleScalarResult();
    }

    public function getColisByArrivage($arrivage)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT c.code
			FROM App\Entity\Pack c
			WHERE c.arrivage = :arrivage"
        )->setParameter('arrivage', $arrivage);

        return $query->getScalarResult();
    }

	/**
	 * @param Arrivage $arrivage
	 * @return int
	 * @throws NonUniqueResultException
	 * @throws NoResultException
	 */
    public function countLitigesUnsolvedByArrivage($arrivage)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(l)
			FROM App\Entity\Litige l
			JOIN l.packs c
			JOIN l.status s
			WHERE s.state = :stateNotTreated
			AND c.arrivage = :arrivage"
        )
            ->setParameter('stateNotTreated', Statut::NOT_TREATED)
            ->setParameter('arrivage', $arrivage);

        return $query->getSingleScalarResult();
    }

    /**
     * @param $firstDay
     * @param $lastDay
     * @return mixed
     * @throws Exception
     */
    public function countByDays($firstDay, $lastDay)
    {
        $from = new DateTime(str_replace("/", "-", $firstDay) . " 00:00:00");
        $to = new DateTime(str_replace("/", "-", $lastDay) . " 23:59:59");
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(a) as count, a.date as date
                FROM App\Entity\Arrivage a
                WHERE a.date BETWEEN :firstDay AND :lastDay
                GROUP BY a.date"
        )->setParameters([
            'lastDay' => $to,
            'firstDay' => $from
        ]);
        return $query->execute();
    }

    /**
     * @param array|null $params
     * @param array|null $filters
     * @param int|null $userId
     * @param $freeFieldLabelsToIds
     * @return array
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function findByParamsAndFilters($params, $filters, $userId, $freeFieldLabelsToIds)
    {
        $qb = $this->createQueryBuilder("a");

        // filtre arrivages de l'utilisateur
        if ($userId) {
            $qb
                ->join('a.acheteurs', 'ach')
                ->where('ach.id = :userId')
                ->setParameter('userId', $userId);
        }

        $total = QueryCounter::count($qb);

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statut':
					$value = explode(',', $filter['value']);
					$qb
						->join('a.statut', 's')
						->andWhere('s.id in (:statut)')
						->setParameter('statut', $value);
					break;
                case 'utilisateurs':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('a.destinataire', 'dest')
                        ->andWhere("dest.id in (:userId)")
                        ->setParameter('userId', $value);
                    break;
                case 'providers':
                    $value = explode(',', $filter['value']);
					$qb
                        ->join('a.fournisseur', 'f2')
                        ->andWhere("f2.id in (:fournisseurId)")
                        ->setParameter('fournisseurId', $value);
                    break;
                case 'carriers':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('a.transporteur', 't2')
                        ->andWhere("t2.id in (:transporteurId)")
                        ->setParameter('transporteurId', $value);
                    break;
                case 'dateMin':
                    $qb->andWhere('a.date >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb->andWhere('a.date <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
                case 'emergency':
                    $qb
                        ->andWhere('a.isUrgent = :isUrgent')
                        ->setParameter('isUrgent', $filter['value']);
                    break;
                case 'duty':
                    if ($filter['value'] === '1') {
                        $qb
                            ->andWhere('a.duty = :value')
                            ->setParameter('value', $filter['value']);
                    }
                    break;
                case 'frozen':
                    if ($filter['value'] === '1') {
                        $qb
                            ->andWhere('a.frozen = :value')
                            ->setParameter('value', $filter['value']);
                    }
                    break;
                case 'numArrivage':
                    $qb
                        ->andWhere('a.numeroArrivage = :numeroArrivage')
                        ->setParameter('numeroArrivage', $filter['value']);
                    break;
            }
        }

		$orderStatut = null;
		//Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->leftJoin('a.transporteur', 't3')
                        ->leftJoin('a.chauffeur', 'ch3')
                        ->leftJoin('a.fournisseur', 'f3')
                        ->leftJoin('a.destinataire', 'd3')
                        ->leftJoin('a.acheteurs', 'ach3')
                        ->leftJoin('a.utilisateur', 'u3')
                        ->leftJoin('a.type', 'search_type')
                        ->andWhere("(
                            a.numeroArrivage LIKE :value
                            OR t3.label LIKE :value
                            OR ch3.nom LIKE :value
                            OR ch3.prenom LIKE :value
                            OR a.noTracking LIKE :value
                            OR a.numeroCommandeList LIKE :value
                            OR f3.nom LIKE :value
                            OR d3.username LIKE :value
                            OR ach3.username LIKE :value
                            OR u3.username LIKE :value
                            OR search_type.label LIKE :value
                            OR a.businessUnit LIKE :value
                            OR a.projectNumber LIKE :value
                            OR DATE_FORMAT(a.date, '%e/%m/%Y') LIKE :value
                        )")
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $orderData = $params->get('columns')[$params->get('order')[0]['column']]['data'];
                    $column = self::DtToDbLabels[$orderData] ?? $orderData;

                    if ($column === 'carrier') {
                        $qb
                            ->leftJoin('a.transporteur', 't2')
                            ->orderBy('t2.label', $order);
                    } else if ($column === 'driver') {
                        $qb
                            ->leftJoin('a.chauffeur', 'c2')
                            ->orderBy('c2.nom', $order);
                    } else if ($column === 'arrivalNumber') {
                        $qb
                            ->orderBy('a.numeroArrivage', $order);
                    } else if ($column === 'trackingCarrierNumber') {
                        $qb
                            ->orderBy('a.noTracking', $order);
                    } else if ($column === 'orderNumber') {
                        $qb
                            ->orderBy('a.numeroCommandeList', $order);
                    } else if ($column === 'type') {
                        $qb
                            ->leftJoin('a.type', 'order_type')
                            ->orderBy('order_type.label', $order);
                    } else if ($column === 'provider') {
                        $qb
                            ->leftJoin('a.fournisseur', 'order_fournisseur')
                            ->orderBy('order_fournisseur.nom', $order);
                    } else if ($column === 'receiver') {
                        $qb
                            ->leftJoin('a.destinataire', 'a2')
                            ->orderBy('a2.username', $order);
                    } else if ($column === 'buyers') {
                        $qb
                            ->leftJoin('a.acheteurs', 'ach2')
                            ->orderBy('ach2.username', $order);
                    } else if ($column === 'user') {
                        $qb
                            ->leftJoin('a.utilisateur', 'u2')
                            ->orderBy('u2.username', $order);
                    } else if ($column === 'custom') {
                        $qb
                            ->orderBy('a.duty', $order);
                    } else if ($column === 'frozen') {
                        $qb
                            ->orderBy('a.frozen', $order);
                    } else if ($column === 'projectNumber') {
                        $qb
                            ->orderBy('a.projectNumber', $order);
                    } else if ($column === 'businessUnit') {
                        $qb
                            ->orderBy('a.businessUnit', $order);
                    } else if ($column === 'nbUm') {
                        $qb
                            ->addSelect('count(col2.id) as hidden nbum')
                            ->leftJoin('a.packs', 'col2')
                            ->orderBy('nbum', $order)
                            ->groupBy('col2.arrivage, a');
                    } else if ($column === 'status') {
                        $qb
                            ->leftJoin('a.statut', 'order_status')
                            ->orderBy('order_status.nom', $order);
                    } else {
                        if (property_exists(Arrivage::class, $column)) {
                            $qb
                                ->orderBy('a.' . $column, $order);
                        } else {
                            $clId = $freeFieldLabelsToIds[trim(mb_strtolower($column))] ?? null;
                            if ($clId) {
                                $jsonOrderQuery = "CAST(JSON_EXTRACT(a.freeFields, '$.\"${clId}\"') AS CHAR)";
                                $qb
                                    ->orderBy($jsonOrderQuery, $order);
                            }
                        }
                    }
                }
            }
        }

        $filtered = QueryCounter::count($qb);

        if (!empty($params)) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

        return [
            'data' => $qb->getQuery()->getResult(),
            'count' => $filtered,
            'total' => $total
        ];
    }

    /**
     * @param Utilisateur $user
     * @return int
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function countByUser($user)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(a)
            FROM App\Entity\Arrivage a
            WHERE a.utilisateur = :user OR a.destinataire = :user"
        )->setParameter('user', $user);

        return $query->getSingleScalarResult();
    }

}
