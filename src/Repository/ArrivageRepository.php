<?php

namespace App\Repository;

use App\Entity\Arrivage;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

/**
 * @method Arrivage|null find($id, $lockMode = null, $lockVersion = null)
 * @method Arrivage|null findOneBy(array $criteria, array $orderBy = null)
 * @method Arrivage[]    findAll()
 * @method Arrivage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArrivageRepository extends ServiceEntityRepository
{
    private const DtToDbLabels = [
        'Date' => 'date',
        'NumeroArrivage' => 'numeroArrivage',
        'Transporteur' => 'transporteur',
        'Chauffeur' => 'chauffeur',
        'NoTracking' => 'noTracking',
        'NumeroBL' => 'numeroBL',
        'Fournisseur' => 'fournisseur',
        'Destinataire' => 'destinataire',
        'Acheteurs' => 'acheteurs',
        'NbUM' => 'nbUM',
        'Statut' => 'statut',
        'Utilisateur' => 'utilisateur',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Arrivage::class);
    }

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return Arrivage[]|null
     */
    public function findByDates($dateMin, $dateMax)
    {
		$dateMax = $dateMax->format('Y-m-d H:i:s');
		$dateMin = $dateMin->format('Y-m-d H:i:s');

        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\Arrivage a
            WHERE a.date BETWEEN :dateMin AND :dateMax'
        )->setParameters([
            'dateMin' => $dateMin,
            'dateMax' => $dateMax
        ]);
        return $query->execute();
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
     */
    public function countColisByArrivage($arrivage)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(c)
			FROM App\Entity\Colis c
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
			FROM App\Entity\Colis c
			WHERE c.arrivage = :arrivage"
        )->setParameter('arrivage', $arrivage);

        return $query->getScalarResult();
    }

    /**
     * @param Arrivage $arrivage
     * @return int
     * @throws NonUniqueResultException
     */
    public function countLitigesUnsolvedByArrivage($arrivage)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(l)
			FROM App\Entity\Litige l
			JOIN l.colis c
			JOIN l.status s
			WHERE s.treated = 0
			AND c.arrivage = :arrivage"
        )->setParameter('arrivage', $arrivage);

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
     * @return array
     * @throws Exception
     */
    public function findByParamsAndFilters($params, $filters, $userId)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('a')
            ->from('App\Entity\Arrivage', 'a');

        // filtre arrivages de l'utilisateur
        if ($userId) {
            $qb
                ->join('a.acheteurs', 'ach')
                ->where('ach.id = :userId')
                ->setParameter('userId', $userId);
        }

        $countTotal = count($qb->getQuery()->getResult());

        // filtres sup
        $statut = null;
        $needsDefaultDateFilter = true;
        dump($filters);
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
                    $needsDefaultDateFilter = false;
                    $qb->andWhere('a.date >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $needsDefaultDateFilter = false;
                    $qb->andWhere('a.date <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
                case 'emergency':
                    $qb
                        ->andWhere('a.isUrgent = :isUrgent')
                        ->setParameter('isUrgent', $filter['value']);
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
            if (!empty($params->get('clicked'))) {
                if ($params->get('clicked') === 'true' && $needsDefaultDateFilter) $needsDefaultDateFilter = false;
            }
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
                        ->andWhere('
						a.numeroArrivage LIKE :value
						OR t3.label LIKE :value
						OR ch3.nom LIKE :value
						OR a.noTracking LIKE :value
						OR a.numeroBL LIKE :value
						OR f3.nom LIKE :value
						OR d3.username LIKE :value
						OR ach3.username LIKE :value
						OR u3.username LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];

                    if ($column === 'transporteur') {
                        $qb
                            ->leftJoin('a.transporteur', 't2')
                            ->orderBy('t2.label', $order);
                    } else if ($column === 'chauffeur') {
                        $qb
                            ->leftJoin('a.chauffeur', 'c2')
                            ->orderBy('c2.nom', $order);
                    } else if ($column === 'fournisseur') {
                        $qb
                            ->leftJoin('a.fournisseur', 'f2')
                            ->orderBy('f2.nom', $order);
                    } else if ($column === 'destinataire') {
                        $qb
                            ->leftJoin('a.destinataire', 'a2')
                            ->orderBy('a2.username', $order);
                    } else if ($column === 'acheteurs') {
                        $qb
                            ->leftJoin('a.acheteurs', 'ach2')
                            ->orderBy('ach2.username', $order);
                    } else if ($column === 'utilisateur') {
                        $qb
                            ->leftJoin('a.utilisateur', 'u2')
                            ->orderBy('u2.username', $order);
                    } else if ($column === 'nbUM') {
                        $qb
                            ->addSelect('count(col2.id) as hidden nbum')
                            ->leftJoin('a.colis', 'col2')
                            ->orderBy('nbum', $order)
                            ->groupBy('col2.arrivage');
                    } else if ($column === 'statut') {
                        $orderStatut = $order;
                    } else {
                        $qb
                            ->orderBy('a.' . $column, $order);
                    }
                }
            }
        }
        if ($needsDefaultDateFilter) {
            $now = new DateTime('now', New \DateTimeZone('Europe/Paris'));
            $nowToString = $now->format('Y-m-d');
            $qb->andWhere('a.date >= :dateMin')
                ->setParameter('dateMin', $nowToString . " 00:00:00");
            $qb->andWhere('a.date <= :dateMax')
                ->setParameter('dateMax', $nowToString . " 23:59:59");
        }
        $arrivages = $qb->getQuery()->getResult();
        if ($statut) {
            $arrivages = array_filter($arrivages, function ($arrivage) use ($statut) {
                return ($arrivage->getStatus() === $statut);
            });
        }

        if ($orderStatut) {
            usort($arrivages, function ($arrivage1, $arrivage2) use ($orderStatut) {
                return $orderStatut === 'asc'
                    ?
                    strcmp($arrivage1->getStatus(), $arrivage2->getStatus())
                    :
                    strcmp($arrivage2->getStatus(), $arrivage1->getStatus());
            });
        }

        $countFiltered = count($arrivages);
        if (!empty($params)) {
            if ((!empty($params->get('start')) || $params->get('start') === "0") && !empty($params->get('length'))) {
                $arrivages = array_slice($arrivages, intval($params->get('start')), intval($params->get('length')));
            }
        }


        return [
            'data' => $arrivages ?? null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

}
