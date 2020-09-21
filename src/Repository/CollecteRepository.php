<?php

namespace App\Repository;

use App\Entity\Collecte;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * @method Collecte|null find($id, $lockMode = null, $lockVersion = null)
 * @method Collecte|null findOneBy(array $criteria, array $orderBy = null)
 * @method Collecte[]    findAll()
 * @method Collecte[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CollecteRepository extends EntityRepository
{
    private const DtToDbLabels = [
        'Création' => 'date',
        'Validation' => 'validationDate',
        'Demandeur' => 'demandeur',
        'Numéro' => 'numero',
        'Objet' => 'objet',
        'Statut' => 'statut',
        'Type' => 'type',
    ];

    public function findByStatutLabelAndUser($statutLabel, $user)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c
            FROM App\Entity\Collecte c
            JOIN c.statut s
            WHERE s.nom = :statutLabel AND c.demandeur = :user "
        )->setParameters([
            'statutLabel' => $statutLabel,
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
     * @throws NonUniqueResultException
     * @throws NoResultException
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

    public function findByParamsAndFilters($params, $filters)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('c')
            ->from('App\Entity\Collecte', 'c');

        $countTotal = count($qb->getQuery()->getResult());

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statut':
					$value = explode(',', $filter['value']);
					$qb
						->join('c.statut', 's')
						->andWhere('s.id in (:statut)')
						->setParameter('statut', $value);
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
                        ->andWhere("d.id in (:id)")
                        ->setParameter('id', $value);
                    break;
                case 'dateMin':
                    $qb->andWhere('c.date >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb->andWhere('c.date <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
            }
        }

        //Filter search
        if (!empty($params)) {
			if (!empty($params->get('search'))) {
				$search = $params->get('search')['value'];
				if (!empty($search)) {
                    $exprBuilder = $qb->expr();
					$qb
						->andWhere(
                            $exprBuilder->orX(
						        'c.objet LIKE :value',
						        'c.numero LIKE :value',
						        'demandeur_search.username LIKE :value',
						        'type_search.label LIKE :value',
						        'statut_search.nom LIKE :value'
                            )
                        )
						->setParameter('value', '%' . $search . '%')
                        ->leftJoin('c.demandeur', 'demandeur_search')
                        ->leftJoin('c.type', 'type_search')
                        ->leftJoin('c.statut', 'statut_search');
				}
			}

			if (!empty($params->get('order'))) {
				$order = $params->get('order')[0]['dir'];
				if (!empty($order)) {
					$column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];

					switch ($column) {
						case 'type':
							$qb
								->leftJoin('c.type', 't2')
								->orderBy('t2.label', $order);
							break;
						case 'statut':
							$qb
								->leftJoin('c.statut', 's2')
								->orderBy('s2.nom', $order);
							break;
						case 'demandeur':
							$qb
								->leftJoin('c.demandeur', 'd2')
								->orderBy('d2.username', $order);
							break;
						default:
							$qb->orderBy('c.' . $column, $order);
							break;
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

	public function getIdAndLibelleBySearch($search)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT c.id, c.numero as text
          FROM App\Entity\Collecte c
          WHERE c.numero LIKE :search"
		)->setParameter('search', '%' . $search . '%');

		return $query->execute();
	}

}
