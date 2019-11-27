<?php

namespace App\Repository;

use App\Entity\Arrivage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bridge\Doctrine\RegistryInterface;

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

    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Arrivage::class);
    }

	/**
	 * @param string $dateMin
	 * @param string $dateMax
	 * @return Arrivage[]|null
	 */
    public function findByDates($dateMin, $dateMax)
    {
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
	 * @param array|null $params
	 * @param array|null $filters
	 * @param int|null $userId
	 * @return array
	 * @throws \Exception
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
		foreach ($filters as $filter) {
			switch($filter['field']) {
				//TODO CG
				case 'statut':
//					$subQb2 = $em->createQueryBuilder();
//					$subQb2
//						->select('count(l2)')
//						->from('App\Entity\Arrivage', 'a4')
//						->leftJoin('a4.colis', 'col')
//						->leftJoin('col.litiges', 'l2')
//						->leftJoin('l2.status', 's2')
//						->andWhere('s2.treated = 0 OR s2.treated is null')
//						->groupBy('a4.id');
//					$query2 = $subQb2->getQuery()->getDQL();
//dump($subQb2->getQuery()->getSQL());
//					$qb
//						->addSelect('(' . $query2 . ') as nbLitiges')
//						->andWhere('nbLitiges > 0');
//dump($qb->getQuery()->getSQL());

				case 'utilisateurs':
					$value = explode(',', $filter['value']);
					$qb
						->join('a.destinataire', 'dest')
						->andWhere("dest.id in (:userId)")
						->setParameter('userId', $value);
					break;
				case 'dateMin':
					$qb->andWhere('a.date >= :dateMin')
						->setParameter('dateMin', $filter['value']. " 00:00:00");
					break;
				case 'dateMax':
					$qb->andWhere('a.date <= :dateMax')
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

			if (!empty($params->get('order')))
			{
				$order = $params->get('order')[0]['dir'];
				if (!empty($order))
				{
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
					} else {
						$qb
							->orderBy('a.' . $column, $order);
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
