<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\FreeField;
use App\Entity\FieldsParam;
use App\Entity\FiltreSup;
use App\Entity\Nature;
use App\Entity\Pack;
use App\Entity\Setting;
use App\Entity\TrackingMovement;
use App\Entity\Urgence;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use WiiCommon\Helper\Stream;


class ArrivageService {

    /** @Required */
    public Environment $templating;

    /** @Required */
    public RouterInterface $router;

    /** @Required */
    public Security $security;

    /** @Required */
    public EntityManagerInterface $entityManager;

    /** @Required */
    public MailerService $mailerService;

    /** @Required */
    public UrgenceService $urgenceService;

    /** @Required */
    public SpecificService $specificService;

    /** @Required */
    public StringService $stringService;

    /** @Required */
    public TranslatorInterface $translator;

    /** @Required */
    public FreeFieldService $freeFieldService;

    /** @Required */
    public FieldsParamService $fieldsParamService;

    /** @Required */
    public VisibleColumnService $visibleColumnService;

    private ?array $freeFieldsConfig = null;

    #[Required]
    public CSVExportService $CSVExportService;

    private ?array $exportCache = null;

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
            'status' => $arrival->getStatut() ? $arrival->getStatut()->getNom() : '',
            'creationDate' => $arrival->getDate() ? $arrival->getDate()->format('d/m/Y H:i:s') : '',
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
            $row[$freeFieldName] = FormatHelper::freeField($freeFieldValue, $freeField);
        }

        return $row;
    }

    public function sendArrivalEmails(Arrivage $arrival, array $emergencies = []): void {
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
            $title = 'Arrivage reçu : ' . $arrival->getNumeroArrivage() . ', le ' . $arrival->getDate()->format('d/m/Y à H:i');

            $freeFields = $this->freeFieldService->getFilledFreeFieldArray(
                $this->entityManager,
                $arrival,
                ['type' => $arrival->getType()]
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
            ['type' => $arrivage->getType()]
        );

        $config = [
            [
                'label' => 'Type',
                'value' => $type ? $this->stringService->mbUcfirst($type->getLabel()) : ''
            ],
            [
                'label' => 'Statut',
                'value' => $status ? $this->stringService->mbUcfirst($status->getNom()) : ''
            ],
            [
                'label' => 'Fournisseur',
                'value' => $provider ? $provider->getNom() : '',
                'show' => [ 'fieldName' => 'fournisseur' ]
            ],
            [
                'label' => 'Emplacement de dépose',
                'value' => $dropLocation ? $dropLocation->getLabel() : '',
                'show' => [ 'fieldName' => FieldsParam::FIELD_CODE_DROP_LOCATION_ARRIVAGE ]
            ],
            [
                'label' => 'Transporteur',
                'value' => $carrier ? $carrier->getLabel() : '',
                'show' => [ 'fieldName' => 'transporteur' ]
            ],
            [
                'label' => 'Chauffeur',
                'value' => $driver ? $driver->getNom() : '',
                'show' => [ 'fieldName' => 'chauffeur' ]
            ],
            [
                'label' => 'N° tracking transporteur',
                'value' => $arrivage->getNoTracking(),
                'show' => [ 'fieldName' => 'noTracking' ]
            ],
            [
                'label' => $this->translator->trans('arrivage.Numéro de commande'),
                'title' => 'Numéro de commande',
                'value' => !empty($numeroCommandeList) ? implode(', ', $numeroCommandeList) : '',
                'show' => [ 'fieldName' => 'numeroCommandeList' ]
            ],
            [
                'label' => $this->translator->trans('arrivage.destinataire'),
                'title' => 'destinataire',
                'value' => $destinataire ? $destinataire->getUsername() : '',
                'show' => [ 'fieldName' => 'destinataire' ]
            ],
            [
                'label' => $this->translator->trans('arrivage.acheteurs'),
                'title' => 'acheteurs',
                'value' => $buyers->count() > 0 ? implode(', ', $buyers->map(function (Utilisateur $buyer) {return $buyer->getUsername();})->toArray()) : '',
                'show' => [ 'fieldName' => 'acheteurs' ]
            ],
            [
                'label' => 'Numéro de projet',
                'value' => $arrivage->getProjectNumber(),
                'show' => [ 'fieldName' => 'projectNumber' ]
            ],
            [
                'label' => $this->translator->trans('acheminement.Business unit'),
                'value' => $arrivage->getBusinessUnit(),
                'show' => [ 'fieldName' => 'businessUnit' ]
            ],
            [
                'label' => $this->translator->trans('arrivage.douane'),
                'title' => 'douane',
                'value' => $arrivage->getCustoms() ? 'oui' : 'non',
                'show' => [ 'fieldName' => 'customs' ]
            ],
            [
                'label' => $this->translator->trans('arrivage.congelé'),
                'title' => 'congelé',
                'value' => $arrivage->getFrozen() ? 'oui' : 'non',
                'show' => [ 'fieldName' => 'frozen' ]
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
            ['title' => 'Date de création', 'name' => 'creationDate', 'type' => ($dispatchMode ? 'customDate' : '')],
            ['title' => 'arrivage.n° d\'arrivage',  'name' => 'arrivalNumber', 'translated' => true],
            ['title' => 'Poids total (kg)', 'name' => 'totalWeight'],
            ['title' => 'Transporteur', 'name' => 'carrier'],
            ['title' => 'Chauffeur', 'name' => 'driver'],
            ['title' => 'N° tracking transporteur', 'name' => 'trackingCarrierNumber'],
            ['title' => 'arrivage.Numéro de commande', 'name' => 'orderNumber', 'translated' => true],
            ['title' => 'Type', 'name' => 'type'],
            ['title' => 'Fournisseur', 'name' => 'provider'],
            ['title' => 'arrivage.destinataire', 'name' => 'receiver', 'translated' => true],
            ['title' => 'arrivage.acheteurs', 'name' => 'buyers', 'translated' => true],
            ['title' => 'Nb um', 'name' => 'nbUm'],
            ['title' => 'Douane', 'name' => 'customs'],
            ['title' => 'Congelé', 'name' => 'frozen'],
            ['title' => 'Statut', 'name' => 'status'],
            ['title' => 'Utilisateur', 'name' => 'user'],
            ['title' => 'Urgent', 'name' => 'emergency'],
            ['title' => 'Numéro de projet', 'name' => 'projectNumber'],
            ['title' => 'acheminement.Business unit', 'name' => 'businessUnit', 'translated' => true],
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
            $columns[] = ['title' => 'Emplacement de dépose', 'name' => 'dropLocation'];
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

    public function putArrivalLine($handle,
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
            $arrival['date'] ? $arrival['date']->format('d/m/Y H:i:s') : '',
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
            $line[] = FormatHelper::freeField($arrival["freeFields"][$freeFieldId] ?? '', $freeField);
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

    public function getHeaderForExport(EntityManagerInterface $entityManager,
                                       array $columnToExport): array
    {
        $exportableColumns = $this->getArrivalExportableColumns($entityManager);
        return Stream::from($columnToExport)
            ->filterMap(function(string $code) use ($exportableColumns) {
                $column = Stream::from($exportableColumns)
                    ->find(fn(array $config) => $config['code'] === $code);
                return $column['label'] ?? null;
            })
            ->toArray();
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

    public function putArrivalLineInUniqueExport($output,
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
                    ? FormatHelper::freeField($value, $freeField)
                    : $value;
            }
            else {
                $line[] = match ($column) {
                    FieldsParam::FIELD_CODE_ARRIVAL_NUMBER           => $arrival->getNumeroArrivage(),
                    FieldsParam::FIELD_CODE_ARRIVAL_TOTAL_WEIGHT     => $packsTotalWeight[$arrival->getId()] ?? '',
                    FieldsParam::FIELD_CODE_ARRIVAL_TYPE             => FormatHelper::type($arrival->getType()),
                    FieldsParam::FIELD_CODE_ARRIVAL_STATUS           => FormatHelper::status($arrival->getStatut()),
                    FieldsParam::FIELD_CODE_ARRIVAL_DATE             => FormatHelper::datetime($arrival->getDate()),
                    FieldsParam::FIELD_CODE_ARRIVAL_CREATOR          => FormatHelper::user($arrival->getUtilisateur()),
                    FieldsParam::FIELD_CODE_BUYERS_ARRIVAGE          => FormatHelper::users($arrival->getAcheteurs()),
                    FieldsParam::FIELD_CODE_BUSINESS_UNIT            => $arrival->getBusinessUnit() ?? '',
                    FieldsParam::FIELD_CODE_CHAUFFEUR_ARRIVAGE       => $arrival->getChauffeur()->getNom() ?? '',
                    FieldsParam::FIELD_CODE_COMMENTAIRE_ARRIVAGE     => $arrival->getCommentaire() ?? '',
                    FieldsParam::FIELD_CODE_FROZEN_ARRIVAGE          => FormatHelper::bool($arrival->getFrozen()),
                    FieldsParam::FIELD_CODE_TARGET_ARRIVAGE          => FormatHelper::user($arrival->getDestinataire()),
                    FieldsParam::FIELD_CODE_CUSTOMS_ARRIVAGE         => FormatHelper::bool($arrival->getCustoms()),
                    FieldsParam::FIELD_CODE_DROP_LOCATION_ARRIVAGE   => FormatHelper::location($arrival->getDropLocation()),
                    FieldsParam::FIELD_CODE_PROVIDER_ARRIVAGE        => FormatHelper::supplier($arrival->getFournisseur()),
                    FieldsParam::FIELD_CODE_NUM_COMMANDE_ARRIVAGE    => $arrival->getNumeroCommandeList() ? implode(",", $arrival->getNumeroCommandeList()) : '',
                    FieldsParam::FIELD_CODE_PROJECT_NUMBER           => $arrival->getProjectNumber() ?? '',
                    FieldsParam::FIELD_CODE_NUMERO_TRACKING_ARRIVAGE => $arrival->getNoTracking() ?? '',
                    FieldsParam::FIELD_CODE_CARRIER_ARRIVAGE         => $arrival->getTransporteur()->getLabel() ?? '',
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

        return Stream::from(
            [
                ["code" => FieldsParam::FIELD_CODE_ARRIVAL_NUMBER, "label" => FieldsParam::FIELD_LABEL_ARRIVAL_NUMBER],
                ["code" => FieldsParam::FIELD_CODE_ARRIVAL_TOTAL_WEIGHT, "label" => FieldsParam::FIELD_LABEL_ARRIVAL_TOTAL_WEIGHT],
                ["code" => FieldsParam::FIELD_CODE_ARRIVAL_TYPE, "label" => FieldsParam::FIELD_LABEL_ARRIVAL_TYPE],
                ["code" => FieldsParam::FIELD_CODE_ARRIVAL_STATUS, "label" => FieldsParam::FIELD_LABEL_ARRIVAL_STATUS],
                ["code" => FieldsParam::FIELD_CODE_ARRIVAL_DATE, "label" => FieldsParam::FIELD_LABEL_ARRIVAL_DATE],
                ["code" => FieldsParam::FIELD_CODE_ARRIVAL_CREATOR, "label" => FieldsParam::FIELD_LABEL_ARRIVAL_CREATOR],
            ],
            Stream::from($arrivalFields)
                ->filter(fn(FieldsParam $field) => !in_array($field->getFieldCode(), [FieldsParam::FIELD_CODE_PJ_ARRIVAGE, FieldsParam::FIELD_CODE_PRINT_ARRIVAGE]))
                ->map(fn(FieldsParam $field) => [
                    "code" => $field->getFieldCode(),
                    "label" => $field->getFieldLabel()
                ]),
            Stream::from($natures)
                ->map(fn(Nature $nature) => [
                    'code' => "nature_{$nature->getId()}",
                    'label' => $nature->getLabel()
                ]),
            Stream::from($freeFields)
                ->map(fn(FreeField $field) => [
                    "code" => "free_field_{$field->getId()}",
                    "label" => $field->getLabel(),
                ])
        )
            ->toArray();
    }
}
