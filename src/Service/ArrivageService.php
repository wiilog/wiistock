<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\FreeField;
use App\Entity\FieldsParam;
use App\Entity\FiltreSup;
use App\Entity\Pack;
use App\Entity\Setting;
use App\Entity\TrackingMovement;
use App\Entity\Urgence;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use DateTime;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\TranslationService;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment;


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

    private ?array $freeFieldsConfig = null;

    public function getDataForDatatable(Request $request, ?int $userIdArrivalFilter)
    {
        $arrivalRepository = $this->entityManager->getRepository(Arrivage::class);
        $supFilterRepository = $this->entityManager->getRepository(FiltreSup::class);

        /** @var Utilisateur $currentUser */
        $currentUser = $this->security->getUser();
        $dispatchMode = $request->query->getBoolean('dispatchMode');

        $filters = $supFilterRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ARRIVAGE, $currentUser);
        $queryResult = $arrivalRepository->findByParamsAndFilters(
            $request->request,
            $filters,
            $this->visibleColumnService,
            [
                'userIdArrivalFilter' => $userIdArrivalFilter,
                'user' => $this->security->getUser(),
                'dispatchMode' => $dispatchMode
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
            'packsInDispatch' => $options['packsInDispatchCount'] > 0 ? "<td><i class='fas fa-exchange-alt mr-2' title='Colis acheminé(s)'></i></td>" : '',
            'arrivalNumber' => $arrival->getNumeroArrivage() ?? '',
            'carrier' => $arrival->getTransporteur() ? $arrival->getTransporteur()->getLabel() : '',
            'totalWeight' => $options['totalWeight'] ?? '',
            'driver' => $arrival->getChauffeur() ? $arrival->getChauffeur()->getPrenomNom() : '',
            'trackingCarrierNumber' => $arrival->getNoTracking() ?? '',
            'orderNumber' => implode(',', $arrival->getNumeroCommandeList()),
            'type' => $arrival->getType() ? $arrival->getType()->getLabel() : '',
            'nbUm' => $options['packsCount'] ?? '',
            'customs' => $arrival->getCustoms() ? 'oui' : 'non',
            'frozen' => $arrival->getFrozen() ? 'oui' : 'non',
            'provider' => $arrival->getFournisseur() ? $arrival->getFournisseur()->getNom() : '',
            'receiver' => $arrival->getDestinataire() ? $arrival->getDestinataire()->getUsername() : '',
            'buyers' => implode(', ', $acheteursUsernames),
            'status' => $arrival->getStatut() ? $this->formatService->status($arrival->getStatut()) : '',
            'creationDate' => $arrival->getDate() ? $arrival->getDate()->format($user->getDateFormat() ? $user->getDateFormat() . ' H:i:s' : 'd/m/Y H:i:s') : '',
            'user' => $arrival->getUtilisateur() ? $arrival->getUtilisateur()->getUsername() : '',
            'emergency' => $arrival->getIsUrgent() ? 'oui' : 'non',
            'projectNumber' => $arrival->getProjectNumber() ?? '',
            'businessUnit' => $arrival->getBusinessUnit() ?? '',
            'dropLocation' => FormatHelper::location($arrival->getDropLocation()),
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
            $row[$freeFieldName] = FormatHelper::freeField($freeFieldValue, $freeField, $this->security->getUser());
        }

        return $row;
    }

    public function sendArrivalEmails(Arrivage $arrival, array $emergencies = []): void {
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
            $title = 'Arrivage reçu : ' . $arrival->getNumeroArrivage() . ', le ' . $arrival->getDate()->format($user->getDateFormat() ? $user->getDateFormat() . ' H:i' : 'd/m/Y à H:i');

            $freeFields = $this->freeFieldService->getFilledFreeFieldArray(
                $this->entityManager,
                $arrival,
                ['type' => $arrival->getType()],
                $this->security->getUser()
            );

            $this->mailerService->sendMail(
                'FOLLOW GT // Arrivage' . ($isUrgentArrival ? ' urgent' : ''),
                $this->templating->render(
                    'mails/contents/mailArrivage.html.twig',
                    [
                        'title' => $title,
                        'arrival' => $arrival,
                        'emergencies' => $emergencies,
                        'isUrgentArrival' => $isUrgentArrival,
                        'freeFields' => $freeFields,
                        'urlSuffix' => $this->router->generate("arrivage_show", ["id" => $arrival->getId()])
                    ]
                ),
                $finalRecipients
            );
        }
    }

    public function setArrivalUrgent(Arrivage $arrivage, array $emergencies): void
    {
        if (!empty($emergencies)) {
            $arrivage->setIsUrgent(true);
            foreach ($emergencies as $emergency) {
                $emergency->setLastArrival($arrivage);
            }
            $this->sendArrivalEmails($arrivage, $emergencies);
        }
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
                'message' => "Attention, les colis $supplierName doivent être traités en urgence",
                'iconType' => 'warning',
                'modalType' => 'info',
                'autoPrint' => !$settingRepository->getOneParamByLabel(Setting::REDIRECT_AFTER_NEW_ARRIVAL),
                'arrivalId' => $arrival->getId() ?: $arrival->getNumeroArrivage()
            ]
            : null;
    }

    public function processEmergenciesOnArrival(Arrivage $arrival): array
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
                        $this->setArrivalUrgent($arrival, $urgencesMatching);
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

        $freeFieldArray = $this->freeFieldService->getFilledFreeFieldArray(
            $this->entityManager,
            $arrivage,
            ['type' => $arrivage->getType()],
            $this->security->getUser()
        );

        $config = [
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Type'),
                'value' => $type ? $this->stringService->mbUcfirst($type->getLabel()) : '',
                'isRaw' => true
            ],
            [
                'label' => 'Statut',
                'value' => $status ? $this->stringService->mbUcfirst($this->formatService->status($status)) : ''
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Fournisseur'),
                'value' => $provider ? $provider->getNom() : '',
                'show' => [ 'fieldName' => 'fournisseur' ],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Emplacement de dépose'),
                'value' => $dropLocation ? $dropLocation->getLabel() : '',
                'show' => [ 'fieldName' => FieldsParam::FIELD_CODE_DROP_LOCATION_ARRIVAGE ],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Transporteur'),
                'value' => $carrier ? $carrier->getLabel() : '',
                'show' => [ 'fieldName' => 'transporteur' ],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Chauffeur'),
                'value' => $driver ? $driver->getNom() : '',
                'show' => [ 'fieldName' => 'chauffeur' ],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'N° tracking transporteur'),
                'value' => $arrivage->getNoTracking(),
                'show' => [ 'fieldName' => 'noTracking' ],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'N° commande / BL'),
                'value' => !empty($numeroCommandeList) ? implode(', ', $numeroCommandeList) : '',
                'show' => [ 'fieldName' => 'numeroCommandeList' ],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Destinataire'),
                'value' => $destinataire ? $destinataire->getUsername() : '',
                'show' => [ 'fieldName' => 'destinataire' ],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Acheteur(s)'),
                'value' => $buyers->count() > 0 ? implode(', ', $buyers->map(function (Utilisateur $buyer) {return $buyer->getUsername();})->toArray()) : '',
                'show' => [ 'fieldName' => 'acheteurs' ],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Numéro de projet'),
                'value' => $arrivage->getProjectNumber(),
                'show' => [ 'fieldName' => 'projectNumber' ],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Business unit'),
                'value' => $arrivage->getBusinessUnit(),
                'show' => [ 'fieldName' => 'businessUnit' ],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Douane'),
                'value' => $arrivage->getCustoms() ? 'oui' : 'non',
                'show' => [ 'fieldName' => 'customs' ],
                'isRaw' => true
            ],
            [
                'label' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Congelé'),
                'value' => $arrivage->getFrozen() ? 'oui' : 'non',
                'show' => [ 'fieldName' => 'frozen' ],
                'isRaw' => true
            ],
        ];

        $configFiltered =  $this->fieldsParamService->filterHeaderConfig($config, FieldsParam::ENTITY_CODE_ARRIVAGE);

        return array_merge(
            $configFiltered,
            $freeFieldArray,
            $this->fieldsParamService->isFieldRequired($fieldsParam, 'commentaire', 'displayedCreate')
            || $this->fieldsParamService->isFieldRequired($fieldsParam, 'commentaire', 'displayedEdit')
                ? [[
                'label' => 'Commentaire',
                'value' => $comment ?: '',
                'isRaw' => true,
                'colClass' => 'col-sm-6 col-12',
                'isScrollable' => true,
                'isNeededNotEmpty' => true
            ]]
                : [],
                $this->fieldsParamService->isFieldRequired($fieldsParam, 'pj', 'displayedCreate')
                || $this->fieldsParamService->isFieldRequired($fieldsParam, 'pj', 'displayedEdit')
                ? [[
                    'label' => 'Pièces jointes',
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
        $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);

        $columnsVisible = $currentUser->getVisibleColumns()['arrival'];
        $categorieCL = $categorieCLRepository->findOneBy(['label' => CategorieCL::ARRIVAGE]);
        $freeFields = $champLibreRepository->getByCategoryTypeAndCategoryCL(CategoryType::ARRIVAGE, $categorieCL);

        $columns = [
            ['name' => 'packsInDispatch', 'alwaysVisible' => true, 'orderable' => false, 'class' => 'noVis'],
            ['title' => $this->translation->translate('Traçabilité', 'Général', 'Date de création'), 'name' => 'creationDate', 'type' => ($dispatchMode ? 'customDate' : '')],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Divers', 'N° d\'arrivage'), 'name' => 'arrivalNumber'],
            ['title' => $this->translation->translate('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', 'Poids (kg)'), 'name' => 'totalWeight'],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Transporteur'), 'name' => 'carrier'],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Chauffeur'), 'name' => 'driver'],
            ['title' => $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'N° tracking transporteur'), 'name' => 'trackingCarrierNumber'],
            ['title' => $this->translation->translate('Demande', 'Acheminements', 'Champs fixes', 'N° commande'), 'name' => 'orderNumber'],
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
            $dispatchCheckboxLine = ['title' => "<input type='checkbox' class='checkbox check-all'>", 'name' => 'actions', 'alwaysVisible' => true, 'orderable' => false, 'class' => 'noVis'];
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

    public function putArrivalLine(Utilisateur $user,
                                   $handle,
                                   CSVExportService $csvService,
                                   array $freeFieldsConfig,
                                   array $arrival,
                                   array $buyersByArrival,
                                   array $natureLabels,
                                   array $packs,
                                   array $fieldsParam,
                                   array $packsTotalWeight)
    {
        $id = (int)$arrival['id'];
        $line = [
            $arrival['numeroArrivage'] ?: '',
            $packsTotalWeight[$id] ?? '',
            $arrival['recipientUsername'] ?: '',
            $arrival['fournisseurName'] ?: '',
            $arrival['transporteurLabel'] ?: '',
            (!empty($arrival['chauffeurFirstname']) && !empty($arrival['chauffeurSurname']))
                ? $arrival['chauffeurFirstname'] . ' ' . $arrival['chauffeurSurname']
                : ($arrival['chauffeurFirstname'] ?: $arrival['chauffeurSurname'] ?: ''),
            $arrival['noTracking'] ?: '',
            !empty($arrival['numeroCommandeList']) ? implode(' / ', $arrival['numeroCommandeList']) : '',
            $arrival['type'] ?: '',
            $buyersByArrival[$id] ?? '',
            $arrival['customs'] ? 'oui' : 'non',
            $arrival['frozen'] ? 'oui' : 'non',
            $arrival['statusName'] ?: '',
            $arrival['commentaire'] ? strip_tags($arrival['commentaire']) : '',
            $arrival['date'] ? $arrival['date']->format($user->getDateFormat() ? $user->getDateFormat() . ' H:i:s' : 'd/m/Y H:i:s') : '',
            $arrival['userUsername'] ?: '',
            $arrival['projectNumber'] ?: '',
            $arrival['businessUnit'] ?: '',
        ];
        if ($this->fieldsParamService->isFieldRequired($fieldsParam, FieldsParam::FIELD_CODE_DROP_LOCATION_ARRIVAGE, 'displayedCreate')
            || $this->fieldsParamService->isFieldRequired($fieldsParam, FieldsParam::FIELD_CODE_DROP_LOCATION_ARRIVAGE, 'displayedEdit')) {
            $line[] = $arrival['dropLocation'] ?: '';
        }

        foreach($natureLabels as $natureLabel) {
            $line[] = $packs[$id][$natureLabel] ?? 0;
        }

        foreach($freeFieldsConfig["freeFields"] as $freeFieldId => $freeField) {
            $line[] = FormatHelper::freeField($arrival["freeFields"][$freeFieldId] ?? '', $freeField, $this->security->getUser());
        }

        $csvService->putLine($handle, $line);
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
                    'FOLLOW GT // Dépose effectuée',
                    $this->templating->render(
                        'mails/contents/mail-pack-delivery-done.html.twig',
                        [
                            'title' => 'Votre colis a été livré.',
                            'orderNumber' => implode(', ', $arrivage->getNumeroCommandeList()),
                            'colis' => FormatHelper::pack($pack),
                            'emplacement' => $location,
                            'fournisseur' => FormatHelper::supplier($arrivage->getFournisseur()),
                            'date' => $date,
                            'operateur' => FormatHelper::user($user),
                            'pjs' => $arrivage->getAttachments()
                        ]
                    ),
                    $receiver
                );
            }
        }
    }
}
