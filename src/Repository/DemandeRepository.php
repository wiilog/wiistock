<?php

namespace App\Repository;

use App\Entity\Demande;
use App\Entity\Livraison;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Demande|null find($id, $lockMode = null, $lockVersion = null)
 * @method Demande|null findOneBy(array $criteria, array $orderBy = null)
 * @method Demande[]    findAll()
 * @method Demande[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DemandeRepository extends ServiceEntityRepository
{
    private const DtToDbLabels = [
        'Date' => 'date',
        'Demandeur' => 'demandeur',
        'Statut' => 'statut',
        'Numéro' => 'numero',
        'Type' => 'type',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Demande::class);
    }

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
			ORDER BY d.date DESC'
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
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('d')
            ->from('App\Entity\Demande', 'd');

        $countTotal = count($qb->getQuery()->getResult());

        if ($receptionFilter) {
            $qb
                ->join('d.reception', 'r')
                ->andWhere('r.id = :reception')
                ->setParameter('reception', $receptionFilter);
        }

		// filtres sup
		foreach ($filters as $filter) {
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

		//Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
						->join('d.statut', 's2')
						->join('d.type', 't2')
						->join('d.utilisateur', 'u2')
                        ->andWhere('
                  		d.date LIKE :value
                  		OR u2.username LIKE :value
                        OR d.numero LIKE :value
               			OR s2.nom LIKE :value
               			OR t2.label LIKE :value
                        ')
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
                            ->leftJoin('d.type', 't2')
                            ->orderBy('t2.label', $order);
                    } else if ($column === 'statut') {
                        $qb
                            ->leftJoin('d.statut', 's2')
                            ->orderBy('s2.nom', $order);
                    } else if ($column === 'demandeur') {
                        $qb
                            ->leftJoin('d.utilisateur', 'u2')
                            ->orderBy('u2.username', $order);
                    } else {
                        $qb
                            ->orderBy('d.' . $column, $order);
                    }
                }
            }
        }

		// compte éléments filtrés
		$countFiltered = count($qb->getQuery()->getResult());

		if ($params) {
			if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
			if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
		}

		$query = $qb->getQuery();

        return [
        	'data' => $query ? $query->getResult() : null ,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }
}
