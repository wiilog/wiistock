<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\FreeField;
use App\Entity\FiltreRef;
use App\Entity\InventoryFrequency;
use App\Entity\InventoryMission;
use App\Entity\Livraison;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Helper\QueryCounter;
use WiiCommon\Helper\Stream;
use App\Service\VisibleColumnService;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * @method ReferenceArticle|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReferenceArticle|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReferenceArticle[]    findAll()
 * @method ReferenceArticle[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReferenceArticleRepository extends EntityRepository {

    private const DtToDbLabels = [
        'label' => 'libelle',
        'quantityType' => 'typeQuantite',
        'availableQuantity' => 'quantiteDisponible',
        'stockQuantity' => 'quantiteStock',
        'securityThreshold' => 'limitSecurity',
        'warningThreshold' => 'limitWarning',
        'unitPrice' => 'prixUnitaire',
        'emergency' => 'isUrgent',
        'mobileSync' => 'needsMobileSync',
        'lastInventory' => 'dateLastInventory',
        'comment' => 'commentaire',
    ];

    private const FIELDS_TYPE_DATE = [
        "dateLastInventory"
    ];

    private const CART_COLUMNS_ASSOCIATION = [
        "label" => "libelle",
        "availableQuantity" => "quantiteDisponible",
    ];

    public function getForSelect(?string $term, Utilisateur $user) {
        $queryBuilder = $this->createQueryBuilder("reference");

        $visibilityGroup = $user->getVisibilityGroups();
        if (!$visibilityGroup->isEmpty()) {
            $queryBuilder
                ->join('reference.visibilityGroup', 'visibility_group')
                ->andWhere('visibility_group.id IN (:userVisibilityGroups)')
                ->setParameter('userVisibilityGroups', Stream::from(
                    $visibilityGroup->toArray()
                )->map(fn(VisibilityGroup $visibilityGroup) => $visibilityGroup->getId())->toArray());
        }

        return $queryBuilder
            ->select("reference.id AS id, reference.reference AS text, reference.libelle AS label")
            ->andWhere("reference.reference LIKE :term")
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getArrayResult();
    }

    public function iterateAll(Utilisateur $user): iterable {
        $queryBuilder = $this->createQueryBuilder('referenceArticle');

        $visibilityGroup = $user->getVisibilityGroups();
        if (!$visibilityGroup->isEmpty()) {
            $queryBuilder
                ->join('referenceArticle.visibilityGroup', 'visibility_group')
                ->andWhere('visibility_group.id IN (:userVisibilityGroups)')
                ->setParameter('userVisibilityGroups', Stream::from(
                    $visibilityGroup->toArray()
                )->map(fn(VisibilityGroup $visibilityGroup) => $visibilityGroup->getId())->toArray());
        }

        return $queryBuilder->distinct()
            ->select('referenceArticle.id')
            ->addSelect('referenceArticle.reference')
            ->addSelect('referenceArticle.libelle')
            ->addSelect('referenceArticle.quantiteStock')
            ->addSelect('typeRef.label as type')
            ->addSelect('join_buyer.username as buyer')
            ->addSelect('referenceArticle.typeQuantite')
            ->addSelect('statutRef.nom as statut')
            ->addSelect('referenceArticle.commentaire')
            ->addSelect('emplacementRef.label as emplacement')
            ->addSelect('referenceArticle.limitSecurity')
            ->addSelect('referenceArticle.limitWarning')
            ->addSelect('referenceArticle.prixUnitaire')
            ->addSelect('referenceArticle.barCode')
            ->addSelect('categoryRef.label as category')
            ->addSelect('referenceArticle.dateLastInventory')
            ->addSelect('referenceArticle.needsMobileSync')
            ->addSelect('referenceArticle.freeFields')
            ->addSelect('referenceArticle.stockManagement')
            ->addSelect('join_visibilityGroup.label AS visibilityGroup')
            ->leftJoin('referenceArticle.statut', 'statutRef')
            ->leftJoin('referenceArticle.emplacement', 'emplacementRef')
            ->leftJoin('referenceArticle.type', 'typeRef')
            ->leftJoin('referenceArticle.category', 'categoryRef')
            ->leftJoin('referenceArticle.buyer', 'join_buyer')
            ->leftJoin('referenceArticle.visibilityGroup', 'join_visibilityGroup')
            ->groupBy('referenceArticle.id')
            ->orderBy('referenceArticle.id', 'ASC')
            ->getQuery()
            ->toIterable();
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

    public function getByTransferOrders(array $transfersOrders): array {
        if (!empty($transfersOrders)) {
            $res = $this->createQueryBuilder('referenceArticle')
                ->select('referenceArticle.barCode AS barcode')
                ->addSelect('referenceArticle.libelle AS label')
                ->addSelect('referenceArticle.reference AS reference')
                ->addSelect('referenceArticle_location.label AS location')
                ->addSelect('referenceArticle.quantiteDisponible AS quantity')
                ->addSelect('transferOrder.id AS transfer_order_id')
                ->join('referenceArticle.transferRequests', 'transferRequest')
                ->leftJoin('referenceArticle.emplacement', 'referenceArticle_location')
                ->join('transferRequest.order', 'transferOrder')
                ->where('transferOrder IN (:transferOrders)')
                ->setParameter('transferOrders', $transfersOrders)
                ->getQuery()
                ->getResult();
        }
        else {
            $res = [];
        }
        return $res;
    }

    public function getIdAndRefBySearch(?string $search,
                                        Utilisateur $user,
                                        $activeOnly = false,
                                        $minQuantity = null,
                                        $typeQuantity = null,
                                        $field = 'reference',
                                        $locationFilter = null,
                                        $buyerFilter = null)
    {
        $queryBuilder = $this->createQueryBuilder('reference')
            ->select('reference.id')
            ->addSelect("reference.${field} as text")
            ->addSelect('reference.typeQuantite as typeQuantity')
            ->addSelect('reference.isUrgent as urgent')
            ->addSelect('reference.emergencyComment as emergencyComment')
            ->addSelect('reference.libelle')
            ->addSelect('reference.barCode')
            ->addSelect('join_location.label AS location')
            ->addSelect('reference.quantiteDisponible')
            ->leftJoin('reference.emplacement', 'join_location')
            ->where("reference.${field} LIKE :search")
            ->setParameter('search', '%' . $search . '%');

        $visibilityGroup = $user->getVisibilityGroups();
        if (!$visibilityGroup->isEmpty()) {
            $queryBuilder
                ->join('reference.visibilityGroup', 'visibility_group')
                ->andWhere('visibility_group.id IN (:userVisibilityGroups)')
                ->setParameter('userVisibilityGroups', Stream::from(
                    $visibilityGroup->toArray()
                )->map(fn(VisibilityGroup $visibilityGroup) => $visibilityGroup->getId())->toArray());
        }

        if ($activeOnly !== null) {
            $queryBuilder
                ->leftJoin('reference.statut', 'join_status')
                ->andWhere('join_status.nom = :activeStatus')
                ->setParameter('activeStatus', ReferenceArticle::STATUT_ACTIF);
        }

        if($minQuantity !== null) {
            $queryBuilder
                ->andWhere('reference.quantiteDisponible > :minQuantity')
                ->setParameter('minQuantity', $minQuantity);
        }

        if ($typeQuantity) {
            $queryBuilder
                ->andWhere('reference.typeQuantite = :type')
                ->setParameter('type', $typeQuantity);
        }

        if ($locationFilter) {
            $queryBuilder
                ->andWhere("(reference.emplacement IS NULL OR reference.typeQuantite = :typeQuantityArticle OR reference.emplacement = :location)")
                ->setParameter('location', $locationFilter)
                ->setParameter('typeQuantityArticle', ReferenceArticle::TYPE_QUANTITE_ARTICLE);
        }

        if ($buyerFilter) {
            $queryBuilder
                ->andWhere("reference.buyer = :buyer")
                ->setParameter('buyer', $buyerFilter);
        }

        return $queryBuilder
            ->getQuery()
            ->execute();
    }

    public function findByFiltersAndParams($filters, $params, Utilisateur $user)
    {
        $em = $this->getEntityManager();
        $index = 0;

        // fait le lien entre intitulé champs dans datatable/filtres côté front
        // et le nom des attributs de l'entité ReferenceArticle (+ typage)
        $linkFieldLabelToColumn = [
            'Libellé' => ['field' => 'libelle', 'typage' => 'text'],
            'Référence' => ['field' => 'reference', 'typage' => 'text'],
            'Type' => ['field' => 'type_id', 'typage' => 'list'],
            'Quantité stock' => ['field' => 'quantiteStock', 'typage' => 'number'],
            'Prix unitaire' => ['field' => 'prixUnitaire', 'typage' => 'number'],
            'Emplacement' => ['field' => 'emplacement_id', 'typage' => 'list'],
            'Code barre' => ['field' => 'barCode', 'typage' => 'text'],
            'Quantité disponible' => ['field' => 'quantiteDisponible', 'typage' => 'text'],
            'Commentaire d\'urgence' => ['field' => 'emergencyComment', 'typage' => 'text'],
            'Dernier inventaire' => ['field' => 'dateLastInventory', 'typage' => 'text'],
            'Seuil d\'alerte' => ['field' => 'limitWarning', 'typage' => 'number'],
            'Seuil de sécurité' => ['field' => 'limitSecurity', 'typage' => 'number'],
            'Urgence' => ['field' => 'isUrgent', 'typage' => 'boolean'],
            'Synchronisation nomade' => ['field' => 'needsMobileSync', 'typage' => 'sync'],
            'Gestion de stock' => ['field' => 'stockManagement', 'typage' => 'text']
        ];

        $queryBuilder = $this->createQueryBuilder("ra");
        $visibilityGroup = $user->getVisibilityGroups();
        if (!$visibilityGroup->isEmpty()) {
            $queryBuilder
                ->join('ra.visibilityGroup', 'visibility_group')
                ->andWhere('visibility_group.id IN (:userVisibilityGroups)')
                ->setParameter('userVisibilityGroups', Stream::from(
                    $visibilityGroup->toArray()
                )->map(fn(VisibilityGroup $visibilityGroup) => $visibilityGroup->getId())->toArray());
        }

        foreach ($filters as $filter) {
            $index++;
            if ($filter['champFixe'] === FiltreRef::FIXED_FIELD_STATUT) {
                if ($filter['value'] === ReferenceArticle::STATUT_ACTIF) {
                    $queryBuilder->leftJoin('ra.statut', 'filter_sra');
                    $queryBuilder->andWhere('filter_sra.nom LIKE \'' . $filter['value'] . '\'');
                }
            } else if ($filter['champFixe'] === FiltreRef::FIXED_FIELD_VISIBILITY_GROUP) {
                $queryBuilder->leftJoin('ra.visibilityGroups', 'filter_visibility_groups')
                    ->andWhere('filter_visibility_groups.label LIKE :filter_visibility_group')
                    ->setParameter('filter_visibility_group', '%' . $filter['value'] . '%');
            } else if ($filter['champFixe'] === FiltreRef::FIXED_FIELD_MANAGERS) {
                $queryBuilder->leftJoin('ra.managers', 'filter_manager')
                    ->andWhere('filter_manager.username LIKE :filter_manager')
                    ->setParameter('filter_manager', '%' . $filter['value'] . '%');
            } else if ($filter['champFixe'] === FiltreRef::FIXED_FIELD_PROVIDER_CODE) {
                $queryBuilder
                    ->leftJoin('ra.articlesFournisseur', 'filter_af_provider_code')
                    ->leftJoin('filter_af_provider_code.fournisseur', 'filter_provider_code')
                    ->andWhere('filter_provider_code.codeReference LIKE :providerCode')
                    ->setParameter('providerCode', '%' . $filter['value'] . '%');
            } else if ($filter['champFixe'] === FiltreRef::FIXED_FIELD_PROVIDER_LABEL) {
                $queryBuilder
                    ->leftJoin('ra.articlesFournisseur', 'filter_af_provider_label')
                    ->leftJoin('filter_af_provider_label.fournisseur', 'filter_provider_label')
                    ->andWhere('filter_provider_label.nom LIKE :providerLabel')
                    ->setParameter('providerLabel', '%' . $filter['value'] . '%');
            }
            else {
                // cas particulier champ référence article fournisseur
                if ($filter['champFixe'] === FiltreRef::FIXED_FIELD_REF_ART_FOURN) {
                    $queryBuilder
                        ->leftJoin('ra.articlesFournisseur', 'filter_af')
                        ->andWhere('filter_af.reference LIKE :reference')
                        ->setParameter('reference', '%' . $filter['value'] . '%');
                } // cas champ fixe
                else if ($label = $filter['champFixe']) {
                    $array = $linkFieldLabelToColumn[$label] ?? null;
                    if ($array) {
                        $field = $array['field'];
                        $typage = $array['typage'];

                        switch ($typage) {
                            case 'sync':
                                if ($filter['value'] == 0) {
                                    $queryBuilder
                                        ->andWhere("ra.needsMobileSync = :value$index OR ra.needsMobileSync IS NULL")
                                        ->setParameter("value$index", $filter['value']);
                                }
                                else {
                                    $queryBuilder
                                        ->andWhere("ra.needsMobileSync = :value$index")
                                        ->setParameter("value$index", $filter['value']);
                                }
                                break;
                            case 'text':
                                $queryBuilder
                                    ->andWhere("ra." . $field . " LIKE :value" . $index)
                                    ->setParameter('value' . $index, '%' . $filter['value'] . '%');
                                break;
                            case 'number':
                                $queryBuilder
                                    ->andWhere("ra.$field = :value$index")
                                    ->setParameter("value$index", $filter['value']);
                                break;
                            case 'boolean':
                                $queryBuilder
                                    ->andWhere("ra.isUrgent = :value$index")
                                    ->setParameter("value$index", $filter['value']);
                                break;
                            case 'list':
                                switch ($field) {
                                    case 'type_id':
                                        $queryBuilder
                                            ->leftJoin('ra.type', 'tFilter')
                                            ->andWhere('tFilter.label = :typeLabel')
                                            ->setParameter('typeLabel', $filter['value']);
                                        break;
                                    case 'emplacement_id':
                                        $queryBuilder
                                            ->leftJoin('ra.emplacement', 'eFilter')
                                            ->andWhere('eFilter.label = :emplacementLabel')
                                            ->setParameter('emplacementLabel', $filter['value']);
                                        break;
                                }
                                break;
                        }
                    }
                } // cas champ libre
                else if ($filter['champLibre']) {
                    $value = $filter['value'];
                    $clId = $filter['champLibre'];
                    $freeFieldType = $filter['typage'];
                    switch ($freeFieldType) {
                        case FreeField::TYPE_BOOL:
                            $value = empty($value) ? "0" : $value;
                            break;
                        case FreeField::TYPE_TEXT:
                            $value = '%' . $value . '%';
                            break;
                        case FreeField::TYPE_DATE:
                        case FreeField::TYPE_DATETIME:
                            $formattedDate = DateTime::createFromFormat('d/m/Y', $value) ?: $value;
                             $value = $formattedDate ? $formattedDate->format('Y-m-d') : null;
                            if ($freeFieldType === FreeField::TYPE_DATETIME) {
                                $value .= '%';
                            }
                            break;
                        case FreeField::TYPE_LIST:
                        case FreeField::TYPE_LIST_MULTIPLE:
                            $value = Stream::from(json_decode($value) ?: [])
                                ->map(function (?string $value) {
                                    return '%' . ($value ?? '') . '%';
                                })
                                ->toArray();
                            break;
                        case FreeField::TYPE_NUMBER:
                            break;
                    }
                    if (!is_array($value)) {
                        $value = [$value];
                    }

                    $jsonSearchesQueryArray = Stream::from($value)
                        ->map(function(?string $item) use ($clId, $freeFieldType) {
                            $item = isset($item) ? $item : '';
                            $conditionType = ' IS NOT NULL';
                            if ($item === "0" && $freeFieldType === FreeField::TYPE_BOOL) {
                                $item = "1";
                                $conditionType = ' IS NULL';
                            }
                            return "JSON_SEARCH(ra.freeFields, 'one', '${item}', NULL, '$.\"${clId}\"')" . $conditionType;
                        })
                        ->toArray();

                    if (!empty($jsonSearchesQueryArray)) {
                        $jsonSearchesQueryString = '(' . implode(' OR ', $jsonSearchesQueryArray) . ')';
                        $queryBuilder->andWhere($jsonSearchesQueryString);
                    }
                }
            }
        }

        // prise en compte des paramètres issus du datatable
        if (!empty($params) && !empty($params->get('search'))) {
            $searchValue = is_string($params->get('search')) ? $params->get('search') : $params->get('search')['value'];
            if (!empty($searchValue)) {
                $date = DateTime::createFromFormat('d/m/Y', $searchValue);
                $date = $date ? $date->format('Y-m-d') : null;
                $search = "%$searchValue%";
                $ids = [];
                $query = [];

                foreach ($user->getRecherche() as $key => $searchField) {
                    $searchField = self::DtToDbLabels[$searchField] ?? $searchField;
                    switch ($searchField) {
                        case "supplierLabel":
                            $subqb = $em->createQueryBuilder()
                                ->select('ra.id')
                                ->from('App\Entity\ReferenceArticle', 'ra')
                                ->leftJoin('ra.articlesFournisseur', 'afra')
                                ->leftJoin('afra.fournisseur', 'fra')
                                ->andWhere('fra.nom LIKE :valueSearch')
                                ->setParameter('valueSearch', $search);

                            foreach ($subqb->getQuery()->execute() as $idArray) {
                                $ids[] = $idArray['id'];
                            }
                            break;

                        case "supplierCode":
                            $subqb = $em->createQueryBuilder()
                                ->select('referenceArticle.id')
                                ->from('App\Entity\ReferenceArticle', 'referenceArticle')
                                ->innerJoin('referenceArticle.articlesFournisseur', 'articleFournisseur')
                                ->innerJoin('articleFournisseur.fournisseur', 'fournisseur')
                                ->andWhere('fournisseur.codeReference LIKE :valueSearch')
                                ->setParameter('valueSearch', $search);

                            $res = $subqb->getQuery()->execute();

                            foreach ($res as $idArray) {
                                $ids[] = $idArray['id'];
                            }
                            break;

                        case "referenceSupplierArticle":
                            $subqb = $em->createQueryBuilder()
                                ->select('referenceArticle.id')
                                ->from(ReferenceArticle::class, 'referenceArticle')
                                ->innerJoin('referenceArticle.articlesFournisseur', 'articleFournisseur')
                                ->andWhere('articleFournisseur.reference LIKE :valueSearch')
                                ->setParameter('valueSearch', $search);

                            $res = $subqb->getQuery()->execute();

                            foreach ($res as $idArray) {
                                $ids[] = $idArray['id'];
                            }
                            break;
                        case "managers":
                            $subqb = $this->createQueryBuilder('referenceArticle');
                            $subqb
                                ->select('referenceArticle.id')
                                ->leftJoin('referenceArticle.managers', 'managers')
                                ->andWhere('managers.username LIKE :username')
                                ->setParameter('username', $search);

                            foreach ($subqb->getQuery()->execute() as $idArray) {
                                $ids[] = $idArray['id'];
                            }
                            break;
                        case "buyer":
                            $subqb = $this->createQueryBuilder('referenceArticle');
                            $subqb
                                ->select('referenceArticle.id')
                                ->leftJoin('referenceArticle.buyer', 'buyer')
                                ->andWhere('buyer.username LIKE :username')
                                ->setParameter('username', $search);

                            foreach ($subqb->getQuery()->execute() as $idArray) {
                                $ids[] = $idArray['id'];
                            }
                            break;
                        default:
                            $field = self::DtToDbLabels[$searchField] ?? $searchField;
                            $freeFieldId = VisibleColumnService::extractFreeFieldId($field);
                            if(is_numeric($freeFieldId)) {
                                $query[] = "JSON_SEARCH(ra.freeFields, 'one', :search, NULL, '$.\"$freeFieldId\"') IS NOT NULL";
                                $queryBuilder->setParameter("search", $date ?: $search);
                            } else if (property_exists(ReferenceArticle::class, $field)) {
                                if ($date && in_array($field, self::FIELDS_TYPE_DATE)) {
                                    $query[] = "ra.$field BETWEEN :dateMin AND :dateMax";
                                    $queryBuilder
                                        ->setParameter("dateMin", "$date 00:00:00")
                                        ->setParameter("dateMax", "$date 23:59:59");
                                } else {
                                    $query[] = "ra.$field LIKE :search";
                                    $queryBuilder->setParameter('search', $search);
                                }
                            }
                            break;
                    }
                }

                $treatedIds = [];
                foreach ($ids as $id) {
                    if (!in_array($id, $treatedIds)) {
                        $query[] = 'ra.id = ' . $id;
                        $treatedIds[] = $id;
                    }
                }

                if (!empty($query)) {
                    $queryBuilder->andWhere(implode(' OR ', $query));
                }
                else {
                    // false condition because search is corresponding to 0 ra.id
                    $queryBuilder->andWhere('ra.id = 0');
                }
            }
        }

        // compte éléments filtrés
        $countQuery = QueryCounter::count($queryBuilder, "ra");

        if (!empty($params) && !empty($params->get('order'))) {
            $order = $params->get('order')[0]['dir'];
            if (!empty($order)) {
                $columnIndex = $params->get('order')[0]['column'];
                $columnName = $params->get('columns')[$columnIndex]['data'];
                $column = self::DtToDbLabels[$columnName] ?? $columnName;

                $orderAddSelect = [];

                switch ($column) {
                    case "actions":
                        break;
                    case "supplier":
                        $orderAddSelect[] = 'order_supplier.nom';
                        $queryBuilder
                            ->leftJoin('ra.articlesFournisseur', 'order_articlesFournisseur')
                            ->leftJoin('order_articlesFournisseur.fournisseur', 'order_supplier')
                            ->orderBy('order_supplier.nom', $order);
                        break;
                    case "type":
                        $orderAddSelect[] = 'order_type.label';
                        $queryBuilder
                            ->leftJoin('ra.type', 'order_type')
                            ->orderBy('order_type.label', $order);
                        break;
                    case "location":
                        $orderAddSelect[] = 'order_location.label';
                        $queryBuilder
                            ->leftJoin('ra.emplacement', 'order_location')
                            ->orderBy('order_location.label', $order);
                        break;
                    case "status":
                        $orderAddSelect[] = 'order_status.nom';
                        $queryBuilder
                            ->leftJoin('ra.statut', 'order_status')
                            ->orderBy('order_status.nom', $order);
                        break;
                    case "buyer":
                        $orderAddSelect[] = 'order_buyer.username';
                        $queryBuilder
                            ->leftJoin('ra.buyer', 'order_buyer')
                            ->orderBy('order_buyer.username', $order);
                        break;
                    default:
                        $freeFieldId = VisibleColumnService::extractFreeFieldId($column);
                        if(is_numeric($freeFieldId)) {
                            /** @var FreeField $freeField */
                            $freeField = $this->getEntityManager()->getRepository(FreeField::class)->find($freeFieldId);
                            if($freeField->getTypage() === FreeField::TYPE_NUMBER) {
                                $queryBuilder->orderBy("CAST(JSON_EXTRACT(ra.freeFields, '$.\"$freeFieldId\"') AS SIGNED)", $order);
                            } else {
                                $queryBuilder->orderBy("JSON_EXTRACT(ra.freeFields, '$.\"$freeFieldId\"')", $order);
                            }
                        } else if ($column != 'attachments' && property_exists(ReferenceArticle::class, $column)) {
                            $queryBuilder->orderBy("ra.$column", $order);
                        }
                        break;
                }
            }
        }

        if (!empty($params) && !empty($params->get('start'))) {
            $queryBuilder->setFirstResult($params->get('start'));
        }
        if (!empty($params) && !empty($params->get('length'))) {
            $queryBuilder->setMaxResults($params->get('length'));
        }

        $queryBuilder
            ->select('ra')
            ->distinct();

        foreach ($orderAddSelect ?? [] as $addSelect) {
            $queryBuilder->addSelect($addSelect);
        }

        return [
            'data' => $queryBuilder->getQuery()->getResult(),
            'count' => $countQuery
        ];
    }

    public function countAll(): int {
        return $this->createQueryBuilder("r")
            ->select("COUNT(r)")
            ->getQuery()
            ->getSingleScalarResult();
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

    public function countByReference($reference, $refId = null): int {
        $qb = $this->createQueryBuilder("ra")
            ->select("COUNT(ra)")
            ->where("ra.reference = :reference")
            ->setParameter('reference', $reference);

        if ($refId) {
            $qb->andWhere("ra.id != :id")
                ->setParameter('id', $refId);
        }

        return $qb->getQuery()->getSingleScalarResult();
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
        return $this->createQueryBuilder('referenceArticle')
            ->select('referenceArticle.reference AS reference')
            ->addSelect('join_location.label AS location')
            ->addSelect('referenceArticle.libelle AS label')
            ->addSelect('join_preparationLine.pickedQuantity AS quantity')
            ->addSelect('1 AS is_ref')
            ->addSelect('join_delivery.id AS id_livraison')
            ->addSelect('referenceArticle.barCode AS barCode')
            ->leftJoin('referenceArticle.emplacement', 'join_location')
            ->join('referenceArticle.preparationOrderReferenceLines', 'join_preparationLine')
            ->join('join_preparationLine.preparation', 'join_preparation')
            ->join('join_preparation.livraison', 'join_delivery')
            ->andWhere('join_delivery.id IN (:deliveryIds) AND join_preparationLine.pickedQuantity > 0')
            ->setParameter('livraisonsIds', $livraisonsIds, Connection::PARAM_STR_ARRAY)
            ->getQuery()
            ->execute();
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
                    ra.barCode,
                    ra.libelle as reference_label
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

    public function getEntryByMission(InventoryMission $mission, $refId)
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

    /**
     * @return ReferenceArticle[]
     */
    public function findByFrequencyOrderedByLocation(InventoryFrequency $frequency): array
    {
        $queryBuilder = $this->createQueryBuilder('referenceArticle')
            ->select('referenceArticle')
            ->join('referenceArticle.category', 'category')
            ->join('referenceArticle.statut', 'status')
            ->leftJoin('referenceArticle.emplacement', 'location')
            ->where('category.frequency = :frequency')
            ->orderBy('location.label')
            ->andWhere('status.nom = :status')
            ->setParameters([
                'frequency' => $frequency,
                'status' => ReferenceArticle::STATUT_ACTIF
            ]);

        return $queryBuilder
            ->getQuery()
            ->execute();
    }

    public function countActiveByFrequencyWithoutDateInventory(InventoryFrequency $frequency)
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
            (ra.typeQuantite = 'article' AND a.dateLastInventory is null AND (sa.nom = :artActive OR sa.nom = :artDispute))
            )"
        )->setParameters([
            'frequency' => $frequency,
            'refActive' => ReferenceArticle::STATUT_ACTIF,
            'artActive' => Article::STATUT_ACTIF,
            'artDispute' => Article::STATUT_EN_LITIGE
        ]);

        return $query->getOneOrNullResult();
    }

    /**
     * @return ReferenceArticle[]
     */
    public function findActiveByFrequencyWithoutDateInventoryOrderedByEmplacementLimited(InventoryFrequency $frequency, int $limit): array
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

    public function getHighestBarCodeByDateCode(string $dateCode)
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

    public function getReservedQuantity(ReferenceArticle $referenceArticle): int
    {
        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $referenceReservedQuantity = $this->createQueryBuilder('referenceArticle')
                ->select('SUM(preparationLine.quantity)')
                ->join('referenceArticle.preparationOrderReferenceLines', 'preparationLine')
                ->join('preparationLine.preparation', 'preparation')
                ->join('preparation.statut', 'preparationStatus')
                ->andWhere('preparationStatus.nom IN (:inProgressPreparationStatus)')
                ->andWhere('referenceArticle = :referenceArticle')
                ->setMaxResults(1)
                ->setParameters([
                    'referenceArticle' => $referenceArticle,
                    'inProgressPreparationStatus' => [Preparation::STATUT_A_TRAITER, Preparation::STATUT_EN_COURS_DE_PREPARATION],
                ])
                ->getQuery()
                ->getSingleScalarResult();
            $articleReservedQuantity = $this->createQueryBuilder('referenceArticle')
                ->select('SUM(preparationOrderLine.quantity)')
                ->join('referenceArticle.articlesFournisseur', 'supplierArticles')
                ->join('supplierArticles.articles', 'article')
                ->join('article.statut', 'articleStatus')
                ->join('article.preparationOrderLines', 'preparationOrderLine')
                ->join('preparationOrderLine.preparation', 'preparation')
                ->leftJoin('preparation.livraison', 'delivery')
                ->join('preparation.statut', 'preparationStatus')
                ->leftJoin('delivery.statut', 'deliveryStatus')
                ->andWhere('(preparationStatus.nom IN (:inProgressPreparationStatus) OR deliveryStatus.nom IN (:inProgressDeliveryStatus))')
                ->andWhere('articleStatus.nom = :transitArticleStatus')
                ->andWhere('referenceArticle = :referenceArticle')
                ->setMaxResults(1)
                ->setParameters([
                    'referenceArticle' => $referenceArticle,
                    'transitArticleStatus' => Article::STATUT_EN_TRANSIT,
                    'inProgressPreparationStatus' => [Preparation::STATUT_A_TRAITER, Preparation::STATUT_EN_COURS_DE_PREPARATION],
                    'inProgressDeliveryStatus' => [Livraison::STATUT_A_TRAITER],
                ])
                ->getQuery()
                ->getSingleScalarResult();
            $reservedQuantity = ($referenceReservedQuantity ?? 0) + ($articleReservedQuantity ?? 0);
        } else {
            $reservedQuantity = $referenceArticle->getQuantiteReservee();
        }
        return $reservedQuantity;
    }

    public function countInventoryAnomaliesByRef(ReferenceArticle $ref): int
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

    public function getReferenceArticlesGroupedByTransfer(array $requests, bool $isRequests = true) {
        if (!empty($requests)) {
            $queryBuilder = $this->createQueryBuilder('referenceArticle')
                ->select('referenceArticle.barCode AS barCode')
                ->addSelect('referenceArticle.reference AS reference')
                ->join('referenceArticle.transferRequests', 'transferRequest');

            if ($isRequests) {
                $queryBuilder
                    ->addSelect('transferRequest.id AS transferId')
                    ->where('transferRequest.id IN (:requests)')
                    ->setParameter('requests', $requests);
            }
            else {
                $queryBuilder
                    ->addSelect('transferOrder.id AS transferId')
                    ->join('transferRequest.order', 'transferOrder')
                    ->where('transferOrder.id IN (:orders)')
                    ->setParameter('orders', $requests);
            }

            $res = $queryBuilder
                ->getQuery()
                ->getResult();

            return Stream::from($res)
                ->reduce(function (array $acc, array $articleArray) {
                    $transferRequestId = $articleArray['transferId'];
                    if (!isset($acc[$transferRequestId])) {
                        $acc[$transferRequestId] = [];
                    }
                    $acc[$transferRequestId][] = $articleArray;
                    return $acc;
                }, []);
        }
        else {
            return [];
        }
    }

    public function findInCart(Utilisateur $user, array $params) {
        $qb = $this->createQueryBuilder("reference_article");

        $qb
            ->innerJoin('reference_article.carts', 'cart')
            ->where("cart.user = :user")
            ->setParameter("user", $user);

        foreach($params["order"] as $order) {
            $column = $params["columns"][$order["column"]]['name'];
            $column = self::CART_COLUMNS_ASSOCIATION[$column] ?? $column;

            if($column === "type") {
                $qb->join("reference_article.type", "search_type")
                    ->addOrderBy("search_type.label", $order["dir"]);
            } else if ($column !== 'supplierReference') {
                $qb->addOrderBy("reference_article.$column", $order["dir"]);
            }
        }

        $countTotal = QueryCounter::count($qb, "reference_article");

        return [
            "data" => $qb->getQuery()->getResult(),
            "count" => $countTotal,
            "total" => $countTotal
        ];
    }

}
