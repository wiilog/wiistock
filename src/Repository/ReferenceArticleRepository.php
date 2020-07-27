<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\ChampLibre;
use App\Entity\FiltreRef;
use App\Entity\InventoryFrequency;
use App\Entity\InventoryMission;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;

/**
 * @method ReferenceArticle|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReferenceArticle|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReferenceArticle[]    findAll()
 * @method ReferenceArticle[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReferenceArticleRepository extends EntityRepository
{
    private const DtToDbLabels = [
        'Label' => 'libelle',
        'Libellé' => 'libelle',
        'Référence' => 'reference',
        'SeuilAlerte' => 'limitWarning',
        'SeuilSecurite' => 'limitSecurity',
        'Urgence' => 'isUrgent',
        'Type' => 'Type',
        'Quantité disponible' => 'quantiteDisponible',
        'Quantité stock' => 'quantiteStock',
        'Emplacement' => 'Emplacement',
        'Actions' => 'Actions',
        'Fournisseur' => 'Fournisseur',
        'Statut' => 'status',
        'Code barre' => 'barCode',
        'Date d\'alerte' => 'dateEmergencyTriggered',
        'typeQuantite' => 'typeQuantite',
        'Dernier inventaire' => 'dateLastInventory',
        'Synchronisation nomade' => 'needsMobileSync'
    ];

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

    public function getBetweenLimits($min, $step)
    {
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

    public function getByNeedsMobileSync()
    {
        $queryBuilder = $this->createQueryBuilder('referenceArticle');
        $queryBuilderExpr = $queryBuilder->expr();
        return $queryBuilder
            ->select('referenceArticle.barCode AS bar_code')
            ->addSelect('referenceArticle.reference AS reference')
            ->addSelect('referenceArticle.libelle AS label')
            ->addSelect('referenceArticle.quantiteDisponible AS available_quantity')
            ->addSelect('referenceArticle.typeQuantite AS type_quantity')
            ->addSelect('emplacement.label AS location_label')
            ->leftJoin('referenceArticle.emplacement', 'emplacement') // pour les références gérées par article
            ->join('referenceArticle.statut', 'statut')
            ->where($queryBuilderExpr->andX(
                $queryBuilderExpr->eq('statut.nom', ':actif'),
                $queryBuilderExpr->eq('referenceArticle.needsMobileSync', ':mobileSync')
            ))
            ->setParameter('actif', ReferenceArticle::STATUT_ACTIF)
            ->setParameter('mobileSync', true)
            ->getQuery()
            ->execute();
    }

    /**
     * @param string $search
     * @param bool $activeOnly
     * @param null $typeQuantity
     * @param $field
     * @return mixed
     */
    public function getIdAndRefBySearch($search, $activeOnly = false, $typeQuantity = null, $field = 'reference')
    {
        $em = $this->getEntityManager();

        $dql = "SELECT r.id,
                r.${field} as text,
                r.typeQuantite as typeQuantity,
                r.isUrgent as urgent,
                r.emergencyComment as emergencyComment,
                r.libelle,
                r.barCode,
                e.label as location,
                r.quantiteDisponible
          FROM App\Entity\ReferenceArticle r
          LEFT JOIN r.statut s
          LEFT JOIN r.emplacement e
          WHERE r.${field} LIKE :search ";

        if ($activeOnly) {
            $dql .= " AND s.nom = '" . ReferenceArticle::STATUT_ACTIF . "'";
        }

        if ($typeQuantity) {
            $dql .= "  AND r.typeQuantite = :type";
        }

        $query = $em
            ->createQuery($dql)
            ->setParameter('search', '%' . $search . '%');
        if ($typeQuantity) {
            $query
                ->setParameter('type', $typeQuantity);
        }

        return $query->execute();
    }

    public function findByFiltersAndParams($filters, $params, $user)
    {
        $needCLOrder = null;
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
            'Quantité stock' => ['field' => 'quantiteStock', 'typage' => 'number'],
            'Statut' => ['field' => 'Statut', 'typage' => 'text'],
            'Prix unitaire' => ['field' => 'prixUnitaire', 'typage' => 'number'],
            'Emplacement' => ['field' => 'emplacement_id', 'typage' => 'list'],
            'Code barre' => ['field' => 'barCode', 'typage' => 'text'],
            'Quantité disponible' => ['field' => 'quantiteDisponible', 'typage' => 'text'],
            'Commentaire d\'urgence' => ['field' => 'emergencyComment', 'typage' => 'text'],
            'Dernier inventaire' => ['field' => 'dateLastInventory', 'typage' => 'text'],
            'Seuil d\'alerte' => ['field' => 'limitWarning', 'typage' => 'number'],
            'Seuil de sécurité' => ['field' => 'limitSecurity', 'typage' => 'number'],
            'Urgence' => ['field' => 'isUrgent', 'typage' => 'boolean'],
            'Synchronisation nomade' =>['field' => 'needsMobileSync', 'typage' => 'sync'],
        ];

        $qb
            ->from('App\Entity\ReferenceArticle', 'ra');
        foreach ($filters as $filter) {
            $index++;

            if ($filter['champFixe'] === FiltreRef::CHAMP_FIXE_STATUT) {
                if ($filter['value'] === ReferenceArticle::STATUT_ACTIF) {
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
                        case 'sync':
                            if ($filter['value'] == 0 ){
                                 $qb
                                     ->andWhere("ra.needsMobileSync = :value$index OR ra.needsMobileSync IS NULL")
                                     ->setParameter("value$index", $filter['value']);
                            } else {
                                $qb
                                    ->andWhere("ra.needsMobileSync = :value$index")
                                    ->setParameter("value$index", $filter['value']);
                            }
                            break;
                        case 'text':
                            $qb
                                ->andWhere("ra." . $field . " LIKE :value" . $index)
                                ->setParameter('value' . $index, '%' . $filter['value'] . '%');
                            break;
                        case 'number':
                            $qb
                                ->andWhere("ra.$field = :value$index")
                                ->setParameter("value$index", $filter['value']);
                            break;
                        case 'boolean':
                            $qb
                                ->andWhere("ra.isUrgent = :value$index")
                                ->setParameter("value$index", $filter['value']);
                            break;
                        case 'list':
                            switch ($field) {
                                case 'type_id':
                                    $qb
                                        ->leftJoin('ra.type', 'tFilter')
                                        ->andWhere('tFilter.label = :typeLabel')
                                        ->setParameter('typeLabel', $filter['value']);
                                    break;
                                case 'emplacement_id':
                                    $qb
                                        ->leftJoin('ra.emplacement', 'eFilter')
                                        ->andWhere('eFilter.label = :emplacementLabel')
                                        ->setParameter('emplacementLabel', $filter['value']);
                                    break;
                            }
                            break;
                    }
                } // cas champ libre
                else if ($filter['champLibre']) {
                    $champLibreLabelAlias = "champLibreLabel_$index";
                    $qbSub = $em->createQueryBuilder();
                    $qbSub
                        ->select('ra' . $index . '.id')
                        ->from('App\Entity\ReferenceArticle', 'ra' . $index)
                        ->leftJoin('ra' . $index . '.valeurChampsLibres', 'vcl' . $index)
                        ->where("vcl$index.champLibre = :$champLibreLabelAlias")
                        ->setParameter($champLibreLabelAlias, $filter['champLibre']);
                    switch ($filter['typage']) {
                        case ChampLibre::TYPE_BOOL:
                            if ($filter['value'] === "1") {
                                $qbSub
                                    ->andWhere('vcl' . $index . '.valeur = 1');
                            } else {
                                $forbiddenIds = $this->array_values_recursive($qbSub->getQuery()->getResult());
                                $qbSub = $em->createQueryBuilder()
                                    ->select('raWithoutCL' . $index . '.id')
                                    ->from('App\Entity\ReferenceArticle', 'raWithoutCL' . $index)
                                    ->where('raWithoutCL' . $index . '.id NOT IN (:ids)')
                                    ->setParameter('ids', $forbiddenIds, Connection::PARAM_STR_ARRAY);
                            }
                            break;
                        case ChampLibre::TYPE_TEXT:
                            $qbSub
                                ->andWhere('vcl' . $index . '.valeur LIKE :value' . $index)
                                ->setParameter('value' . $index, '%' . $filter['value'] . '%');
                            break;
                        case ChampLibre::TYPE_NUMBER:
                        case ChampLibre::TYPE_LIST:
                            $qbSub
                                ->andWhere('vcl' . $index . '.valeur = :value' . $index)
                                ->setParameter('value' . $index, $filter['value']);
                            break;
                        case ChampLibre::TYPE_LIST_MULTIPLE:
                            $conditions = [];
                            foreach (explode(',', $filter['value']) as $listElement) {
                                $conditions[] = 'vcl' . $index . ".valeur LIKE '%" . $listElement . "%'";
                            }
                            $qbSub
                                ->andWhere(
                                    $qbSub->expr()->andX()->addMultiple($conditions)
                                );
                            break;
                        case ChampLibre::TYPE_DATE:
                        case ChampLibre::TYPE_DATETIME:
                            $date = explode('/', $filter['value']);
                            $formattedDated = substr($date[2], 0, 4) . '-' . $date[1] . '-' . $date[0] . '%';
                            $qbSub
                                ->andWhere("vcl$index.valeur LIKE :value$index")
                                ->setParameter("value$index", $formattedDated);
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
                $searchValue = is_string($params->get('search')) ? $params->get('search') : $params->get('search')['value'];
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
                        case 'Fournisseur':
                            $qb
                                ->leftJoin('ra.articlesFournisseur', 'afra')
                                ->leftJoin('afra.fournisseur', 'fra')
                                ->orderBy('fra.nom', $order);
                            break;
                        case 'Type':
                            $qb
                                ->leftJoin('ra.type', 't')
                                ->orderBy('t.label', $order);
                            break;
                        case 'Emplacement':
                            $qb
                                ->leftJoin('ra.emplacement', 'e')
                                ->orderBy('e.label', $order);
                            break;
                        case 'status':
                            $qb
                                ->leftJoin('ra.statut', 's')
                                ->orderBy('s.nom', $order);
                            break;
                        default:
                            if (property_exists(ReferenceArticle::class, $column)) {
                                $qb
                                    ->orderBy('ra.' . $column, $order);
                            } else {
                                $needCLOrder = [$order, $column];
                            }
                            break;
                    }
                }
            }
        }
        // compte éléments filtrés
        if (empty($filters) && empty($searchValue)) {
            $qb->select('count(ra)');
        } else {
            $qb
                ->select('count(distinct(ra))');
        }
        $countQuery = $qb->getQuery()->getSingleScalarResult();

        if (!empty($params)) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }
        $qb
            ->select('ra');
        if ($needCLOrder) {
            $paramsQuery = $qb->getParameters();
            $paramsQuery[] = new Parameter('orderField', $needCLOrder[1], 2);
            $qb
                ->addSelect('MAX((CASE WHEN cla.id IS NULL THEN 0 ELSE vclra.valeur END)) as vsort')
                ->leftJoin('ra.valeurChampsLibres', 'vclra')
                ->leftJoin('vclra.champLibre', 'cla', 'WITH', 'cla.label LIKE :orderField')
                ->groupBy('ra')
                ->orderBy('vsort', $needCLOrder[0])
                ->setParameters($paramsQuery);
        }
        return [
            'data' => $qb->getQuery()->getResult(),
            'count' => $countQuery
        ];
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

    public function getByPreparationsIds($preparationsIds)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT
                    ra.reference,
                    ra.typeQuantite as type_quantite,
                    e.label as location,
                    ra.libelle as label,
                    la.quantite as quantity,
                    1 as is_ref,
                    ra.barCode,
                    p.id as id_prepa
			FROM App\Entity\ReferenceArticle ra
			LEFT JOIN ra.emplacement e
			JOIN ra.ligneArticlePreparations la
			JOIN la.preparation p
			JOIN p.statut s
			WHERE p.id IN (:preparationsIds)"
        )->setParameter('preparationsIds', $preparationsIds, Connection::PARAM_STR_ARRAY);

        return $query->execute();
    }

    public function getByLivraisonsIds($livraisonsIds)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT ra.reference,
                         e.label as location,
                         ra.libelle as label,
                         la.quantitePrelevee as quantity,
                         1 as is_ref,
                         l.id as id_livraison,
                         ra.barCode
			FROM App\Entity\ReferenceArticle ra
			LEFT JOIN ra.emplacement e
			JOIN ra.ligneArticlePreparations la
			JOIN la.preparation p
			JOIN p.livraison l
			JOIN l.statut s
			WHERE l.id IN (:livraisonsIds) AND la.quantitePrelevee > 0"
        )->setParameter('livraisonsIds', $livraisonsIds, Connection::PARAM_STR_ARRAY);

        return $query->execute();
    }

    public function getByOrdreCollectesIds($collectesIds)
    {

        $em = $this->getEntityManager();
        $query = $em
            ->createQuery($this->getRefArticleCollecteQuery() . " WHERE oc.id IN (:collectesIds)")
            ->setParameter('collectesIds', $collectesIds, Connection::PARAM_STR_ARRAY);

        return $query->execute();
    }

    public function getByOrdreCollecteId($collecteId)
    {

        $em = $this->getEntityManager();
        $query = $em
            ->createQuery($this->getRefArticleCollecteQuery() . " WHERE oc.id = :id")
            ->setParameter('id', $collecteId);

        return $query->execute();
    }

    private function getRefArticleCollecteQuery()
    {
        return (/** @lang DQL */
        "SELECT ra.reference,
                         e.label as location,
                         ra.libelle as label,
                         ocr.quantite as quantity,
                         1 as is_ref,
                         oc.id as id_collecte,
                         ra.barCode
			FROM App\Entity\ReferenceArticle ra
			LEFT JOIN ra.emplacement e
			JOIN ra.ordreCollecteReferences ocr
			JOIN ocr.ordreCollecte oc
			JOIN oc.statut s");
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

    /**
     * @param InventoryMission $mission
     * @param $refId
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function getEntryByMission($mission, $refId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT e.date, e.quantity
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

        if ($limit) $query->setMaxResults($limit);

        return $query->execute();
    }

    /**
     * @param string $dateCode
     * @return mixed
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

    public function getAlertDataByParams($params, $filters)
    {
        $qb = $this->getDataAlert();

        $countTotal = count($qb->getQuery()->getResult());

        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'type':
                    $qb
                        ->join('ra.type', 't3')
                        ->andWhere('t3.label LIKE :type')
                        ->setParameter('type', $filter['value']);
            }
        }

        // prise en compte des paramètres issus du datatable
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('ra.reference LIKE :value OR ra.libelle LIKE :value')
                        ->setParameter('value', '%' . str_replace('_', '\_', $search) . '%');
                }
            }

            $countFiltered = count($qb->getQuery()->getResult());

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];
                    switch ($column) {
                        case 'Type':
                            $qb
                                ->join('ra.type', 't2')
                                ->orderBy('t2.label', $order);
                        case 'quantiteStock':
                            $qb
                                ->leftJoin('ra.articlesFournisseur', 'af')
                                ->leftJoin('af.articles', 'a')
                                ->addSelect('(CASE
								WHEN ra.typeQuantite = :typeQteArt
								THEN (SUM(a.quantite))
								ELSE ra.quantiteStock
								END) as quantity')
                                ->groupBy('ra.id')
                                ->orderBy('quantity', $order)
                                ->setParameter('typeQteArt', ReferenceArticle::TYPE_QUANTITE_ARTICLE);
                            break;
                        default:
                            $qb->orderBy('ra.' . $column, $order);
                            break;
                    }
                }
            }
        }

        if (!empty($params)) {
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

    /**
     * @param ReferenceArticle $referenceArticle
     * @return int
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function getStockQuantity(ReferenceArticle $referenceArticle): int
    {
        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $em = $this->getEntityManager();
            $query = $em->createQuery(
            /** @lang DQL */
                "SELECT SUM(a.quantite)
                    FROM App\Entity\ReferenceArticle ra
                    JOIN ra.articlesFournisseur af
                    JOIN af.articles a
                    JOIN a.statut s
                    WHERE s.nom NOT IN (:inactiveStatus)
                      AND ra = :refArt
                ")
                ->setParameters([
                    'refArt' => $referenceArticle->getId(),
                    'inactiveStatus' => [Article::STATUT_INACTIF, Article::STATUT_EN_LITIGE]
                ]);
            $stockQuantity = ($query->getSingleScalarResult() ?? 0);
        } else {
            $stockQuantity = $referenceArticle->getQuantiteStock();
        }
        return $stockQuantity;
    }

    /**
     * @param ReferenceArticle $referenceArticle
     * @return int
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function getReservedQuantity(ReferenceArticle $referenceArticle): int
    {
        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $em = $this->getEntityManager();
            $queryForLignes = $em
                ->createQuery("
                    SELECT SUM(ligneArticles.quantite)
                    FROM App\Entity\ReferenceArticle ra
                    JOIN ra.ligneArticlePreparations ligneArticles
                    JOIN ligneArticles.preparation preparation
                    JOIN preparation.statut preparationStatus
                    WHERE (preparationStatus.nom = :preparationStatusToTreat OR preparationStatus.nom = :preparationStatusCurrent)
                      AND ra = :refArt
                ")
                ->setParameters([
                    'refArt' => $referenceArticle->getId(),
                    'preparationStatusToTreat' => Preparation::STATUT_A_TRAITER,
                    'preparationStatusCurrent' => Preparation::STATUT_EN_COURS_DE_PREPARATION,
                ]);
            $queryForArticles = $em
                ->createQuery("
                        SELECT SUM(a.quantiteAPrelever)
                        FROM App\Entity\Article a
                        JOIN a.articleFournisseur artf
                        JOIN a.statut statut
                        WHERE artf.referenceArticle = :refArt
                        AND statut.nom = :transitStatutArt
                ")->setParameters([
                    'refArt' => $referenceArticle->getId(),
                    'transitStatutArt' => Article::STATUT_EN_TRANSIT
                ]);
            $reservedQuantityLignes = ($queryForLignes->getSingleScalarResult() ?? 0);
            $reservedQuantityArticles = ($queryForArticles->getSingleScalarResult() ?? 0);
            $reservedQuantity = $reservedQuantityLignes + $reservedQuantityArticles;
        } else {
            $reservedQuantity = $referenceArticle->getQuantiteReservee();
        }
        return $reservedQuantity;
    }

    public function countAlert()
    {
        $qb = $this->getDataAlert();

        return count($qb->getQuery()->getResult());
    }

    public function getDataAlert()
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('
                ra.reference,
                ra.libelle,
                ra.typeQuantite,
                ra.id,
                ra.quantiteStock,
                ra.limitSecurity,
                ra.limitWarning,
                ra.dateEmergencyTriggered,
                ra.typeQuantite,
                ra.quantiteDisponible,
                t.label as type')
            ->from('App\Entity\ReferenceArticle', 'ra')
            ->join('ra.type', 't')
            ->join('ra.statut', 'status')
            ->andWhere('status.nom = :activeStatus')
            ->andWhere('ra.dateEmergencyTriggered IS NOT NULL')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->gt('ra.limitSecurity', 0),
                    $qb->expr()->gt('ra.limitWarning', 0)
                )
            )
            ->setParameter('activeStatus', ReferenceArticle::STATUT_ACTIF);
        return $qb;
    }

    /**
     * @param ReferenceArticle $ref
     * @return int
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function countInventoryAnomaliesByRef($ref)
    {
        $em = $this->getEntityManager();

        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(ie)
			FROM App\Entity\InventoryEntry ie
			JOIN ie.refArticle ra
			WHERE ie.anomaly = 1 AND ra.id = :refId
			")->setParameter('refId', $ref->getId());

        return $query->getSingleScalarResult();
    }

    public function getStockMiniClByRef($ref)
    {
        $em = $this->getEntityManager();

        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT v.valeur
            FROM App\Entity\ValeurChampLibre v
            JOIN v.champLibre c
            JOIN v.articleReference a
            WHERE c.label LIKE 'stock mini%' AND v.valeur is not null AND a =:ref"
        )->setParameter('ref', $ref);

        return $query->execute();
    }

    public function getStockAlerteClByRef($ref)
    {
        $em = $this->getEntityManager();

        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT v.valeur
            FROM App\Entity\ValeurChampLibre v
            JOIN v.champLibre c
            JOIN v.articleReference a
            WHERE c.label LIKE 'stock alerte%' AND v.valeur is not null AND a =:ref"
        )->setParameter('ref', $ref);

        return $query->execute();
    }

    public function getStockPriceClByRef($ref)
    {
        $em = $this->getEntityManager();

        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT v.valeur
            FROM App\Entity\ValeurChampLibre v
            JOIN v.champLibre c
            JOIN v.articleReference a
            WHERE c.label LIKE 'prix unitaire%' AND v.valeur is not null AND a =:ref"
        )->setParameter('ref', $ref);

        return $query->execute();
    }

    public function getOneReferenceByBarCodeAndLocation(string $barCode, string $location)
    {
        $queryBuilder = $this
            ->createQueryBuilderByBarCodeAndLocation($barCode, $location, false)
            ->select('referenceArticle.reference as reference')
            ->addSelect('referenceArticle.id as id')
            ->addSelect('referenceArticle.barCode as barCode')
            ->addSelect('referenceArticle.quantiteDisponible as quantity')
            ->addSelect('1 as is_ref');

        $result = $queryBuilder->getQuery()->execute();
        return !empty($result) ? $result[0] : null;
    }

    public function findReferenceByBarCodeAndLocation(string $barCode, string $location)
    {
        $queryBuilder = $this
            ->createQueryBuilderByBarCodeAndLocation($barCode, $location)
            ->select('referenceArticle');

        return $queryBuilder->getQuery()->execute();
    }

    private function createQueryBuilderByBarCodeAndLocation(string $barCode, string $location, bool $onlyActive = true): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('referenceArticle');
        $queryBuilder
            ->join('referenceArticle.emplacement', 'emplacement')
            ->andWhere('emplacement.label = :location')
            ->andWhere('referenceArticle.barCode = :barCode')
            ->andWhere('referenceArticle.typeQuantite = :typeQuantite')
            ->setParameter('location', $location)
            ->setParameter('barCode', $barCode)
            ->setParameter('typeQuantite', ReferenceArticle::TYPE_QUANTITE_REFERENCE);

        if ($onlyActive) {
            $queryBuilder
                ->join('referenceArticle.statut', 'status')
                ->andWhere('status.nom = :activeStatus')
                ->setParameter('activeStatus', ReferenceArticle::STATUT_ACTIF);
        }

        return $queryBuilder;
    }

    public function getRefTypeQtyArticleByReception($id, $reference = null, $commande = null)
    {

        $queryBuilder = $this->createQueryBuilder('ra')
            ->select('ra.reference as reference')
            ->addSelect('rra.commande as commande')
            ->join('ra.receptionReferenceArticles', 'rra')
            ->join('rra.reception', 'r')
            ->andWhere('r.id = :id')
            ->andWhere('(rra.quantiteAR > rra.quantite OR rra.quantite IS NULL)')
            ->andWhere('ra.typeQuantite = :typeQty')
            ->setParameters([
            'id' => $id,
            'typeQty' => ReferenceArticle::TYPE_QUANTITE_ARTICLE
        ]);

        if (!empty($reference)) {
            $queryBuilder
                ->andWhere('ra.reference = :reference')
                ->setParameter('reference', $reference);
        }

        if (!empty($commande)) {
            $queryBuilder
                ->andWhere('rra.commande = :commande')
                ->setParameter('commande', $commande);
        }

        return $queryBuilder
            ->getQuery()
            ->execute();
    }

    private function array_values_recursive($array)
    {
        $flat = [];
        foreach ($array as $value) {
            if (is_array($value)) {
                $flat = array_merge($flat, $this->array_values_recursive($value));
            } else {
                $flat[] = $value;
            }
        }
        return $flat;
    }
}
