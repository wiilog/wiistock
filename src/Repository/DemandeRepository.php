<?php

namespace App\Repository;

use App\Entity\AverageRequestTime;
use App\Entity\Demande;
use App\Entity\Utilisateur;
use App\Helper\QueryCounter;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;

/**
 * @method Demande|null find($id, $lockMode = null, $lockVersion = null)
 * @method Demande|null findOneBy(array $criteria, array $orderBy = null)
 * @method Demande[]    findAll()
 * @method Demande[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DemandeRepository extends EntityRepository
{
    private const DtToDbLabels = [
        'Date' => 'date',
        'Demandeur' => 'demandeur',
        'Statut' => 'statut',
        'Numéro' => 'numero',
        'Type' => 'type',
    ];

    public function findByUserAndNotStatus($user, $status)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT d
            FROM App\Entity\Demande d
            JOIN d.statut s
            WHERE s.nom <> :status AND d.utilisateur = :user"
        )->setParameters(['user' => $user, 'status' => $status]);

        return $query->execute();
    }

    public function findRequestToTreatByUser(?Utilisateur $requester, int $limit) {
        $statuses = [
            Demande::STATUT_BROUILLON,
            Demande::STATUT_A_TRAITER,
            Demande::STATUT_INCOMPLETE,
            Demande::STATUT_PREPARE,
            Demande::STATUT_LIVRE_INCOMPLETE,
        ];

        $queryBuilder = $this->createQueryBuilder('demande');
        if($requester) {
            $queryBuilder->andWhere("demande.utilisateur = :requester")
                ->setParameter("requester", $requester);
        }

        $queryBuilderExpr = $queryBuilder->expr();
        return $queryBuilder
            ->select('demande')
            ->innerJoin('demande.statut', 'status')
            ->leftJoin(AverageRequestTime::class, 'art', Join::WITH, 'art.type = demande.type')
            ->andWhere($queryBuilderExpr->in('status.nom', ':statusNames'))
            ->setParameter('statusNames', $statuses)
            ->addOrderBy(sprintf("FIELD(status.nom, '%s', '%s', '%s', '%s', '%s')", ...$statuses), 'DESC')
            ->addOrderBy("DATE_ADD(demande.date, art.average, 'second')", 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    public function getProcessingTime(): array {
        $status = Demande::STATUT_LIVRE;
        $threeMonthsAgo = new DateTime("-3 months");
        $threeMonthsAgo = $threeMonthsAgo->format('Y-m-d H:i:s');

        $query = $this->getEntityManager()->getConnection()->executeQuery("
            SELECT times.id                                                   AS type,
                   SUM(UNIX_TIMESTAMP(times.max) - UNIX_TIMESTAMP(times.min)) AS total,
                   COUNT(times.id)                                            AS count
            FROM (SELECT type.id                 AS id,
                         MAX(livraison.date_fin) AS max,
                         MIN(preparation.date)   AS min
                  FROM demande
                           INNER JOIN type ON demande.type_id = type.id
                           INNER JOIN statut ON demande.statut_id = statut.id
                           INNER JOIN preparation ON demande.id = preparation.demande_id
                           INNER JOIN livraison ON preparation.id = livraison.preparation_id
                  WHERE statut.nom LIKE '$status'
                  GROUP BY demande.id
                  HAVING MIN(preparation.date) >= '$threeMonthsAgo'
                 ) AS times
            GROUP BY times.id");

        return $query->fetchAll();
    }

    public function findByStatutAndUser($statut, $user)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT d
            FROM App\Entity\Demande d
            WHERE d.statut = :statut AND d.utilisateur = :user"
        )->setParameters([
            'statut' => $statut,
            'user' => $user
        ]);
        return $query->execute();
    }

    public function countByEmplacement($emplacementId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(d)
            FROM App\Entity\Demande d
            JOIN d.destination dest
            WHERE dest.id = :emplacementId"
        )->setParameter('emplacementId', $emplacementId);

        return $query->getSingleScalarResult();
    }

    public function countByStatutAndUser($statut, $user)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(d)
            FROM App\Entity\Demande d
            WHERE d.statut = :statut AND d.utilisateur = :user"
        )->setParameters([
            'statut' => $statut,
            'user' => $user,
        ]);

        return $query->getSingleScalarResult();
    }

    public function countByStatut($statut)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(d)
            FROM App\Entity\Demande d
            WHERE d.statut = :statut "
        )->setParameter('statut', $statut);
        return $query->getSingleScalarResult();
    }

    public function countByStatusesId($listStatusId)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(d)
            FROM App\Entity\Demande d
            WHERE d.statut in (:listStatus)"
        )->setParameter('listStatus', $listStatusId, Connection::PARAM_STR_ARRAY);
        return $query->getSingleScalarResult();
    }

	/**
	 * @param DateTime $dateMin
	 * @param DateTime $dateMax
	 * @return Demande[]|null
	 */
    public function findByDates($dateMin, $dateMax)
    {
		$dateMax = $dateMax->format('Y-m-d H:i:s');
		$dateMin = $dateMin->format('Y-m-d H:i:s');

        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT d
            FROM App\Entity\Demande d
            WHERE d.date BETWEEN :dateMin AND :dateMax'
        )->setParameters([
            'dateMin' => $dateMin,
            'dateMax' => $dateMax
        ]);
        return $query->execute();
    }

    public function getLastNumeroByPrefixeAndDate($prefixe, $date)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			'SELECT d.numero
			FROM App\Entity\Demande d
			WHERE d.numero LIKE :value
			ORDER BY d.numero DESC'
		)->setParameter('value', $prefixe . $date . '%');

		$result = $query->execute();
		return $result ? $result[0]['numero'] : null;
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
			"SELECT COUNT(d)
            FROM App\Entity\Demande d
            WHERE d.utilisateur = :user"
		)->setParameter('user', $user);

		return $query->getSingleScalarResult();
	}

	public function findByParamsAndFilters($params, $filters, $receptionFilter)
    {
        $qb = $this->createQueryBuilder("d");

        $countTotal = QueryCounter::count($qb, 'd');

        if ($receptionFilter) {
            $qb
                ->join('d.reception', 'r')
                ->andWhere('r.id = :reception')
                ->setParameter('reception', $receptionFilter);
        } else {
            // filtres sup
            foreach($filters as $filter) {
                switch($filter['field']) {
                    case 'statut':
                        $value = explode(',', $filter['value']);
                        $qb
                            ->join('d.statut', 's')
                            ->andWhere('s.id in (:statut)')
                            ->setParameter('statut', $value);
                        break;
                    case 'type':
                        $qb
                            ->join('d.type', 't')
                            ->andWhere('t.label = :type')
                            ->setParameter('type', $filter['value']);
                        break;
                    case 'utilisateurs':
                        $value = explode(',', $filter['value']);
                        $qb
                            ->join('d.utilisateur', 'u')
                            ->andWhere("u.id in (:id)")
                            ->setParameter('id', $value);
                        break;
                    case 'dateMin':
                        $qb->andWhere('d.date >= :dateMin')
                            ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                        break;
                    case 'dateMax':
                        $qb->andWhere('d.date <= :dateMax')
                            ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                        break;
                }
            }
        }

		//Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
						->join('d.statut', 's2')
						->join('d.type', 't2')
						->join('d.utilisateur', 'u2')
                        ->andWhere("(
                            DATE_FORMAT(d.date, '%d/%m/%Y') LIKE :value
                            OR u2.username LIKE :value
                            OR d.numero LIKE :value
                            OR s2.nom LIKE :value
                            OR t2.label LIKE :value
                        )")
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order')))
            {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order))
                {
                    $column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];
                    if ($column === 'type') {
                        $qb
                            ->leftJoin('d.type', 'search_type')
                            ->orderBy('search_type.label', $order);
                    } else if ($column === 'statut') {
                        $qb
                            ->leftJoin('d.statut', 'search_status')
                            ->orderBy('search_status.nom', $order);
                    } else if ($column === 'demandeur') {
                        $qb
                            ->leftJoin('d.utilisateur', 'search_user')
                            ->orderBy('search_user.username', $order);
                    } else {
                        if (property_exists(Demande::class, $column)) {
                            $qb->orderBy("d.$column", $order);
                        }
                    }
                }
            }
        }

		// compte éléments filtrés
		$countFiltered = QueryCounter::count($qb, 'd');

		if ($params) {
			if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
			if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
		}

        return [
        	'data' => $qb->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    /**
     * @param $search
     * @return mixed
     */
    public function getIdAndLibelleBySearch($search)
    {
        return $this->createQueryBuilder('demande')
            ->select('demande.id')
            ->addSelect('demande.numero AS text')
            ->andWhere('demande.numero LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->getQuery()
            ->execute();
    }



    public function getOlderDateToTreat(array $types = [],
                                        array $statuses = []): ?DateTime {
        if (!empty($statuses)) {
            $statusesStr = implode(',', $statuses);
            $typesStr = implode(',', $types);
            $query = $this->getEntityManager()
                ->getConnection()
                ->executeQuery("
                    SELECT preparation_date.date
                    FROM demande
                    INNER JOIN (
                        SELECT sub_demande.id AS demande_id,
                               MAX(preparation.date) AS date
                        FROM demande AS sub_demande
                        INNER JOIN preparation ON preparation.demande_id = sub_demande.id
                        WHERE sub_demande.type_id IN (${typesStr})
                          AND sub_demande.statut_id IN (${statusesStr})
                        GROUP BY sub_demande.id
                    ) AS preparation_date ON preparation_date.demande_id = demande.id
                    ORDER BY preparation_date.date ASC
                    LIMIT 1
                ");

            $res = $query->fetchColumn();
            return $res
                ? (DateTime::createFromFormat('Y-m-d H:i:s', $res) ?: null)
                : null;
        }
        else {
            return null;
        }
    }

}
