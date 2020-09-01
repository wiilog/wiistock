<?php

namespace App\Repository;

use App\Entity\Handling;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Handling|null find($id, $lockMode = null, $lockVersion = null)
 * @method Handling|null findOneBy(array $criteria, array $orderBy = null)
 * @method Handling[]    findAll()
 * @method Handling[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HandlingRepository extends ServiceEntityRepository
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
        parent::__construct($registry, Handling::class);
    }

    public function countByStatut($statut){
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        	/** @lang DQL */
            "SELECT COUNT(h)
            FROM App\Entity\Handling h
            WHERE h.statut = :statut
           "
            )->setParameter('statut', $statut);
        return $query->getSingleScalarResult();
    }

    public function findByStatut($statut) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @Lang DQL */
            "SELECT h.id, h.dateAttendue as dateAttendueDT, d.username as demandeur, h.commentaire, h.source, h.destination, h.libelle as objet
        FROM App\Entity\Handling h
        JOIN h.demandeur d
        WHERE h.statut = :statut
        "
        )->setParameter('statut', $statut);
        return $query->execute();
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
            "SELECT COUNT(h)
            FROM App\Entity\Handling h
            WHERE h.demandeur = :user"
		)->setParameter('user', $user);

		return $query->getSingleScalarResult();
	}

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return Handling[]|null
     */
    public function findByDates($dateMin, $dateMax)
    {
        $dateMax = $dateMax->format('Y-m-d H:i:s');
        $dateMin = $dateMin->format('Y-m-d H:i:s');

        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT h
            FROM App\Entity\Handling h
            WHERE h.date BETWEEN :dateMin AND :dateMax'
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
			->select('h')
            ->from('Handling', 'h');

        $countTotal = count($qb->getQuery()->getResult());

        // filtres sup
        foreach ($filters as $filter) {
            switch($filter['field']) {
                case 'statut':
					$value = explode(',', $filter['value']);
					$qb
						->join('h.statut', 's')
						->andWhere('s.id in (:statut)')
						->setParameter('statut', $value);
					break;
                    break;
                case 'utilisateurs':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('h.demandeur', 'd')
                        ->andWhere("d.id in (:username)")
                        ->setParameter('username', $value);
                    break;
                case 'dateMin':
                    $qb->andWhere('h.date >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb->andWhere('h.date <= :dateMax')
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
						->andWhere('h.libelle LIKE :value OR h.date LIKE :value')
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
                            ->leftJoin('h.statut', 's2')
                            ->orderBy('s2.nom', $order);
                    } else if ($column === 'demandeur') {
                        $qb
                            ->leftJoin('h.demandeur', 'u2')
                            ->orderBy('u2.username', $order);
                    } else {
                        $qb
                            ->orderBy('h.' . $column, $order);
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
