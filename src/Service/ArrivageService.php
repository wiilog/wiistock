<?php

namespace App\Service;

use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Chauffeur;
use App\Entity\Emplacement;
use App\Entity\Fournisseur;
use App\Entity\FreeField;
use App\Entity\FieldsParam;
use App\Entity\FiltreSup;
use App\Entity\Language;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Pack;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\TrackingMovement;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\TruckArrivalLine;
use App\Entity\Urgence;
use App\Entity\Utilisateur;
use App\Helper\LanguageHelper;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment;
use WiiCommon\Helper\Stream;


class ArrivageService {

    #[Required]
    public Environment $templating;

    #[Required]
    public RouterInterface $router;

    #[Required]
    public Security $security;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public KeptFieldService $keptFieldService;

    #[Required]
    public MailerService $mailerService;

    #[Required]
    public UrgenceService $urgenceService;

    #[Required]
    public SpecificService $specificService;

    #[Required]
    public StringService $stringService;

    #[Required]
    public TranslationService $translation;

    #[Required]
    public FreeFieldService $freeFieldService;

    #[Required]
    public FieldsParamService $fieldsParamService;

    #[Required]
    public VisibleColumnService $visibleColumnService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public LanguageService $languageService;

    #[Required]
    public CSVExportService $CSVExportService;

    #[Required]
    public UserService $userService;

    private ?array $freeFieldsConfig = null;

    private ?array $exportCache = null;

    public function getDataForDatatable(Request $request, ?int $userIdArrivalFilter)
    {
        $arrivalRepository = $this->entityManager->getRepository(Arrivage::class);
        $supFilterRepository = $this->entityManager->getRepository(FiltreSup::class);

        /** @var Utilisateur $currentUser */
        $currentUser = $this->security->getUser();
        $dispatchMode = $request->query->getBoolean('dispatchMode');

        $filters = $supFilterRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ARRIVAGE, $currentUser);
        $defaultSlug = LanguageHelper::clearLanguage($this->languageService->getDefaultSlug());
        $defaultLanguage = $this->entityManager->getRepository(Language::class)->findOneBy(['slug' => $defaultSlug]);
        $language = $this->security->getUser()->getLanguage() ?: $defaultLanguage;
        $queryResult = $arrivalRepository->findByParamsAndFilters(
            $request->request,
            $filters,
            $this->visibleColumnService,
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
        foreach ($arrival->getAcheteurs() as $acheteur) {
            $acheteursUsernames[] = $acheteur->getUsername();
        }

        $row = [
            'id' => $arrivalId,
            'packsInDispatch' => $options['packsInDispatchCount'] > 0 ? "<td><i class='fas fa-exchange-alt mr-2' title='UL acheminée(s)'></i></td>" : '',
            'arrivalNumber' => $arrival->getNumeroArrivage() ?? '',
            'carrier' => $arrival->getTransporteur() ? $arrival->getTransporteur()->getLabel() : '',
            'totalWeight' => $options['totalWeight'] ?? '',
            'driver' => $arrival->getChauffeur() ? $arrival->getChauffeur()->getPrenomNom() : '',
            'trackingCarrierNumber' => $arrival->getNoTracking() ? $arrival->getNoTracking() : $this->formatService->truckArrivalLines($arrival->getTruckArrivalLines()),
            'orderNumber' => implode(',', $arrival->getNumeroCommandeList()),
            'type' => $this->formatService->type($arrival->getType()),
            'nbUm' => $options['packsCount'] ?? '',
            'customs' => $this->formatService->bool($arrival->getCustoms()),
            'frozen' => $this->formatService->bool($arrival->getFrozen()),
            'provider' => $arrival->getFournisseur() ? $arrival->getFournisseur()->getNom() : '',
            'receiver' => $arrival->getDestinataire() ? $arrival->getDestinataire()->getUsername() : '',
            'buyers' => implode(', ', $acheteursUsernames),
            'status' => $arrival->getStatut() ? $this->formatService->status($arrival->getStatut()) : '',
            'creationDate' => $arrival->getDate() ? $arrival->getDate()->format($user->getDateFormat() ? $user->getDateFormat() . ' H:i:s' : 'd/m/Y H:i:s') : '',
            'user' => $arrival->getUtilisateur() ? $arrival->getUtilisateur()->getUsername() : '',
            'emergency' => $this->formatService->bool($arrival->getIsUrgent()),
            'checkEmergency' => $arrival->getIsUrgent(),
            'projectNumber' => $arrival->getProjectNumber() ?? '',
            'businessUnit' => $arrival->getBusinessUnit() ?? '',
            'dropLocation' => $this->formatService->location($arrival->getDropLocation()),
            'truckArrivalNumber' => !$arrival->getTruckArrivalLines()->isEmpty() ? $arrival->getTruckArrivalLines()->first()->getTruckArrival()->getNumber() : '',
            'url' => $url,
        ];

        if(isset($options['dispatchMode']) && $options['dispatchMode']) {
            $disabled = $options['packsInDispatchCount'] >= $arrival->getPacks()->count() ? 'disabled' : '';
            $row['actions'] = "<td><input type='checkbox' class='checkbox dispatch-checkbox' value='$arrivalId' $disabled></td>";
        } else {
            $row['actions'] = $this->templating->render('arrivage/datatableArrivageRow.html.twig', ['url' => $url, 'arrivage' => $arrival]);
        }

        foreach ($this->freeFieldsConfig as $freeFieldId => $freeField) {
            $freeFieldName = $this->visibleColumnService->getFreeFieldName($freeFieldId);
            $freeFieldValue = $arrival->getFreeFieldValue($freeFieldId);
            $row[$freeFieldName] = $this->formatService->freeField($freeFieldValue, $freeField, $this->security->getUser());
        }

        return $row;
    }

    public function sendArrivalEmails(EntityManager $entityManager, Arrivage $arrival, array $emergencies = []): void {
        /** @var Utilisateur $user */
        $user = $this->security->getUser();

        $isUrgentArrival = !empty($emergencies);
        $finalRecipients = [];
        if ($isUrgentArrival) {
            $finalRecipients = array_reduce(
                $emergencies,
                function (array $carry, Urgence $emergency) {
                    $buyer = $emergency->getBuyer();
                    $buyerId = $buyer->getId();
                    $carry[$buyerId] = $buyer;
                    return $carry;
                },
                []
            );
        } else if ($arrival->getDestinataire()) {
            $recipient = $arrival->getDestinataire();
            $finalRecipients = $recipient ? [$recipient] : [];
        }

        if (!empty($finalRecipients)) {
            $title = ['Traçabilité', 'Flux - Arrivages', 'Email arrivage', 'Arrivage reçu : le {1} à {2}', false, [
                1 => $arrival->getNumeroArrivage(),
                2 => $arrival->getDate()->format($user->getDateFormat() ? $user->getDateFormat() . ' H:i' : 'd/m/Y à H:i')
            ]];
            $freeFields = $this->freeFieldService->getFilledFreeFieldArray(
                $this->entityManager,
                $arrival,
                ['type' => $arrival->getType()],
                $this->security->getUser()
            );

            $packsNatureNb = [];
            $natures = $entityManager->getRepository(Nature::class)->findAll();
            foreach ($natures as $nature) {
                if (isset($nature->getAllowedForms()['arrival']) && $nature->getAllowedForms()['arrival'] === 'all') {
                    $packsNatureNb[$nature->getLabel()] = 0;
                }
            }
            foreach ($arrival->getPacks() as $pack) {
                $packsNatureNb[$pack->getNature()->getLabel()] += 1;
            }

            $this->mailerService->sendMail(
                ($isUrgentArrival
                    ? ['Traçabilité', 'Flux - Arrivages', 'Email arrivage', 'FOLLOW GT // Arrivage urgent', false]
                    : ['Traçabilité', 'Flux - Arrivages', 'Email arrivage', 'FOLLOW GT // Arrivage', false]
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

    public function setArrivalUrgent(EntityManager $entityManager, Arrivage $arrivage, array $emergencies): void
    {
        if (!empty($emergencies)) {
            $arrivage->setIsUrgent(true);
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

        if ($askQuestion && $isArrivalUrgent) {
            $numeroCommande = $urgences[0]->getCommande();
            $postNb = $urgences[0]->getPostNb();

            $posts = array_map(
                function (Urgence $urgence) {
                    return $urgence->getPostNb();
                },
                $urgences
            );

            $nbPosts = count($posts);

            if ($nbPosts == 0) {
                $msgSedUrgent = "L'arrivage est-il urgent sur la commande $numeroCommande ?";
            }
            else {
                if ($nbPosts == 1) {
                    $msgSedUrgent = "
                        Le poste <span class='bold'>" . $posts[0] . "</span> est urgent sur la commande <span class=\"bold\">$numeroCommande</span>.<br/>
					    L'avez-vous reçu dans cet arrivage ?
					";
                }
                else {
                    $postsStr = implode(', ', $posts);
                    $msgSedUrgent = "
                        Les postes <span class=\"bold\">$postsStr</span> sont urgents sur la commande <span class=\"bold\">$numeroCommande</span>.<br/>
					    Les avez-vous reçus dans cet arrivage ?
                    ";
                }
            }
        }
        else {
            $numeroCommande = null;
            $postNb = null;
        }
        $settingRepository = $this->entityManager->getRepository(Setting::class);

        return [
            'autoHide' => (!$askQuestion && !$isArrivalUrgent),
            'message' => ($isArrivalUrgent
                ? (!$askQuestion
                    ? 'Arrivage URGENT enregistré avec succès.'
                    : ($msgSedUrgent ?? ''))
                : 'Arrivage enregistré avec succès.'),
            'iconType' => $isArrivalUrgent ? 'warning' : 'success',
            'modalKey' => 'emergency',
            'modalType' => ($askQuestion && $isArrivalUrgent) ? 'yes-no-question' : 'info',
            'autoPrint' => !$settingRepository->getOneParamByLabel(Setting::REDIRECT_AFTER_NEW_ARRIVAL),
            'emergencyAlert' => $isArrivalUrgent,
            'numeroCommande' => $numeroCommande,
            'postNb' => $postNb,
            'arrivalId' => $arrivage->getId() ?: $arrivage->getNumeroArrivage()
        ];
    }

    public function createSupplierEmergencyAlert(Arrivage $arrival): ?array {
        $supplier = $arrival->getFournisseur();
        $supplierName = $supplier?->getNom();
        $isArrivalUrgent = ($supplier && $supplier->isUrgent() && $supplierName);
        $settingRepository = $this->entityManager->getRepository(Setting::class);
        return $isArrivalUrgent
            ? [
                'autoHide' => false,
                'message' => "Attention, les unités logistiques $supplierName doivent être traitées en urgence",
                'iconType' => 'warning',
                'modalType' => 'info',
                'autoPrint' => !$settingRepository->getOneParamByLabel(Setting::REDIRECT_AFTER_NEW_ARRIVAL),
                'arrivalId' => $arrival->getId() ?: $arrival->getNumeroArrivage()
            ]
            : null;
    }

    public function processEmergenciesOnArrival(EntityManager $entityManager, Arrivage $arrival): array
    {
        $numeroCommandeList = $arrival->getNumeroCommandeList();
        $alertConfigs = [];
        $isSEDCurrentClient =
            $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED)
            || $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_NS);

        $allMatchingEmergencies = [];

        if (!empty($numeroCommandeList)) {
            foreach ($numeroCommandeList as $numeroCommande) {
                $urgencesMatching = $this->urgenceService->matchingEmergencies(
                    $arrival,
                    $numeroCommande,
                    null,
                    $isSEDCurrentClient
                );

                if (!empty($urgencesMatching)) {
                    if (!$isSEDCurrentClient) {
                        $this->setArrivalUrgent($entityManager, $arrival, $urgencesMatching);
                        array_push($allMatchingEmergencies, ...$urgencesMatching);
                    } else {
                        $currentAlertConfig = array_map(function (Urgence $urgence) use ($arrival, $isSEDCurrentClient) {
                            return $this->createArrivalAlertConfig(
                                $arrival,
                                $isSEDCurrentClient,
                                [$urgence]
                            );
                        }, $urgencesMatching);
                        array_push($alertConfigs, ...$currentAlertConfig);
                    }
                }
            }
        }

        if (!$isSEDCurrentClient
            && $arrival->getIsUrgent()
            && !empty($allMatchingEmergencies)) {
            $alertConfigs[] = $this->createArrivalAlertConfig(
                $arrival,
                false,
                $allMatchingEmergencies
            );
        }

        if (empty($alertConfigs)) {
            $alertConfigs[] = $this->createArrivalAlertConfig($arrival, $isSEDCurrentClient);
        }

        return $alertConfigs;
    }

    public function createHeaderDetailsConfig(Arrivage $arrivage): array {
        $fieldsParamRepository = $this->entityManager->getRepository(FieldsParam::class);
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);

        $provider = $arrivage->getFournisseur();
        $carrier = $arrivage->getTransporteur();
        $driver = $arrivage->getChauffeur();
        $numeroCommandeList = $arrivage->getNumeroCommandeList();
        $status = $arrivage->getStatut();
        $type = $arrivage->getType();
        $destinataire = $arrivage->getDestinataire();
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
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Type'),
                'value' => $this->formatService->type($type, '-'),
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Statut'),
                'value' => $status ? $this->stringService->mbUcfirst($this->formatService->status($status)) : '',
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Fournisseur'),
                'value' => $provider ? $provider->getNom() : '',
                'show' => ['fieldName' => 'fournisseur'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Emplacement de dépose'),
                'value' => $dropLocation ? $dropLocation->getLabel() : '',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_DROP_LOCATION_ARRIVAGE ],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Transporteur'),
                'value' => $carrier ? $carrier->getLabel() : '',
                'show' => ['fieldName' => 'transporteur'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Chauffeur'),
                'value' => $driver ? $driver->getNom() : '',
                'show' => ['fieldName' => 'chauffeur'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'N°Arrivage camion'),
                'value' => count($truckArrivalLines) > 0 ?
                    '<a href="/arrivage-camion/voir/'. $truckArrivalLines->first()->getTruckArrival()->getId() . '" title="Détail Arrivage Camion">' . $truckArrivalLines->first()->getTruckArrival()->getNumber() . '</a>' : '',
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'N° tracking transporteur'),
                'value' => $numeroTrackingArray ?: $numeroTrackingOld,
                'show' => ['fieldName' => 'noTracking'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'N° commande / BL'),
                'value' => !empty($numeroCommandeList) ? implode(', ', $numeroCommandeList) : '',
                'show' => ['fieldName' => 'numeroCommandeList'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Destinataire'),
                'value' => $destinataire ? $destinataire->getUsername() : '',
                'show' => ['fieldName' => 'destinataire'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Acheteur(s)'),
                'value' => $buyers->count() > 0 ? implode(', ', $buyers->map(fn (Utilisateur $buyer) => $buyer->getUsername())->toArray()) : '',
                'show' => ['fieldName' => 'acheteurs'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Numéro de projet'),
                'value' => $arrivage->getProjectNumber(),
                'show' => ['fieldName' => 'projectNumber'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Business unit'),
                'value' => $arrivage->getBusinessUnit(),
                'show' => ['fieldName' => 'businessUnit'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Douane'),
                'value' => $this->formatService->bool($arrivage->getCustoms()),
                'show' => ['fieldName' => 'customs'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Congelé'),
                'value' => $this->formatService->bool($arrivage->getFrozen()),
                'show' => ['fieldName' => 'frozen'],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'N° d\'arrivage camion'),
                'value' => $arrivage->getTruckArrivalLines(),
                'show' => ['fieldName' => 'truckArrivalNumber'],
                'isRaw' => true
            ],
        ];

        $configFiltered =  $this->fieldsParamService->filterHeaderConfig($config, FieldsParam::ENTITY_CODE_ARRIVAGE);

        return array_merge(
            $configFiltered,
            $freeFieldArray,
            $this->fieldsParamService->isFieldRequired($fieldsParam, FieldsParam::FIELD_CODE_COMMENTAIRE_ARRIVAGE, 'displayedCreate')
            || $this->fieldsParamService->isFieldRequired($fieldsParam, FieldsParam::FIELD_CODE_COMMENTAIRE_ARRIVAGE, 'displayedEdit')
                ? [[
                'label' => $this->translation->translate('Général', null, 'Modale', 'Commentaire'),
                'value' => $comment ?: '',
                'isRaw' => true,
                'colClass' => 'col-sm-6 col-12',
                'isScrollable' => true,
                'isNeededNotEmpty' => true
            ]]
                : [],
                $this->fieldsParamService->isFieldRequired($fieldsParam, FieldsParam::FIELD_CODE_PJ_ARRIVAGE, 'displayedCreate')
                || $this->fieldsParamService->isFieldRequired($fieldsParam, FieldsParam::FIELD_CODE_PJ_ARRIVAGE, 'displayedEdit')
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
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);

        $columnsVisible = $currentUser->getVisibleColumns()['arrival'];
        $freeFields = $champLibreRepository->findByCategoryTypeAndCategoryCL(CategoryType::ARRIVAGE, CategorieCL::ARRIVAGE);

        $columns = [
            ['name' => 'packsInDispatch', 'alwaysVisible' => true, 'orderable' => false, 'class' => 'noVis'],
            ['title' => $this->translation->translate('Général', null, 'Zone liste', 'Date de création'), 'name' => 'creationDate', 'type' => ($dispatchMode ? 'customDate' : '')],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Divers', 'N° d\'arrivage'), 'name' => 'arrivalNumber'],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'N° d\'arrivage camion'), 'name' => 'truckArrivalNumber'],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Divers', 'Poids total (kg)'), 'name' => 'totalWeight'],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Transporteur'), 'name' => 'carrier'],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Chauffeur'), 'name' => 'driver'],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'N° tracking transporteur'), 'name' => 'trackingCarrierNumber'],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'N° commande / BL'), 'name' => 'orderNumber'],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Type'), 'name' => 'type'],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Fournisseur'), 'name' => 'provider'],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Destinataire'),'name' => 'receiver'],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Acheteur(s)'), 'name' => 'buyers'],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Divers', 'Nombre d\'UL'), 'name' => 'nbUm'],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Douane'), 'name' => 'customs'],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Congelé'), 'name' => 'frozen'],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Statut'), 'name' => 'status'],
            ['title' => $this->translation->translate('Traçabilité', 'Général', 'Utilisateur'), 'name' => 'user'],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Divers', 'Urgent'), 'name' => 'emergency'],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Numéro de projet'), 'name' => 'projectNumber'],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Business unit'), 'name' => 'businessUnit'],
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

        $arrivalFieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);

        if ($this->fieldsParamService->isFieldRequired($arrivalFieldsParam, FieldsParam::FIELD_CODE_DROP_LOCATION_ARRIVAGE, 'displayedCreate')
            || $this->fieldsParamService->isFieldRequired($arrivalFieldsParam, FieldsParam::FIELD_CODE_DROP_LOCATION_ARRIVAGE, 'displayedEdit')) {
            $columns[] = ['title' =>  $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Emplacement de dépose'), 'name' => 'dropLocation'];
        }

        return $this->visibleColumnService->getArrayConfig($columns, $freeFields, $columnsVisible);
    }

    public function getLocationForTracking(EntityManagerInterface $entityManager,
                                           Arrivage $arrivage): ?Emplacement {

        $settingRepository = $entityManager->getRepository(Setting::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        if($arrivage->getCustoms() && $customsArrivalsLocation = $settingRepository->getOneParamByLabel(Setting::DROP_OFF_LOCATION_IF_CUSTOMS)) {
            $location = $emplacementRepository->find($customsArrivalsLocation);
        }
        else if($arrivage->getIsUrgent() && $emergenciesArrivalsLocation = $settingRepository->getOneParamByLabel(Setting::DROP_OFF_LOCATION_IF_EMERGENCY)) {
            $location = $emplacementRepository->find($emergenciesArrivalsLocation);
        }
        else if (
            (
                $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED)
                || $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_NS)
            ) && $arrivage->getDestinataire()) {
            $location = $emplacementRepository->findOneBy(['label' => SpecificService::ARRIVAGE_SPECIFIQUE_SED_MVT_DEPOSE]);
        } else if ($arrivage->getDropLocation()) {
            $location = $arrivage->getDropLocation();
        } else if($defaultArrivalsLocation = $settingRepository->getOneParamByLabel(Setting::MVT_DEPOSE_DESTINATION)) {
            $location = $emplacementRepository->find($defaultArrivalsLocation);
        }
        else {
            $location = null;
        }

        return $location;
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
            $receiver = $arrivage->getDestinataire();
            $pack->setIsDeliveryDone(true);
            if ($receiver) {
                $this->mailerService->sendMail(
                    ['Traçabilité', 'Général', 'FOLLOW GT // Dépose effectuée', false],
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
                    $receiver
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
                    FieldsParam::FIELD_CODE_ARRIVAL_NUMBER           => $arrival->getNumeroArrivage(),
                    FieldsParam::FIELD_CODE_ARRIVAL_TOTAL_WEIGHT     => $packsTotalWeight[$arrival->getId()] ?? '',
                    FieldsParam::FIELD_CODE_ARRIVAL_TYPE             => $this->formatService->type($arrival->getType()),
                    FieldsParam::FIELD_CODE_ARRIVAL_STATUS           => $this->formatService->status($arrival->getStatut()),
                    FieldsParam::FIELD_CODE_ARRIVAL_DATE             => $this->formatService->datetime($arrival->getDate(), "", false, $this->userService->getUser()),
                    FieldsParam::FIELD_CODE_ARRIVAL_CREATOR          => $this->formatService->user($arrival->getUtilisateur()),
                    FieldsParam::FIELD_CODE_BUYERS_ARRIVAGE          => $this->formatService->users($arrival->getAcheteurs()),
                    FieldsParam::FIELD_CODE_BUSINESS_UNIT            => $arrival->getBusinessUnit() ?? '',
                    FieldsParam::FIELD_CODE_CHAUFFEUR_ARRIVAGE       => $arrival->getChauffeur()?->getPrenomNom() ?: '',
                    FieldsParam::FIELD_CODE_COMMENTAIRE_ARRIVAGE     => strip_tags($arrival->getCommentaire() ?? ''),
                    FieldsParam::FIELD_CODE_FROZEN_ARRIVAGE          => $this->formatService->bool($arrival->getFrozen()),
                    FieldsParam::FIELD_CODE_TARGET_ARRIVAGE          => $this->formatService->user($arrival->getDestinataire()),
                    FieldsParam::FIELD_CODE_CUSTOMS_ARRIVAGE         => $this->formatService->bool($arrival->getCustoms()),
                    FieldsParam::FIELD_CODE_DROP_LOCATION_ARRIVAGE   => $this->formatService->location($arrival->getDropLocation()),
                    FieldsParam::FIELD_CODE_PROVIDER_ARRIVAGE        => $this->formatService->supplier($arrival->getFournisseur()),
                    FieldsParam::FIELD_CODE_NUM_COMMANDE_ARRIVAGE    => $arrival->getNumeroCommandeList() ? implode(",", $arrival->getNumeroCommandeList()) : '',
                    FieldsParam::FIELD_CODE_PROJECT_NUMBER           => $arrival->getProjectNumber() ?: '',
                    FieldsParam::FIELD_CODE_NUMERO_TRACKING_ARRIVAGE => $arrival->getNoTracking() ?: '',
                    FieldsParam::FIELD_CODE_CARRIER_ARRIVAGE         => $arrival->getTransporteur()?->getLabel() ?: '',
                    default                                          => throw new \Exception("Invalid column name $column")
                };
            }
        }
        $this->CSVExportService->putLine($output, $line);
    }

    public function getArrivalExportableColumns(EntityManagerInterface $entityManager): array {
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $freeFieldsRepository = $entityManager->getRepository(FreeField::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $arrivalFields = $fieldsParamRepository->getByEntityForExport(FieldsParam::ENTITY_CODE_ARRIVAGE);
        $freeFields = $freeFieldsRepository->findByFreeFieldCategoryLabels([CategorieCL::ARRIVAGE]);
        $natures = $natureRepository->findBy([], ['id' => Criteria::ASC]);

        $userLanguage = $this->userService->getUser()?->getLanguage() ?: $this->languageService->getDefaultSlug();
        $defaultLanguage = $this->languageService->getDefaultSlug();

        return Stream::from(
            [
                ["code" => FieldsParam::FIELD_CODE_ARRIVAL_NUMBER, "label" => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Divers', 'N° d\'arrivage', false)],
                ["code" => FieldsParam::FIELD_CODE_ARRIVAL_TOTAL_WEIGHT, "label" => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Divers', 'Poids total (kg)', false)],
                ["code" => FieldsParam::FIELD_CODE_ARRIVAL_TYPE, "label" => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Type', false)],
                ["code" => FieldsParam::FIELD_CODE_ARRIVAL_STATUS, "label" => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Statut', false)],
                ["code" => FieldsParam::FIELD_CODE_ARRIVAL_DATE, "label" => $this->translation->translate('Général', null, 'Zone liste', 'Date de création', false)],
                ["code" => FieldsParam::FIELD_CODE_ARRIVAL_CREATOR, "label" => $this->translation->translate('Traçabilité', 'Général', 'Utilisateur', false)],
            ],
            Stream::from($arrivalFields)
                ->filter(fn(FieldsParam $field) => !in_array($field->getFieldCode(), [FieldsParam::FIELD_CODE_PJ_ARRIVAGE, FieldsParam::FIELD_CODE_PRINT_ARRIVAGE, FieldsParam::FIELD_CODE_PROJECT]))
                ->map(fn(FieldsParam $field) => [
                    "code" => $field->getFieldCode(),
                    "label" => match($field->getFieldCode()) {
                        FieldsParam::FIELD_CODE_BUYERS_ARRIVAGE => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Acheteur(s)', false),
                        FieldsParam::FIELD_CODE_CHAUFFEUR_ARRIVAGE => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Chauffeur', false),
                        FieldsParam::FIELD_CODE_COMMENTAIRE_ARRIVAGE => $this->translation->translate('Général', null, 'Modale', 'Commentaire', false),
                        FieldsParam::FIELD_CODE_CARRIER_ARRIVAGE => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Transporteur', false),
                        FieldsParam::FIELD_CODE_PROVIDER_ARRIVAGE => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Fournisseur', false),
                        FieldsParam::FIELD_CODE_TARGET_ARRIVAGE => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Destinataire', false),
                        FieldsParam::FIELD_CODE_NUM_COMMANDE_ARRIVAGE => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'N° commande / BL', false),
                        FieldsParam::FIELD_CODE_NUMERO_TRACKING_ARRIVAGE => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'N° tracking transporteur', false),
                        FieldsParam::FIELD_CODE_CUSTOMS_ARRIVAGE => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Douane', false),
                        FieldsParam::FIELD_CODE_FROZEN_ARRIVAGE => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Congelé', false),
                        FieldsParam::FIELD_CODE_PROJECT_NUMBER => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Numéro de projet', false),
                        FieldsParam::FIELD_CODE_BUSINESS_UNIT => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Business unit', false),
                        FieldsParam::FIELD_CODE_DROP_LOCATION_ARRIVAGE => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Emplacement de dépose', false),
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


    public function generateNewForm(EntityManagerInterface $entityManager, array $fromTruckArrivalOptions = []): array
    {
        if ($this->userService->hasRightFunction(Menu::TRACA, Action::CREATE)) {
            $settingRepository = $entityManager->getRepository(Setting::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $natureRepository = $entityManager->getRepository(Nature::class);
            $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
            $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $transporteurRepository = $entityManager->getRepository(Transporteur::class);
            $locationRepository = $entityManager->getRepository(Emplacement::class);

            $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);

            $statuses = Stream::from($statutRepository->findStatusByType(CategorieStatut::ARRIVAGE))
                ->map(fn(Statut $statut) => [
                    'id' => $statut->getId(),
                    'type' => $statut->getType(),
                    'nom' => $this->formatService->status($statut),
                ])
                ->toArray();
            $defaultLocation = $settingRepository->getOneParamByLabel(Setting::MVT_DEPOSE_DESTINATION);
            $defaultLocation = $defaultLocation ? $emplacementRepository->find($defaultLocation) : null;

            $natures = Stream::from($natureRepository->findByAllowedForms([Nature::ARRIVAL_CODE]))
                ->map(fn(Nature $nature) => [
                    'id' => $nature->getId(),
                    'label' => $this->formatService->nature($nature),
                    'defaultQuantity' => $nature->getDefaultQuantity(),
                ])
                ->toArray();

            $keptFields = $this->keptFieldService->getAll(FieldsParam::ENTITY_CODE_ARRIVAGE);

            if(isset($keptFields[FieldsParam::FIELD_CODE_DROP_LOCATION_ARRIVAGE])) {
                $keptFields[FieldsParam::FIELD_CODE_DROP_LOCATION_ARRIVAGE] = $locationRepository->find($keptFields[FieldsParam::FIELD_CODE_DROP_LOCATION_ARRIVAGE]);
            }

            $html = $this->templating->render("arrivage/modalNewArrivage.html.twig", [
                "keptFields" => $keptFields,
                "typesArrival" => $typeRepository->findByCategoryLabels([CategoryType::ARRIVAGE]),
                "statuses" => $statuses,
                "users" => $utilisateurRepository->findBy(['status' => true], ['username' => 'ASC']),
                "fournisseurs" => $fournisseurRepository->findBy([], ['nom' => 'ASC']),
                "natures" => $natures,
                "carriers" => $transporteurRepository->findAllSorted(),
                "chauffeurs" => $chauffeurRepository->findAllSorted(),
                "fieldsParam" => $fieldsParam,
                "businessUnits" => $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_ARRIVAGE, FieldsParam::FIELD_CODE_BUSINESS_UNIT),
                "defaultLocation" => $defaultLocation,
                "defaultStatuses" => $statutRepository->getIdDefaultsByCategoryName(CategorieStatut::ARRIVAGE),
                "autoPrint" => $settingRepository->getOneParamByLabel(Setting::AUTO_PRINT_LU),
                "fromTruckArrivalOptions" => $fromTruckArrivalOptions,
            ]);
        }

        return [
            'html' => $html ?? "",
            'acheteurs' => $acheteursUsernames ?? []
        ];
    }
}
