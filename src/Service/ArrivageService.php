<?php

namespace App\Service;

use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Chauffeur;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\FreeField\FreeField;
use App\Entity\Language;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Transporteur;
use App\Entity\TruckArrivalLine;
use App\Entity\Type;
use App\Entity\Urgence;
use App\Entity\Utilisateur;
use App\Helper\LanguageHelper;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use WiiCommon\Helper\Stream;


class ArrivageService {

    private ?array $freeFieldsConfig = null;

    private ?array $exportCache = null;

    public function __construct(
        private Environment            $templating,
        private RouterInterface        $router,
        private Security               $security,
        private EntityManagerInterface $entityManager,
        private KeptFieldService       $keptFieldService,
        private MailerService          $mailerService,
        private UrgenceService         $urgenceService,
        private StringService          $stringService,
        private TranslationService     $translation,
        private FreeFieldService       $freeFieldService,
        private FixedFieldService      $fieldsParamService,
        private FieldModesService      $fieldModesService,
        private FormatService          $formatService,
        private LanguageService        $languageService,
        private CSVExportService       $CSVExportService,
        private UserService            $userService,
        private SettingsService        $settingsService,
    ) {
    }

    public function getDataForDatatable(Request $request, ?int $userIdArrivalFilter)
    {
        $arrivalRepository = $this->entityManager->getRepository(Arrivage::class);
        $supFilterRepository = $this->entityManager->getRepository(FiltreSup::class);

        /** @var Utilisateur $currentUser */
        $currentUser = $this->security->getUser();
        $dispatchMode = $request->query->getBoolean('dispatchMode');

        $filters = $supFilterRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_LU_ARRIVAL, $currentUser);
        $defaultSlug = LanguageHelper::clearLanguage($this->languageService->getDefaultSlug());
        $defaultLanguage = $this->entityManager->getRepository(Language::class)->findOneBy(['slug' => $defaultSlug]);
        $language = $this->security->getUser()->getLanguage() ?: $defaultLanguage;
        $queryResult = $arrivalRepository->findByParamsAndFilters(
            $request->request,
            $filters,
            $this->fieldModesService,
            [
                'userIdArrivalFilter' => $userIdArrivalFilter,
                'user' => $this->security->getUser(),
                'dispatchMode' => $dispatchMode,
                'defaultLanguage' => $defaultLanguage,
                'language' => $language,
            ]
        );

        $arrivals = $queryResult['data'];

        $rows = [];
        foreach ($arrivals as $arrival) {
            $rows[] = $this->dataRowArrivage($arrival[0], [
                'totalWeight' => $arrival['totalWeight'],
                'packsCount' => $arrival['packsCount'],
                'dispatchMode' => $dispatchMode,
                'packsInDispatchCount' => $arrival['dispatchedPacksCount']
            ]);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    public function dataRowArrivage(Arrivage $arrival, array $options = []): array
    {
        $user = $this->security->getUser();
        $arrivalId = $arrival->getId();
        $url = $this->router->generate('arrivage_show', [
            'id' => $arrivalId,
        ]);

        if (!isset($this->freeFieldsConfig)) {
            $this->freeFieldsConfig = $this->freeFieldService->getListFreeFieldConfig($this->entityManager, CategorieCL::ARRIVAGE, CategoryType::ARRIVAGE);
        }

        $acheteursUsernames = [];
        foreach ($arrival->getAcheteurs()->filter(fn($acheteur) => $acheteur) as $acheteur) {
            $acheteursUsernames[] = $acheteur->getUsername();
        }

        $arrivalHasLine = $arrival->getTruckArrivalLines()->first();
        $truckArrivalNumber = $arrival->getTruckArrival()?->getNumber() ?: '';

        $row = [
            'id' => $arrivalId,
            'packsInDispatch' => $options['packsInDispatchCount'] > 0 ? "<td><i class='fas fa-exchange-alt mr-2' title='UL acheminée(s)'></i></td>" : '',
            'arrivalNumber' => $arrival->getNumeroArrivage() ?? '',
            'carrier' => $arrival->getTransporteur() ? $arrival->getTransporteur()->getLabel() : '',
            'totalWeight' => $options['totalWeight'] ?? '',
            'driver' => $arrival->getChauffeur() ? $arrival->getChauffeur()->getPrenomNom() : '',
            'trackingCarrierNumber' => $arrival->getNoTracking() ?: $this->formatService->truckArrivalLines($arrival->getTruckArrivalLines()),
            'orderNumber' => implode(',', $arrival->getNumeroCommandeList()),
            'type' => $this->formatService->type($arrival->getType()),
            'nbUm' => $options['packsCount'] ?? '',
            'customs' => $this->formatService->bool($arrival->getCustoms()),
            'frozen' => $this->formatService->bool($arrival->getFrozen()),
            'provider' => $arrival->getFournisseur() ? $arrival->getFournisseur()->getNom() : '',
            'receivers' => Stream::from($arrival->getReceivers())
                ->map(fn(Utilisateur $receiver) => $this->formatService->user($receiver))
                ->join(", "),
            'buyers' => implode(', ', $acheteursUsernames),
            'status' => $arrival->getStatut() ? $this->formatService->status($arrival->getStatut()) : '',
            'creationDate' => $arrival->getDate() ? $arrival->getDate()->format($user->getDateFormat() ? $user->getDateFormat() . ' H:i:s' : 'd/m/Y H:i:s') : '',
            'user' => $arrival->getUtilisateur() ? $arrival->getUtilisateur()->getUsername() : '',
            'emergency' => $this->formatService->bool($arrival->getIsUrgent()),
            'checkEmergency' => $arrival->getIsUrgent(),
            'projectNumber' => $arrival->getProjectNumber() ?? '',
            'businessUnit' => $arrival->getBusinessUnit() ?? '',
            'dropLocation' => $this->formatService->location($arrival->getDropLocation()),
            'truckArrivalNumber' => $arrivalHasLine
                ? $arrivalHasLine->getTruckArrival()->getNumber()
                : ($truckArrivalNumber),
            'url' => $url,
        ];

        if(isset($options['dispatchMode']) && $options['dispatchMode']) {
            $disabled = $options['packsInDispatchCount'] >= $arrival->getPacks()->count() ? 'disabled' : '';
            $row['actions'] = "<td><input type='checkbox' class='checkbox dispatch-checkbox' value='$arrivalId' $disabled></td>";
        } else {
            $row['actions'] = $this->templating->render('arrivage/datatableArrivageRow.html.twig', ['url' => $url, 'arrivage' => $arrival]);
        }

        foreach ($this->freeFieldsConfig as $freeFieldId => $freeField) {
            $freeFieldName = $this->fieldModesService->getFreeFieldName($freeFieldId);
            $freeFieldValue = $arrival->getFreeFieldValue($freeFieldId);
            $row[$freeFieldName] = $this->formatService->freeField($freeFieldValue, $freeField, $this->security->getUser());
        }

        return $row;
    }

    public function sendArrivalEmails(EntityManagerInterface $entityManager, Arrivage $arrival, array $emergencies = []): void {
        /** @var Utilisateur $user */
        $user = $this->security->getUser();

        $isUrgentArrival = !empty($emergencies);
        $finalRecipients = [];
        if ($isUrgentArrival) {
            $finalRecipients = array_reduce(
                $emergencies,
                function (array $carry, Urgence $emergency) {
                    $buyer = $emergency->getBuyer();
                    $buyerId = $buyer?->getId();
                    if ($buyerId){
                        $carry[$buyerId] = $buyer;
                    }
                    return $carry;
                },
                []
            );
        } else if (!$arrival->getReceivers()->isEmpty()) {
            $finalRecipients = $arrival->getReceivers()->toArray();
        }

        if (!empty($finalRecipients)) {
            $title = ['Traçabilité', 'Arrivages UL', 'Email arrivage UL', 'Arrivage UL reçu : le {1} à {2}', false, [
                1 => $arrival->getNumeroArrivage(),
                2 => $arrival->getDate()->format($user->getDateFormat() ? $user->getDateFormat() . ' H:i' : 'd/m/Y à H:i')
            ]];
            $freeFields = $this->freeFieldService->getFilledFreeFieldArray(
                $this->entityManager,
                $arrival,
                ['type' => $arrival->getType()],
                $this->security->getUser()
            );

            $natures = $entityManager->getRepository(Nature::class)->findAll();
            $packsNatureNb = Stream::from($natures)
                ->filter(static fn(Nature $nature) => isset($nature->getAllowedForms()['arrival']) && $nature->getAllowedForms()['arrival'] === 'all')
                ->keymap(fn(Nature $nature) => [
                    $nature->getId(),
                    [
                        "counter" => 0,
                        "label" => $this->formatService->nature($nature)
                    ]
                ])
                ->toArray();

            foreach ($arrival->getPacks() as $pack) {
                $nature = $pack->getNature();
                $natureId = $nature->getId();
                if (!isset($packsNatureNb[$natureId])) {
                    $packsNatureNb[$natureId] = [
                        "counter" => 0,
                        "label" => $this->formatService->nature($nature)
                    ];
                }

                $packsNatureNb[$natureId]['counter']++;
            }

            $this->mailerService->sendMail(
                ($isUrgentArrival
                    ? ['Traçabilité', 'Arrivages UL', 'Email arrivage UL', 'Arrivage UL urgent', false]
                    : ['Traçabilité', 'Arrivages UL', 'Email arrivage UL', 'Arrivage UL', false]
                ),
                [
                    'name' => 'mails/contents/mailArrivage.html.twig',
                    'context' => [
                        'title' => $title,
                        'arrival' => $arrival,
                        'emergencies' => $emergencies,
                        'isUrgentArrival' => $isUrgentArrival,
                        'freeFields' => $freeFields,
                        'packsNatureNb' => $packsNatureNb,
                        'urlSuffix' => $this->router->generate("arrivage_show", ["id" => $arrival->getId()]),
                    ]
                ],
                $finalRecipients
            );
        }
    }

    public function setArrivalUrgent(EntityManagerInterface $entityManager,
                                     Arrivage               $arrivage,
                                     bool                   $urgent,
                                     array                  $emergencies = []): void {
        if ($urgent) {
            $locationRepository = $entityManager->getRepository(Emplacement::class);

            $dropLocationId = $this->settingsService->getValue($entityManager, Setting::DROP_OFF_LOCATION_IF_EMERGENCY);
            $dropLocation = $dropLocationId ? $locationRepository->find($dropLocationId) : null;

            $arrivage->setIsUrgent(true);

            if ($dropLocation) {
                $arrivage->setDropLocation($dropLocation);
            }
        }

        if ($urgent && !empty($emergencies)) {
            foreach ($emergencies as $emergency) {
                $emergency->setLastArrival($arrivage);
            }
            $this->sendArrivalEmails($entityManager, $arrivage, $emergencies);
        }
    }

    public function createArrivalReserveModalConfig(Arrivage $arrivage, string $lines) {
        return [
            'autoHide' => false,
            'message' => '<span class="bold">Réserve qualité</span><br><br>'
                . "Une réserve qualité a été indiquée sur le(s) numéro(s) de tracking transporteur $lines. Souhaitez vous confirmer la réserve ? Vous pourrez alors créer un litige.",
            'iconType' => 'warning',
            'modalKey' => 'reserve',
            'modalType' => 'yes-no-question',
            'autoPrint' => false,
            'emergencyAlert' => false,
            'numeroCommande' => null,
            'postNb' => null,
            'arrivalId' => $arrivage->getId() ?: $arrivage->getNumeroArrivage()
        ];
    }

    public function createArrivalAlertConfig(Arrivage $arrivage,
                                             bool $askQuestion,
                                             array $urgences = []): array
    {
        $isArrivalUrgent = count($urgences);
        $emergencyOrderNumber = null;
        $arrivalOrderNumbersStr = null;

        if ($askQuestion && $isArrivalUrgent) {
            $emergencyOrderNumber = $urgences[0]->getCommande();
            $postNb = $urgences[0]->getPostNb();
            $internalArticleCode = $urgences[0]->getInternalArticleCode()
                ? $this->translation->translate('Traçabilité', 'Urgences', 'Code article interne', false) . ' : ' . $urgences[0]->getInternalArticleCode() . '</br>'
                : '';
            $supplierArticleCode = $urgences[0]->getSupplierArticleCode()
                ? $this->translation->translate('Traçabilité', 'Urgences', 'Code article fournisseur', false) . " : " . $urgences[0]->getSupplierArticleCode() . '</br>'
                : '';

            $posts = Stream::from($urgences)
                ->map(static fn(Urgence $urgence) => $urgence->getPostNb())
                ->toArray();

            $nbPosts = count($posts);

            $arrivalOrderNumbersStr = Stream::from($arrivage->getNumeroCommandeList())
                ->join(',');

            if ($nbPosts == 0) {
                $emergencyMessage = $emergencyOrderNumber
                    ? "L'arrivage est-il urgent sur la commande $emergencyOrderNumber ?"
                    : "L'arrivage est-il urgent sur la ou les commandes $arrivalOrderNumbersStr ?";
            }
            else {
                $orderLabel = $emergencyOrderNumber
                    ? "la commande <span class=\"bold\">$emergencyOrderNumber</span>"
                    : "la ou les commandes <span class=\"bold\">$arrivalOrderNumbersStr</span>";

                if ($nbPosts == 1) {
                    $emergencyMessage = "
                        Le poste <span class='bold'>" . $posts[0] . "</span> est urgent sur {$orderLabel}.<br/>"
                        . $internalArticleCode
                        . $supplierArticleCode
					    . "L'avez-vous reçu dans cet arrivage ?
					";
                }
                else {
                    $postsStr = implode(', ', $posts);
                    $emergencyMessage = "
                        Les postes <span class=\"bold\">$postsStr</span> sont urgents sur $orderLabel.<br/>"
                        . $internalArticleCode
                        . $supplierArticleCode
					    . "Les avez-vous reçus dans cet arrivage ?
                    ";
                }
            }
        }

        return [
            'autoHide' => (!$askQuestion && !$isArrivalUrgent),
            'message' => ($isArrivalUrgent
                ? (!$askQuestion
                    ? 'Arrivage URGENT enregistré avec succès.'
                    : ($emergencyMessage ?? ''))
                : 'Arrivage enregistré avec succès.'),
            'iconType' => $isArrivalUrgent ? 'warning' : 'success',
            'modalKey' => 'emergency',
            'modalType' => ($askQuestion && $isArrivalUrgent) ? 'yes-no-question' : 'info',
            'autoPrint' => !$this->settingsService->getValue($this->entityManager, Setting::REDIRECT_AFTER_NEW_ARRIVAL),
            'emergencyAlert' => $isArrivalUrgent,
            'numeroCommande' => $emergencyOrderNumber
                ?: $arrivalOrderNumbersStr
                ?: null,
            'postNb' => $postNb ?? null,
            'arrivalId' => $arrivage->getId() ?: $arrivage->getNumeroArrivage()
        ];
    }

    public function createSupplierEmergencyAlert(Arrivage $arrival): ?array {
        $supplier = $arrival->getFournisseur();
        $supplierName = $supplier?->getNom();
        $isArrivalUrgent = ($supplier && $supplier->isUrgent() && $supplierName);

        return $isArrivalUrgent
            ? [
                'autoHide' => false,
                'message' => "Attention, les unités logistiques $supplierName doivent être traitées en urgence",
                'iconType' => 'warning',
                'modalType' => 'info',
                'autoPrint' => !$this->settingsService->getValue($this->entityManager, Setting::REDIRECT_AFTER_NEW_ARRIVAL),
                'arrivalId' => $arrival->getId() ?: $arrival->getNumeroArrivage()
            ]
            : null;
    }

    public function processEmergenciesOnArrival(EntityManagerInterface $entityManager, Arrivage $arrival): array
    {
        $numeroCommandeList = $arrival->getNumeroCommandeList();
        $alertConfigs = [];

        $confirmEmergency = boolval($this->settingsService->getValue($entityManager, Setting::CONFIRM_EMERGENCY_ON_ARRIVAL));

        $allMatchingEmergencies = [];

        if (!empty($numeroCommandeList)) {
            foreach ($numeroCommandeList as $numeroCommande) {
                $urgencesMatching = $this->urgenceService->matchingEmergencies(
                    $arrival,
                    $numeroCommande,
                    null,
                    $confirmEmergency
                );

                if (!empty($urgencesMatching)) {
                    if (!$confirmEmergency) {
                        $this->setArrivalUrgent($entityManager, $arrival, true, $urgencesMatching);
                        array_push($allMatchingEmergencies, ...$urgencesMatching);
                    } else {
                        $currentAlertConfig = array_map(function (Urgence $urgence) use ($arrival, $confirmEmergency) {
                            return $this->createArrivalAlertConfig(
                                $arrival,
                                $confirmEmergency,
                                [$urgence]
                            );
                        }, $urgencesMatching);
                        array_push($alertConfigs, ...$currentAlertConfig);
                    }
                }
            }
        }

        if (!$confirmEmergency
            && $arrival->getIsUrgent()
            && !empty($allMatchingEmergencies)) {
            $alertConfigs[] = $this->createArrivalAlertConfig(
                $arrival,
                false,
                $allMatchingEmergencies
            );
        }

        if (empty($alertConfigs)) {
            $alertConfigs[] = $this->createArrivalAlertConfig($arrival, $confirmEmergency);
        }

        return $alertConfigs;
    }

    public function createHeaderDetailsConfig(Arrivage $arrivage): array {
        $fieldsParamRepository = $this->entityManager->getRepository(FixedFieldStandard::class);
        $fieldsParam = $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_ARRIVAGE);

        $provider = $arrivage->getFournisseur();
        $carrier = $arrivage->getTransporteur();
        $driver = $arrivage->getChauffeur();
        $numeroCommandeList = $arrivage->getNumeroCommandeList();
        $status = $arrivage->getStatut();
        $type = $arrivage->getType();
        $receivers = Stream::from($arrivage->getReceivers())
            ->map(fn(Utilisateur $receiver) => $this->formatService->user($receiver))
            ->join(", ");
        $dropLocation = $arrivage->getDropLocation();
        $buyers = $arrivage->getAcheteurs();
        $comment = $arrivage->getCommentaire();
        $attachments = $arrivage->getAttachments();
        $truckArrivalLines = $arrivage->getTruckArrivalLines();
        $numeroTrackingOld = $arrivage->getNoTracking();
        $numeroTrackingArray = Stream::from($truckArrivalLines)
            ->map(fn(TruckArrivalLine $line) => $line->getNumber())
            ->join(', ');

        $freeFieldArray = $this->freeFieldService->getFilledFreeFieldArray(
            $this->entityManager,
            $arrivage,
            ['type' => $arrivage->getType()],
            $this->security->getUser()
        );

        $config = [
            [
                'label' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Type'),
                'value' => $this->formatService->type($type, '-'),
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Statut'),
                'value' => $status ? $this->stringService->mbUcfirst($this->formatService->status($status)) : '',
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Fournisseur'),
                'value' => $provider ? $provider->getNom() : '',
                'show' => ['fieldName' => 'fournisseur'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Emplacement de dépose'),
                'value' => $dropLocation ? $dropLocation->getLabel() : '',
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_DROP_LOCATION_ARRIVAGE ],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Transporteur'),
                'value' => $carrier ? $carrier->getLabel() : '',
                'show' => ['fieldName' => 'transporteur'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Chauffeur'),
                'value' => $this->formatService->driver($driver),
                'show' => ['fieldName' => 'chauffeur'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'N°Arrivage camion'),
                'value' => count($truckArrivalLines) > 0
                    ? '<a href="/arrivage-camion/voir/'. $truckArrivalLines->first()->getTruckArrival()->getId() . '" title="Détail Arrivage Camion">' . $truckArrivalLines->first()->getTruckArrival()->getNumber() . '</a>'
                    : '<a href="/arrivage-camion/voir/'. $arrivage->getTruckArrival()?->getId() . '" title="Détail Arrivage Camion">' . $arrivage->getTruckArrival()?->getNumber() . '</a>',
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'N° tracking transporteur'),
                'value' => $numeroTrackingArray ?: $numeroTrackingOld,
                'show' => ['fieldName' => 'noTracking'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'N° commande / BL'),
                'value' => !empty($numeroCommandeList) ? implode(', ', $numeroCommandeList) : '',
                'show' => ['fieldName' => 'numeroCommandeList'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Destinataire(s)'),
                'value' => $receivers,
                'show' => ['fieldName' => 'receivers'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Acheteur(s)'),
                'value' => $buyers->count() > 0 ? implode(', ', $buyers->map(fn (Utilisateur $buyer) => $buyer->getUsername())->toArray()) : '',
                'show' => ['fieldName' => 'acheteurs'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Numéro de projet'),
                'value' => $arrivage->getProjectNumber(),
                'show' => ['fieldName' => 'projectNumber'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Business unit'),
                'value' => $arrivage->getBusinessUnit(),
                'show' => ['fieldName' => 'businessUnit'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Douane'),
                'value' => $this->formatService->bool($arrivage->getCustoms()),
                'show' => ['fieldName' => 'customs'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Congelé'),
                'value' => $this->formatService->bool($arrivage->getFrozen()),
                'show' => ['fieldName' => 'frozen'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'N° d\'arrivage camion'),
                'value' => $arrivage->getTruckArrivalLines(),
                'show' => ['fieldName' => 'truckArrivalNumber'],
                'isRaw' => true
            ],
        ];

        $configFiltered =  $this->fieldsParamService->filterHeaderConfig($config, FixedFieldStandard::ENTITY_CODE_ARRIVAGE);

        return array_merge(
            $configFiltered,
            $freeFieldArray,
            $this->fieldsParamService->isFieldRequired($fieldsParam, FixedFieldStandard::FIELD_CODE_COMMENTAIRE_ARRIVAGE, 'displayedCreate')
            || $this->fieldsParamService->isFieldRequired($fieldsParam, FixedFieldStandard::FIELD_CODE_COMMENTAIRE_ARRIVAGE, 'displayedEdit')
                ? [[
                'label' => $this->translation->translate('Général', null, 'Modale', 'Commentaire'),
                'value' => $comment ?: '',
                'isRaw' => true,
                'colClass' => 'col-sm-6 col-12',
                'isScrollable' => true,
                'isNeededNotEmpty' => true
            ]]
                : [],
                $this->fieldsParamService->isFieldRequired($fieldsParam, FixedFieldStandard::FIELD_CODE_PJ_ARRIVAGE, 'displayedCreate')
                || $this->fieldsParamService->isFieldRequired($fieldsParam, FixedFieldStandard::FIELD_CODE_PJ_ARRIVAGE, 'displayedEdit')
                ? [[
                    'label' => $this->translation->translate('Général', null, 'Modale', 'Pièces jointes', false),
                    'value' => $attachments->toArray(),
                    'isAttachments' => true,
                    'isNeededNotEmpty' => true
                ]]
                : []
        );
    }

    public function getColumnVisibleConfig(EntityManagerInterface $entityManager,
                                           Utilisateur $currentUser,
                                           bool $dispatchMode = false): array {

        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);

        $columnsVisible = $currentUser->getFieldModes('arrival');
        $freeFields = $champLibreRepository->findByCategoryTypeAndCategoryCL(CategoryType::ARRIVAGE, CategorieCL::ARRIVAGE);

        $columns = [
            ['name' => 'packsInDispatch', 'alwaysVisible' => true, 'orderable' => false, 'class' => 'noVis'],
            ['title' => $this->translation->translate('Général', null, 'Zone liste', 'Date de création'), 'name' => 'creationDate', 'type' => ($dispatchMode ? 'customDate' : '')],
            ['title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Divers', 'N° d\'arrivage UL'), 'name' => 'arrivalNumber'],
            ['title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'N° d\'arrivage camion'), 'name' => 'truckArrivalNumber'],
            ['title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Divers', 'Poids total (kg)'), 'name' => 'totalWeight'],
            ['title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Transporteur'), 'name' => 'carrier'],
            ['title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Chauffeur'), 'name' => 'driver'],
            ['title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'N° tracking transporteur'), 'name' => 'trackingCarrierNumber'],
            ['title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'N° commande / BL'), 'name' => 'orderNumber'],
            ['title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Type'), 'name' => 'type'],
            ['title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Fournisseur'), 'name' => 'provider'],
            ['title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Destinataire(s)'),'name' => 'receivers'],
            ['title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Acheteur(s)'), 'name' => 'buyers'],
            ['title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Divers', 'Nombre d\'UL'), 'name' => 'nbUm'],
            ['title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Douane'), 'name' => 'customs'],
            ['title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Congelé'), 'name' => 'frozen'],
            ['title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Statut'), 'name' => 'status'],
            ['title' => $this->translation->translate('Traçabilité', 'Général', 'Utilisateur'), 'name' => 'user'],
            ['title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Divers', 'Urgent'), 'name' => 'emergency'],
            ['title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Numéro de projet'), 'name' => 'projectNumber'],
            ['title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Business unit'), 'name' => 'businessUnit'],
        ];

        if($dispatchMode) {
            $dispatchCheckboxLine = [
                'title' => "<input type='checkbox' class='checkbox check-all'>",
                'name' => 'actions',
                'alwaysVisible' => true,
                'orderable' => false,
                'class' => 'noVis'
            ];
            array_unshift($columns, $dispatchCheckboxLine);
        } else {
            array_unshift($columns, ['name' => 'actions', 'alwaysVisible' => true, 'orderable' => false, 'class' => 'noVis actions']);
        }

        $arrivalFieldsParam = $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_ARRIVAGE);

        if ($this->fieldsParamService->isFieldRequired($arrivalFieldsParam, FixedFieldStandard::FIELD_CODE_DROP_LOCATION_ARRIVAGE, 'displayedCreate')
            || $this->fieldsParamService->isFieldRequired($arrivalFieldsParam, FixedFieldStandard::FIELD_CODE_DROP_LOCATION_ARRIVAGE, 'displayedEdit')) {
            $columns[] = ['title' =>  $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Emplacement de dépose'), 'name' => 'dropLocation'];
        }

        return $this->fieldModesService->getArrayConfig($columns, $freeFields, $columnsVisible);
    }

    public function sendMailForDeliveredPack(Emplacement            $location,
                                             Pack                   $pack,
                                             Utilisateur            $user,
                                             string                 $trackingType,
                                             DateTime               $date): void {
        if ($location->getIsDeliveryPoint()
            && $trackingType === TrackingMovement::TYPE_DEPOSE
            && !$pack->isDeliveryDone()
            && $pack->getArrivage()) {
            $arrivage = $pack->getArrivage();
            $receivers = $arrivage->getReceivers()->toArray();
            $pack->setIsDeliveryDone(true);
            if (!empty($receivers)) {
                $this->mailerService->sendMail(
                    ['Traçabilité', 'Général', 'Dépose effectuée', false],
                    [
                        "name" => "mails/contents/mailPackDeliveryDone.html.twig",
                        "context" => [
                            'title' => ['Traçabilité', 'Général', 'Votre unité logistique a été livrée', false],
                            'orderNumber' => implode(', ', $arrivage->getNumeroCommandeList()),
                            'pack' => $this->formatService->pack($pack),
                            'emplacement' => $location,
                            'fournisseur' => $this->formatService->supplier($arrivage->getFournisseur()),
                            'date' => $date,
                            'operateur' => $this->formatService->user($user),
                            'pjs' => $arrivage->getAttachments()
                        ]
                    ],
                    $receivers
                );
            }
        }
    }

    public function launchExportCache(EntityManagerInterface $entityManager,
                                      DateTime $from,
                                      DateTime $to): void {

        $natureRepository = $entityManager->getRepository(Nature::class);
        $packRepository = $entityManager->getRepository(Pack::class);
        $arrivalRepository = $entityManager->getRepository(Arrivage::class);
        $freeFieldsConfig = $this->freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::ARRIVAGE]);
        $this->exportCache = [
            "natures" => $natureRepository->findBy([], ['id' => Criteria::ASC]),
            "packs" => $packRepository->countPacksByArrival($from, $to),
            "packsTotalWeight" => $arrivalRepository->getTotalWeightByArrivals($from, $to),
            "freeFields" => $freeFieldsConfig['freeFields']
        ];
    }

    public function putArrivalLine($output,
                                   Arrivage $arrival,
                                   array $columnToExport): void {

        if (!isset($this->exportCache)) {
            throw new \Exception('Export cache unloaded, call ArrivageService::launchExportCache before');
        }

        $packsTotalWeight = $this->exportCache['packsTotalWeight'];
        $packs = $this->exportCache['packs'];

        $line = [];
        foreach ($columnToExport as $column) {
            if (preg_match('/nature_(\d+)/', $column, $matches)) {
                $natureId = $matches[1];
                $line[] = $packs[$arrival->getId()][$natureId] ?? 0 ?: '';
            }
            else if (preg_match('/free_field_(\d+)/', $column, $matches)) {
                $freeFieldId = $matches[1];
                $freeField = $this->exportCache['freeFields'][$freeFieldId] ?? null;
                $value = $arrival->getFreeFieldValue($freeFieldId) ?: '';
                $line[] = $freeField
                    ? $this->formatService->freeField($value, $freeField, $this->userService->getUser())
                    : $value;
            }
            else {
                $line[] = match ($column) {
                    FixedFieldStandard::FIELD_CODE_ARRIVAL_NUMBER           => $arrival->getNumeroArrivage(),
                    FixedFieldStandard::FIELD_CODE_ARRIVAL_TOTAL_WEIGHT     => $packsTotalWeight[$arrival->getId()] ?? '',
                    FixedFieldStandard::FIELD_CODE_ARRIVAL_TYPE             => $this->formatService->type($arrival->getType()),
                    FixedFieldStandard::FIELD_CODE_ARRIVAL_STATUS           => $this->formatService->status($arrival->getStatut()),
                    FixedFieldStandard::FIELD_CODE_ARRIVAL_DATE             => $this->formatService->datetime($arrival->getDate(), "", false, $this->userService->getUser()),
                    FixedFieldStandard::FIELD_CODE_ARRIVAL_CREATOR          => $this->formatService->user($arrival->getUtilisateur()),
                    FixedFieldStandard::FIELD_CODE_BUYERS_ARRIVAGE          => $this->formatService->users($arrival->getAcheteurs()),
                    FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT            => $arrival->getBusinessUnit() ?? '',
                    FixedFieldStandard::FIELD_CODE_CHAUFFEUR_ARRIVAGE       => $arrival->getChauffeur()?->getPrenomNom() ?: '',
                    FixedFieldStandard::FIELD_CODE_COMMENTAIRE_ARRIVAGE     => strip_tags($arrival->getCommentaire() ?? ''),
                    FixedFieldStandard::FIELD_CODE_FROZEN_ARRIVAGE          => $this->formatService->bool($arrival->getFrozen()),
                    FixedFieldStandard::FIELD_CODE_RECEIVERS                => Stream::from($arrival->getReceivers())->map(fn(Utilisateur $receiver) => $this->formatService->user($receiver))->join(", "),
                    FixedFieldStandard::FIELD_CODE_CUSTOMS_ARRIVAGE         => $this->formatService->bool($arrival->getCustoms()),
                    FixedFieldStandard::FIELD_CODE_DROP_LOCATION_ARRIVAGE   => $this->formatService->location($arrival->getDropLocation()),
                    FixedFieldStandard::FIELD_CODE_PROVIDER_ARRIVAGE        => $this->formatService->supplier($arrival->getFournisseur()),
                    FixedFieldStandard::FIELD_CODE_NUM_COMMANDE_ARRIVAGE    => $arrival->getNumeroCommandeList() ? implode(",", $arrival->getNumeroCommandeList()) : '',
                    FixedFieldStandard::FIELD_CODE_PROJECT_NUMBER           => $arrival->getProjectNumber() ?: '',
                    FixedFieldStandard::FIELD_CODE_NUMERO_TRACKING_ARRIVAGE => $arrival->getNoTracking() ?: $this->formatService->truckArrivalLines($arrival->getTruckArrivalLines()),
                    FixedFieldStandard::FIELD_CODE_CARRIER_ARRIVAGE         => $arrival->getTransporteur()?->getLabel() ?: '',
                    FixedFieldStandard::FIELD_CODE_EMERGENCY                => $this->formatService->bool($arrival->getIsUrgent()),
                    default                                                 => null
                };
            }
        }
        $this->CSVExportService->putLine($output, $line);
    }

    public function getArrivalExportableColumns(EntityManagerInterface $entityManager): array {
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);
        $freeFieldsRepository = $entityManager->getRepository(FreeField::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $arrivalFields = $fieldsParamRepository->getByEntityForExport(FixedFieldStandard::ENTITY_CODE_ARRIVAGE);
        $freeFields = $freeFieldsRepository->findByFreeFieldCategoryLabels([CategorieCL::ARRIVAGE]);
        $natures = $natureRepository->findBy([], ['id' => Order::Ascending->value]);

        $userLanguage = $this->userService->getUser()?->getLanguage() ?: $this->languageService->getDefaultSlug();
        $defaultLanguage = $this->languageService->getDefaultSlug();

        return Stream::from(
            [
                ["code" => FixedFieldStandard::FIELD_CODE_ARRIVAL_NUMBER, "label" => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Divers', 'N° d\'arrivage UL', false)],
                ["code" => FixedFieldStandard::FIELD_CODE_ARRIVAL_TOTAL_WEIGHT, "label" => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Divers', 'Poids total (kg)', false)],
                ["code" => FixedFieldStandard::FIELD_CODE_ARRIVAL_TYPE, "label" => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Type', false)],
                ["code" => FixedFieldStandard::FIELD_CODE_ARRIVAL_STATUS, "label" => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Statut', false)],
                ["code" => FixedFieldStandard::FIELD_CODE_ARRIVAL_DATE, "label" => $this->translation->translate('Général', null, 'Zone liste', 'Date de création', false)],
                ["code" => FixedFieldStandard::FIELD_CODE_ARRIVAL_CREATOR, "label" => $this->translation->translate('Traçabilité', 'Général', 'Utilisateur', false)],
                ["code" => FixedFieldStandard::FIELD_CODE_EMERGENCY, "label" => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Divers', 'Urgent', false)],
            ],
            Stream::from($arrivalFields)
                ->filter(fn(FixedFieldStandard $field) => !in_array($field->getFieldCode(), [
                    FixedFieldStandard::FIELD_CODE_PJ_ARRIVAGE,
                    FixedFieldStandard::FIELD_CODE_PRINT_ARRIVAGE,
                    FixedFieldStandard::FIELD_CODE_PROJECT,
                    FixedFieldStandard::FIELD_CODE_ARRIVAL_TYPE,
                ]))
                ->map(fn(FixedFieldStandard $field) => [
                    "code" => $field->getFieldCode(),
                    "label" => match($field->getFieldCode()) {
                        FixedFieldStandard::FIELD_CODE_BUYERS_ARRIVAGE => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Acheteur(s)', false),
                        FixedFieldStandard::FIELD_CODE_CHAUFFEUR_ARRIVAGE => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Chauffeur', false),
                        FixedFieldStandard::FIELD_CODE_COMMENTAIRE_ARRIVAGE => $this->translation->translate('Général', null, 'Modale', 'Commentaire', false),
                        FixedFieldStandard::FIELD_CODE_CARRIER_ARRIVAGE => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Transporteur', false),
                        FixedFieldStandard::FIELD_CODE_PROVIDER_ARRIVAGE => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Fournisseur', false),
                        FixedFieldStandard::FIELD_CODE_RECEIVERS => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Destinataire(s)', false),
                        FixedFieldStandard::FIELD_CODE_NUM_COMMANDE_ARRIVAGE => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'N° commande / BL', false),
                        FixedFieldStandard::FIELD_CODE_NUMERO_TRACKING_ARRIVAGE => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'N° tracking transporteur', false),
                        FixedFieldStandard::FIELD_CODE_CUSTOMS_ARRIVAGE => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Douane', false),
                        FixedFieldStandard::FIELD_CODE_FROZEN_ARRIVAGE => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Congelé', false),
                        FixedFieldStandard::FIELD_CODE_PROJECT_NUMBER => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Numéro de projet', false),
                        FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Business unit', false),
                        FixedFieldStandard::FIELD_CODE_DROP_LOCATION_ARRIVAGE => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Emplacement de dépose', false),
                        default => $field->getFieldLabel()
                    }
                ]),
            Stream::from($natures)
                ->map(fn(Nature $nature) => [
                    'code' => "nature_{$nature->getId()}",
                    'label' => $this->formatService->nature($nature)
                ]),
            Stream::from($freeFields)
                ->map(fn(FreeField $field) => [
                    "code" => "free_field_{$field->getId()}",
                    "label" => $field->getLabelIn($userLanguage, $defaultLanguage)
                        ?: $field->getLabel(),
                ])
        )
            ->toArray();
    }

    public function getArrivalExportableColumnsSorted(EntityManagerInterface $entityManager): array {
        return Stream::from($this->getArrivalExportableColumns($entityManager))
            ->reduce(function(array $carry, array $column) {
                $carry["labels"][] = $column["label"] ?? '';
                $carry["codes"][] = $column["code"] ?? '';
                return $carry;
            },
            ["labels" => [], "codes" => []]
        );
    }


    public function generateNewForm(EntityManagerInterface $entityManager, array $fromTruckArrivalOptions = []): array
    {
        if ($this->userService->hasRightFunction(Menu::TRACA, Action::CREATE)) {
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $natureRepository = $entityManager->getRepository(Nature::class);
            $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);
            $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $transporteurRepository = $entityManager->getRepository(Transporteur::class);
            $locationRepository = $entityManager->getRepository(Emplacement::class);
            $categoryTypeRepository = $entityManager->getRepository(CategoryType::class);

            $fieldsParam = $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_ARRIVAGE);

            $statuses = Stream::from($statutRepository->findStatusByType(CategorieStatut::ARRIVAGE))
                ->map(fn(Statut $statut) => [
                    'id' => $statut->getId(),
                    'type' => $statut->getType(),
                    'nom' => $this->formatService->status($statut),
                ])
                ->toArray();
            $defaultLocationId = $this->settingsService->getValue($entityManager, Setting::MVT_DEPOSE_DESTINATION);
            $defaultLocation = $defaultLocationId ? $emplacementRepository->find($defaultLocationId) : null;

            $defaultLocationIdIfCustomId = $this->settingsService->getValue($entityManager, Setting::DROP_OFF_LOCATION_IF_CUSTOMS);
            $defaultLocationIfCustoms = $defaultLocationIdIfCustomId ? $emplacementRepository->find($defaultLocationIdIfCustomId) : null;

            $defaultLocationIdifRecipient = $this->settingsService->getValue($entityManager, Setting::DROP_OFF_LOCATION_IF_RECIPIENT);
            $defaultLocationIfRecipient = $defaultLocationIdifRecipient ? $emplacementRepository->find($defaultLocationIdifRecipient) : null;

            $natures = Stream::from($natureRepository->findByAllowedForms([Nature::ARRIVAL_CODE]))
                ->map(fn(Nature $nature) => [
                    'id' => $nature->getId(),
                    'label' => $this->formatService->nature($nature),
                    'defaultQuantity' => $nature->getDefaultQuantity(),
                ])
                ->toArray();

            $keptFields = $this->keptFieldService->getAll(FixedFieldStandard::ENTITY_CODE_ARRIVAGE);

            if(isset($keptFields[FixedFieldStandard::FIELD_CODE_DROP_LOCATION_ARRIVAGE])) {
                $keptFields[FixedFieldStandard::FIELD_CODE_DROP_LOCATION_ARRIVAGE] = $locationRepository->find($keptFields[FixedFieldStandard::FIELD_CODE_DROP_LOCATION_ARRIVAGE]);
            }

            if(isset($keptFields[FixedFieldStandard::FIELD_CODE_RECEIVERS])) {
                $keptFields[FixedFieldStandard::FIELD_CODE_RECEIVERS] = $utilisateurRepository->findBy(['id' => explode(",", $keptFields[FixedFieldStandard::FIELD_CODE_RECEIVERS])]);
            }

            if(isset($keptFields[FixedFieldStandard::FIELD_CODE_BUYERS_ARRIVAGE])) {
                $keptFields[FixedFieldStandard::FIELD_CODE_BUYERS_ARRIVAGE] = $utilisateurRepository->findBy(['id' => $keptFields[FixedFieldStandard::FIELD_CODE_BUYERS_ARRIVAGE]]);
            }

            $arrivalCategoryType = $categoryTypeRepository->findOneBy(['label' => CategoryType::ARRIVAGE]);

            $html = $this->templating->render("arrivage/modalNewArrivage.html.twig", [
                "keptFields" => $keptFields,
                "typesArrival" => $typeRepository->findByCategoryLabels([CategoryType::ARRIVAGE]),
                "statuses" => $statuses,
                "fournisseurs" => $fournisseurRepository->findBy([], ['nom' => 'ASC']),
                "natures" => $natures,
                "carriers" => $transporteurRepository->findAllSorted(),
                "chauffeurs" => $chauffeurRepository->findAllSorted(),
                "fieldsParam" => $fieldsParam,
                "businessUnits" => $fieldsParamRepository->getElements(FixedFieldStandard::ENTITY_CODE_ARRIVAGE, FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT),
                "defaultLocation" => $defaultLocation,
                "defaultLocationIfCustoms" => $defaultLocationIfCustoms,
                "defaultLocationIfRecipient" => $defaultLocationIfRecipient,
                "defaultStatuses" => $statutRepository->getIdDefaultsByCategoryName(CategorieStatut::ARRIVAGE),
                "autoPrint" => $this->settingsService->getValue($entityManager, Setting::AUTO_PRINT_LU),
                "fromTruckArrivalOptions" => $fromTruckArrivalOptions,
                'defaultType' => $typeRepository->findOneBy(['category' => $arrivalCategoryType, 'defaultType' => true]),
            ]);
        }

        return [
            'html' => $html ?? "",
            'acheteurs' => $acheteursUsernames ?? []
        ];
    }

    public function getDefaultDropLocation(EntityManagerInterface $entityManager,
                                           Arrivage               $arrivage,
                                           ?Emplacement           $enteredLocation): ?Emplacement {
        $locationRepository = $entityManager->getRepository(Emplacement::class);

        $emergenciesArrivalsLocation = $this->settingsService->getValue($entityManager, Setting::DROP_OFF_LOCATION_IF_EMERGENCY);
        if($arrivage->getIsUrgent() && $emergenciesArrivalsLocation) {
            return $locationRepository->find($emergenciesArrivalsLocation);
        }

        if ($enteredLocation) {
            return $enteredLocation;
        }

        $customsArrivalsLocation = $this->settingsService->getValue($entityManager, Setting::DROP_OFF_LOCATION_IF_CUSTOMS);
        if($arrivage->getCustoms() && $customsArrivalsLocation) {
            return $locationRepository->find($customsArrivalsLocation);
        }

        $receiverDefaultLocation = $this->settingsService->getValue($entityManager, Setting::DROP_OFF_LOCATION_IF_RECIPIENT);
        if (!$arrivage->getReceivers()->isEmpty() && $receiverDefaultLocation) {
            return $locationRepository->find($receiverDefaultLocation);
        }

        $defaultArrivalsLocation = $this->settingsService->getValue($entityManager, Setting::MVT_DEPOSE_DESTINATION);
        if($defaultArrivalsLocation) {
            return $locationRepository->find($defaultArrivalsLocation);
        }

        return null;
    }

    public function serialize(Arrivage $arrival): array {
        return [
            FixedFieldEnum::status->value => $this->formatService->status($arrival->getStatut()),
            FixedFieldEnum::type->value => $this->formatService->type($arrival->getType()),
            FixedFieldEnum::carrier->value => $this->formatService->carrier($arrival->getTransporteur()),
            FixedFieldEnum::comment->value => $this->formatService->html($arrival->getCommentaire() ?? ''),
        ];
    }

    public function generateArrivalNumber(int $numberOfArrivalsByDate, DateTime $date = null): string {
        $date = $date ?? new DateTime('now');

        return sprintf('%s%s%s',
            $date->format('ymdHis'),
            $this->generateArrivalNumberNeedsHyphen() ? '-' : '',
            $this->formatCount($numberOfArrivalsByDate)
        );
    }

    private function formatCount(int $numberOfArrivalsByDate): string {
        return $numberOfArrivalsByDate < 10
            ? "0{$numberOfArrivalsByDate}"
            : (string)$numberOfArrivalsByDate;
    }

    private function generateArrivalNumberNeedsHyphen(): bool {
        return $this->settingsService->getValue(
                $this->entityManager,
                Setting::FORMAT_CODE_ARRIVALS
            ) === Arrivage::WITH_HYPHEN;
    }

}
