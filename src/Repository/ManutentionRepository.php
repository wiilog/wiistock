<?php

namespace App\Repository;

use App\Entity\Manutention;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Manutention|null find($id, $lockMode = null, $lockVersion = null)
 * @method Manutention|null findOneBy(array $criteria, array $orderBy = null)
 * @method Manutention[]    findAll()
 * @method Manutention[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ManutentionRepository extends ServiceEntityRepository
{

    private const DtToDbLabels = [
        'Date demande' => 'date',
        'Demandeur' => 'demandeur',
        'Libellé' => 'libelle',
        'Date souhaitée' => 'dateAttendue',
        'Date de réalisation' => 'dateEnd',
        'Statut' => 'statut',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Manutention::class);
    }

    public function countByStatut($statut){
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        	/** @lang DQL */
            "SELECT COUNT(m)
            FROM App\Entity\Manutention m
            WHERE m.statut = :statut
           "
            )->setParameter('statut', $statut);
        return $query->getSingleScalarResult();
    }

    public function findByStatut($statut) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @Lang DQL */
        "SELECT m.id, m.dateAttendue as dateAttendueDT, d.username as demandeur, m.commentaire, m.source, m.destination, m.libelle as objet
        FROM App\Entity\Manutention m
        JOIN m.demandeur d
        WHERE m.statut = :statut
        "
        )->setParameter('statut', $statut);
        return $query->execute();
    }

    public function findOneForAPI($id) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @Lang DQL */
            "SELECT m.id, m.dateAttendue as date_attendue, d.username as demandeur, m.commentaire, m.source, m.destination
        FROM App\Entity\Manutention m
        JOIN m.demandeur d
        WHERE m.id = :id
        "
        )->setParameter('id', $id);
        return $query->getOneOrNullResult();
    }

	/**
	 * @param Utilisateur $user
	 * @return int
	 * @throws NonUniqueResultException
	 */
	public function countByUser($user)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT COUNT(m)
            FROM App\Entity\Manutention m
            WHERE m.demandeur = :user"
		)->setParameter('user', $user);

		return $query->getSingleScalarResult();
	}

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return Manutention[]|null
     */
    public function findByDates($dateMin, $dateMax)
    {
        $dateMax = $dateMax->format('Y-m-d H:i:s');
        $dateMin = $dateMin->format('Y-m-d H:i:s');

        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT m
            FROM App\Entity\Manutention m
            WHERE m.date BETWEEN :dateMin AND :dateMax'
        )->setParameters([
            'dateMin' => $dateMin,
            'dateMax' => $dateMax
        ]);
        return $query->execute();
    }

	public function findByParamAndFilters($params, $filters)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
			->select('m')
            ->from('App\Entity\Manutention', 'm');

        $countTotal = count($qb->getQuery()->getResult());

        // filtres sup
        foreach ($filters as $filter) {
            switch($filter['field']) {
                case 'statut':
					$value = explode(',', $filter['value']);
					$qb
						->join('m.statut', 's')
						->andWhere('s.id in (:statut)')
						->setParameter('statut', $value);
					break;
                    break;
                case 'utilisateurs':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('m.demandeur', 'd')
                        ->andWhere("d.id in (:username)")
                        ->setParameter('username', $value);
                    break;
                case 'dateMin':
                    $qb->andWhere('m.date >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb->andWhere('m.date <= :dateMax')
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
						->andWhere('m.libelle LIKE :value OR m.date LIKE :value')
						->setParameter('value', '%' . $search . '%');
				}
			}
            if (!empty($params->get('order')))
            {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order))
                {
                    $column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];
                    if ($column === 'statut') {
                        $qb
                            ->leftJoin('m.statut', 's2')
                            ->orderBy('s2.nom', $order);
                    } else if ($column === 'demandeur') {
                        $qb
                            ->leftJoin('m.demandeur', 'u2')
                            ->orderBy('u2.username', $order);
                    } else {
                        $qb
                            ->orderBy('m.' . $column, $order);
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
        	'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }
}
