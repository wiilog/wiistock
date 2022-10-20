<?php


namespace App\Service;


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
use App\Helper\FormatHelper;
use DateTime;
use InvalidArgumentException;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\EntityManagerInterface;

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

    public function createAndPersistReception(EntityManagerInterface $entityManager,
                                              ?Utilisateur $currentUser,
                                              array $data,
                                              $fromImport = false): Reception {

        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $ransporteurRepository = $entityManager->getRepository(Transporteur::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        if(!empty($data['anomalie'])) {
            $anomaly = (
                isset($data['anomalie'])
                && (
                    filter_var($data['anomalie'], FILTER_VALIDATE_BOOLEAN)
                    || in_array($data['anomalie'], ['oui', 'Oui', 'OUI'])
                )
            );
            $statusCode = $anomaly
                ? Reception::STATUT_ANOMALIE
                : Reception::STATUT_EN_ATTENTE;
        } else {
            $statusCode = Reception::STATUT_EN_ATTENTE;
        }

        $statut = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::RECEPTION, $statusCode);
        $type = $typeRepository->findOneByCategoryLabel(CategoryType::RECEPTION);

        $reception = new Reception();
        $date = new DateTime('now');

        $numero = $this->uniqueNumberService->create($entityManager, Reception::NUMBER_PREFIX, Reception::class, UniqueNumberService::DATE_COUNTER_FORMAT_RECEPTION);

        if(!empty($data['fournisseur'])) {
            if($fromImport) {
                $fournisseur = $fournisseurRepository->findOneBy(['codeReference' => $data['fournisseur']]);
                if (!isset($fournisseur)) {
                    throw new InvalidArgumentException(self::INVALID_PROVIDER);
                }
            } else {
                $fournisseur = $fournisseurRepository->find(intval($data['fournisseur']));
            }
            $reception
                ->setFournisseur($fournisseur);
        }

        if ($fromImport && (!isset($data['location']) || empty($data['location']))) {
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
            if($fromImport) {
                $transporteur = $ransporteurRepository->findOneBy(['code' => $data['transporteur']]);
                if (!isset($transporteur)) {
                    throw new InvalidArgumentException(self::INVALID_CARRIER);
                }
            } else {
                $transporteur = $ransporteurRepository->find(intval($data['transporteur']));
            }
            $reception
                ->setTransporteur($transporteur);
        }

        if(!empty($data['storageLocation'])) {
            if($fromImport) {
                $storageLocation = $emplacementRepository->findOneBy(['label' => $data['storageLocation']]);
            if (!isset($storageLocation)) {
                    throw new InvalidArgumentException(self::INVALID_STORAGE_LOCATION);
                }
            } else {
                $storageLocation = $emplacementRepository->find(intval($data['storageLocation']));
            }
            $reception
                ->setStorageLocation($storageLocation);
        }

        if(!empty($data['manualUrgent'])) {
            $reception->setManualUrgent(
                isset($data['manualUrgent'])
                && (
                    filter_var($data['manualUrgent'], FILTER_VALIDATE_BOOLEAN)
                    || in_array($data['manualUrgent'], ['oui', 'Oui', 'OUI'])
                )
            );
        }

        $reception
            ->setOrderNumber(!empty($data['orderNumber']) ? $data['orderNumber'] : null)
            ->setStatut($statut)
            ->setNumber($numero)
            ->setDate($date)
            ->setUtilisateur($currentUser)
            ->setType($type)
            ->setCommentaire(!empty($data['commentaire']) ? $data['commentaire'] : null);

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

        $entityManager->persist($reception);
        return $reception;
    }

    public function dataRowReception(Reception $reception)
    {
        return [
            "id" => ($reception->getId()),
            "Statut" => FormatHelper::status($reception->getStatut()),
            "Date" => FormatHelper::datetime($reception->getDate()),
            "dateAttendue" => FormatHelper::date($reception->getDateAttendue()),
            "DateFin" => FormatHelper::datetime($reception->getDateFinReception()),
            "Fournisseur" => FormatHelper::supplier($reception->getFournisseur()),
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
            "orderNumber" => $reception->getOrderNumber() ?: "",
            "storageLocation" => FormatHelper::location($reception->getStorageLocation()),
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
            ["title" => "Numéro commande", "name" => "orderNumber", 'searchable' => true],
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
        $orderNumber = $reception->getOrderNumber();
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

    public function getAlreadySavedReception(array &$collection, ?string $orderNumber, ?string $expectedDate, callable $onAdd = null): ?Reception {
        $reception = null;
        $receptionRepository = $this->entityManager->getRepository(Reception::class);

        foreach($collection as &$receptionIntel) {
            if ($orderNumber === $receptionIntel['orderNumber']
                && $expectedDate === $receptionIntel['expectedDate']) {
                $reception = $receptionIntel['reception'];
                $isPersistedReception = $this->entityManager->getUnitOfWork()->isInIdentityMap($reception);
                if (!$isPersistedReception) {
                    $reception = $receptionRepository->find($reception->getId());
                    $receptionIntel['reception'] = $reception;
                }
                break;
            }
        }

        if (!$reception) {
            $receptions = $receptionRepository->findBy(
                [
                    'orderNumber' => $orderNumber,
                    'dateAttendue' => $expectedDate
                        ? DateTime::createFromFormat('d/m/Y', $expectedDate) ?: null
                        : null
                ],
                [
                    'id' => 'DESC'
                ]);

            if (!empty($receptions)) {
                $reception = $receptions[0];
                $collection[] = [
                    'orderNumber' => $orderNumber,
                    'expectedDate' => $expectedDate,
                    'reception' => $reception
                ];

                if(isset($onAdd)) {
                    $onAdd();
                }
            }
        }

        return $reception;
    }

    public function setAlreadySavedReception(array &$collection, ?string $orderNumber, ?string $expectedDate, Reception $reception): void {
        $receptionSaved = false;
        foreach($collection as &$receptionIntel) {
            if ($orderNumber === $receptionIntel['orderNumber']
                && $expectedDate === $receptionIntel['expectedDate']) {
                $receptionIntel['reception'] = $reception;
                $receptionSaved = true;
                break;
            }
        }
        if (!$receptionSaved) {
            $collection[] = [
                'orderNumber' => $orderNumber,
                'expectedDate' => $expectedDate,
                'reception' => $reception
            ];
        }
    }
}
