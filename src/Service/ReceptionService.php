<?php


namespace App\Service;


use App\Entity\Arrivage;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\FieldsParam;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\Setting;
use App\Entity\Reception;
use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;
use DateTime;
use InvalidArgumentException;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\EntityManagerInterface;
use WiiCommon\Helper\StringHelper;

class ReceptionService
{

    public const INVALID_EXPECTED_DATE = 'invalid-expected-date';
    public const INVALID_ORDER_DATE = 'invalid-order-date';
    public const INVALID_LOCATION = 'invalid-location';
    public const INVALID_STORAGE_LOCATION = 'invalid-storage-location';
    public const INVALID_CARRIER = 'invalid-carrier';
    public const INVALID_PROVIDER = 'invalid-provider';

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public FieldsParamService $fieldsParamService;

    #[Required]
    public StringService $stringService;

    #[Required]
    public TranslationService $translation;

    #[Required]
    public FreeFieldService $freeFieldService;

    #[Required]
    public UniqueNumberService $uniqueNumberService;

    #[Required]
    public FormService $formService;

    #[Required]
    public SettingsService $settingsService;

    #[Required]
    public VisibleColumnService $visibleColumnService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public ReceptionLineService $receptionLineService;

    public function getDataForDatatable(Utilisateur $user, $params = null, $purchaseRequestFilter = null)
    {

        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $receptionRepository = $this->entityManager->getRepository(Reception::class);

        if ($purchaseRequestFilter) {
            $filters = [
                [
                    'field' => 'purchaseRequest',
                    'value' => $purchaseRequestFilter
                ]
            ];
        } else {
            $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_RECEPTION, $user);
        }

        $queryResult = $receptionRepository->findByParamAndFilters($params, $filters, $user, $this->visibleColumnService);

        $receptions = $queryResult['data'];

        $rows = [];
        foreach ($receptions as $reception) {
            $rows[] = $this->dataRowReception($reception);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function persistReception(EntityManagerInterface $entityManager,
                                     ?Utilisateur           $currentUser,
                                     array                  $data,
                                     array                  $options = []): Reception {

        $typeRepository = $entityManager->getRepository(Type::class);
        $arrivageRepository = $entityManager->getRepository(Arrivage::class);

        $type = $typeRepository->findOneByCategoryLabel(CategoryType::RECEPTION);

        $reception = new Reception();
        $date = new DateTime('now');

        $numero = $this->uniqueNumberService->create($entityManager, Reception::NUMBER_PREFIX, Reception::class, UniqueNumberService::DATE_COUNTER_FORMAT_RECEPTION);

        $fromImport = $options['import'] ?? false;

        if(!empty($data['arrivage'])) {
            $arrivageId = $data['arrivage'];
            $arrivage = $arrivageRepository->find($arrivageId);
            if ($arrivage && !$arrivage->getReception()) {
                $arrivage->setReception($reception);
                foreach ($arrivage->getPacks() as $pack) {
                    $this->receptionLineService->persistReceptionLine($entityManager, $reception, $pack);
                }
            }
        }

        $reception
            ->setOrderNumber(!empty($data['orderNumber']) ? [$data['orderNumber']] : [])
            ->setNumber($numero)
            ->setDate($date)
            ->setUtilisateur($currentUser)
            ->setType($type);

        // Date attendue provenant des imports de réception
        if ($fromImport && isset($data['expectedDate'])) {
            $this->formService->validateDate($data['expectedDate'], self::INVALID_ORDER_DATE);
            $expectedDate = DateTime::createFromFormat('d/m/Y', $data['expectedDate'] ?: '') ?: null;
            $reception->setDateAttendue($expectedDate);
        }
        // Date attendue pour création d'une réception standard
        else {
            $reception->setDateAttendue(
                !empty($data['dateAttendue'])
                    ? new DateTime(str_replace('/', '-', $data['dateAttendue']))
                    : null);
        }

        $this->updateReception($entityManager, $reception, $data, ['import' => $fromImport]);

        $entityManager->persist($reception);
        $entityManager->flush();
        return $reception;
    }

    public function updateReception(EntityManagerInterface $entityManager,
                                    Reception              $reception,
                                    array                  $data,
                                    array                  $options = []): void {

        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $statutRepository = $this->entityManager->getRepository(Statut::class);

        $fromImport = $options['import'] ?? false;
        $update = $options['update'] ?? false;

        if(!empty($data['fournisseur'])) {
            if($fromImport) {
                $fournisseur = $fournisseurRepository->findOneBy(['codeReference' => $data['fournisseur']]);
                if (!isset($fournisseur)) {
                    throw new InvalidArgumentException(self::INVALID_PROVIDER);
                }
            }
            else {
                $fournisseur = $fournisseurRepository->find(intval($data['fournisseur']));
            }
            $reception
                ->setFournisseur($fournisseur);
        }

        if (!$update && $fromImport && empty($data['location'])) {
            $defaultLocation = $this->settingsService->getParamLocation(Setting::DEFAULT_LOCATION_RECEPTION);
            if (isset($defaultLocation)) {
                $location = $emplacementRepository->find(intval($defaultLocation['id']));
                $reception
                    ->setLocation($location);
            }
        }

        if(!empty($data['location'])) {
            if($fromImport) {
                $location = $emplacementRepository->findOneBy(['label' => $data['location']]);
                if (!isset($location)) {
                    throw new InvalidArgumentException(self::INVALID_LOCATION);
                }
            } else {
                $location = $emplacementRepository->find(intval($data['location']));
            }
            $reception
                ->setLocation($location);
        }

        if(!empty($data['transporteur'])) {
            if ($fromImport) {
                $carrier = $transporteurRepository->findOneBy(['code' => $data['transporteur']]);
                if (!isset($carrier)) {
                    throw new InvalidArgumentException(self::INVALID_CARRIER);
                }
            }
            else {
                $carrier = $transporteurRepository->find(intval($data['transporteur']));
            }
            $reception
                ->setTransporteur($carrier);
        }

        if(!empty($data['manualUrgent'])) {
            $reception->setManualUrgent(
                filter_var($data['manualUrgent'], FILTER_VALIDATE_BOOLEAN)
                || in_array($data['manualUrgent'], ImportService::POSITIVE_ARRAY)
            );
        }

        $reception
            ->setCommentaire(!empty($data['commentaire']) ? StringHelper::cleanedComment($data['commentaire']) : null);

        // Date commande provenant des imports de réception
        if ($fromImport && isset($data['orderDate'])) {
            $this->formService->validateDate($data['orderDate'], self::INVALID_ORDER_DATE);
            $orderDate = DateTime::createFromFormat('d/m/Y', $data['orderDate'] ?: '') ?: null;
            $reception->setDateCommande($orderDate);
        }
        // Date commande pour création d'une réception standard
        else {
            $reception->setDateCommande(
                !empty($data['dateCommande'])
                    ? new DateTime(str_replace('/', '-', $data['dateCommande']))
                    : null);
        }


        if (!empty($data['anomalie'])) {
            $receptionAnomaly = (
                filter_var($data['anomalie'], FILTER_VALIDATE_BOOLEAN)
                || in_array($data['anomalie'], ImportService::POSITIVE_ARRAY)
            );
            if ($receptionAnomaly && $reception->getStatut()?->getCode() === Reception::STATUT_EN_ATTENTE) {
                $statusCode = Reception::STATUT_ANOMALIE;
            }
        }

        // on reception creation
        if (!isset($statusCode) && !$update) {
            $statusCode = Reception::STATUT_EN_ATTENTE;
        }

        if (isset($statusCode)) {
            $status = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::RECEPTION, $statusCode);
            $reception->setStatut($status);
        }
    }

    public function dataRowReception(Reception $reception)
    {
        return [
            "id" => ($reception->getId()),
            "Statut" => $this->formatService->status($reception->getStatut()),
            "Date" => $this->formatService->datetime($reception->getDate()),
            "dateAttendue" => $this->formatService->date($reception->getDateAttendue()),
            "DateFin" => $this->formatService->datetime($reception->getDateFinReception()),
            "Fournisseur" => $this->formatService->supplier($reception->getFournisseur()),
            "Commentaire" => $reception->getCommentaire() ?: '',
            "receiver" => implode(', ', array_unique(
                $reception->getDemandes()
                    ->map(function (Demande $request) {
                        return $request->getUtilisateur() ? $request->getUtilisateur()->getUsername() : '';
                    })
                    ->filter(function (string $username) {
                        return !empty($username);
                    })
                    ->toArray())
            ),
            "number" => $reception->getNumber() ?: "",
            "orderNumber" => $reception->getOrderNumber() ? join(",", $reception->getOrderNumber()) : "",
            "storageLocation" => $this->formatService->location($reception->getStorageLocation()),
            "emergency" => $reception->isManualUrgent() || $reception->hasUrgentArticles(),
            "deliveries" => $this->templating->render('reception/delivery_types.html.twig', [
                'deliveries' => $reception->getDemandes()
            ]),
            'Actions' => $this->templating->render(
                'reception/datatableReceptionRow.html.twig',
                ['reception' => $reception]
            ),
        ];
    }

    public function getColumnVisibleConfig(Utilisateur $currentUser): array {

        $columnsVisible = $currentUser->getVisibleColumns()['reception'];
        $columns = [
            ['name' => "Actions", "class" => "noVis", "orderable" => false, "alwaysVisible" => true],
            ["title" => "Date création", "name" => "Date", 'searchable' => true],
            ["title" => $this->translation->translate("Ordre", "Réceptions", "n° de réception", false), "name" => "number", 'searchable' => true, 'translated' => true],
            ["title" => "Date attendue", "name" => "dateAttendue", 'searchable' => true],
            ["title" => "Date fin", "name" => "DateFin", 'searchable' => true],
            ["title" => "Numéro(s) commande", "name" => "orderNumber", "searchable" => true, "orderable" => false],
            ["title" => "Destinataire(s)", "name" => "receiver", 'searchable' => true],
            ["title" => "Fournisseur", "name" => "Fournisseur", 'searchable' => true],
            ["title" => "Statut", "name" => "Statut", 'searchable' => true],
            ["title" => "Emplacement de stockage", "name" => "storageLocation", 'searchable' => true],
            ["title" => "Commentaire", "name" => "Commentaire", 'searchable' => true],
            ["title" => "Type(s) de demande(s) de livraison liée(s)", "name" => "deliveries", 'searchable' => false, 'orderable' => false],
            ["title" => "Urgence", "name" => "emergency", 'searchable' => false, 'orderable' => false, 'alwaysVisible' => true, 'class' => 'noVis', 'visible' => false],
        ];

        return $this->visibleColumnService->getArrayConfig($columns, [], $columnsVisible);
    }

    public function createHeaderDetailsConfig(Reception $reception): array {
        $fieldsParamRepository = $this->entityManager->getRepository(FieldsParam::class);
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_RECEPTION);

        $status = $reception->getStatut();
        $provider = $reception->getFournisseur();
        $carrier = $reception->getTransporteur();
        $location = $reception->getLocation();
        $dateCommande = $reception->getDateCommande();
        $dateAttendue = $reception->getDateAttendue();
        $dateEndReception = $reception->getDateFinReception();
        $creationDate = $reception->getDate();
        $orderNumber = $reception->getOrderNumber() ? join(", ", $reception->getOrderNumber()) : null;
        $comment = $reception->getCommentaire();
        $storageLocation = $reception->getStorageLocation();
        $attachments = $reception->getAttachments();
        $receivers = implode(', ', array_unique(
                $reception->getDemandes()
                    ->map(function (Demande $request) {
                        return $request->getUtilisateur() ? $request->getUtilisateur()->getUsername() : '';
                    })
                    ->filter(function (string $username) {
                        return !empty($username);
                    })
                    ->toArray())
        );

        $freeFieldArray = $this->freeFieldService->getFilledFreeFieldArray(
            $this->entityManager,
            $reception,
            ['type' => $reception->getType()]
        );

        $config = [
            [
                'label' => 'Statut',
                'value' => $status ? $this->stringService->mbUcfirst($this->formatService->status($status)) : ''
            ],
            [
                'label' => $this->translation->translate("Ordre", "Réceptions", "n° de réception", false),
                'title' => 'n° de réception',
                'value' => $reception->getNumber(),
                'show' => [ 'fieldName' => 'number' ]
            ],
            [
                'label' => 'Fournisseur',
                'value' => $provider ? $provider->getNom() : '',
                'show' => [ 'fieldName' => 'fournisseur' ]
            ],
            [
                'label' => 'Transporteur',
                'value' => $carrier ? $carrier->getLabel() : '',
                'show' => [ 'fieldName' => 'transporteur' ]
            ],
            [
                'label' => 'Emplacement',
                'value' => $location ? $location->getLabel() : '',
                'show' => [ 'fieldName' => 'emplacement' ]
            ],
            [
                'label' => 'Date commande',
                'value' => $dateCommande ? $dateCommande->format('d/m/Y') : '',
                'show' => [ 'fieldName' => 'dateCommande' ]
            ],
            [
                'label' => 'Numéro de commande',
                'value' => $orderNumber ?: '',
                'show' => [ 'fieldName' => 'numCommande' ]
            ],
            [
                'label' => 'Destinataire(s)',
                'value' => $receivers ?: ''
            ],
            [
                'label' => 'Date attendue',
                'value' => $dateAttendue ? $dateAttendue->format('d/m/Y') : '',
                'show' => [ 'fieldName' => 'dateAttendue' ]
            ],
            [ 'label' => 'Date de création', 'value' => $creationDate ? $creationDate->format('d/m/Y H:i') : '' ],
            [ 'label' => 'Date de fin', 'value' => $dateEndReception ? $dateEndReception->format('d/m/Y H:i') : '' ],
            [
                'label' => 'Emplacement de stockage',
                'value' => $storageLocation ?: '',
                'show' => [ 'fieldName' => 'storageLocation' ]
            ],
        ];

        $configFiltered =  $this->fieldsParamService->filterHeaderConfig($config, FieldsParam::ENTITY_CODE_RECEPTION);

        return array_merge(
            $configFiltered,
            $freeFieldArray,
            ($this->fieldsParamService->isFieldRequired($fieldsParam, 'commentaire', 'displayedCreate')
                || $this->fieldsParamService->isFieldRequired($fieldsParam, 'commentaire', 'displayedEdit'))
                ? [[
                'label' => 'Commentaire',
                'value' => $comment ?: '',
                'isRaw' => true,
                'colClass' => 'col-sm-6 col-12',
                'isScrollable' => true,
                'isNeededNotEmpty' => true
            ]]
                : [],
            $this->fieldsParamService->isFieldRequired($fieldsParam, 'attachment', 'displayedCreate')
            || $this->fieldsParamService->isFieldRequired($fieldsParam, 'attachment', 'displayedEdit')
                ? [[
                'label' => 'Pièces jointes',
                'value' => $attachments->toArray(),
                'isAttachments' => true,
                'isNeededNotEmpty' => true
            ]]
                : []
        );
    }


    public function getAlreadySavedReception(EntityManagerInterface $entityManager,
                                             array                  &$collection,
                                             array                  $uniqueConstraint): ?Reception {
        $reception = null;
        $receptionRepository = $entityManager->getRepository(Reception::class);

        $expectedDate = isset($uniqueConstraint['expectedDate'])
            ? DateTime::createFromFormat("d/m/Y", $uniqueConstraint['expectedDate']) ?: null
            : null;
        $expectedDateStr = $expectedDate?->format("Y-m-d");

        $orderNumber = $uniqueConstraint['orderNumber'] ?? null;

        foreach($collection as &$receptionIntel) {
            if ($expectedDateStr === $receptionIntel['expectedDate']
                && $orderNumber === $receptionIntel['orderNumber']) {
                $reception = $receptionIntel['reception'];
                $isPersistedReception = $entityManager->getUnitOfWork()->isInIdentityMap($reception);
                if (!$isPersistedReception) {
                    $reception = $receptionRepository->find($reception->getId());
                    $receptionIntel['reception'] = $reception;
                }
                break;
            }
        }

        return $reception;
    }

    public function setAlreadySavedReception(array &$collection,
                                             array $uniqueConstraint,
                                             Reception $reception): void {
        $receptionSaved = false;

        $expectedDate = isset($uniqueConstraint['expectedDate'])
            ? DateTime::createFromFormat("d/m/Y", $uniqueConstraint['expectedDate']) ?: null
            : null;
        $expectedDateStr = $expectedDate?->format("Y-m-d");

        $orderNumber = $uniqueConstraint['orderNumber'] ?? null;

        foreach($collection as &$receptionIntel) {
            if ($expectedDateStr === $receptionIntel['expectedDate']
                && $orderNumber === $receptionIntel['orderNumber']) {
                $receptionIntel['reception'] = $reception;
                $receptionSaved = true;
                break;
            }
        }
        if (!$receptionSaved) {
            $collection[] = [
                'reception' => $reception,
                'expectedDate' => $expectedDateStr,
                'orderNumber' => $orderNumber,
            ];
        }
    }
}
