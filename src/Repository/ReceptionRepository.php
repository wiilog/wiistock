<?php

namespace App\Repository;

use App\Entity\Reception;
use App\Entity\Utilisateur;
use App\Helper\QueryCounter;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Reception|null find($id, $lockMode = null, $lockVersion = null)
 * @method Reception|null findOneBy(array $criteria, array $orderBy = null)
 * @method Reception[]    findAll()
 * @method Reception[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceptionRepository extends ServiceEntityRepository
{

    private const DtToDbLabels = [
        'Date' => 'date',
        'DateFin' => 'dateFinReception',
        'Numéro de commande' => 'numeroReception',
        'Référence' => 'reference',
        'Commentaire' => 'commentaire',
        'Statut' => 'statut',
        'Fournisseur' => 'fournisseur',
        'urgence' => 'emergencyTriggered',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reception::class);
    }

	public function countByFournisseur($fournisseurId)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT COUNT(r)
			FROM App\Entity\Reception r
			WHERE r.fournisseur = :fournisseurId"
		)->setParameter('fournisseurId', $fournisseurId);

		return $query->getSingleScalarResult();
	}

	public function getLastNumeroByPrefixeAndDate($prefixe, $date)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
		/** @lang DQL */
			'SELECT r.numeroReception as numero
			FROM App\Entity\Reception r
			WHERE r.numeroReception LIKE :value
			ORDER BY r.date DESC'
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
			"SELECT COUNT(r)
            FROM App\Entity\Reception r
            WHERE r.utilisateur = :user"
		)->setParameter('user', $user);

		return $query->getSingleScalarResult();
	}

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return Reception[]|null
     */
    public function getByDates(DateTime $dateMin,
                               DateTime $dateMax)
    {
        $queryBuilder = $this->createQueryBuilder('reception')
            ->select('reception.id')
            ->addSelect('article.id AS articleId')
            ->addSelect('referenceArticle.id AS referenceArticleId')
            ->addSelect('reception.numeroReception')
            ->addSelect('reception.reference')
            ->addSelect('provider.nom AS providerName')
            ->addSelect('user.username AS userUsername')
            ->addSelect('status.nom AS statusName')
            ->addSelect('reception.date')
            ->addSelect('reception.dateFinReception')
            ->addSelect('reception.commentaire')
            ->addSelect('receptionReferenceArticle.quantiteAR AS receptionRefArticleQuantiteAR')
            ->addSelect('receptionReferenceArticle.quantite AS receptionRefArticleQuantite')
            ->addSelect('referenceArticle.reference AS referenceArticleReference')
            ->addSelect('referenceArticle.libelle AS referenceArticleLibelle')
            ->addSelect('referenceArticle.quantiteStock AS referenceArticleQuantiteStock')
            ->addSelect('referenceArticleType.label AS referenceArticleTypeLabel')
            ->addSelect('referenceArticle.barCode AS referenceArticleBarcode')
            ->addSelect('article.reference AS articleReference')
            ->addSelect('article.label AS articleLabel')
            ->addSelect('article.quantite AS articleQuantity')
            ->addSelect('articleType.label AS articleTypeLabel')
            ->addSelect('articleReferenceArticle.barCode AS articleReferenceArticleBarcode')
            ->addSelect('article.barCode as articleBarcode')

            ->where('reception.date BETWEEN :dateMin AND :dateMax')

            ->leftJoin('reception.fournisseur', 'provider')
            ->leftJoin('reception.utilisateur', 'user')
            ->leftJoin('reception.statut', 'status')
            ->leftJoin('reception.receptionReferenceArticles', 'receptionReferenceArticle')
            ->leftJoin('receptionReferenceArticle.referenceArticle', 'referenceArticle')
            ->leftJoin('referenceArticle.type', 'referenceArticleType')
            ->leftJoin('receptionReferenceArticle.articles', 'article')
            ->leftJoin('article.type', 'articleType')
            ->leftJoin('article.articleFournisseur', 'articleFournisseur')
            ->leftJoin('articleFournisseur.referenceArticle', 'articleReferenceArticle')

            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function findByParamAndFilters($params, $filters)
    {
        $qb = $this->createQueryBuilder("r");

        $countTotal = QueryCounter::count($qb);

        // filtres sup
        foreach ($filters as $filter) {
            switch($filter['field']) {
                case 'statut':
					$value = explode(',', $filter['value']);
					$qb
						->join('r.statut', 's')
						->andWhere('s.id in (:statut)')
						->setParameter('statut', $value);
					break;
                case 'providers':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('r.fournisseur', 'f')
                        ->andWhere("f.id in (:fournisseur)")
                        ->setParameter('fournisseur', $value);
                    break;
                case 'dateMin':
                    $qb->andWhere('r.date >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . ' 00:00:00');
                    break;
                case 'dateMax':
                    $qb->andWhere('r.date <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . ' 23:59:59');
                    break;
				case 'emergency':
					$qb
						->andWhere('r.emergencyTriggered = :isUrgent')
						->setParameter('isUrgent', $filter['value']);
					break;
            }
        }
        //Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
						->leftJoin('r.statut', 's2')
						->leftJoin('r.fournisseur', 'f2')
                        ->andWhere('r.date LIKE :value
                        OR r.numeroReception LIKE :value
                        OR r.reference LIKE :value
                        OR r.commentaire lIKE :value
                        OR s2.nom LIKE :value
                        OR f2.nom LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
            }
            if (!empty($params->get('order')))
            {
                foreach ($params->get('order') as $sort) {
                    $order = $sort['dir'];
                    if (!empty($order))
                    {
                        $column = self::DtToDbLabels[$params->get('columns')[$sort['column']]['data']];
                        if ($column === 'statut') {
                            $qb
                                ->leftJoin('r.statut', 's2')
                                ->addOrderBy('s2.nom', $order);
                        } else if ($column === 'fournisseur') {
                            $qb
                                ->leftJoin('r.fournisseur', 'u2')
                                ->addOrderBy('u2.nom', $order);
                        } else {
                            $qb
                                ->addOrderBy('r.' . $column, $order);
                        }
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryCounter::count($qb);

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
