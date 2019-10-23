<?php

namespace App\Repository;

use App\Entity\AlerteExpiry;
use App\Entity\Article;
use App\Entity\Demande;
use App\Entity\FiltreRef;
use App\Entity\InventoryFrequency;
use App\Entity\InventoryMission;
use App\Entity\ReferenceArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ReferenceArticle|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReferenceArticle|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReferenceArticle[]    findAll()
 * @method ReferenceArticle[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReferenceArticleRepository extends ServiceEntityRepository
{

    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ReferenceArticle::class);
    }

    public function getIdAndLibelle()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT r.id, r.libelle 
            FROM App\Entity\ReferenceArticle r
            "
        );
        return $query->execute();
    }

    public function getBetweenLimits($min, $step) {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT ra 
                  FROM App\Entity\ReferenceArticle ra
                  ORDER BY ra.id ASC"
        )
            ->setMaxResults($step)
            ->setFirstResult($min);
        return $query->execute();
    }

    public function getChampFixeById($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT r.id, r.libelle, r.reference, r.commentaire, r.quantite_stock, r.type_quantite, r.statut, r.type
            FROM App\Entity\ReferenceArticle r
            WHERE r.id = :id 
            "
        )->setParameter('id', $id);

        return $query->execute();
    }

    public function findOneByLigneReception($ligne)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT ra
            FROM App\Entity\ReferenceArticle ra
            JOIN App\Entity\ReceptionReferenceArticle rra
            WHERE rra.referenceArticle = ra AND rra = :ligne 
        "
        )->setParameter('ligne', $ligne);
        return $query->getOneOrNullResult();
    }

	/**
	 * @param $reference
	 * @return ReferenceArticle|null
	 * @throws NonUniqueResultException
	 */
    public function findOneByReference($reference)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT r
            FROM App\Entity\ReferenceArticle r
            WHERE r.reference = :reference
            "
        )->setParameter('reference', $reference);

        return $query->getOneOrNullResult();
    }

	/**
	 * @param string $search
	 * @param bool $activeOnly
	 * @return mixed
	 */
    public function getIdAndRefBySearch($search, $activeOnly = false, $typeQuantity = null)
    {
        $em = $this->getEntityManager();

        $dql = "SELECT r.id, r.reference as text
          FROM App\Entity\ReferenceArticle r
          LEFT JOIN r.statut s
          WHERE r.reference LIKE :search ";

        if ($activeOnly) {
        	$dql .= " AND s.nom = '" . ReferenceArticle::STATUT_ACTIF . "'";
		}

        if ($typeQuantity)
        {
            $dql .= "  AND r.typeQuantite = :type";
        }

        $query = $em
			->createQuery($dql)
			->setParameter('search', '%' . $search . '%');
        if ($typeQuantity)
        {
            $query
                ->setParameter('type', $typeQuantity);
        }

        return $query->execute();
    }

    //TODO CG remplacer par $ref->getQuantiteStock()
    public function getQuantiteStockById($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT r.quantiteStock
            FROM App\Entity\ReferenceArticle r
            WHERE r.id = $id
           "
        );
        return $query->getSingleScalarResult();
    }

    public function findByFiltersAndParams($filters, $params, $user)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();
        $index = 0;

        $subQueries = [];

        // fait le lien entre intitulé champs dans datatable/filtres côté front
        // et le nom des attributs de l'entité ReferenceArticle (+ typage)
        $linkChampLibreLabelToField = [
            'Libellé' => ['field' => 'libelle', 'typage' => 'text'],
            'Référence' => ['field' => 'reference', 'typage' => 'text'],
            'Type' => ['field' => 'type_id', 'typage' => 'list'],
            'Quantité' => ['field' => 'quantiteStock', 'typage' => 'number'],
            'Statut' => ['field' => 'Statut', 'typage' => 'text'],
			'Emplacement' => ['field' => 'emplacement_id', 'typage' => 'list']
        ];
        //TODO trouver + dynamique
        $qb->from('App\Entity\ReferenceArticle', 'ra');

		foreach ($filters as $filter) {
            $index++;

            if ($filter['champFixe'] === FiltreRef::CHAMP_FIXE_STATUT) {
                if ($filter['value'] === Article::STATUT_ACTIF) {
                    $qb->leftJoin('ra.statut', 'sra');
                    $qb->andWhere('sra.nom LIKE \'' . $filter['value'] . '\'');
                }
            } else {
                // cas particulier champ référence article fournisseur
                if ($filter['champFixe'] === FiltreRef::CHAMP_FIXE_REF_ART_FOURN) {
                    $qb
                        ->leftJoin('ra.articlesFournisseur', 'af')
                        ->andWhere('af.reference LIKE :reference')
                        ->setParameter('reference', '%' . $filter['value'] . '%');
                } // cas champ fixe
                else if ($label = $filter['champFixe']) {
                    $array = $linkChampLibreLabelToField[$label];
                    $field = $array['field'];
                    $typage = $array['typage'];

                    switch ($typage) {
                        case 'text':
                            $qb
                                ->andWhere("ra." . $field . " LIKE :value" . $index)
                                ->setParameter('value' . $index, '%' . $filter['value'] . '%');
                            break;
                        case 'number':
                            $qb->andWhere("ra." . $field . " = " . $filter['value']);
                            break;
                        case 'list':
                            switch ($field) {
                                case 'type_id':
                                    $qb
                                        ->leftJoin('ra.type', 't')
                                        ->andWhere('t.label = :typeLabel')
                                        ->setParameter('typeLabel', $filter['value']);
                                    break;
                                case 'emplacement_id':
                                    $qb
                                        ->leftJoin('ra.emplacement', 'e')
                                        ->andWhere('e.label = :emplacementLabel')
                                        ->setParameter('emplacementLabel', $filter['value']);
                                    break;
                            }
                            break;
                    }
                } // cas champ libre
                else if ($filter['champLibre']) {
                    $qbSub = $em->createQueryBuilder();
                    $qbSub
                        ->select('ra' . $index . '.id')
                        ->from('App\Entity\ReferenceArticle', 'ra' . $index)
                        ->leftJoin('ra' . $index . '.valeurChampsLibres', 'vcl' . $index);

                    switch ($filter['typage']) {
                        case 'booleen':
                            $value = $filter['value'] == 1 ? '1' : '0';
                            $qbSub
                                ->andWhere('vcl' . $index . '.champLibre = ' . $filter['champLibre'])
                                ->andWhere('vcl' . $index . '.valeur = ' . $value);
                            break;
                        case 'text':
                            $qbSub
                                ->andWhere('vcl' . $index . '.champLibre = ' . $filter['champLibre'])
                                ->andWhere('vcl' . $index . '.valeur LIKE :value' . $index)
                                ->setParameter('value' . $index, '%' . $filter['value'] . '%');
                            break;
                        case 'number':
                        case 'list':
                            $qbSub
                                ->andWhere('vcl' . $index . '.champLibre = ' . $filter['champLibre'])
                                ->andWhere('vcl' . $index . '.valeur = :value' . $index)
                                ->setParameter('value' . $index, $filter['value']);
                            break;
                        case 'date':
                            $date = explode('-', $filter['value']);
                            $formattedDated = $date[2] . '/' . $date[1] . '/' . $date[0];
                            $qbSub
                                ->andWhere('vcl' . $index . '.champLibre = ' . $filter['champLibre'])
                                ->andWhere('vcl' . $index . ".valeur = '" . $formattedDated . "'");
                            break;
                    }
                    $subQueries[] = $qbSub->getQuery()->getResult();
                }
            }
        }

        foreach ($subQueries as $subQuery) {
            $ids = [];
            foreach ($subQuery as $idArray) { //TODO optim php natif ?
                $ids[] = $idArray['id'];
            }
            if (empty($ids)) $ids = 0;
            $qb->andWhere($qb->expr()->in('ra.id', $ids));
        }

        // prise en compte des paramètres issus du datatable
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $searchValue = $params->get('search')['value'];
                if (!empty($searchValue)) {
                    $ids = [];
                    $query = [];
                    foreach ($user->getRecherche() as $key => $searchField) {

                    	switch ($searchField) {
							case 'Fournisseur':
								$subqb = $em->createQueryBuilder();
								$subqb
									->select('ra.id')
									->from('App\Entity\ReferenceArticle', 'ra');
								$subqb
									->leftJoin('ra.articlesFournisseur', 'afra')
									->leftJoin('afra.fournisseur', 'fra')
									->andWhere('fra.nom LIKE :valueSearch')
									->setParameter('valueSearch', '%' . $searchValue . '%');

								foreach ($subqb->getQuery()->execute() as $idArray) {
									$ids[] = $idArray['id'];
								}
								break;

							case 'Référence article fournisseur':
								$subqb = $em->createQueryBuilder();
								$subqb
									->select('ra.id')
									->from('App\Entity\ReferenceArticle', 'ra');
								$subqb
									->leftJoin('ra.articlesFournisseur', 'afra')
									->andWhere('afra.reference LIKE :valueSearch')
									->setParameter('valueSearch', '%' . $searchValue . '%');

								foreach ($subqb->getQuery()->execute() as $idArray) {
									$ids[] = $idArray['id'];
								}
								break;

							default:
								$metadatas = $em->getClassMetadata(ReferenceArticle::class);
								$field = !empty($linkChampLibreLabelToField[$searchField]) ? $linkChampLibreLabelToField[$searchField]['field'] : '';

								// champs fixes
								if ($field !== '' && in_array($field, $metadatas->getFieldNames())) {
									$query[] = 'ra.' . $field . ' LIKE :valueSearch';
									$qb->setParameter('valueSearch', '%' . $searchValue . '%');

								// champs libres
								} else {
									$subqb = $em->createQueryBuilder();
									$subqb
										->select('ra.id')
										->from('App\Entity\ReferenceArticle', 'ra');
									$subqb
										->leftJoin('ra.valeurChampsLibres', 'vclra')
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
						$query[] = 'ra.id  = ' . $id;
					}
					$qb->andWhere(implode(' OR ', $query));
				}
            }
		}

		// compte éléments filtrés
		if (empty($filters) && empty($searchValue)) {
			$qb->select('count(ra)');
		}
		else {
			$qb
				->select('count(distinct(ra))')
				->leftJoin('ra.valeurChampsLibres', 'vcl');
		}

		$countQuery = $qb->getQuery()->getSingleScalarResult();

		if (!empty($params)) {
			if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
			if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
		}

		$qb->select('ra')
			->distinct();

        return ['data' => $qb->getQuery()->getResult(), 'count' => $countQuery];
    }

    public function countByType($typeId)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(ra)
            FROM App\Entity\ReferenceArticle ra
            WHERE ra.type = :typeId
           "
        )->setParameter('typeId', $typeId);

        return $query->getSingleScalarResult();
    }

    public function setTypeIdNull($typeId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "UPDATE App\Entity\ReferenceArticle ra
            SET ra.type = null 
            WHERE ra.type = :typeId"
        )->setParameter('typeId', $typeId);

        return $query->execute();
    }

    public function getIdAndLabelByFournisseur($fournisseurId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT DISTINCT(ra.id) as id, ra.libelle, IDENTITY(af.fournisseur) as fournisseur
            FROM App\Entity\ArticleFournisseur af
            JOIN af.referenceArticle ra
            WHERE af.fournisseur = :fournisseurId
            "
        )->setParameter('fournisseurId', $fournisseurId);

        return $query->execute();
    }

    public function countAll()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(ra)
            FROM App\Entity\ReferenceArticle ra
           "
        );

        return $query->getSingleScalarResult();
    }

    public function countActiveTypeRefRef()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        	/** @lang DQL */
            "SELECT COUNT(ra)
            FROM App\Entity\ReferenceArticle ra
            JOIN ra.statut s
            WHERE s.nom = :active
            AND ra.typeQuantite = :typeQuantite
            "
		)->setParameters([
			'active' => ReferenceArticle::STATUT_ACTIF,
			'typeQuantite' => ReferenceArticle::TYPE_QUANTITE_REFERENCE
		]);

        return $query->getSingleScalarResult();
    }

    public function setQuantiteZeroForType($type)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "UPDATE App\Entity\ReferenceArticle ra
            SET ra.quantiteStock = 0
            WHERE ra.type = :type"
        )->setParameter('type', $type);

        return $query->execute();
    }

    public function countByReference($reference, $refId = null)
    {
        $em = $this->getEntityManager();
        $dql = "SELECT COUNT (ra)
            FROM App\Entity\ReferenceArticle ra
            WHERE ra.reference = :reference";

		if ($refId) {
			$dql .= " AND ra.id != :id";
		}

        $query = $em
			->createQuery($dql)
			->setParameter('reference', $reference);

		if ($refId) {
			$query->setParameter('id', $refId);
		}

        return $query->getSingleScalarResult();
    }

    public function getIdRefLabelAndQuantityByTypeQuantite($typeQuantite)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT ra.id, ra.reference, ra.libelle, ra.quantiteStock
            FROM App\Entity\ReferenceArticle ra
            WHERE ra.typeQuantite = :typeQuantite"
        )->setParameter('typeQuantite', $typeQuantite);
        return $query->execute();
    }

    public function getTotalQuantityReservedByRefArticle($refArticle) {
        $em = $this->getEntityManager();
        return $em->createQuery(
            'SELECT SUM(l.quantite)
                  FROM App\Entity\LigneArticle l 
                  JOIN l.demande d
                  JOIN d.statut s
                  WHERE l.reference = :refArticle AND s.nom = :statut'
        )->setParameters([
            'refArticle' => $refArticle,
            'statut' => Demande::STATUT_A_TRAITER
        ])->getSingleScalarResult();
    }

    public function getTotalQuantityReservedWithoutLigne($refArticle, $ligneArticle, $statut) {
        $em = $this->getEntityManager();
        return $em->createQuery(
            'SELECT SUM(l.quantite)
                  FROM App\Entity\LigneArticle l 
                  JOIN l.demande d
                  WHERE l.reference = :refArticle AND l.id != :id AND d.statut = :statut'
        )->setParameters([
            'refArticle' => $refArticle,
            'id' => $ligneArticle->getId(),
            'statut' => $statut
        ])->getSingleScalarResult();
    }

    public function getByPreparationStatutLabelAndUser($statutLabel, $enCours, $user) {
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT ra.reference, e.label as location, ra.libelle as label, la.quantite as quantity, 1 as is_ref, p.id as id_prepa
			FROM App\Entity\ReferenceArticle ra
			LEFT JOIN ra.emplacement e
			JOIN ra.ligneArticles la
			JOIN la.demande d
			JOIN d.preparation p
			JOIN p.statut s
			WHERE s.nom = :statutLabel OR (s.nom = :enCours AND p.utilisateur = :user)"
		)->setParameters([
		    'statutLabel' => $statutLabel,
            'enCours' => $enCours,
            'user' => $user
        ]);

		return $query->execute();
	}

    public function getByLivraisonStatutLabelAndWithoutOtherUser($statutLabel, $user) {
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT ra.reference, e.label as location, ra.libelle as label, la.quantite as quantity, 1 as is_ref, l.id as id_livraison
			FROM App\Entity\ReferenceArticle ra
			LEFT JOIN ra.emplacement e
			JOIN ra.ligneArticles la
			JOIN la.demande d
			JOIN d.livraison l
			JOIN l.statut s
			WHERE s.nom = :statutLabel OR (l.utilisateur is null OR l.utilisateur = :user)"
		)->setParameters([
		    'statutLabel' => $statutLabel,
            'user' => $user
        ]);

		return $query->execute();
	}

    public function countByEmplacement($emplacementId)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(ra)
            FROM App\Entity\ReferenceArticle ra
            JOIN ra.emplacement e
            WHERE e.id =:emplacementId
           "
        )->setParameter('emplacementId', $emplacementId);

        return $query->getSingleScalarResult();
    }

	/**
	 * @param int $nbPeriod
	 * @param string $typePeriod
	 * @return int|null
	 * @throws NonUniqueResultException
	 */
	public function countWithExpiryDateUpTo($nbPeriod, $typePeriod)
	{
		switch($typePeriod) {
			case AlerteExpiry::TYPE_PERIOD_DAY:
				$typePeriod = 'day';
				break;
			case AlerteExpiry::TYPE_PERIOD_WEEK:
				$typePeriod = 'week';
				break;
			case AlerteExpiry::TYPE_PERIOD_MONTH:
				$typePeriod = 'month';
				break;
			default:
				return 0;
		}

		$now = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
		$now->setTime(0,0);
		$now = $now->format('Y-m-d H:i:s');

		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */"
			SELECT COUNT(ra)
			FROM App\Entity\ReferenceArticle ra
			WHERE ra.expiryDate IS NOT NULL
			AND DATE_SUB(ra.expiryDate, :nbPeriod, '" . $typePeriod . "') <= '" . $now . "'
		")->setParameters([
			'nbPeriod' => $nbPeriod,
		]);

		return $query->getSingleScalarResult();
	}

	/**
	 * @param int $nbPeriod
	 * @param string $typePeriod
	 * @return int|null
	 * @throws \Exception
	 */
	public function findWithExpiryDateUpTo($nbPeriod, $typePeriod)
	{
		switch($typePeriod) {
			case AlerteExpiry::TYPE_PERIOD_DAY:
				$typePeriod = 'day';
				break;
			case AlerteExpiry::TYPE_PERIOD_WEEK:
				$typePeriod = 'week';
				break;
			case AlerteExpiry::TYPE_PERIOD_MONTH:
				$typePeriod = 'month';
				break;
			default:
				return 0;
		}

		$now = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
		$now->setTime(0,0);
		$now = $now->format('Y-m-d H:i:s');

		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */"
			SELECT ra
			FROM App\Entity\ReferenceArticle ra
			WHERE ra.expiryDate IS NOT NULL
			AND DATE_SUB(ra.expiryDate, :nbPeriod, '" . $typePeriod . "') <= '" . $now . "'
			ORDER BY ra.expiryDate")
			->setParameters([
			'nbPeriod' => $nbPeriod,
		]);

		return $query->execute();
	}

    public function countByCategory($category)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(ra)
            FROM App\Entity\ReferenceArticle ra
            WHERE ra.category = :category"
        )->setParameter('category', $category);

        return $query->getSingleScalarResult();
    }

    public function getByMission($mission)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT ra.libelle, ra.reference, ra.hasInventoryAnomaly, ra.id
            FROM App\Entity\ReferenceArticle ra
            JOIN ra.inventoryMissions m
            LEFT JOIN ra.inventoryEntries e
            WHERE m = :mission"
        )->setParameter('mission', $mission);

        return $query->execute();
    }


    /**
     * @param InventoryMission $mission
     * @param int $refId
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function getEntryDateByMission($mission, $refId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT e.date
            FROM App\Entity\InventoryEntry e
            WHERE e.mission = :mission AND e.refArticle = :ref"
        )->setParameters([
            'mission' => $mission,
            'ref' => $refId
        ]);
        return $query->getOneOrNullResult();
    }

    public function countByMission($mission)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            /** @lang DQL */
            "SELECT COUNT(ra)
            FROM App\Entity\InventoryMission im
            LEFT JOIN im.refArticles ra
            WHERE im.id = :missionId"
        )->setParameter('missionId', $mission->getId());

        return $query->getSingleScalarResult();
    }

//    public function getIdAndReferenceBySearch($search, $activeOnly = false)
//    {
//        $em = $this->getEntityManager();
//
//        $dql = "SELECT r.id, r.reference as text
//          FROM App\Entity\ReferenceArticle r
//          LEFT JOIN r.statut s
//          WHERE r.reference LIKE :search AND r.typeQuantite = :qte_ref";
//
//        if ($activeOnly) {
//            $dql .= " AND s.nom = '" . ReferenceArticle::STATUT_ACTIF . "'";
//        }
//
//        $query = $em
//            ->createQuery($dql)
//            ->setParameters([
//            'search' => '%' . $search . '%',
//            'qte_ref' => ReferenceArticle::TYPE_QUANTITE_REFERENCE
//        ]);
//
//        return $query->execute();
//    }

    /**
     * @param InventoryFrequency $frequency
     * @return ReferenceArticle[]
     */
    public function findByFrequencyOrderedByLocation($frequency)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT ra
            FROM App\Entity\ReferenceArticle ra
            JOIN ra.category c
            LEFT JOIN ra.emplacement e
            WHERE c.frequency = :frequency ORDER BY e.label"
		)
			->setParameter('frequency', $frequency);

        return $query->execute();
    }

	/**
	 * @param InventoryFrequency $frequency
	 * @return mixed
	 * @throws NonUniqueResultException
	 */
    public function countActiveByFrequencyWithoutDateInventory($frequency)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT COUNT(ra.id) as nbRa, COUNT(a.id) as nbA
            FROM App\Entity\ReferenceArticle ra
            JOIN ra.category c
            LEFT JOIN ra.articlesFournisseur af
            LEFT JOIN af.articles a
            LEFT JOIN ra.statut sra
            LEFT JOIN a.statut sa
            WHERE c.frequency = :frequency
            AND (
            (ra.typeQuantite = 'reference' AND ra.dateLastInventory is null AND sra.nom = :refActive)
            OR
            (ra.typeQuantite = 'article' AND a.dateLastInventory is null AND sa.nom = :artActive)
            )"
		)->setParameters([
			'frequency' => $frequency,
			'refActive' => ReferenceArticle::STATUT_ACTIF,
			'artActive' => Article::STATUT_ACTIF
		]);

		return $query->getOneOrNullResult();
	}

	/**
	 * @param InventoryFrequency $frequency
	 * @param int $limit
	 * @return ReferenceArticle[]|Article[]
	 */
	public function findActiveByFrequencyWithoutDateInventoryOrderedByEmplacementLimited($frequency, $limit)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT ra
            FROM App\Entity\ReferenceArticle ra
            JOIN ra.category c
            LEFT JOIN ra.statut sra
            LEFT JOIN ra.emplacement rae
            WHERE c.frequency = :frequency
            AND ra.typeQuantite = :typeQuantity
            AND ra.dateLastInventory is null 
            AND sra.nom = :refActive
            ORDER BY rae.label"
		)->setParameters([
			'frequency' => $frequency,
			'typeQuantity' => ReferenceArticle::TYPE_QUANTITE_REFERENCE,
			'refActive' => ReferenceArticle::STATUT_ACTIF,
		]);

		if ($limit)	$query->setMaxResults($limit);

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
		"SELECT ra.barCode
		FROM App\Entity\ReferenceArticle ra
		WHERE ra.barCode LIKE :barCode
		ORDER BY ra.barCode DESC
		")
			->setParameter('barCode', ReferenceArticle::BARCODE_PREFIX . $dateCode . '%')
			->setMaxResults(1);

		$result = $query->execute();
		return $result ? $result[0]['barCode'] : null;
	}

}