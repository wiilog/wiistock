<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\FiltreRef;
use App\Entity\FreeField\FreeField;
use App\Entity\Inventory\InventoryCategory;
use App\Entity\Inventory\InventoryFrequency;
use App\Entity\Inventory\InventoryMission;
use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\OrdreCollecte;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Entity\ReferenceArticle;
use App\Entity\ShippingRequest\ShippingRequestExpectedLine;
use App\Entity\TransferRequest;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Helper\AdvancedSearchHelper;
use App\Helper\QueryBuilderHelper;
use App\Service\FormatService;
use App\Service\FieldModesService;
use App\Service\SleepingStockPlanService;
use DateTime;
use Doctrine\Common\Collections\Criteria;
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
        "lastSleepingStockAlertAnswer",
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

    private const ADVANCED_SEARCH_COLUMN_WEIGHTING = [
        "libelle" => 5,
        // add more columns here with their respective weighting (higher = more relevant)
    ];

    private const MAX_REFERENCE_ARTICLES_IN_ALERT = 10;

    public function getForSelect(?string $term, Utilisateur $user, array $options = []): array {
        $queryBuilder = $this->createQueryBuilder("reference");

        $visibilityGroup = $user->getVisibilityGroups();
        if (!($options['visibilityGroup'] ?? false) && !$visibilityGroup->isEmpty()) {
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

        if ($options['active-only'] ?? false) {
            $queryBuilder
                ->leftJoin('reference.statut', 'join_status')
                ->andWhere('join_status.nom = :activeStatus')
                ->setParameter('activeStatus', ReferenceArticle::STATUT_ACTIF);
        }

        if($options['status'] ?? false) {
            $queryBuilder
                ->andWhere('status.code = :status')
                ->setParameter('status', $options['status']);
        }

        if($options['visibilityGroup'] ?? false) {
            $queryBuilder
                ->leftJoin('reference.visibilityGroup', 'visibility_group')
                ->andWhere('visibility_group.id = :visibilityGroup')
                ->setParameter('visibilityGroup', $options['visibilityGroup']);
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

        $queryBuilder
            ->distinct()
            ->select("reference.id AS id");

        if($options['multipleFields']) {
            $queryBuilder->addSelect("CONCAT_WS(' / ', reference.reference, reference.libelle, GROUP_CONCAT(supplier.codeReference SEPARATOR ', '), reference.barCode) AS text");
        } else {
            $queryBuilder->addSelect('reference.reference AS text');
        }

        if($options['filterFields']) {
            $filterFields = json_decode($options['filterFields'], true);

            $expression = Stream::from($filterFields ?: [])
                ->filterMap(static function(array $filterField) use ($queryBuilder) {
                    $label = $filterField['label'];
                    $value = $filterField['value'];
                    if($value && intval($label)) {
                        if(is_array($value)) {
                            return $queryBuilder
                                ->expr()
                                ->orX(
                                    ...Stream::from($value)
                                    ->map(static fn($item) => "(JSON_EXTRACT(reference.freeFields, '$.\"$label\"') LIKE '%$item%')")
                                    ->toArray()
                                );
                        } else {
                            return "JSON_EXTRACT(reference.freeFields, '$.\"$label\"') LIKE '%$value%'";
                        }
                    } else if($value && $label === 'type') {
                        return "reference.type = $value";
                    } else {
                        return null;
                    }
                })
                ->toArray();

            if(!empty($expression)) {
                $queryBuilder->andWhere($queryBuilder->expr()->andX(...$expression));
            }
        }

        $queryBuilder
            ->addSelect('reference.libelle AS label')
            ->addSelect('emplacement.label AS location')
            ->addSelect('reference.description AS description')
            ->addSelect('reference.typeQuantite AS typeQuantite')
            ->addSelect('reference.barCode AS barCode')
            ->addSelect('type.id AS typeId')
            ->addSelect('reference.dangerousGoods AS dangerous')
            ->addSelect('reference.isUrgent AS urgent')
            ->addSelect('reference.emergencyComment AS emergencyComment')
            ->addSelect('reference.quantiteDisponible AS quantityDisponible')
            ->orHaving("text LIKE :term")
            ->andWhere("status.code != :draft")
            ->leftJoin("reference.statut", "status")
            ->leftJoin("reference.emplacement", "emplacement")
            ->leftJoin("reference.type", "type")
            ->leftJoin("reference.articlesFournisseur", "supplierArticle")
            ->leftJoin("supplierArticle.fournisseur", "supplier")
            ->setParameter("term", "%$term%")
            ->setParameter("draft", ReferenceArticle::DRAFT_STATUS)
            ->setMaxResults(100);

        if($options['multipleFields']) {
            Stream::from($queryBuilder->getDQLParts()['select'])
                ->flatMap(static fn($selectPart) => [$selectPart->getParts()[0]])
                ->map(static fn($selectString) => trim(explode('AS', $selectString)[1]))
                ->filter(static fn($selectAlias) => !in_array($selectAlias, ['text']))
                ->each(static fn($field) => $queryBuilder->addGroupBy($field));
        }

        return $queryBuilder
            ->getQuery()
            ->getArrayResult();
    }

    public function getForNomade() {
        $qb = $this->createQueryBuilder('referenceArticle');

        $qb
            ->select('referenceArticle.id AS id')
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
            ->addSelect("GROUP_CONCAT(join_location.id SEPARATOR ',') AS storageRuleLocations")
            ->leftJoin("referenceArticle.storageRules", "join_storageRules")
            ->leftJoin("join_storageRules.location", "join_location")
            ->andWhere('referenceArticle.needsMobileSync = true');

        $qb = QueryBuilderHelper::setGroupBy($qb, ["storageRuleLocations"]);

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
            ->addSelect('referenceArticle.lastSleepingStockAlertAnswer')
            ->addSelect('referenceArticle.needsMobileSync')
            ->addSelect('referenceArticle.freeFields')
            ->addSelect('referenceArticle.stockManagement')
            ->addSelect('sleeping_stock_plan.maxStorageTime')
            ->addSelect('join_visibilityGroup.label AS visibilityGroup')
            ->addSelect("GROUP_CONCAT(DISTINCT join_manager.username SEPARATOR ',') AS managers")
            ->addSelect("GROUP_CONCAT(DISTINCT join_supplier.codeReference SEPARATOR ',') AS supplierCodes")
            ->addSelect("GROUP_CONCAT(DISTINCT join_supplier.nom SEPARATOR ',') AS supplierLabels")
            ->leftJoin('referenceArticle.statut', 'statutRef')
            ->leftJoin('referenceArticle.emplacement', 'emplacementRef')
            ->leftJoin('referenceArticle.type', 'typeRef')
            ->leftJoin('typeRef.sleepingStockPlan', 'sleeping_stock_plan')
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
            ->addSelect("reference.{$field} as text")
            ->addSelect('reference.typeQuantite as typeQuantity')
            ->addSelect('reference.isUrgent as urgent')
            ->addSelect('reference.emergencyComment as emergencyComment')
            ->addSelect('reference.libelle')
            ->addSelect('reference.barCode')
            ->addSelect('join_location.label AS location')
            ->addSelect('reference.quantiteDisponible')
            ->leftJoin('reference.emplacement', 'join_location')
            ->leftJoin('reference.statut', 'join_draft_status')
            ->where("reference.{$field} LIKE :search")
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

    public function findByFiltersAndParams(array                 $filters,
                                           InputBag              $params,
                                           Utilisateur           $user,
                                           FormatService         $formatService): array
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
            'Dernière entrée le' => ['field' => 'lastStockEntry', 'typage' => 'date'],
            'Dernière réponse au stockage' => ['field' => "lastSleepingStockAlertAnswer", 'typage' => 'date'],
            'Durée max autorisée en stock' => ['field' => "maxStorageTime", 'typage' => 'number'],
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
                            $value = json_decode($value);
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
                            return "JSON_SEARCH(ra.freeFields, 'one', '{$item}', NULL, '$.\"{$clId}\"')" . $conditionType;
                        })
                        ->toArray();

                    if (!empty($jsonSearchesQueryArray)) {
                        $jsonSearchesQueryString = '(' . implode(' OR ', $jsonSearchesQueryArray) . ')';
                        $queryBuilder->andWhere($jsonSearchesQueryString);
                    }
                }
            }
        }

        if (!empty($params) && !empty($params->all('order'))) {
            $order = $params->all('order')[0]['dir'];
            if (!empty($order)) {
                $columnIndex = $params->all('order')[0]['column'];
                $columnName = $params->all('columns')[$columnIndex]['data'];
                $column = self::DtToDbLabels[$columnName] ?? $columnName;

                switch ($column) {
                    case "actions":
                        break;
                    case "supplier":
                        $queryBuilder
                            ->leftJoin('ra.articlesFournisseur', 'order_articlesFournisseur')
                            ->leftJoin('order_articlesFournisseur.fournisseur', 'order_supplier')
                            ->orderBy('order_supplier.nom', $order);
                        break;
                    case 'visibilityGroups':
                        $queryBuilder
                            ->leftJoin('ra.visibilityGroup', 'order_group')
                            ->orderBy('order_group.label', $order);
                        break;
                    case "type":
                        $queryBuilder
                            ->leftJoin('ra.type', 'order_type')
                            ->orderBy('order_type.label', $order);
                        break;
                    case "location":
                        $queryBuilder
                            ->leftJoin('ra.emplacement', 'order_location')
                            ->orderBy('order_location.label', $order);
                        break;
                    case "status":
                        $queryBuilder
                            ->leftJoin('ra.statut', 'order_status')
                            ->orderBy('order_status.nom', $order);
                        break;
                    case "buyer":
                        $queryBuilder
                            ->leftJoin('ra.buyer', 'order_buyer')
                            ->orderBy('order_buyer.username', $order);
                        break;
                    case "createdBy":
                        $queryBuilder
                            ->leftJoin('ra.createdBy', 'order_createdBy')
                            ->orderBy('order_createdBy.username', $order);
                        break;
                    case "editedBy":
                        $queryBuilder
                            ->leftJoin('ra.editedBy', 'order_editedBy')
                            ->orderBy('order_editedBy.username', $order);
                        break;
                    default:
                        $freeFieldId = FieldModesService::extractFreeFieldId($column);
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

        $searchParts = Stream::explode(" ", $params->all("search")["value"] ?? "")
            ->filter(static fn(string $part) => $part && strlen($part) >= AdvancedSearchHelper::MIN_SEARCH_PART_LENGTH)
            ->values();

        if (!empty($searchParts)) {
            $freeFieldRepository = $em->getRepository(FreeField::class);

            $searchableFields = Stream::from($user->getRecherche())
                ->map(static fn(string $field) => self::DtToDbLabels[$field] ?? $field)
                ->toArray();

            $searchBooleanValues = Stream::from($searchParts)
                ->filter(static fn(string $part) => in_array(strtolower($part), ["oui", "non"]))
                ->map(static fn(string $part) => strtolower($part) === "oui" ? 1 : 0)
                ->unique();

            $searchDateValues = Stream::from($searchParts)
                ->filterMap(static fn(string $part) => ($formatService->parseDatetime($part) ?: null)?->format("Y-m-d"))
                ->toArray();

            $conditions = [];

            foreach ($searchableFields as $key => $searchableField) {
                switch ($searchableField) {
                    case "supplierLabel":
                    case "supplierCode":
                        $dbField = match ($searchableField) {
                            "supplierLabel" => "nom",
                            default => "codeReference",
                        };

                        $queryBuilder
                            ->leftJoin("ra.articlesFournisseur", "search_supplierArticle_$key")
                            ->leftJoin("search_supplierArticle_$key.fournisseur", "search_supplier_$key");

                        $conditions[$searchableField] = "search_supplier_$key.$dbField LIKE :search_value";

                        break;
                    case "referenceSupplierArticle":
                        $queryBuilder->leftJoin("ra.articlesFournisseur", "search_supplierArticle");

                        $conditions[$searchableField] = "search_supplierArticle.reference LIKE :search_value";

                        break;
                    case "managers":
                        $queryBuilder->leftJoin("ra.managers", "search_managers");

                        $conditions[$searchableField] = "search_managers.username LIKE :search_value";

                        break;
                    case "buyer":
                        $queryBuilder->leftJoin("ra.buyer", "search_buyer");

                        $conditions[$searchableField] = "search_buyer.username LIKE :search_value";

                        break;
                    default:
                        $field = self::DtToDbLabels[$searchableField] ?? $searchableField;
                        $freeFieldId = FieldModesService::extractFreeFieldId($field);
                        $freeField = is_numeric($freeFieldId)
                            ? $freeFieldRepository->find($freeFieldId)
                            : null;

                        if ($freeField) {
                            $freeFieldTyping = $freeField->getTypage();
                            if ($freeFieldTyping === FreeField::TYPE_BOOL) {
                                if (!$searchBooleanValues->isEmpty()) {
                                    foreach ($searchBooleanValues->toArray() as $index => $booleanValue) {
                                        $conditions["freeField$freeFieldId\_$index"] = "JSON_SEARCH(ra.freeFields, 'one', '$booleanValue', NULL, '$.\"$freeFieldId\"') IS NOT NULL";
                                    }
                                }
                            } elseif (in_array($freeFieldTyping, [FreeField::TYPE_DATE, FreeField::TYPE_DATETIME])) {
                                foreach ($searchDateValues as $index => $dateValue) {
                                    $conditions["freeField$freeFieldId\_$index"] = "JSON_SEARCH(ra.freeFields, 'one', '$dateValue', NULL, '$.\"$freeFieldId\"') IS NOT NULL";
                                }
                            } else {
                                $conditions["freeField$freeFieldId"] = "JSON_SEARCH(ra.freeFields, 'one', :search_value, NULL, '$.\"$freeFieldId\"') IS NOT NULL";
                            }
                        } else if (property_exists(ReferenceArticle::class, $field)) {
                            if (in_array($field, self::FIELDS_TYPE_DATE)) {
                                foreach ($searchDateValues as $dateIndex => $dateValue) {
                                    $conditions[$field] = "ra.$field BETWEEN :dateMin_{$key}_$dateIndex AND :dateMax_{$key}_$dateIndex";

                                    $queryBuilder
                                        ->setParameter("dateMin_{$key}_$dateIndex", "$dateValue 00:00:00")
                                        ->setParameter("dateMax_{$key}_$dateIndex", "$dateValue 23:59:59");
                                }
                            } else {
                                $conditions[$field] = "ra.$field LIKE :search_value";
                            }
                        }
                        break;
                }
            }

            $orX = $queryBuilder->expr()->orX();
            $searchPartsLength = count($searchParts);
            foreach ($searchParts as $index => $part) {
                $orX->addMultiple(AdvancedSearchHelper::bindSearch($conditions, $index, $searchPartsLength, false, self::ADVANCED_SEARCH_COLUMN_WEIGHTING)->toArray());

                $selectExpression = AdvancedSearchHelper::bindSearch($conditions, $index, $searchPartsLength, true, self::ADVANCED_SEARCH_COLUMN_WEIGHTING)
                    ->join(" + ");

                $queryBuilder
                    ->addSelect("$selectExpression AS HIDDEN search_relevance_$index")
                    ->setParameter("search_value_$index", "%$part%");
            }

            if($orX->count() > 0) {
                $relevances = AdvancedSearchHelper::getRelevances($queryBuilder);

                $previousAction = $params->get("previousAction");
                if ($previousAction === AdvancedSearchHelper::ORDER_ACTION) {
                    $queryBuilder->addOrderBy("{$relevances->join(" + ")} + 0 + 0", Criteria::DESC);
                } elseif ($previousAction === AdvancedSearchHelper::SEARCH_ACTION) {
                    $queryBuilder->orderBy("{$relevances->join(" + ")} + 0 + 0", Criteria::DESC);
                }

                $queryBuilder
                    ->andWhere($orX)
                    ->groupBy("ra.id");

                foreach ($relevances as $relevance) {
                    $queryBuilder->addGroupBy($relevance);
                }
            }
        }

        $queryBuilder->addSelect("COUNT_OVER(ra.id) AS __query_count");

        if ($params->getInt('start')) {
            $queryBuilder->setFirstResult($params->getInt('start'));
        }

        if ($params->getInt('length')) {
            $queryBuilder->setMaxResults($params->getInt('length'));
        }

        $results = $queryBuilder->getQuery()->getResult();
        return [
            "data" => $results,
            "count" => $results[0]["__query_count"] ?? 0,
            "searchParts" => $searchParts,
            "searchableFields" => $user->getRecherche(),
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

    public function getLastDraftReferenceNumber(): int {
        $length = strlen(ReferenceArticle::TO_DEFINE_LABEL) + 1;
        return $this->createQueryBuilder("reference_article")
            ->select("MAX(CAST(SUBSTRING(reference_article.reference, $length) AS SIGNED)) AS max")
            ->andWhere("reference_article.reference LIKE :toDefineLabel")
            ->setParameter("toDefineLabel", ReferenceArticle::TO_DEFINE_LABEL . "%")
            ->getQuery()
            ->getOneOrNullResult()['max'] ?: 0;
    }

    public function getByPreparationsIds(array $preparationsIds): array {
        $selectedLocationsSubQuery = function(?string $stockManagement, string $subArticleAlias): string  {
            $queryBuilder = $this->getEntityManager()->createQueryBuilder()
                ->select("{$subArticleAlias}_location.label")
                ->from(Article::class, $subArticleAlias)
                ->innerJoin("$subArticleAlias.emplacement", "{$subArticleAlias}_location")
                ->innerJoin("$subArticleAlias.statut", "{$subArticleAlias}_status", Join::WITH, "{$subArticleAlias}_status.code = :status")
                ->innerJoin("$subArticleAlias.articleFournisseur", "{$subArticleAlias}_supplierArticle")
                ->innerJoin("{$subArticleAlias}_supplierArticle.referenceArticle", "{$subArticleAlias}_referenceArticle", Join::WITH, "{$subArticleAlias}_referenceArticle = reference_article");

            if ($stockManagement === ReferenceArticle::STOCK_MANAGEMENT_FEFO) {
                $queryBuilder
                    ->andWhere("{$subArticleAlias}_referenceArticle.stockManagement = :fefoStockManagement")
                    ->orderBy("IF($subArticleAlias.expiryDate IS NOT NULL, 1, 0)", Criteria::DESC)
                    ->addOrderBy("$subArticleAlias.expiryDate", Criteria::ASC)
                    ->addOrderBy("$subArticleAlias.stockEntryDate", Criteria::ASC)
                    ->addOrderBy("$subArticleAlias.id", Criteria::ASC);
            } else if($stockManagement === ReferenceArticle::STOCK_MANAGEMENT_FIFO) {
                $queryBuilder
                    ->andWhere("{$subArticleAlias}_referenceArticle.stockManagement = :fifoStockManagement")
                    ->andWhere("$subArticleAlias.stockEntryDate IS NOT NULL")
                    ->orderBy("$subArticleAlias.stockEntryDate", Criteria::ASC);
            } else {
                $queryBuilder
                    ->orderBy("$subArticleAlias.stockEntryDate", Criteria::ASC);
            }

            return $queryBuilder
                ->getQuery()
                ->getDQL();
        };

        return $this->createQueryBuilder('reference_article')
            ->select('reference_article.reference AS reference')
            ->addSelect('reference_article.typeQuantite AS type_quantite')
            ->addSelect("
                IF(
                    reference_article.typeQuantite = :articleQuantityType,
                    IF(
                        reference_article.stockManagement = :fefoStockManagement,
                        FIRST({$selectedLocationsSubQuery(ReferenceArticle::STOCK_MANAGEMENT_FEFO, "sub_articleFEFO")}),
                        IF(
                            reference_article.stockManagement = :fifoStockManagement,
                            FIRST({$selectedLocationsSubQuery(ReferenceArticle::STOCK_MANAGEMENT_FIFO, "sub_articleFIFO")}),
                            FIRST({$selectedLocationsSubQuery(null, "sub_article")})
                        )
                    ),
                    join_location.label
                ) AS location"
            )
            ->addSelect('reference_article.libelle AS label')
            ->addSelect('join_preparationLine.quantityToPick AS quantity')
            ->addSelect('1 as is_ref')
            ->addSelect('reference_article.barCode AS barCode')
            ->addSelect('join_preparation.id AS id_prepa')
            ->addSelect('join_targetLocationPicking.label AS targetLocationPicking')
            ->leftJoin('reference_article.emplacement', 'join_location')
            ->innerJoin('reference_article.preparationOrderReferenceLines', 'join_preparationLine')
            ->innerJoin('join_preparationLine.preparation', 'join_preparation')
            ->leftJoin('join_preparationLine.targetLocationPicking', 'join_targetLocationPicking')
            ->andWhere('join_preparation.id IN (:preparationsIds)')
            ->setParameter('preparationsIds', $preparationsIds)
            ->setParameter('articleQuantityType', ReferenceArticle::QUANTITY_TYPE_ARTICLE)
            ->setParameter("status", Article::STATUT_ACTIF)
            ->setParameter("fefoStockManagement", ReferenceArticle::STOCK_MANAGEMENT_FEFO)
            ->setParameter("fifoStockManagement", ReferenceArticle::STOCK_MANAGEMENT_FIFO)
            ->getQuery()
            ->getResult();
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

    public function countByLocation(Emplacement $location): int {
        return $this->createQueryBuilder('reference_article')
            ->select('COUNT(reference_article.id)')
            ->andWhere('reference_article.emplacement = :location')
            ->setParameter('location', $location)
            ->getQuery()
            ->getSingleScalarResult();
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

    /**
     * @return array [ referenceId => stockQuantity ]
     */
    public function getStockQuantities(array $references): array
    {
        $referencesByQuantityManagement = Stream::from($references)
            ->keymap(fn(ReferenceArticle $referenceArticle) => [
                $referenceArticle->getTypeQuantite(),
                $referenceArticle
            ], true)
            ->toArray();

        if (!empty($referencesByQuantityManagement[ReferenceArticle::QUANTITY_TYPE_ARTICLE])) {
            $byArticleResult = $this->createQueryBuilder("referenceArticle")
                ->select("referenceArticle.id AS referenceArticleId")
                ->addSelect("SUM(article.quantite) AS quantity")
                ->leftJoin("referenceArticle.articlesFournisseur", "supplierArticle")
                ->leftJoin("supplierArticle.articles", "article")
                ->innerJoin("article.statut", "status", Join::WITH, "status.code NOT IN (:inactiveStatusCode)")
                ->andWhere('referenceArticle IN (:referenceArticles)')
                ->groupBy("referenceArticle.id")
                ->setParameter("inactiveStatusCode", [Article::STATUT_INACTIF, Article::STATUT_EN_LITIGE])
                ->setParameter("referenceArticles", $referencesByQuantityManagement[ReferenceArticle::QUANTITY_TYPE_ARTICLE])
                ->getQuery()
                ->getResult();

            $quantityByReferences = Stream::from($byArticleResult)
                ->keymap(fn(array $row) => [$row["referenceArticleId"], $row["quantity"]]);
        }
        else {
            $quantityByReferences = Stream::from([]);
        }

        return $quantityByReferences
            ->concat(
                Stream::from($referencesByQuantityManagement[ReferenceArticle::QUANTITY_TYPE_REFERENCE] ?? [])
                    ->keymap(fn(ReferenceArticle $referenceArticle) => [$referenceArticle->getId(), $referenceArticle->getQuantiteStock()]),
                true
            )
            ->toArray();
    }

    public function getReservedQuantities(array $references, bool $includeDeliveryReferences = false): array
    {
        $referencesByQuantityManagement = Stream::from($references)
            ->keymap(fn(ReferenceArticle $referenceArticle) => [
                $referenceArticle->getTypeQuantite(),
                $referenceArticle
            ], true)
            ->toArray();
        if (!empty($referencesByQuantityManagement[ReferenceArticle::QUANTITY_TYPE_ARTICLE])) {
            $referenceReservedQuantities = $this->createQueryBuilder('referenceArticle')
                ->select("referenceArticle.id AS referenceArticleId")
                ->addSelect('SUM(preparationLine.quantityToPick) AS quantity')
                ->leftJoin('referenceArticle.preparationOrderReferenceLines', 'preparationLine')
                ->leftJoin('preparationLine.preparation', 'preparation')
                ->innerJoin('preparation.statut', 'preparationStatus', Join::WITH, "preparationStatus.nom IN (:inProgressPreparationStatus)")
                ->andWhere('referenceArticle IN (:referenceArticles)')
                ->groupBy("referenceArticle.id")
                ->setParameters([
                    'referenceArticles' => $referencesByQuantityManagement[ReferenceArticle::QUANTITY_TYPE_ARTICLE],
                    'inProgressPreparationStatus' => [Preparation::STATUT_A_TRAITER, Preparation::STATUT_EN_COURS_DE_PREPARATION],
                ])
                ->getQuery()
                ->getResult();
            $articleReservedQuantities = $this->createQueryBuilder('referenceArticle')
                ->select("referenceArticle.id AS referenceArticleId")
                ->addSelect('SUM(COALESCE(preparationOrderLine.quantityToPick, article.quantite)) as quantity')
                ->leftJoin('referenceArticle.articlesFournisseur', 'supplierArticles')
                ->leftJoin('supplierArticles.articles', 'article')
                ->innerJoin('article.statut', 'articleStatus', Join::WITH, "articleStatus.code = :transitArticleStatus")
                ->leftJoin('article.preparationOrderLines', 'preparationOrderLine')
                ->leftJoin('preparationOrderLine.preparation', 'preparation')
                ->leftJoin('preparation.statut', 'preparationStatus')
                ->leftJoin('preparation.livraison', 'delivery')
                ->leftJoin('delivery.statut', 'deliveryStatus')
                ->andWhere('referenceArticle IN (:referenceArticles)')
                ->groupBy("referenceArticle.id")
                ->setParameters([
                    'referenceArticles' => $referencesByQuantityManagement[ReferenceArticle::QUANTITY_TYPE_ARTICLE],
                    'transitArticleStatus' => Article::STATUT_EN_TRANSIT,
                ])
                ->getQuery()
                ->getResult();

            $quantityByReferencesArray = [];
            foreach ($referenceReservedQuantities as $referenceReservedQuantityRow) {
                $referenceArticleId = $referenceReservedQuantityRow["referenceArticleId"];
                $referenceReservedQuantity = $referenceReservedQuantityRow["quantity"];
                $quantityByReferencesArray[$referenceArticleId] = $referenceReservedQuantity;
            }
            foreach ($articleReservedQuantities as $articleReservedQuantityRow) {
                $referenceArticleId = $articleReservedQuantityRow["referenceArticleId"];
                $articleReservedQuantity = $articleReservedQuantityRow["quantity"];
                $referenceReservedQuantity = $quantityByReferencesArray[$referenceArticleId] ?? 0;
                $quantityByReferencesArray[$referenceArticleId] = $referenceReservedQuantity + $articleReservedQuantity;
            }
        }

        return Stream::from($quantityByReferencesArray ?? [])
            ->concat(
                Stream::from($referencesByQuantityManagement[ReferenceArticle::QUANTITY_TYPE_REFERENCE] ?? [])
                    ->keymap(fn(ReferenceArticle $referenceArticle) => [
                        $referenceArticle->getId(),
                        Stream::from($referenceArticle->getPreparationOrderReferenceLines())
                            ->filter(function (PreparationOrderReferenceLine $ligneArticlePreparation) use ($includeDeliveryReferences) {
                                $preparation = $ligneArticlePreparation->getPreparation();
                                $livraison = $preparation->getLivraison();
                                return $preparation->getStatut()?->getCode() === Preparation::STATUT_EN_COURS_DE_PREPARATION
                                    || $preparation->getStatut()?->getCode() === Preparation::STATUT_A_TRAITER
                                    || (
                                        $includeDeliveryReferences &&
                                        $livraison &&
                                        $livraison->getStatut()?->getCode() === Livraison::STATUT_A_TRAITER
                                    );
                            })
                            ->map(fn(PreparationOrderReferenceLine $ligneArticlePrepaEnCours) => $ligneArticlePrepaEnCours->getQuantityToPick())
                            ->sum()
                    ]),
                true
            )
            ->toArray();
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



    /**
     * @return array{
     *   "countTotal": int,
     *   "referenceArticles": array<
     *     array{
     *       "id": int,
     *       "reference": string,
     *       "label": string,
     *       "quantityStock": int,
     *       "lastMovementDate": string,
     *     }
     *   >
     * }
     */
    public function findSleepingReferenceArticlesByTypeAndManager(Utilisateur $utilisateur,
                                                                  DateTime $dateLimit,
                                                                  Type $type): array {
        $stockMovementRepository = $this->getEntityManager()->getRepository(MouvementStock::class);
        $queryBuilder = $this->createQueryBuilder("reference_article")
            ->select("reference_article.id AS id")
            ->addSelect("reference_article.reference AS reference")
            ->addSelect("reference_article.libelle AS label")
            ->addSelect("reference_article.quantiteStock AS quantityStock")
            ->addSelect("COUNT_OVER(reference_article.id) AS __query_count")
            ->addSelect("({$stockMovementRepository->getMaxMovementDateForReferenceArticleQuery("reference_article")}) AS lastMovementDate")
            ->innerJoin("reference_article.managers", "manager", Join::WITH, 'manager.id = :manager')
            ->andWhere("reference_article.type = :type")
            ->distinct()
            ->setMaxResults(self::MAX_REFERENCE_ARTICLES_IN_ALERT)
            ->setParameter("type", $type)
            ->setParameter("manager", $utilisateur->getId());

        $queryBuilder = $this->filterBySleepingReference($queryBuilder, $dateLimit, "reference_article");

        $queryResult = $queryBuilder
            ->getQuery()
            ->getResult();

        $countTotal = $queryResult[0]["__query_count"] ?? 0;

        return [
            "countTotal" => $countTotal,
            "referenceArticles" => $queryResult
        ];
    }

    public function filterBySleepingReference(QueryBuilder $queryBuilder, DateTime $dateLimit, string $referenceArticleAlias): QueryBuilder {
        $stockMovementRepository = $this->getEntityManager()->getRepository(MouvementStock::class);
        return $queryBuilder
            ->andWhere("({$stockMovementRepository->getMaxMovementDateForReferenceArticleQuery($referenceArticleAlias)}) < :dateLimit")
            ->andWhere("$referenceArticleAlias.quantiteStock > 0")
            ->andWhere("$referenceArticleAlias.quantiteDisponible > 0")
            ->setParameter("dateLimit", $dateLimit);
    }
}
