<?php

namespace App\Repository;

use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\Preparation;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method MouvementStock|null find($id, $lockMode = null, $lockVersion = null)
 * @method MouvementStock|null findOneBy(array $criteria, array $orderBy = null)
 * @method MouvementStock[]    findAll()
 * @method MouvementStock[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MouvementStockRepository extends ServiceEntityRepository
{
	private const DtToDbLabels = [
		'date' => 'date',
		'refArticle' => 'refArticle',
		'quantite' => 'quantity',
		'origine' => 'emplacementFrom',
		'destination' => 'emplacementTo',
		'type' => 'type',
		'operateur' => 'user',
	];

	private $statutRepository;

    public function __construct(ManagerRegistry $registry, StatutRepository $statutRepository)
    {
        parent::__construct($registry, MouvementStock::class);

        $this->statutRepository = $statutRepository;
    }

    public function countByEmplacement($emplacementId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            /** @lang DQL */
            "SELECT COUNT(m)
            FROM App\Entity\MouvementStock m
            JOIN m.emplacementFrom ef
            JOIN m.emplacementTo et
            WHERE ef.id = :emplacementId OR et.id =:emplacementId"
        )->setParameter('emplacementId', $emplacementId);
        return $query->getSingleScalarResult();
    }

	/**
	 * @param Preparation $preparation
	 * @return MouvementStock[]
	 */
    public function findByPreparation($preparation)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT m
            FROM App\Entity\MouvementStock m
            WHERE m.preparationOrder = :preparation"
		)->setParameter('preparation', $preparation);

		return $query->execute();
	}

	/**
	 * @param Livraison $livraison
	 * @return MouvementStock[]
	 */
	public function findByLivraison($livraison)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT m
            FROM App\Entity\MouvementStock m
            WHERE m.livraisonOrder = :livraison"
		)->setParameter('livraison', $livraison);

		return $query->execute();
	}

	/**
	 * @param DateTime $dateMin
	 * @param DateTime $dateMax
	 * @return MouvementStock[]
	 * @throws Exception
	 */
	public function findByDates($dateMin, $dateMax)
	{
		$dateMax = $dateMax->format('Y-m-d H:i:s');
		$dateMin = $dateMin->format('Y-m-d H:i:s');

		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			'SELECT m
            FROM App\Entity\MouvementStock m
            WHERE m.date BETWEEN :dateMin AND :dateMax'
		)->setParameters([
			'dateMin' => $dateMin,
			'dateMax' => $dateMax
		]);
		return $query->execute();
	}

	/**
	 * @param string[] $types
	 * @return int
	 * @throws NonUniqueResultException
	 * @throws NoResultException
	 */
	public function countByTypes($types, $dateDebut = '', $dateFin = '')
    {
        $em = $this->getEntityManager();

        $dql = "SELECT COUNT(m)
            FROM App\Entity\MouvementStock m
            WHERE m.type IN (:types)";


        if(!empty($dateDebut))
        {
            $dql .= " AND m.date > :dateDebut";
        }

        if(!empty($dateFin))
        {
            $dql .= " AND m.date < :dateFin";
        }
        $query = $em->createQuery(
            $dql
        );

        $query->setParameter('types', $types,Connection::PARAM_STR_ARRAY);
        if (!empty($dateDebut))
        {
            $query->setParameter('dateDebut', $dateDebut);
        }

        if (!empty($dateFin))
        {
            $query->setParameter('dateFin', $dateFin);
        }


        $query = $em->createQuery(
        /** @lang DQL */
        "SELECT COUNT(m)
            FROM App\Entity\MouvementStock m
            WHERE m.type
            IN (:types)"
        )->setParameter('types', $types);
        return $query->getSingleScalarResult();
    }

    public function countTotalEntryPriceRefArticle($dateDebut = '', $dateFin = '')
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('SUM(m.quantity * ra.prixUnitaire)')
            ->from('App\Entity\MouvementStock', 'm')
            ->join('m.refArticle', 'ra');

        if($dateDebut == '' && $dateFin == '')
        {
            $qb
                ->where('m.type = :entreeInv')
                ->setParameter('entreeInv', MouvementStock::TYPE_INVENTAIRE_ENTREE);
        }
        else if(!empty($dateDebut) && $dateFin == '')
        {
            $qb
                ->where('m.type = :entreeInv AND m.date > :dateDebut')
                ->setParameters(['entreeInv' => MouvementStock::TYPE_INVENTAIRE_ENTREE,
                    'dateDebut' => $dateDebut]);
        }
        else if (!empty($dateDebut) && !empty($dateFin))
        {
            $qb
                ->where('m.type = :entreeInv AND m.date BETWEEN :dateDebut AND :dateFin')
                ->setParameters(['entreeInv' => MouvementStock::TYPE_INVENTAIRE_ENTREE,
                    'dateDebut' => $dateDebut,
                    'dateFin' => $dateFin]);
        }
        $query = $qb->getQuery();
        return $query->getSingleScalarResult();
    }

    public function countTotalExitPriceRefArticle($dateDebut = '', $dateFin = '')
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('SUM(m.quantity * ra.prixUnitaire)')
            ->from('App\Entity\MouvementStock', 'm')
            ->join('m.refArticle', 'ra');

        if($dateDebut == '' && $dateFin == '')
        {
            $qb
                ->where('m.type = :sortieInv')
                ->setParameter('sortieInv', MouvementStock::TYPE_INVENTAIRE_SORTIE);
        }
        else if(!empty($dateDebut) && $dateFin == '')
        {
            $qb
                ->where('m.type = :sortieInv AND m.date > :dateDebut')
                ->setParameters(['sortieInv'=> MouvementStock::TYPE_INVENTAIRE_SORTIE,
                    'dateDebut' => $dateDebut]);
        }
        else if (!empty($dateDebut) && !empty($dateFin))
        {
            $qb
                ->where('m.type = :sortieInv AND m.date BETWEEN :dateDebut AND :dateFin')
                ->setParameters(['sortieInv'=> MouvementStock::TYPE_INVENTAIRE_SORTIE,
                    'dateDebut' => $dateDebut,
                    'dateFin' => $dateFin]);
        }
        $query = $qb->getQuery();
        return $query->getSingleScalarResult();
    }

    public function countTotalEntryPriceArticle($dateDebut = '', $dateFin = '')
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('SUM(m.quantity * a.prixUnitaire)')
            ->from('App\Entity\MouvementStock', 'm')
            ->join('m.article', 'a');

        if($dateDebut == '' && $dateFin == '')
        {
            $qb
                ->where('m.type = :entreeInv')
                ->setParameter('entreeInv', MouvementStock::TYPE_INVENTAIRE_ENTREE);
        }
        else if(!empty($dateDebut) && $dateFin == '')
        {
            $qb
                ->where('m.type = :entreeInv AND m.date > :dateDebut')
                ->setParameters(['entreeInv' => MouvementStock::TYPE_INVENTAIRE_ENTREE,
                    'dateDebut' => $dateDebut]);
        }
        else if (!empty($dateDebut) && !empty($dateFin))
        {
            $qb
                ->where('m.type = :entreeInv AND m.date BETWEEN :dateDebut AND :dateFin')
                ->setParameters(['entreeInv' => MouvementStock::TYPE_INVENTAIRE_ENTREE,
                    'dateDebut' => $dateDebut,
                    'dateFin' => $dateFin]);
        }
        $query = $qb->getQuery();
        return $query->getSingleScalarResult();
    }

    public function countTotalExitPriceArticle($dateDebut = '', $dateFin = '')
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('SUM(m.quantity * a.prixUnitaire)')
            ->from('App\Entity\MouvementStock', 'm')
            ->join('m.article', 'a');

        if($dateDebut == '' && $dateFin == '')
        {
            $qb
                ->where('m.type = :sortieInv')
                ->setParameter('sortieInv', MouvementStock::TYPE_INVENTAIRE_SORTIE);
        }
        else if(!empty($dateDebut) && $dateFin == '')
        {
            $qb
                ->where('m.type = :sortieInv AND m.date > :dateDebut')
                ->setParameters(['sortieInv'=> MouvementStock::TYPE_INVENTAIRE_SORTIE,
                    'dateDebut' => $dateDebut]);
        }
        else if (!empty($dateDebut) && !empty($dateFin))
        {
            $qb
                ->where('m.type = :sortieInv AND m.date BETWEEN :dateDebut AND :dateFin')
                ->setParameters(['sortieInv'=> MouvementStock::TYPE_INVENTAIRE_SORTIE,
                    'dateDebut' => $dateDebut,
                    'dateFin' => $dateFin]);
        }
        $query = $qb->getQuery();
        return $query->getSingleScalarResult();
    }

	public function findByRef($id)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT m
            FROM App\Entity\MouvementStock m
            WHERE m.refArticle = :id"
		)->setParameter('id', $id);

		return $query->execute();
	}

    /**
     * @param $idRef
     * @param $idPrep
     * @return MouvementStock | null
     * @throws NonUniqueResultException
     */
    public function findOneByRefAndPrepa($idRef, $idPrep)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT m
            FROM App\Entity\MouvementStock m
            WHERE m.refArticle = :id AND m.preparationOrder = :idP"
        )->setParameters([
            'id' => $idRef,
            'idP' => $idPrep
        ]);

        return $query->getOneOrNullResult();
    }

    /**
     * @param $idArt
     * @param $idPrep
     * @return MouvementStock | null
     * @throws NonUniqueResultException
     */
    public function findByArtAndPrepa($idArt, $idPrep)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT m
            FROM App\Entity\MouvementStock m
            WHERE m.article = :id AND m.preparationOrder = :idP"
        )->setParameters([
            'id' => $idArt,
            'idP' => $idPrep
        ]);

        return $query->getOneOrNullResult();
    }

	/**
	 * @param array|null $params
	 * @param array|null $filters
	 * @return array
	 * @throws Exception
	 */
	public function findByParamsAndFilters($params, $filters)
	{
		$em = $this->getEntityManager();
		$qb = $em->createQueryBuilder();

		$qb
			->select('m')
			->from('App\Entity\MouvementStock', 'm');

		$countTotal = count($qb->getQuery()->getResult());

		// filtres sup
		foreach ($filters as $filter) {
			switch($filter['field']) {
				case 'statut':
                    $types = explode(',', $filter['value']);
                    $typeIds = array_map(function($type) {
                        $splitted = explode(':', $type);
                        return $splitted[1] ?? $type;
                    }, $types);
                    $qb
                        ->andWhere('m.type in (:typeIds)')
                        ->setParameter('typeIds', $typeIds, Connection::PARAM_STR_ARRAY);
					break;
				case 'emplacement':
                    $value = explode(':', $filter['value']);
					$qb
						->leftJoin('m.emplacementFrom', 'ef')
						->leftJoin('m.emplacementTo', 'et')
						->andWhere('ef.label = :location OR et.label = :location')
						->setParameter('location', $value[1] ?? $filter['value']);
					break;
				case 'utilisateurs':
					$value = explode(',', $filter['value']);
					$qb
						->join('m.user', 'u')
						->andWhere("u.id in (:userId)")
						->setParameter('userId', $value);
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
						->leftJoin('m.refArticle', 'ra3')
						->leftJoin('m.emplacementFrom', 'ef3')
						->leftJoin('m.emplacementTo', 'et3')
						->leftJoin('m.user', 'u3')
						->andWhere('
						ra3.reference LIKE :value OR
						ef3.label LIKE :value OR
						et3.label LIKE :value OR
						m.type LIKE :value OR
						u3.username LIKE :value
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

					if ($column === 'refArticle') {
						$qb
							->leftJoin('m.refArticle', 'ra2')
							->orderBy('ra2.reference', $order);
					} else if ($column === 'emplacementFrom') {
						$qb
							->leftJoin('m.emplacementFrom', 'ef2')
							->orderBy('ef2.label', $order);
					} else if ($column === 'emplacementTo') {
						$qb
							->leftJoin('m.emplacementTo', 'et2')
							->orderBy('et2.label', $order);
					} else if ($column === 'user') {
						$qb
							->leftJoin('m.user', 'u2')
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
			'data' => $query ? $query->getResult() : null ,
			'count' => $countFiltered,
			'total' => $countTotal
		];
	}

}
