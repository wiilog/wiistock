<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\FiltreRef;
use App\Entity\FreeField;
use App\Entity\Inventory\InventoryCategory;
use App\Entity\Inventory\InventoryFrequency;
use App\Entity\Inventory\InventoryMission;
use App\Entity\Livraison;
use App\Entity\OrdreCollecte;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\ShippingRequest\ShippingRequestExpectedLine;
use App\Entity\TransferRequest;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Helper\QueryBuilderHelper;
use App\Service\VisibleColumnService;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;

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
        'managers' => 'managers',
    ];

    private const FIELDS_TYPE_DATE = [
        "dateLastInventory",
        "createdAt",
        "editedAt",
        "lastStockEntry",
        "lastStockExit",
    ];

    private const CART_COLUMNS_ASSOCIATION = [
        "label" => "libelle",
        "availableQuantity" => "quantiteDisponible",
    ];

    public function getForSelect(?string $term, Utilisateur $user, array $options = []): array {
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

        if($options['needsOnlyMobileSyncReference'] ?? false) {
            $queryBuilder->andWhere('reference.needsMobileSync = 1');
        }

        if($options['type-quantity'] ?? false) {
            $queryBuilder->andWhere('reference.typeQuantite = :typeQuantity')
                ->setParameter('typeQuantity', $options['type-quantity']);
        }

        if($options['status'] ?? false) {
            $queryBuilder
                ->andWhere('status.code = :status')
                ->setParameter('status', $options['status']);
        }

        if($options['ignoredDeliveryRequest'] ?? false) {
            $queryHasResult = $this->createQueryBuilder("reference_has_delivery_request")
                ->select('COUNT(reference_has_delivery_request)')
                ->join("reference_has_delivery_request.deliveryRequestLines", "lines")
                ->join("lines.request", "deliveryRequest")
                ->andWhere("deliveryRequest.id = :deliveryRequestId")
                ->andWhere("reference_has_delivery_request.id = reference.id")
                ->setMaxResults(1)
                ->getQuery()
                ->getDQL();

            $queryBuilder
                ->andWhere("($queryHasResult) = 0")
                ->setParameter('deliveryRequestId', $options['ignoredDeliveryRequest']);
        }

        if($options['ignoredShippingRequest'] ?? false) {
            $queryHasResult = $this->createQueryBuilder("reference_has_shipping_request")
                ->select('COUNT(reference_has_shipping_request)')
                ->join(ShippingRequestExpectedLine::class, "shippingRequestExpectedLine", Join::WITH, 'shippingRequestExpectedLine.referenceArticle = reference')
                ->join("shippingRequestExpectedLine.request", "shippingRequest")
                ->andWhere("shippingRequest.id = :shippingRequestId")
                ->setMaxResults(1)
                ->getQuery()
                ->getDQL();

            $queryBuilder
                ->andWhere("($queryHasResult) = 0")
                ->setParameter('shippingRequestId', $options['ignoredShippingRequest']);
        }

        return $queryBuilder
            ->distinct()
            ->select("reference.id AS id")
            ->addSelect('reference.reference AS text')
            ->addSelect('reference.libelle AS label')
            ->addSelect('emplacement.label AS location')
            ->addSelect('reference.description AS description')
            ->addSelect('reference.typeQuantite as typeQuantite')
            ->addSelect('reference.barCode as barCode')
            ->addSelect('type.id as typeId')
            ->addSelect('reference.dangerous_goods as dangerous')
            ->andWhere("reference.reference LIKE :term")
            ->andWhere("status.code != :draft")
            ->leftJoin("reference.statut", "status")
            ->leftJoin("reference.emplacement", "emplacement")
            ->leftJoin("reference.type", "type")
            ->setParameter("term", "%$term%")
            ->setParameter("draft", ReferenceArticle::DRAFT_STATUS)
            ->setMaxResults(100)
            ->getQuery()
            ->getArrayResult();
    }

    public function getForNomade() {
        $qb = $this->createQueryBuilder('referenceArticle');

        $qb->select('referenceArticle.id AS id')
            ->addSelect('referenceArticle.reference AS reference')
            ->addSelect("IF(JSON_UNQUOTE(JSON_EXTRACT(referenceArticle.description, '$.\"outFormatEquipment\"')) = 'null',
                                null,
                                JSON_UNQUOTE(JSON_EXTRACT(referenceArticle.description, '$.\"outFormatEquipment\"'))) AS outFormatEquipment")
            ->addSelect("IF(JSON_UNQUOTE(JSON_EXTRACT(referenceArticle.description, '$.\"manufacturerCode\"')) = 'null',
                                null,
                                JSON_UNQUOTE(JSON_EXTRACT(referenceArticle.description, '$.\"manufacturerCode\"'))) AS manufacturerCode")
            ->addSelect("IF(JSON_UNQUOTE(JSON_EXTRACT(referenceArticle.description, '$.\"length\"')) = 'null',
                                null,
                                JSON_UNQUOTE(JSON_EXTRACT(referenceArticle.description, '$.\"length\"'))) AS length")
            ->addSelect("IF(JSON_UNQUOTE(JSON_EXTRACT(referenceArticle.description, '$.\"width\"')) = 'null',
                                null,
                                JSON_UNQUOTE(JSON_EXTRACT(referenceArticle.description, '$.\"width\"'))) AS width")
            ->addSelect("IF(JSON_UNQUOTE(JSON_EXTRACT(referenceArticle.description, '$.\"height\"')) = 'null',
                                null,
                                JSON_UNQUOTE(JSON_EXTRACT(referenceArticle.description, '$.\"height\"'))) AS height")
            ->addSelect("IF(JSON_UNQUOTE(JSON_EXTRACT(referenceArticle.description, '$.\"volume\"')) = 'null',
                                null,
                                JSON_UNQUOTE(JSON_EXTRACT(referenceArticle.description, '$.\"volume\"'))) AS volume")
            ->addSelect("IF(JSON_UNQUOTE(JSON_EXTRACT(referenceArticle.description, '$.\"weight\"')) = 'null',
                                null,
                                JSON_UNQUOTE(JSON_EXTRACT(referenceArticle.description, '$.\"weight\"'))) AS weight")
            ->addSelect("IF(JSON_UNQUOTE(JSON_EXTRACT(referenceArticle.description, '$.\"associatedDocumentTypes\"')) = 'null',
                                null,
                                JSON_UNQUOTE(JSON_EXTRACT(referenceArticle.description, '$.\"associatedDocumentTypes\"'))) AS associatedDocumentTypes")
            ->andWhere('referenceArticle.needsMobileSync = true');

        return $qb->getQuery()->getResult();
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
            // if there are large images in the comment then we ignore it
            ->addSelect('IF(LENGTH(referenceArticle.commentaire) < 512, referenceArticle.commentaire, NULL) AS commentaire')
            ->addSelect('emplacementRef.label as emplacement')
            ->addSelect('referenceArticle.limitSecurity')
            ->addSelect('referenceArticle.limitWarning')
            ->addSelect('referenceArticle.prixUnitaire')
            ->addSelect('referenceArticle.barCode')
            ->addSelect('join_createdBy.username as createdBy')
            ->addSelect('referenceArticle.createdAt')
            ->addSelect('referenceArticle.editedAt')
            ->addSelect('join_editedBy.username as editedBy')
            ->addSelect('referenceArticle.lastStockEntry')
            ->addSelect('referenceArticle.lastStockExit')
            ->addSelect('categoryRef.label as category')
            ->addSelect('referenceArticle.dateLastInventory')
            ->addSelect('referenceArticle.needsMobileSync')
            ->addSelect('referenceArticle.freeFields')
            ->addSelect('referenceArticle.stockManagement')
            ->addSelect('join_visibilityGroup.label AS visibilityGroup')
            ->addSelect("GROUP_CONCAT(DISTINCT join_manager.username SEPARATOR ',') AS managers")
            ->addSelect("GROUP_CONCAT(DISTINCT join_supplier.codeReference SEPARATOR ',') AS supplierCodes")
            ->addSelect("GROUP_CONCAT(DISTINCT join_supplier.nom SEPARATOR ',') AS supplierLabels")
            ->leftJoin('referenceArticle.statut', 'statutRef')
            ->leftJoin('referenceArticle.emplacement', 'emplacementRef')
            ->leftJoin('referenceArticle.type', 'typeRef')
            ->leftJoin('referenceArticle.category', 'categoryRef')
            ->leftJoin('referenceArticle.buyer', 'join_buyer')
            ->leftJoin('referenceArticle.visibilityGroup', 'join_visibilityGroup')
            ->leftJoin('referenceArticle.createdBy', 'join_createdBy')
            ->leftJoin('referenceArticle.editedBy', 'join_editedBy')
            ->leftJoin('referenceArticle.managers', 'join_manager')
            ->leftJoin('referenceArticle.articlesFournisseur', 'join_supplierArticle')
            ->leftJoin('join_supplierArticle.fournisseur', 'join_supplier')
            ->groupBy('referenceArticle.id')
            ->orderBy('referenceArticle.id', 'ASC')
            ->getQuery()
            ->toIterable();
    }

    public function updateFields(ReferenceArticle $referenceArticle, array $fields) {
        $queryBuilder = $this
            ->createQueryBuilder('referenceArticle')
            ->update(ReferenceArticle::class, 'referenceArticle')
            ->where('referenceArticle = :reference')
            ->setParameter('reference', $referenceArticle);
        foreach ($fields as $field => $value) {
            $queryBuilder
                ->set("referenceArticle.$field", ":value$field")
                ->setParameter(":value$field", $value);
        }
        return $queryBuilder
            ->getQuery()
            ->execute();
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
            ->leftJoin('reference.statut', 'join_draft_status')
            ->where("reference.${field} LIKE :search")
            ->andWhere("join_draft_status.code <> :draft")
            ->setParameter('search', '%' . $search . '%')
            ->setParameter('draft', ReferenceArticle::DRAFT_STATUS);

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
                ->andWhere('reference.quantiteDisponible >= :minQuantity')
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
                ->setParameter('typeQuantityArticle', ReferenceArticle::QUANTITY_TYPE_ARTICLE);
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

    public function findByFiltersAndParams(array $filters, InputBag $params, Utilisateur $user): array
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
            'Dernier inventaire' => ['field' => 'dateLastInventory', 'typage' => 'date'],
            'Seuil d\'alerte' => ['field' => 'limitWarning', 'typage' => 'number'],
            'Seuil de sécurité' => ['field' => 'limitSecurity', 'typage' => 'number'],
            'Urgence' => ['field' => 'isUrgent', 'typage' => 'boolean'],
            'Synchronisation nomade' => ['field' => 'needsMobileSync', 'typage' => 'sync'],
            'Gestion de stock' => ['field' => 'stockManagement', 'typage' => 'text'],
            'Créée le' => ['field' => 'createdAt', 'typage' => 'date'],
            'Dernière modification le' => ['field' => 'editedAt', 'typage' => 'date'],
            'Dernière sortie le' => ['field' => 'lastStockExit', 'typage' => 'date'],
            'Dernière entrée le' => ['field' => 'lastStockEntry', 'typage' => 'date']
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
            if ($filter['champFixe'] === FiltreRef::FIXED_FIELD_VISIBILITY_GROUP) {
                $value = explode(',', $filter['value']);
                $queryBuilder->leftJoin('ra.visibilityGroup', 'filter_visibility_group')
                    ->andWhere('filter_visibility_group.label IN (:filter_visibility_group)')
                    ->setParameter('filter_visibility_group', $value);
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
            } else if(in_array($filter['champFixe'], [FiltreRef::FIXED_FIELD_CREATED_BY, FiltreRef::FIXED_FIELD_EDITED_BY, FiltreRef::FIXED_FIELD_BUYER])) {
                $field = $filter['champFixe'];
                switch ($field) {
                    case FiltreRef::FIXED_FIELD_CREATED_BY:
                        $field = 'createdBy';
                        break;
                    case FiltreRef::FIXED_FIELD_EDITED_BY:
                        $field = 'editedBy';
                        break;
                    case FiltreRef::FIXED_FIELD_BUYER:
                        $field = 'buyer';
                        break;
                }

                $queryBuilder
                    ->leftJoin("ra.$field", "filter_$field")
                    ->andWhere("filter_$field.username LIKE :$field")
                    ->setParameter($field, '%' . $filter['value'] . '%');
            } else if($filter['champFixe'] === FiltreRef::FIXED_FIELD_STATUS) {
                $queryBuilder
                    ->leftJoin("ra.statut", "filter_status")
                    ->andWhere("filter_status.nom = :status")
                    ->setParameter("status", $filter['value']);
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
                            case 'date':
                                $dateTimeFilter = DateTime::createFromFormat('d/m/Y', $filter['value']);
                                $dateStrFilter = $dateTimeFilter ? $dateTimeFilter->format('Y-m-d') : null;
                                $queryBuilder
                                    ->andWhere("ra." . $field . " LIKE :value" . $index)
                                    ->setParameter('value' . $index, '%' . $dateStrFilter . '%');
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
        $search = isset($user->getSearches()['reference']) ? $user->getSearches()['reference']['value'] : '';
        if (!empty($search)) {
            $searchValue = is_string($search) ? $search : $search['value'];
            if (!empty($searchValue)) {
                $date = DateTime::createFromFormat('d/m/Y', $searchValue);
                $date = $date ? $date->format('Y-m-d') : null;
                $search = "%$searchValue%";
                $ids = [];
                $query = [];
                $freeFieldRepository = $em->getRepository(FreeField::class);
                foreach ($user->getRecherche() as $key => $searchField) {
                    $searchField = self::DtToDbLabels[$searchField] ?? $searchField;
                    switch ($searchField) {
                        case "supplierLabel":
                            $subqb = $this->createQueryBuilder('referenceArticle');
                            $subqb
                                ->select('referenceArticle.id')
                                ->leftJoin('referenceArticle.articlesFournisseur', 'afra')
                                ->leftJoin('afra.fournisseur', 'fra')
                                ->andWhere('fra.nom LIKE :valueSearch')
                                ->setParameter('valueSearch', $search);

                            foreach ($subqb->getQuery()->execute() as $idArray) {
                                $ids[] = $idArray['id'];
                            }
                            break;
                        case "supplierCode":
                            $subqb = $this->createQueryBuilder('referenceArticle');
                            $subqb
                                ->select('referenceArticle.id')
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
                            $subqb = $this->createQueryBuilder('referenceArticle');
                            $subqb
                                ->select('referenceArticle.id')
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
                                ->join('referenceArticle.managers', 'managers')
                                ->andWhere('managers.username LIKE :valueSearch')
                                ->setParameter('valueSearch', $search);

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
                            if (is_numeric($freeFieldId) && $freeField = $freeFieldRepository->find($freeFieldId)) {
                                if ($freeField->getTypage() === FreeField::TYPE_BOOL) {
                                    $lowerSearchValue = strtolower($searchValue);

                                    if (($lowerSearchValue === "oui") || ($lowerSearchValue === "non")) {
                                        $booleanValue = $lowerSearchValue === "oui" ? 1 : 0;
                                        $query[] = "JSON_SEARCH(ra.freeFields, 'one', :search, NULL, '$.\"${freeFieldId}\"') IS NOT NULL";
                                        $queryBuilder->setParameter("search", $booleanValue);
                                    }
                                }
                                else {
                                    $query[] = "JSON_SEARCH(LOWER(ra.freeFields), 'one', :search, NULL, '$.\"$freeFieldId\"') IS NOT NULL";
                                    $queryBuilder->setParameter("search", $date ?: strtolower($search));
                                }
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
        $countQuery = QueryBuilderHelper::count($queryBuilder, "ra");

        if (!empty($params) && !empty($params->all('order'))) {
            $order = $params->all('order')[0]['dir'];
            if (!empty($order)) {
                $columnIndex = $params->all('order')[0]['column'];
                $columnName = $params->all('columns')[$columnIndex]['data'];
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
                    case 'visibilityGroups':
                        $orderAddSelect[] = 'order_group.label';
                        $queryBuilder
                            ->leftJoin('ra.visibilityGroup', 'order_group')
                            ->orderBy('order_group.label', $order);
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
                    case "createdBy":
                        $orderAddSelect[] = 'order_createdBy.username';
                        $queryBuilder
                            ->leftJoin('ra.createdBy', 'order_createdBy')
                            ->orderBy('order_createdBy.username', $order);
                        break;
                    case "editedBy":
                        $orderAddSelect[] = 'order_editedBy.username';
                        $queryBuilder
                            ->leftJoin('ra.editedBy', 'order_editedBy')
                            ->orderBy('order_editedBy.username', $order);
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

        if ($params->getInt('start')) $queryBuilder->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $queryBuilder->setMaxResults($params->getInt('length'));

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
            'typeQuantite' => ReferenceArticle::QUANTITY_TYPE_REFERENCE
        ]);

        return $query->getSingleScalarResult();
    }

    public function countByReference($reference, $referenceId = null, string $operator = "="): int {
        $qb = $this->createQueryBuilder("reference_article")
            ->select("COUNT(reference_article)");

        $parameter = $reference;
        if($operator === "LIKE") {
            $parameter .= "%";
        }

        $qb->andWhere("reference_article.reference $operator :reference")
            ->setParameter("reference", $parameter);

        if ($referenceId) {
            $qb->andWhere("reference_article.id != :id")
                ->setParameter('id', $referenceId);
        }

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getByPreparationsIds($preparationsIds): array
    {
        return $this->createQueryBuilder('reference_article')
            ->select('reference_article.reference AS reference')
            ->addSelect('reference_article.typeQuantite AS type_quantite')
            ->addSelect('join_location.label AS location')
            ->addSelect('reference_article.libelle AS label')
            ->addSelect('join_preparationLine.quantityToPick AS quantity')
            ->addSelect('1 as is_ref')
            ->addSelect('reference_article.barCode AS barCode')
            ->addSelect('join_preparation.id AS id_prepa')
            ->addSelect('join_targetLocationPicking.label AS targetLocationPicking')
            ->leftJoin('reference_article.emplacement', 'join_location')
            ->join('reference_article.preparationOrderReferenceLines', 'join_preparationLine')
            ->join('join_preparationLine.preparation', 'join_preparation')
            ->leftJoin('join_preparationLine.targetLocationPicking', 'join_targetLocationPicking')
            ->andWhere('join_preparation.id IN (:preparationsIds)')
            ->setParameter('preparationsIds', $preparationsIds, Connection::PARAM_STR_ARRAY)
            ->getQuery()
            ->execute();
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
            ->addSelect('referenceArticle.barCode AS barcode')
            ->leftJoin('referenceArticle.emplacement', 'join_location')
            ->join('referenceArticle.preparationOrderReferenceLines', 'join_preparationLine')
            ->join('join_preparationLine.preparation', 'join_preparation')
            ->join('join_preparation.livraison', 'join_delivery')
            ->andWhere('join_delivery.id IN (:deliveryIds) AND join_preparationLine.pickedQuantity > 0')
            ->setParameter('deliveryIds', $livraisonsIds, Connection::PARAM_STR_ARRAY)
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
                    ra.typeQuantite AS quantity_type,
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

    public function countByMission($mission)
    {
        return $this->createQueryBuilder('reference_article')
            ->select('COUNT(reference_article)')
            ->join('reference_article.inventoryMissions', 'mission')
            ->andWhere('mission = :mission')
            ->setParameter('mission', $mission)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return ReferenceArticle[]
     */
    public function iterateReferencesToInventory(InventoryFrequency $frequency, InventoryMission $inventoryMission): iterable
    {
        $queryBuilder = $this->createQueryBuilder('referenceArticle');
        $exprBuilder = $queryBuilder->expr();
        $queryBuilder
            ->select('referenceArticle')
            ->distinct()
            ->join('referenceArticle.category', 'category')
            ->join('referenceArticle.statut', 'status')
            ->join('category.frequency', 'frequency')
            ->leftJoin('referenceArticle.inventoryMissions', 'inventoryMission')
            ->andWhere($exprBuilder->orX(
                'inventoryMission.id IS NULL',
                $exprBuilder->andX(
                    ':startDate < inventoryMission.startPrevDate',
                    ':endDate < inventoryMission.startPrevDate'
                ),
                $exprBuilder->andX(
                    ':startDate > inventoryMission.endPrevDate',
                    ':endDate > inventoryMission.endPrevDate'
                )
            ))
            ->andWhere('frequency = :frequency')
            ->andWhere('status.nom = :status')
            ->andWhere('referenceArticle.typeQuantite = :quantityType_reference')
            ->andWhere('referenceArticle.dateLastInventory IS NOT NULL')
            ->andWhere('TIMESTAMPDIFF(MONTH, referenceArticle.dateLastInventory, NOW()) >= frequency.nbMonths')
            ->setParameters([
                'frequency' => $frequency,
                'status' => ReferenceArticle::STATUT_ACTIF,
                'quantityType_reference' => ReferenceArticle::QUANTITY_TYPE_REFERENCE,
                'startDate' => $inventoryMission->getStartPrevDate(),
                'endDate' => $inventoryMission->getEndPrevDate()
            ]);

        return $queryBuilder
            ->getQuery()
            ->toIterable();
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
            'typeQuantity' => ReferenceArticle::QUANTITY_TYPE_REFERENCE,
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
        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE) {
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
        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE) {
            $referenceReservedQuantity = $this->createQueryBuilder('referenceArticle')
                ->select('SUM(preparationLine.quantityToPick)')
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
                ->select('SUM(preparationOrderLine.quantityToPick)')
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

    public function getOneReferenceByBarCodeAndLocation(string $barCode, ?string $location)
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

    public function findOneByBarCodeAndLocation(string $barCode, string $location): ?ReferenceArticle
    {
        $queryBuilder = $this
            ->createQueryBuilderByBarCodeAndLocation($barCode, $location)
            ->select('referenceArticle')
            ->setMaxResults(1);

        return $queryBuilder
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function createQueryBuilderByBarCodeAndLocation(string $barCode, ?string $location, bool $onlyActive = true): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('referenceArticle');
        $queryBuilder
            ->andWhere('referenceArticle.barCode = :barCode')
            ->andWhere('referenceArticle.typeQuantite = :typeQuantite')
            ->setParameter('barCode', $barCode)
            ->setParameter('typeQuantite', ReferenceArticle::QUANTITY_TYPE_REFERENCE);

        if($location) {
            $queryBuilder->join('referenceArticle.emplacement', 'emplacement')
                ->andWhere('emplacement.label = :location')
                ->setParameter('location', $location);
        }

        if ($onlyActive) {
            $queryBuilder
                ->join('referenceArticle.statut', 'status')
                ->andWhere('status.nom = :activeStatus')
                ->setParameter('activeStatus', ReferenceArticle::STATUT_ACTIF);
        }

        return $queryBuilder;
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

        $countTotal = QueryBuilderHelper::count($qb, "reference_article");

        return [
            "data" => $qb->getQuery()->getResult(),
            "count" => $countTotal,
            "total" => $countTotal
        ];
    }

    public function isUsedInQuantityChangingProcesses(ReferenceArticle $referenceArticle): bool {
        $queryBuilder = $this->createQueryBuilder('reference');
        $exprBuilder = $queryBuilder->expr();
        $articleIsProcessed = $queryBuilder
            ->leftJoin('reference.deliveryRequestLines', 'deliveryRequestLine')
            ->leftJoin('deliveryRequestLine.request', 'deliveryRequest')
            ->leftJoin('deliveryRequest.statut', 'deliveryRequestStatus')

            ->leftJoin('reference.ordreCollecteReferences', 'collectOrderLines')
            ->leftJoin('collectOrderLines.ordreCollecte', 'collectOrder')
            ->leftJoin('collectOrder.statut', 'collectOrderStatus')

            ->leftJoin('reference.transferRequests', 'transferRequest')
            ->leftJoin('transferRequest.status', 'transferRequestStatus')

            ->andWhere('reference = :reference')
            ->andWhere($exprBuilder->orX(
                '(deliveryRequestLine IS NOT NULL AND deliveryRequestStatus.code NOT IN (:deliveryRequestStatus_processed))',
                '(transferRequest.id IS NOT NULL AND transferRequestStatus.code NOT IN (:transferRequestStatus_processed))',
                '(collectOrder.id IS NOT NULL AND collectOrderStatus.code NOT IN (:collectOrderStatusStatus_processed))'
            ))

            ->setParameter('reference', $referenceArticle)
            ->setParameter('deliveryRequestStatus_processed', [Demande::STATUT_BROUILLON, Demande::STATUT_LIVRE, Demande::STATUT_LIVRE_INCOMPLETE])
            ->setParameter('transferRequestStatus_processed', [TransferRequest::DRAFT, TransferRequest::TREATED])
            ->setParameter('collectOrderStatusStatus_processed', [OrdreCollecte::STATUT_TRAITE])

            ->getQuery()
            ->getResult();

        return !empty($articleIsProcessed);
    }

    public function findByIds(array $id): array {
        $references = $this->createQueryBuilder('referenceArticle')
            ->where('referenceArticle.id IN (:id)')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult();

        return Stream::from($references)
            ->keymap(fn(ReferenceArticle $reference) => [$reference->getId(), $reference])
            ->toArray();
    }

    public function countByCategory(InventoryCategory $category): int {
        return $this->createQueryBuilder('reference_article')
            ->select('COUNT(reference_article)')
            ->join(InventoryCategory::class, "category", Join::WITH, "reference_article.category = category.id")
            ->andWhere('category = :category')
            ->setParameter('category', $category)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function updateInventoryStatusQuery() {
        $queryRaw = file_get_contents('assets/sql/update-inventory-status.sql');

        $rsm = new ResultSetMapping();
        $query = $this->getEntityManager()->createNativeQuery($queryRaw, $rsm);

        $query->setParameter('referenceQuantityManagement', ReferenceArticle::QUANTITY_TYPE_REFERENCE);
        $query->setParameter('articleStatuses', [Article::STATUT_ACTIF, Article::STATUT_EN_TRANSIT], Connection::PARAM_STR_ARRAY);

        return $query->execute();
    }
}
