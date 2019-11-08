<?php

namespace App\Repository;

use App\Entity\Demande;
use App\Entity\Livraison;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

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

    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Demande::class);
    }

	/**
	 * @param Livraison $livraison
	 * @return Demande|null
	 * @throws \Doctrine\ORM\NonUniqueResultException
	 */
	public function findOneByLivraison($livraison)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			'SELECT d
            FROM App\Entity\Demande d
            WHERE d.livraison = :livraison'
		)->setParameter('livraison', $livraison);

		return $query->getOneOrNullResult();
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

    public function findOneByPreparation($preparation)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\Demande a
            WHERE a.preparation = :preparation'
        )->setParameter('preparation', $preparation);
        return $query->getOneOrNullResult();
    }

	/**
	 * @param string $dateMin
	 * @param string $dateMax
	 * @return Demande[]|null
	 */
    public function findByDates($dateMin, $dateMax)
    {
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
	 * @throws \Doctrine\ORM\NonUniqueResultException
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

	public function findByParamsAndFilters($params, $filters)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('d')
            ->from('App\Entity\Demande', 'd');

        $countTotal = count($qb->getQuery()->getResult());

		// filtres sup
		foreach ($filters as $filter) {
			switch($filter['field']) {
				case 'statut':
					$qb
						->join('d.statut', 's')
						->andWhere('s.nom = :statut')
						->setParameter('statut', $filter['value']);
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
						->andWhere("u.username in (:username)")
						->setParameter('username', $value);
					break;
				case 'dateMin':
					$qb->andWhere('d.date >= :dateMin')
						->setParameter('dateMin', $filter['value']);
					break;
				case 'dateMax':
					$qb->andWhere('d.date <= :dateMax')
						->setParameter('dateMax', $filter['value']);
					break;
			}
		}

		//Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('d.numero LIKE :value OR d.date LIKE :value')
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