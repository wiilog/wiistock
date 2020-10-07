<?php

namespace App\Repository;

use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Utilisateur;
use App\Helper\QueryCounter;
use DateTime;
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
    public function countByUser($user) {
        return $this->createQueryBuilder("c")
            ->select("COUNT(c)")
            ->where("c.demandeur = :user")
            ->setParameter("user", $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByParamsAndFilters($params, $filters) {
        $qb = $this->createQueryBuilder("c");

        $countTotal =  QueryCounter::count($qb);

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
		$countFiltered =  QueryCounter::count($qb);

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

	public function getIdAndLibelleBySearch($search) {
	    return $this->createQueryBuilder("c")
            ->select("c.id, c.numero AS text")
            ->where("c.numero LIKE :search")
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
	}

	public function findByDates($dateMin, $dateMax)
    {
        $dateMax = $dateMax->format('Y-m-d H:i:s');
        $dateMin = $dateMin->format('Y-m-d H:i:s');

        $queryBuilder = $this->createQueryBuilder('collecte')
            ->select('collecte')

            ->where('collecte.date BETWEEN :dateMin AND :dateMax')

            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function getTreatingTimesWithType() {
        $nowDate = new DateTime();
        $datePrior3Months = (clone $nowDate)->modify('-3 month');
        $queryBuilder = $this->createQueryBuilder('request');
        $queryBuilderExpr = $queryBuilder->expr();
        $query = $queryBuilder
            ->select('type.id AS typeId')
            ->addSelect($queryBuilderExpr->min('request.validationDate') . ' AS validationDate')
            ->addSelect($queryBuilderExpr->max('collect_order.date') . ' AS treatingDate')
            ->join('request.ordreCollecte', 'collect_order')
            ->join('request.statut', 'status')
            ->join('request.type', 'type')
            ->where('status.nom LIKE :treatedStatus')
            ->andHaving($queryBuilderExpr->min('request.validationDate') . ' BETWEEN :start AND :end')
            ->groupBy('request.id')
            ->setParameters([
                'start' => $datePrior3Months,
                'end' => $nowDate,
                'treatedStatus' => Collecte::STATUT_COLLECTE
            ])
            ->getQuery();
        return $query->execute();
    }

    public function findRequestToTreatByUser(Utilisateur $requester) {
        $queryBuilder = $this->createQueryBuilder('request');
        $queryBuilderExpr = $queryBuilder->expr();
        return $queryBuilder
            ->innerJoin('request.statut', 'status')
            ->where(
                $queryBuilderExpr->andX(
                    $queryBuilderExpr->in('status.nom', ':statusNames'),
                    $queryBuilderExpr->eq('request.demandeur', ':requester')
                )
            )
            ->setParameters([
                'statusNames' => [
                    Collecte::STATUT_BROUILLON,
                    Collecte::STATUT_A_TRAITER,
                    Collecte::STATUT_INCOMPLETE
                ],
                'requester' => $requester
            ])
            ->orderBy('request.validationDate', 'DESC')
            ->getQuery()
            ->execute();
    }
}
