<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\Demande;
use App\Entity\InventoryFrequency;
use App\Entity\InventoryMission;
use App\Entity\MouvementStock;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Utilisateur;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Article|null find($id, $lockMode = null, $lockVersion = null)
 * @method Article|null findOneBy(array $criteria, array $orderBy = null)
 * @method Article[]    findAll()
 * @method Article[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticleRepository extends ServiceEntityRepository
{
    private const DtToDbLabels = [
        'Référence' => 'reference',
        'Statut' => 'status',
        'Libellé' => 'label',
        'Date et heure' => 'dateFinReception',
        'Référence article' => 'refArt',
        'Quantité' => 'quantite',
        'Type' => 'Type',
        'Emplacement' => 'Emplacement',
        'Actions' => 'Actions',
        'Code barre' => 'barCode',
    ];

    private const linkChampLibreLabelToField = [
        'Libellé' => ['field' => 'label', 'typage' => 'text'],
        'Référence' => ['field' => 'reference', 'typage' => 'text'],
        'Statut' => ['field' => 'Statut', 'typage' => 'text'],
        'Quantité' => ['field' => 'quantiteStock', 'typage' => 'number'],
        'Date et heure' => ['field' => 'dateLastInventory', 'typage' => 'list'],
        'Commentaire' => ['field' => 'commentaire', 'typage' => 'list'],
        'Prix unitaire' => ['field' => 'prixUnitaire', 'typage' => 'list'],
        'Code barre' => ['field' => 'barCode', 'typage' => 'text'],
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    public function getReferencesByRefAndDate($refPrefix, $date)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			'SELECT a.reference
            FROM App\Entity\Article a
            WHERE a.reference LIKE :refPrefix'
		)->setParameter('refPrefix', $refPrefix . $date . '%');

		return array_column($query->execute(), 'reference');
	}

    /**
     * @param $id
     * @return Article[]|null
     */
    public function findByReception($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\Article a
            WHERE a.reception = :id'
        )->setParameter('id', $id);
        return $query->execute();
    }

    public function setNullByReception($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            '
            UPDATE App\Entity\Article a
            SET a.receptionReferenceArticle = null
            WHERE a.receptionReferenceArticle = :id'
        )->setParameter('id', $id);
        return $query->execute();
    }

    /**
     * @param int $id
     * @return Article[]|null
     */
    public function findByCollecteId($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
             FROM App\Entity\Article a
             JOIN a.collectes c
             WHERE c.id =:id
            "
        )->setParameter('id', $id);
        return $query->getResult();
    }

	/**
	 * @param int $ordreCollecteId
	 * @return Article[]|null
	 */
	public function findByOrdreCollecteId($ordreCollecteId)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
		/** @lang DQL */
			"SELECT a
             FROM App\Entity\Article a
             JOIN a.ordreCollecte oc
             WHERE oc.id =:id
            "
		)->setParameter('id', $ordreCollecteId);
		return $query->getResult();
	}

    /**
     * @param Demande|int $demande
     * @return Article[]|null
     */
    public function findByDemande($demande)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
             FROM App\Entity\Article a
             WHERE a.demande =:demande
            "
        )->setParameter('demande', $demande);
        return $query->execute();
    }

    public function getByDemandeAndType($demande, $type)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
             FROM App\Entity\Article a
             WHERE a.demande =:demande AND a.type = :type
            "
        )->setParameters([
            'demande' => $demande,
            'type' => $type
        ]);
        return $query->execute();
    }


    public function findByEmplacement($empl)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\Article a
            WHERE a.emplacement = :empl'
        )->setParameter('empl', $empl);;
        return $query->execute();
    }



    public function findByStatut($statut)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
            FROM App\Entity\Article a
            WHERE a.statut = :statut "
        )->setParameter('statut', $statut);;
        return $query->execute();
    }

    public function countByType($typeId)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(a)
            FROM App\Entity\Article a
            WHERE a.type = :typeId
           "
        )->setParameter('typeId', $typeId);

        return $query->getSingleScalarResult();
    }

    public function setTypeIdNull($typeId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            /** @lang DQL */
            "UPDATE App\Entity\Article a
            SET a.type = null
            WHERE a.type = :typeId"
        )->setParameter('typeId', $typeId);

        return $query->execute();
    }

    public function getArticleByReception($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a.id as id, a.barCode as text
            FROM App\Entity\Article a
            JOIN a.reception r
            WHERE r.id = :id "
        )->setParameter('id', $id);;
        return $query->execute();
    }

    public function getIdRefLabelAndQuantity()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        	/** @lang DQL */
            "SELECT a.id, a.reference, a.label, a.quantite, a.barCode
            FROM App\Entity\Article a
            "
        );
        return $query->execute();
    }

	public function getIdAndRefBySearch($search, $activeOnly = false)
	{
		$em = $this->getEntityManager();

		$dql = "SELECT a.id, a.reference as text
          FROM App\Entity\Article a
          LEFT JOIN a.statut s
          WHERE a.reference LIKE :search";

        if ($activeOnly) {
            $dql .= " AND s.nom = '" . Article::STATUT_ACTIF . "'";
        }

		$query = $em
			->createQuery($dql)
			->setParameter('search', '%' . $search . '%');

		return $query->execute();
	}

    public function getRefByRecep($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a.reference
            FROM App\Entity\Article a
            JOIN a.reception r
            WHERE r.id =:id
            "
        )->setParameter('id', $id);
        return $query->getResult();
    }

    public function findByPreparation($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
            FROM App\Entity\Article a
            JOIN a.preparations p
            WHERE p.id =:id
            "
        )->setParameter('id', $id);
        return $query->getResult();
    }

	/**
	 * @param ReferenceArticle $refArticle
	 * @param Statut $statut
	 * @return Article[]
	 */
	public function findByRefArticleAndStatut($refArticle, $statut)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			'SELECT a
			FROM App\Entity\Article a
			JOIN a.articleFournisseur af
			JOIN af.referenceArticle ra
			WHERE a.statut =:statut AND ra = :refArticle
			'
		)->setParameters([
			'refArticle' => $refArticle,
			'statut' => $statut
		]);

		return $query->execute();
	}

    public function getTotalQuantiteFromRefNotInDemand($refArticle, $statut)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT SUM(a.quantite)
			FROM App\Entity\Article a
			JOIN a.articleFournisseur af
			JOIN af.referenceArticle ra
			WHERE a.statut =:statut AND ra = :refArticle AND a.demande is null
			'
        )->setParameters([
            'refArticle' => $refArticle,
            'statut' => $statut
        ]);

        return $query->getSingleScalarResult();
    }

    public function getTotalQuantiteByRefAndStatusLabel($refArticle, $statusLabel)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            'SELECT SUM(a.quantite)
			FROM App\Entity\Article a
			JOIN a.articleFournisseur af
			JOIN af.referenceArticle ra
			JOIN a.statut s
			WHERE s.nom =:statusLabel AND ra = :refArticle
			'
		)->setParameters([
			'refArticle' => $refArticle,
			'statusLabel' => $statusLabel
		]);

		return $query->getSingleScalarResult();
	}

	public function findByRefArticleAndStatutWithoutDemand($refArticle, $statut)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			'SELECT a
			FROM App\Entity\Article a
			JOIN a.articleFournisseur af
			JOIN af.referenceArticle ra
			WHERE a.statut =:statut AND ra = :refArticle
			ORDER BY a.quantite DESC
			'
		)->setParameters([
			'refArticle' => $refArticle,
			'statut' => $statut
		]);

		return $query->execute();
	}

    public function findByEtat($etat)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\Article a
            WHERE a.etat = :etat'
        )->setParameter('etat', $etat);;
        return $query->execute();
    }

    public function findAllSortedByName()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a.nom, a.id, a.quantite
          FROM App\Entity\Article a
          ORDER BY a.nom
          "
        );
        return $query->execute();
    }

	/**
	 * @param array|null $params
	 * @param array $filters
	 * @param Utilisateur $user
	 * @return array
	 * @throws ORMException
	 * @throws OptimisticLockException
	 */
    public function findByParamsAndFilters($params = null, $filters, $user)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('a')
            ->from('App\Entity\Article', 'a');

        $countQuery = $countTotal = count($qb->getQuery()->getResult());

		// filtres sup
		foreach ($filters as $filter) {
			switch ($filter['field']) {
				case 'statut':
					$value = explode(',', $filter['value']);
					$qb
						->join('a.statut', 's_filter')
						->andWhere('s_filter.nom IN (:statut)')
						->setParameter('statut', $value);
					break;
			}
		}

        $allArticleDataTable = null;
		// prise en compte des paramètres issus du datatable
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $searchValue = $params->get('search')['value'];
                if (!empty($searchValue)) {
                    $ids = [];
                    $query = [];

                    // valeur par défaut si aucune valeur enregistrée pour cet utilisateur
					$searchForArticle = $user->getRechercheForArticle();
					if (empty($searchForArticle)) {
						$searchForArticle = Utilisateur::SEARCH_DEFAULT;
						$user->setRechercheForArticle($searchForArticle);
						$em->flush();
					}

                    foreach ($searchForArticle as $key => $searchField) {
                        switch ($searchField) {
                            case 'Type':
                                $subqb = $em->createQueryBuilder();
                                $subqb
                                    ->select('a.id')
                                    ->from('App\Entity\Article', 'a');
                                $subqb
                                    ->leftJoin('a.type', 't_search')
                                    ->andWhere('t_search.label LIKE :valueSearch')
                                    ->setParameter('valueSearch', '%' . $searchValue . '%');

                                foreach ($subqb->getQuery()->execute() as $idArray) {
                                    $ids[] = $idArray['id'];
                                }
                                break;

                            case 'Statut':
                                $subqb = $em->createQueryBuilder();
                                $subqb
                                    ->select('a.id')
                                    ->from('App\Entity\Article', 'a');
                                $subqb
                                    ->leftJoin('a.statut', 's_search')
                                    ->andWhere('s_search.nom LIKE :valueSearch')
                                    ->setParameter('valueSearch', '%' . $searchValue . '%');

                                foreach ($subqb->getQuery()->execute() as $idArray) {
                                    $ids[] = $idArray['id'];
                                }
                                break;
                            case 'Emplacement':
                                $subqb = $em->createQueryBuilder();
                                $subqb
                                    ->select('a.id')
                                    ->from('App\Entity\Article', 'a');
                                $subqb
                                    ->leftJoin('a.emplacement', 'e_search')
                                    ->andWhere('e_search.label LIKE :valueSearch')
                                    ->setParameter('valueSearch', '%' . $searchValue . '%');

                                foreach ($subqb->getQuery()->execute() as $idArray) {
                                    $ids[] = $idArray['id'];
                                }
                                break;
                            case 'Référence article':
                                $subqb = $em->createQueryBuilder();
                                $subqb
                                    ->select('a.id')
                                    ->from('App\Entity\Article', 'a');
                                $subqb
                                    ->leftJoin('a.articleFournisseur', 'afa')
                                    ->leftJoin('afa.referenceArticle', 'ra')
                                    ->andWhere('ra.reference LIKE :valueSearch')
                                    ->setParameter('valueSearch', '%' . $searchValue . '%');

                                foreach ($subqb->getQuery()->execute() as $idArray) {
                                    $ids[] = $idArray['id'];
                                }
                                break;
                            default:
                                $metadatas = $em->getClassMetadata(Article::class);
                                $field = !empty(self::linkChampLibreLabelToField[$searchField]) ? self::linkChampLibreLabelToField[$searchField]['field'] : '';
                                if ($field !== '' && in_array($field, $metadatas->getFieldNames())) {
                                    $query[] = 'a.' . $field . ' LIKE :valueSearch';
                                    $qb->setParameter('valueSearch', '%' . $searchValue . '%');
                                    // champs libres
                                } else {
                                    $subqb = $em->createQueryBuilder();
                                    $subqb
                                        ->select('a.id')
                                        ->from('App\Entity\Article', 'a');
                                    $subqb
                                        ->leftJoin('a.valeurChampsLibres', 'vclra')
                                        ->leftJoin('vclra.champLibre', 'clra')
                                        ->andWhere('clra.label = :searchField')
                                        ->andWhere('vclra.valeur LIKE :searchValue')
                                        ->setParameters([
                                            'searchValue' => '%' . $searchValue . '%',
                                            'searchField' => $searchField
                                        ]);

                                    foreach ($subqb->getQuery()->execute() as $idArray) {
                                        $ids[] = $idArray['id'];
                                    }
                                }
                                break;
                        }
                    }

                    // si le résultat de la recherche est vide on renvoie []
                    if (empty($ids)) {
                        $ids = [0];
                    }

                    foreach ($ids as $id) {
                        $query[] = 'a.id  = ' . $id;
                    }
                    $qb->andWhere(implode(' OR ', $query));
                }
				$countQuery = count($qb->getQuery()->getResult());
			}
            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column =
                        isset(self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']])
                            ? self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']]
                            : $params->get('columns')[$params->get('order')[0]['column']]['data'];
                    switch ($column) {
                        case 'Actions':
                            break;
                        case 'Type':
                            $qb
                                ->leftJoin('a.type', 't')
                                ->orderBy('t.label', $order);
                            break;
                        case 'Emplacement':
                            $qb
                                ->leftJoin('a.emplacement', 'e')
                                ->orderBy('e.label', $order);
                            break;
                        case 'refArt':
                            $qb
                                ->leftJoin('a.articleFournisseur', 'af2')
                                ->leftJoin('af2.referenceArticle', 'ra2')
                                ->orderBy('ra2.reference', $order);
                            break;
                        case 'status':
                            $qb
                                ->leftJoin('a.statut', 's_sort')
                                ->orderBy('s_sort.nom', $order);
                            break;
                        case 'dateFinReception':
                            $expr = $qb->expr();
                            $qb
                                ->leftJoin('a.mouvements', 'mouvement')
                                ->andWhere($expr->orX(
                                    $expr->isNull('mouvement.type'),
                                    $expr->eq('mouvement.type', ':mouvementTypeOrder')
                                ))
                                ->distinct()
                                ->orderBy('mouvement.date', $order)
                                ->setParameter('mouvementTypeOrder', MouvementStock::TYPE_ENTREE);
                            break;
                        default:
                            if (property_exists(Article::class, $column)) {
                                $qb
                                    ->orderBy('a.' . $column, $order);
                            } else {
                                $paramsQuery = $qb->getParameters();
                                $paramsQuery[] = new Parameter('orderField', $column, 2);
                                $qb
                                    ->addSelect('(CASE WHEN cla.id IS NULL THEN 0 ELSE vcla.valeur END) as vsort')
                                    ->leftJoin('a.valeurChampsLibres', 'vcla')
                                    ->leftJoin('vcla.champLibre', 'cla', 'WITH', 'cla.label LIKE :orderField')
                                    ->orderBy('vsort', $order)
                                    ->setParameters($paramsQuery);
                            }
                            break;
                    }
                }
            }
            $allArticleDataTable = $qb->getQuery();
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }
        $query = $qb->getQuery();

        return [
            'data' => $query ? $query->getResult() : null,
            'allArticleDataTable' => $allArticleDataTable ? $allArticleDataTable->getResult() : null,
            'count' => $countQuery,
            'total' => $countTotal];
    }

    /**
     * @param ArticleFournisseur[] $listAf
     * @return Article[]|null
     */
    public function findByListAF($listAf)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT a
          FROM App\Entity\Article a
          JOIN a.articleFournisseur af
          WHERE af IN (:articleFournisseur)"
        )->setParameters([
            'articleFournisseur' => $listAf,
        ]);

        return $query->execute();
    }

    public function countActiveArticles()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        	/** @lang DQL */
            "SELECT COUNT(a)
            FROM App\Entity\Article a
            JOIN a.statut s
            WHERE s.nom = :active"
		)->setParameter('active', Article::STATUT_ACTIF);

        return $query->getSingleScalarResult();
    }

    public function countAll()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(a)
            FROM App\Entity\Article a"
		);

        return $query->getSingleScalarResult();
    }

	/**
	 * @param int $limit
	 * @return Article[]|null
	 */
    public function findByQuantityMoreThan($limit)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT a
			FROM App\Entity\Article a
			WHERE a.quantite > :limit"
		)->setParameter('limit', $limit);

		return $query->execute();
	}

    /**
     * @return Article[]|null
     */
    public function findDoublons()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT a1
			FROM App\Entity\Article a1
			WHERE a1.reference IN (
				SELECT a2.reference FROM App\Entity\Article a2
				GROUP BY a2.reference
				HAVING COUNT(a2.reference) > 1)"
        );

        return $query->execute();
    }

    public function getByPreparationsIds($preparationsIds)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT a.reference,
                         e.label as location,
                         a.label,
                         (CASE
                            WHEN a.quantiteAPrelever IS NULL THEN a.quantite
                            ELSE a.quantiteAPrelever
                         END) as quantity,
                         0 as is_ref,
                         p.id as id_prepa,
                         a.barCode,
                         ra.reference as reference_article_reference
			FROM App\Entity\Article a
			LEFT JOIN a.emplacement e
			JOIN a.preparation p
			JOIN p.statut s
			JOIN a.articleFournisseur af
			JOIN af.referenceArticle ra
			WHERE p.id IN (:preparationsIds)
			  AND a.quantite > 0"
        )->setParameter('preparationsIds', $preparationsIds, Connection::PARAM_STR_ARRAY);

		return $query->execute();
	}

    public function getRefArticleByPreparationStatutLabelAndUser($statutLabel, $enCours, $user)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT
                    DISTINCT a.reference,
                    a.label,
                    e.label as location,
                    a.quantite as quantity,
                    ra.reference as reference_article,
                    a.barCode
			FROM App\Entity\Article a
			LEFT JOIN a.emplacement e
			JOIN a.articleFournisseur af
			JOIN af.referenceArticle ra
			JOIN ra.ligneArticlePreparations la
			JOIN la.preparation p
			JOIN p.statut s
			WHERE a.quantite > 0
			  AND a.preparation IS NULL
			  AND (s.nom = :statutLabel OR (s.nom = :enCours AND p.utilisateur = :user))"
        )->setParameters([
            'statutLabel' => $statutLabel,
            'enCours' => $enCours,
            'user' => $user
        ]);

        return $query->execute();
    }

    public function getByLivraisonsIds($livraisonsIds)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT a.reference, e.label as location, a.label, a.quantiteAPrelever as quantity, 0 as is_ref, l.id as id_livraison, a.barCode
			FROM App\Entity\Article a
			LEFT JOIN a.emplacement e
			JOIN a.preparation p
			JOIN p.livraison l
			JOIN l.statut s
			WHERE l.id IN (:livraisonsIds)
			  AND a.quantite > 0"
        )->setParameter('livraisonsIds', $livraisonsIds, Connection::PARAM_STR_ARRAY);

		return $query->execute();
	}

	public function getByOrdreCollectesIds($collectesIds)
	{
		$em = $this->getEntityManager();
		//TODO patch temporaire CEA (sur quantité envoyée)
		$query = $em
			->createQuery($this->getArticleCollecteQuery() . " WHERE oc.id IN (:collectesIds)")
            ->setParameter('collectesIds', $collectesIds, Connection::PARAM_STR_ARRAY);

		return $query->execute();
	}

	public function getByOrdreCollecteId($collecteId)
	{
		$em = $this->getEntityManager();
		$query = $em
			->createQuery($this->getArticleCollecteQuery() . " WHERE oc.id = :id")
			->setParameter('id', $collecteId);

		return $query->execute();
	}

	private function getArticleCollecteQuery()
	{
		return (/** @lang DQL */
		"SELECT a.reference,
			 e.label as location,
			 a.label,
			 a.quantite as quantity,
			 0 as is_ref, oc.id as id_collecte,
			 a.barCode
			FROM App\Entity\Article a
			LEFT JOIN a.emplacement e
			JOIN a.ordreCollecte oc
			LEFT JOIN oc.statut s"
		);
	}

    /**
     * @param string $reference
     * @return Article|null
     * @throws NonUniqueResultException
     */
    public function findOneByReference($reference)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT a
			FROM App\Entity\Article a
			WHERE a.reference = :reference"
		)->setParameter('reference', $reference);

		return $query->getOneOrNullResult();
	}

    /**
     * @param string $reference
     * @return Article|null
     * @throws NonUniqueResultException
     */
    public function findOneByDemandeAndArticle(Demande $demande, Article $article)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT a
			FROM App\Entity\Article a
			JOIN a.articleFournisseur artf
			WHERE a.demande = :demande
			AND a.label = :label
			AND artf = :af
			AND artf.referenceArticle = :ar"
        )->setParameters([
            'demande' => $demande,
            'label' => $article->getLabel(),
            'af' => $article->getArticleFournisseur(),
            'ar' => $article->getArticleFournisseur()->getReferenceArticle()
        ]);

        return $query->getOneOrNullResult();
    }

    /**
     * @param string $reference
     * @return Article|null
     * @throws NonUniqueResultException
     */
    public function findOneByAndArticle(Demande $demande, Article $article)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT a
			FROM App\Entity\Article a
			JOIN a.articleFournisseur artf
			WHERE a.demande = :demande
			AND a.label = :label
			AND artf = :af
			AND artf.referenceArticle = :ar"
        )->setParameters([
            'demande' => $demande,
            'label' => $article->getLabel(),
            'af' => $article->getArticleFournisseur(),
            'ar' => $article->getArticleFournisseur()->getReferenceArticle()
        ]);

        return $query->getOneOrNullResult();
    }

	/**
	 * @param string $reference
	 * @return Article|null
	 */
	public function findByReference($reference)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT a
			FROM App\Entity\Article a
			WHERE a.reference = :reference"
		)->setParameter('reference', $reference);

		return $query->execute();
	}

    public function countByEmplacement($emplacementId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(a)
			FROM App\Entity\Article a
			JOIN a.emplacement e
			WHERE e.id = :emplacementId"
        )->setParameter('emplacementId', $emplacementId);

        return $query->getSingleScalarResult();
    }

    /**
     * @param InventoryMission $mission
     * @param int $artId
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function getEntryDateByMission($mission, $artId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT e.date
            FROM App\Entity\InventoryEntry e
            WHERE e.mission = :mission AND e.article = :art"
        )->setParameters([
            'mission' => $mission,
            'art' => $artId
        ]);
        return $query->getOneOrNullResult();
    }

    public function countByMission($mission)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(a)
            FROM App\Entity\InventoryMission im
            LEFT JOIN im.articles a
            WHERE im = :mission"
        )->setParameter('mission', $mission);

        return $query->getSingleScalarResult();
    }

    public function getIdAndReferenceBySearch($search, $activeOnly = false)
    {
        $em = $this->getEntityManager();

        $dql = "SELECT a.id as id, a.reference as text
          FROM App\Entity\Article a
          LEFT JOIN a.statut s
          WHERE a.reference LIKE :search";

        if ($activeOnly) {
            $dql .= " AND s.nom = '" . Article::STATUT_ACTIF . "'";
        }

        $query = $em
            ->createQuery($dql)
            ->setParameter('search', '%' . $search . '%');

        return $query->execute();
    }

    /**
     * @param InventoryFrequency $frequency
     * @param int $limit
     * @return Article[]
     */
    public function findActiveByFrequencyWithoutDateInventoryOrderedByEmplacementLimited($frequency, $limit)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT a
            FROM App\Entity\Article a
            JOIN a.articleFournisseur af
            JOIN af.referenceArticle ra
            JOIN ra.category c
            LEFT JOIN a.statut sa
            LEFT JOIN a.emplacement ae
            WHERE c.frequency = :frequency
            AND ra.typeQuantite = :typeQuantity
            AND a.dateLastInventory is null
            AND sa.nom = :status
            ORDER BY ae.label"
        )->setParameters([
            'frequency' => $frequency,
            'typeQuantity' => ReferenceArticle::TYPE_QUANTITE_ARTICLE,
            'status' => Article::STATUT_ACTIF,
        ]);

        if ($limit) $query->setMaxResults($limit);

        return $query->execute();
    }

	/**
	 * @param string $dateCode
	 * @return mixed
	 * @throws NonUniqueResultException
	 */
	public function getHighestBarCodeByDateCode($dateCode)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
		"SELECT a.barCode
		FROM App\Entity\Article a
		WHERE a.barCode LIKE :barCode
		ORDER BY a.barCode DESC
		")
            ->setParameter('barCode', Article::BARCODE_PREFIX . $dateCode . '%')
            ->setMaxResults(1);

        $result = $query->execute();
        return $result ? $result[0]['barCode'] : null;;
    }

    public function getRefAndLabelRefAndArtAndBarcodeAndBLById($id)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT ra.libelle as refLabel, ra.reference as refRef, a.label as artLabel, a.barCode as barcode, vcla.valeur as bl, cla.label as cl
		FROM App\Entity\Article a
		LEFT JOIN a.articleFournisseur af
		LEFT JOIN af.referenceArticle ra
		LEFT JOIN a.valeurChampsLibres vcla
		LEFT JOIN vcla.champLibre cla
		WHERE a.id = :id
		")
            ->setParameter('id', $id);

        return $query->execute();
    }

	/**
	 * @param Article $article
	 * @return int
	 * @throws NonUniqueResultException
	 * @throws NoResultException
	 */
    public function countInventoryAnomaliesByArt($article)
    {
        $em = $this->getEntityManager();

        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(ie)
			FROM App\Entity\InventoryEntry ie
			JOIN ie.article a
			WHERE ie.anomaly = 1 AND a.id = :artId
			")->setParameter('artId', $article->getId());

        return $query->getSingleScalarResult();
    }

	public function findArticleByBarCodeAndLocation(string $barCode, string $location) {
        $queryBuilder = $this
            ->createQueryBuilderByBarCodeAndLocation($barCode, $location)
            ->addSelect('article');

        return $queryBuilder->getQuery()->execute();
    }

	public function getArticleByBarCodeAndLocation(string $barCode, string $location) {
        $queryBuilder = $this
            ->createQueryBuilderByBarCodeAndLocation($barCode, $location)
            ->select('article.reference as reference')
            ->addSelect('article.quantite as quantity')
            ->addSelect('0 as is_ref');

        return $queryBuilder->getQuery()->execute();
    }

    private function createQueryBuilderByBarCodeAndLocation(string $barCode, string $location): QueryBuilder {
        $queryBuilder = $this->createQueryBuilder('article');
        return $queryBuilder
            ->join('article.emplacement', 'emplacement')
            ->join('article.statut', 'status')
            ->andWhere('emplacement.label = :location')
            ->andWhere('article.barCode = :barCode')
            ->andWhere('status.nom = :statusNom')
            ->setParameter('location', $location)
            ->setParameter('barCode', $barCode)
            ->setParameter('statusNom', Article::STATUT_ACTIF);
    }
}
