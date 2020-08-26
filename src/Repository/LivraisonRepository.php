<?php

namespace App\Repository;

use App\Entity\FiltreSup;
use App\Entity\Livraison;
use App\Entity\Type;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;

/**
 * @method Livraison|null find($id, $lockMode = null, $lockVersion = null)
 * @method Livraison|null findOneBy(array $criteria, array $orderBy = null)
 * @method Livraison[]    findAll()
 * @method Livraison[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LivraisonRepository extends EntityRepository
{
	const DtToDbLabels = [
		'Numéro' => 'numero',
		'Statut' => 'statut',
		'Date' => 'date',
		'Opérateur' => 'utilisateur',
		'Type' => 'type'
	];

    public function countByEmplacement($emplacementId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(l)
            FROM App\Entity\Livraison l
            JOIN l.destination dest
            WHERE dest.id = :emplacementId"
        )->setParameter('emplacementId', $emplacementId);

        return $query->getSingleScalarResult();
    }

    /**
     * @param Utilisateur $user
     * @return array[]
     */
	public function getMobileDelivery(Utilisateur $user)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			"SELECT l.id,
                    l.numero as number,
                    dest.label as location,
                    t.label as type,
                    user.username as requester
			FROM App\Entity\Livraison l
			JOIN l.statut s
			JOIN l.preparation preparation
			JOIN preparation.demande demande
			JOIN demande.destination dest
			JOIN demande.type t
			JOIN demande.utilisateur user
			WHERE (s.nom = :statusLabel AND (l.utilisateur is null or l.utilisateur = :user)) AND t.id IN (:typeIds)"
		)->setParameters([
			'statusLabel' => Livraison::STATUT_A_TRAITER,
			'user' => $user,
            'typeIds' => $user->getDeliveryTypeIds()
		]);

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
			"SELECT COUNT(l)
            FROM App\Entity\Livraison l
            WHERE l.utilisateur = :user"
		)->setParameter('user', $user);

		return $query->getSingleScalarResult();
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
			->select('livraison')
			->from('App\Entity\Livraison', 'livraison')
            ->join('livraison.preparation', 'preparation')
            ->join('preparation.demande', 'demande');

		$countTotal = count($qb->getQuery()->getResult());

		// filtres sup
		foreach ($filters as $filter) {
			switch ($filter['field']) {
				case FiltreSup::FIELD_STATUT:
					$value = explode(',', $filter['value']);
					$qb
						->join('livraison.statut', 's')
						->andWhere('s.id in (:statut)')
						->setParameter('statut', $value);
					break;
                case FiltreSup::FIELD_TYPE:
					$qb
						->leftJoin('demande.type', 'type')
						->andWhere('type.label = :type')
						->setParameter('type', $filter['value']);
					break;
				case FiltreSup::FIELD_USERS:
					$value = explode(',', $filter['value']);
					$qb
						->join('livraison.utilisateur', 'user')
						->andWhere("user.id in (:userId)")
						->setParameter('userId', $value);
					break;
				case FiltreSup::FIELD_DEMANDE:
                    $qb
                        ->andWhere('demande.id = :id')
                        ->setParameter('id', $filter['value']);
                    break;
				case FiltreSup::FIELD_DATE_MIN:
					$qb
						->andWhere('livraison.date >= :dateMin')
						->setParameter('dateMin', $filter['value'] . " 00:00:00");
					break;
				case FiltreSup::FIELD_DATE_MAX:
					$qb
						->andWhere('livraison.date <= :dateMax')
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
						->leftJoin('livraison.statut', 's2')
						->leftJoin('livraison.utilisateur', 'u2')
						->leftJoin('demande.type', 't2')
						->andWhere('
						livraison.numero LIKE :value
						OR s2.nom LIKE :value
						OR u2.username LIKE :value
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

					if ($column === 'statut') {
						$qb
							->leftJoin('livraison.statut', 's3')
							->orderBy('s3.nom', $order);
					} else if ($column === 'utilisateur') {
						$qb
							->leftJoin('livraison.utilisateur', 'u3')
							->orderBy('u3.username', $order);
					} else if ($column === 'type') {
						$qb
							->leftJoin('demande.type', 't3')
							->orderBy('t3.label', $order);
					} else {
						$qb
							->orderBy('livraison.' . $column, $order);
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


	/**
	 * @param DateTime $dateMin
	 * @param DateTime $dateMax
	 * @return Livraison[]|null
	 */
	public function findByDates($dateMin, $dateMax)
	{
		$dateMax = $dateMax->format('Y-m-d H:i:s');
		$dateMin = $dateMin->format('Y-m-d H:i:s');

		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			'SELECT l
            FROM App\Entity\Livraison l
            WHERE l.date BETWEEN :dateMin AND :dateMax'
		)->setParameters([
			'dateMin' => $dateMin,
			'dateMax' => $dateMax
		]);
		return $query->execute();
	}

    public function countByNumero(string $numero) {
        $queryBuilder = $this
            ->createQueryBuilder('livraison')
            ->select('COUNT(livraison.id) AS counter')
            ->where('livraison.numero = :numero')
            ->setParameter('numero', $numero . '%');

        $result = $queryBuilder
            ->getQuery()
            ->getResult();

        return !empty($result) ? ($result[0]['counter'] ?? 0) : 0;
    }

    public function getNumeroLivraisonGroupByDemande (array $demandes)
    {
        $queryBuilder = $this->createQueryBuilder('livraison')
            ->select('demande.id AS demandeId')
            ->addSelect('livraison.numero AS numeroLivraison')
            ->join('livraison.preparation', 'preparation')
            ->join('preparation.demande', 'demande')
            ->where('preparation.demande in (:demandes)')
            ->setParameter('demandes', $demandes);

        $result = $queryBuilder->getQuery()->execute();
        return array_reduce($result, function (array $carry, $current) {

            $demandeId = $current['demandeId'];
            $numeroLivraison = $current['numeroLivraison'];
            if (!isset($carry[$demandeId])) {
                $carry[$demandeId] = [];
            }
            $carry[$demandeId][] = $numeroLivraison;
            return $carry;
        }, []);
    }
}
