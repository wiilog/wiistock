<?php

namespace App\Repository;

use App\Entity\Collecte;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Collecte|null find($id, $lockMode = null, $lockVersion = null)
 * @method Collecte|null findOneBy(array $criteria, array $orderBy = null)
 * @method Collecte[]    findAll()
 * @method Collecte[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CollecteRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Collecte::class);
    }

    public function getByStatutAndUser($statut, $user)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c
            FROM App\Entity\Collecte c
            WHERE c.statut = :statut AND c.demandeur = :user "
        )->setParameters([
            'statut' => $statut,
            'user' => $user,
        ]);
        return $query->execute();
    }

    public function countByStatut($statut)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(c)
            FROM App\Entity\Collecte c
            WHERE c.statut = :statut "
        )->setParameter('statut', $statut);
        return $query->getSingleScalarResult();
    }

    public function countByEmplacement($emplacementId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(c)
            FROM App\Entity\Collecte c
            JOIN c.pointCollecte pc
            WHERE pc.id = :emplacementId"
        )->setParameter('emplacementId', $emplacementId);

        return $query->getSingleScalarResult();
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
			"SELECT COUNT(c)
            FROM App\Entity\Collecte c
            WHERE c.demandeur = :user"
		)->setParameter('user', $user);

		return $query->getSingleScalarResult();
	}

	public function findByParamsAndFilters($params, $filters = [])
	{
		$em = $this->getEntityManager();
		$qb = $em->createQueryBuilder();

		$qb
			->select('c')
			->from('App\Entity\Collecte', 'c');

		$countQuery = $countTotal = count($qb->getQuery()->getResult());

		$allDemandeDataTable = null;
		//Filter search
		if (!empty($params)) {
			if (!empty($params->get('search'))) {
				$search = $params->get('search')['value'];
				if (!empty($search)) {
					$qb
						->andWhere('c.objet LIKE :value')
						->setParameter('value', '%' . $search . '%');
				}
				$countQuery = count($qb->getQuery()->getResult());
			}
			$allDemandeDataTable = $qb->getQuery();
			if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
			if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
		}
		//Filter by date
		if (!empty($params->get('dateMin'))) {
			$qb
				->andWhere('c.date >= :dateMin')
				->setParameter('dateMin', $params->get('dateMin'));
			$countQuery = count($qb->getQuery()->getResult());
			$allDemandeDataTable = $qb->getQuery();
		}
		if (!empty($params->get('dateMax'))) {
			$qb
				->andWhere('c.date <= :dateMax')
				->setParameter('dateMax', $params->get('dateMax'));
			$countQuery = count($qb->getQuery()->getResult());
			$allDemandeDataTable = $qb->getQuery();
		}
		//Filter by statut
		if (!empty($params->get('statut'))) {
			$qb
				->join('c.statut', 's')
				->andWhere('s.nom = :statut')
				->setParameter('statut', $params->get('statut'));
			$countQuery = count($qb->getQuery()->getResult());
			$allDemandeDataTable = $qb->getQuery();
		}
		//Filter by user
		if (!empty($params->get('user'))) {
			$arrayUser = explode(',', $params->get('user'));
			$qb->join('c.demandeur', 'd');
			$qb
				->andWhere('d.username IN (:user)')
				->setParameter('user', $arrayUser);
			$countQuery = count($qb->getQuery()->getResult());
			$allDemandeDataTable = $qb->getQuery();
		}
		//Filter by type
		if (!empty($params->get('type'))) {
			$qb
				->join('c.type', 't')
				->andWhere('t.label = :type')
				->setParameter('type', $params->get('type'));
			$countQuery = count($qb->getQuery()->getResult());
			$allDemandeDataTable = $qb->getQuery();
		}

		// filtres sup
		foreach ($filters as $filter) {
			switch($filter['field']) {
				case 'statut':
					$qb
						->join('c.statut', 's')
						->andWhere('s.nom = :statut')
						->setParameter('statut', $filter['value']);
					break;
				case 'type':
					$qb
						->join('c.type', 't')
						->andWhere('t.label = :type')
						->setParameter('type', $filter['value']);
					break;
				case 'utilisateurs':
					$value = explode(',', $filter['value']);
					$qb
						->join('c.demandeur', 'd')
						->andWhere("d.username in (:username)")
						->setParameter('username', $value);
					break;
				case 'dateMin':
					$qb->andWhere('c.date >= :dateMin')
						->setParameter('dateMin', $filter['value']);
					break;
				case 'dateMax':
					$qb->andWhere('c.date <= :dateMax')
						->setParameter('dateMax', $filter['value']);
					break;
			}
		}

		$query = $qb->getQuery();

		return ['data' => $query ? $query->getResult() : null ,
			'allDemandeDataTable' => $allDemandeDataTable ? $allDemandeDataTable->getResult() : null,
			'count' => $countQuery,
			'total' => $countTotal
		];
	}
}
