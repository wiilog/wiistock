<?php

namespace App\Repository;

use App\Entity\Demande;
use App\Entity\Filter;
use App\Entity\ReferenceArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
	 * @throws \Doctrine\ORM\NonUniqueResultException
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

    public function getIdAndLibelleBySearch($search)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT r.id, r.reference as text
          FROM App\Entity\ReferenceArticle r
          WHERE r.reference LIKE :search"
        )->setParameter('search', '%' . $search . '%');

        return $query->execute();
    }

    public function getQuantiteStockById($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT r. quantiteStock
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
			'Emplacement' => ['field' => 'emplacement_id', 'typage' => 'list']
        ];
        //TODO trouver + dynamique
        $qb
            ->select('ra')
            ->distinct()
            ->from('App\Entity\ReferenceArticle', 'ra')
            ->leftJoin('ra.valeurChampsLibres', 'vcl');

		$countTotal = count($qb->getQuery()->getResult());

		foreach ($filters as $filter) {
            $index++;
//
//            $operatorOR = $filter['operator'] == 'or';
			//TODO filtres et/ou

            // cas particulier champ référence article fournisseur
            if ($filter['champFixe'] === Filter::CHAMP_FIXE_REF_ART_FOURN) {
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
//                    	$where = "ra." . $field . " LIKE :value" . $index;
//                    	$operatorOR ? $qb->orWhere($where) : $qb->andWhere($where);
//                        $qb->setParameter('value' . $index, '%' . $filter['value'] . '%');
						//TODO filtres et/ou
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
            }

            // cas champ libre
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

        foreach ($subQueries as $subQuery) {
            $ids = [];
            foreach ($subQuery as $idArray) {
                $ids[] = $idArray['id'];
            }
            if (empty($ids)) $ids = 0;
            $qb->andWhere($qb->expr()->in('ra.id', $ids));
        }

        $countQuery = count($qb->getQuery()->getResult());

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

				$countQuery = count($qb->getQuery()->getResult());
			}
			if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
			if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
		}
        $queryResult = $qb->getQuery();

        return ['data' => $queryResult->getResult(), 'count' => $countQuery, 'total' => $countTotal];
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

    public function countByReference($reference)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT (ra)
            FROM App\Entity\ReferenceArticle ra
            WHERE ra.reference = :reference"
        )->setParameter('reference', $reference);

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
			WHERE s.nom = :statutLabel OR (s.nom = :enCours AND p.Utilisateur = :user)"
		)->setParameters([
		    'statutLabel' => $statutLabel,
            'enCours' => $enCours,
            'user' => $user
        ]);

		return $query->execute();
	}

}
