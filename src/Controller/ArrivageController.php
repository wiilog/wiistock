<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\Attachment;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Chauffeur;
use App\Entity\Dispute;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\FreeField;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Pack;
use App\Entity\Project;
use App\Entity\Reception;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\TagTemplate;
use App\Entity\Transporteur;
use App\Entity\TruckArrival;
use App\Entity\TruckArrivalLine;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Service\ArrivageService;
use App\Service\AttachmentService;
use App\Service\CSVExportService;
use App\Service\DataExportService;
use App\Service\DisputeService;
use App\Service\FilterSupService;
use App\Service\FreeFieldService;
use App\Service\KeptFieldService;
use App\Service\LanguageService;
use App\Service\PackService;
use App\Service\PDFGeneratorService;
use App\Service\SpecificService;
use App\Service\TagTemplateService;
use App\Service\TrackingMovementService;
use App\Service\TranslationService;
use App\Service\UniqueNumberService;
use App\Service\UrgenceService;
use App\Service\UserService;
use App\Service\VisibleColumnService;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

/**
 * @Route("/arrivage")
 */
class ArrivageController extends AbstractController {

    #[Required]
    public UserService $userService;

    #[Required]
    public LanguageService $languageService;

    private ?string $defaultLanguageSlug = null;

    /**
     * @Route("/", name="arrivage_index")
     * @HasPermission({Menu::TRACA, Action::DISPLAY_ARRI})
     */
    public function index(Request $request,
                          EntityManagerInterface $entityManager,
                          TagTemplateService $tagTemplateService,
                          ArrivageService $arrivageService,
                          FilterSupService $filterSupService): Response {
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $truckArrivalRepository = $entityManager->getRepository(TruckArrival::class);

        $fromTruckArrivalOptions = [];
        if($request->query->has('truckArrivalId')){
            $truckArrival = $truckArrivalRepository->find($request->query->get('truckArrivalId'));
            $fromTruckArrivalOptions = [
                'carrier' => $truckArrival?->getCarrier()?->getId(),
                'driver' => $truckArrival?->getDriver()?->getId(),
            ];
        }
        $user = $this->getUser();

        $fields = $arrivageService->getColumnVisibleConfig($entityManager, $user);

        $paramGlobalRedirectAfterNewArrivage = $settingRepository->findOneBy(['label' => Setting::REDIRECT_AFTER_NEW_ARRIVAL]);

        $statuses = $statutRepository->findStatusByType(CategorieStatut::ARRIVAGE);
        $fieldsParam = $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_ARRIVAGE);

        $pageLength = $user->getPageLengthForArrivage() ?: 10;
        $request->request->add(['length' => $pageLength]);

        return $this->render('arrivage/index.html.twig', [
            "types" => $typeRepository->findByCategoryLabels([CategoryType::ARRIVAGE]),
            'disputeTypes' => $typeRepository->findByCategoryLabels([CategoryType::DISPUTE]),
            'statuts' => $statuses,
            "fieldsParam" => $fieldsParam,
            "carriers" => $transporteurRepository->findAllSorted(),
            'redirect' => $paramGlobalRedirectAfterNewArrivage ? $paramGlobalRedirectAfterNewArrivage->getValue() : true,
            'champsLibres' => $champLibreRepository->findByCategoryTypeLabels([CategoryType::ARRIVAGE]),
            'pageLengthForArrivage' => $pageLength,
            "fields" => $fields,
            "initial_arrivals" => $this->api($request, $arrivageService)->getContent(),
            "initial_form" => $arrivageService->generateNewForm($entityManager, $fromTruckArrivalOptions),
            "tag_templates" => $tagTemplateService->serializeTagTemplates($entityManager, CategoryType::ARRIVAGE),
            "initial_visible_columns" => $this->apiColumns($arrivageService, $entityManager, $request)->getContent(),
            "initial_filters" => json_encode($filterSupService->getFilters($entityManager, FiltreSup::PAGE_LU_ARRIVAL)),
            "openNewModal" => count($fromTruckArrivalOptions) > 0,
        ]);
    }

    /**
     * @Route("/api", name="arrivage_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::DISPLAY_ARRI}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request, ArrivageService $arrivageService): Response {
        if($this->userService->hasRightFunction(Menu::TRACA, Action::LIST_ALL) || !$this->getUser()) {
            $userId = null;
        } else {
            $userId = $this->getUser()->getId();
        }

        return $this->json($arrivageService->getDataForDatatable($request, $userId));
    }

    /**
     * @Route("/creer", name="arrivage_new", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(Request                $request,
                        EntityManagerInterface $entityManager,
                        AttachmentService      $attachmentService,
                        ArrivageService        $arrivalService,
                        FreeFieldService       $champLibreService,
                        PackService            $packService,
                        KeptFieldService       $keptFieldService,
                        TranslationService     $translation): Response
    {
        $data = $request->request->all();
        $settingRepository = $entityManager->getRepository(Setting::class);
        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $truckArrivalLineRepository = $entityManager->getRepository(TruckArrivalLine::class);
        $sendMail = $settingRepository->getOneParamByLabel(Setting::SEND_MAIL_AFTER_NEW_ARRIVAL);
        $useTruckArrivals = $settingRepository->getOneParamByLabel(Setting::USE_TRUCK_ARRIVALS);

        $date = new DateTime('now');
        $counter = $arrivageRepository->countByDate($date) + 1;
        $suffix = $counter < 10 ? ("0" . $counter) : $counter;
        $numeroArrivage = $date->format('ymdHis') . '-' . $suffix;

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $keptFieldService->save(FixedFieldStandard::ENTITY_CODE_ARRIVAGE, FixedFieldStandard::FIELD_CODE_BUYERS_ARRIVAGE, isset($data["acheteurs"]) ? explode(',', $data["acheteurs"]) : []);
        $keptFieldService->save(FixedFieldStandard::ENTITY_CODE_ARRIVAGE, FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT, $data["businessUnit"] ?? null);
        $keptFieldService->save(FixedFieldStandard::ENTITY_CODE_ARRIVAGE, FixedFieldStandard::FIELD_CODE_CHAUFFEUR_ARRIVAGE, $data["chauffeur"] ?? null);
        $keptFieldService->save(FixedFieldStandard::ENTITY_CODE_ARRIVAGE, FixedFieldStandard::FIELD_CODE_COMMENTAIRE, $data["commentaire"] ?? null);
        $keptFieldService->save(FixedFieldStandard::ENTITY_CODE_ARRIVAGE, FixedFieldStandard::FIELD_CODE_FROZEN_ARRIVAGE, filter_var($data["frozen"] ?? false, FILTER_VALIDATE_BOOLEAN));
        $keptFieldService->save(FixedFieldStandard::ENTITY_CODE_ARRIVAGE, FixedFieldStandard::FIELD_CODE_RECEIVERS, $data["receivers"] ?? null);
        $keptFieldService->save(FixedFieldStandard::ENTITY_CODE_ARRIVAGE, FixedFieldStandard::FIELD_CODE_CUSTOMS_ARRIVAGE, filter_var($data["customs"] ?? false, FILTER_VALIDATE_BOOLEAN));
        $keptFieldService->save(FixedFieldStandard::ENTITY_CODE_ARRIVAGE, FixedFieldStandard::FIELD_CODE_DROP_LOCATION_ARRIVAGE, $data["dropLocation"] ?? null);
        $keptFieldService->save(FixedFieldStandard::ENTITY_CODE_ARRIVAGE, FixedFieldStandard::FIELD_CODE_FOURNISSEUR, $data["fournisseur"] ?? null);
        $keptFieldService->save(FixedFieldStandard::ENTITY_CODE_ARRIVAGE, FixedFieldStandard::FIELD_CODE_PRINT_ARRIVAGE, filter_var($data["printArrivage"] ?? false, FILTER_VALIDATE_BOOLEAN));
        $keptFieldService->save(FixedFieldStandard::ENTITY_CODE_ARRIVAGE, FixedFieldStandard::FIELD_CODE_NUM_COMMANDE_ARRIVAGE, $data["numeroCommandeList"] ?? null);
        $keptFieldService->save(FixedFieldStandard::ENTITY_CODE_ARRIVAGE, FixedFieldStandard::FIELD_CODE_PROJECT_NUMBER, $data["noProject"] ?? null);
        $keptFieldService->save(FixedFieldStandard::ENTITY_CODE_ARRIVAGE, FixedFieldStandard::FIELD_CODE_NUMERO_TRACKING_ARRIVAGE, $data["noTracking"] ?? null);
        $keptFieldService->save(FixedFieldStandard::ENTITY_CODE_ARRIVAGE, FixedFieldStandard::FIELD_CODE_CARRIER_ARRIVAGE, $data["transporteur"] ?? null);
        $keptFieldService->save(FixedFieldStandard::ENTITY_CODE_ARRIVAGE, FixedFieldStandard::FIELD_CODE_PROJECT, $data["project"] ?? null);

        $arrivage = new Arrivage();
        $arrivage
            ->setIsUrgent(false)
            ->setDate($date)
            ->setUtilisateur($currentUser)
            ->setNumeroArrivage($numeroArrivage)
            ->setCustoms(isset($data['customs']) && $data['customs'] == 'true')
            ->setFrozen(isset($data['frozen']) && $data['frozen'] == 'true')
            ->setCommentaire($data['commentaire'] ?? null)
            ->setType($typeRepository->find($data['type']));

        $status = !empty($data['status']) ? $statutRepository->find($data['status']) : null;
        if (!empty($status)) {
            $arrivage->setStatut($status);
        } else {
            return new JsonResponse([
                'success' => false,
                'msg' => $translation->translate("Général", null, "Modale", "Veuillez renseigner le champ {1}", [
                    '1' =>  $translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Statut', false),
                ]),
            ]);
        }

        if (!empty($data['fournisseur'])) {
            $arrivage->setFournisseur($fournisseurRepository->find($data['fournisseur']));
        }

        if (!empty($data['transporteur'])) {
            $arrivage->setTransporteur($transporteurRepository->find($data['transporteur']));
        }

        if (!empty($data['chauffeur'])) {
            $arrivage->setChauffeur($chauffeurRepository->find($data['chauffeur']));
        }

        if (!empty($data['noTracking'])) {
            if ($settingRepository->getOneParamByLabel(Setting::USE_TRUCK_ARRIVALS)) {
                $truckArrivalLineId = explode(',', $data['noTracking']);
                foreach ($truckArrivalLineId as $lineId) {
                    $line = $truckArrivalLineRepository
                        ->find($lineId)
                        ->addArrival($arrivage);
                    $arrivage->addTruckArrivalLine($line);
                }
            } else {
                $arrivage->setNoTracking(substr($data['noTracking'], 0, 64));
            }
        }

        $numeroCommandeList = explode(',', $data['numeroCommandeList'] ?? '');
        if (!empty($numeroCommandeList)) {
            $arrivage->setNumeroCommandeList($numeroCommandeList);
        }

        if (!empty($data['receivers'])) {
            $ids = explode("," , $data['receivers']);

            $receivers = $userRepository->findBy(['id' => $ids]);
            foreach ($receivers as $receiver) {
                $arrivage->addReceiver($receiver);
            }
        }

        if (!empty($data['businessUnit'])) {
            $arrivage->setBusinessUnit($data['businessUnit']);
        }

        if (!empty($data['noProject'])) {
            $arrivage->setProjectNumber($data['noProject']);
        }

        if (!empty($data['acheteurs'])) {
            $acheteursId = explode(',', $data['acheteurs']);
            foreach ($acheteursId as $acheteurId) {
                $arrivage->addAcheteur($userRepository->find($acheteurId));
            }
        }
        $attachmentService->persistAttachments($entityManager, $arrivage, $request->files);

        $natures = Stream::from(isset($data['packs']) ? json_decode($data['packs'], true) : [])
            ->filter()
            ->keymap(fn($value, $key) => [intval($key), intval($value)]);
        $total = $natures->sum();

        if ($total == 0) {
            throw new FormException(
                $translation->translate("Général", null, "Modale", "Veuillez renseigner le champ {1}", [
                    '1' =>  $translation->translate('Traçabilité', 'Général', 'Unités logistiques', false),
                ])
            );
        }

        $champLibreService->manageFreeFields($arrivage, $data, $entityManager, $this->getUser());

        $supplierEmergencyAlert = $arrivalService->createSupplierEmergencyAlert($arrivage);
        $isArrivalUrgent = isset($supplierEmergencyAlert);
        $alertConfigs = $isArrivalUrgent
            ? [
                $supplierEmergencyAlert,
                $arrivalService->createArrivalAlertConfig($arrivage, false)
            ]
            : $arrivalService->processEmergenciesOnArrival($entityManager, $arrivage);

        if ($isArrivalUrgent) {
            $arrivalService->setArrivalUrgent($entityManager, $arrivage, true);
        }

        $enteredLocation = !empty($data['dropLocation']) ? $emplacementRepository->find($data['dropLocation']) : null;
        $dropLocation = $arrivalService->getDefaultDropLocation($entityManager, $arrivage, $enteredLocation);

        $arrivage->setDropLocation($dropLocation);

        $project = !empty($data['project']) ?  $entityManager->getRepository(Project::class)->find($data['project']) : null;
        // persist packs after set arrival urgent
        // packs tracking movement are create at the end of the creation of the arrival, after truckArrivalLine reserve modal
        $packService->createMultiplePacks(
            $entityManager,
            $arrivage,
            $natures->toArray(),
            $currentUser,
            false,
            $project
        );
        $entityManager->persist($arrivage);
        try {
            $entityManager->flush();
        }
        /** @noinspection PhpRedundantCatchClauseInspection */
        catch (UniqueConstraintViolationException) {
            return new JsonResponse([
                'success' => false,
                'msg' => $translation->translate('Traçabilité', 'Arrivages UL', 'Divers', 'Un autre arrivage UL est en cours de création, veuillez réessayer')
            ]);
        }

        if ($sendMail) {
            $arrivalService->sendArrivalEmails($entityManager, $arrivage);
        }

        if ($useTruckArrivals) {
            $linesNeedingConfirmation = Stream::from($arrivage->getTruckArrivalLines())
                ->filterMap(fn(TruckArrivalLine $line) => $line->getReserve() && $line->getArrivals()->count() === 1
                    ? $line->getNumber()
                    : null
                )
                ->join(',');
            if ($linesNeedingConfirmation) {
                $lastElement = array_pop($alertConfigs);
                $alertConfigs[] = $arrivalService->createArrivalReserveModalConfig($arrivage, $linesNeedingConfirmation);
                $alertConfigs[] = $lastElement;
            }
        }

        $entityManager->flush();

        $redirectToArrival = boolval($settingRepository->findOneBy(['label' => Setting::REDIRECT_AFTER_NEW_ARRIVAL])?->getValue());
        return new JsonResponse([
            'success' => true,
            "redirectAfterAlert" => $redirectToArrival
                ? $this->generateUrl('arrivage_show', ['id' => $arrivage->getId()])
                : null,
            'printPacks' => (isset($data['printPacks']) && $data['printPacks'] === 'true'),
            'printArrivage' => isset($data['printArrivage']) && $data['printArrivage'] === 'true',
            'arrivalId' => $arrivage->getId(),
            'numeroArrivage' => $arrivage->getNumeroArrivage(),
            'alertConfigs' => $alertConfigs,
            ...!$redirectToArrival
                ? [
                    "new_form" => $arrivalService->generateNewForm($entityManager),
                ] : [],
        ]);
    }

    /**
     * @Route("/api-modifier", name="arrivage_edit_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::DISPLAY_ARRI}, mode=HasPermission::IN_JSON)
     */
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            if ($this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
                $arrivageRepository = $entityManager->getRepository(Arrivage::class);
                $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);
                $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
                $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
                $attachmentRepository = $entityManager->getRepository(Attachment::class);
                $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
                $statutRepository = $entityManager->getRepository(Statut::class);
                $transporteurRepository = $entityManager->getRepository(Transporteur::class);

                $arrivage = $arrivageRepository->find($data['id']);

                // construction de la chaîne de caractères pour alimenter le select2
                $acheteursUsernames = [];
                foreach ($arrivage->getAcheteurs() as $acheteur) {
                    $acheteursUsernames[] = $acheteur->getUsername();
                }
                $fieldsParam = $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_ARRIVAGE);

                $statuses = Stream::from($statutRepository->findStatusByType(CategorieStatut::ARRIVAGE, $arrivage->getType()))
                    ->map(fn(Statut $statut) => [
                        'id' => $statut->getId(),
                        'type' => $statut->getType(),
                        'nom' => $this->getFormatter()->status($statut),
                    ])
                    ->toArray();

                $html = $this->renderView('arrivage/modalEditArrivageContent.html.twig', [
                    'arrivage' => $arrivage,
                    'attachments' => $attachmentRepository->findBy(['arrivage' => $arrivage]),
                    'utilisateurs' => $utilisateurRepository->findBy(['status' => true], ['username' => 'ASC']),
                    'fournisseurs' => $fournisseurRepository->findBy([], ['nom' => 'ASC']),
                    'transporteurs' => $transporteurRepository->findAllSorted(),
                    'chauffeurs' => $chauffeurRepository->findAllSorted(),
                    'statuts' => $statuses,
                    'fieldsParam' => $fieldsParam,
                    'businessUnits' => $fieldsParamRepository->getElements(FixedFieldStandard::ENTITY_CODE_ARRIVAGE, FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT)
                ]);
            }

            return new JsonResponse([
                'html' => $html ?? "",
                'acheteurs' => $acheteursUsernames ?? []
            ]);
        }
        throw new BadRequestHttpException();
    }

    #[Route("/{arrival}/urgent", name: "patch_arrivage_urgent", options: ["expose" => true], methods: [self::PATCH], condition: "request.isXmlHttpRequest()")]
    #[Entity("arrival", expr: "repository.find(arrival) ?: repository.findOneBy({'numeroArrivage': arrival})")]
    public function patchUrgentArrival(Arrivage $arrival,
                                       Request $request,
                                       ArrivageService $arrivageDataService,
                                       UrgenceService $urgenceService,
                                       EntityManagerInterface $entityManager): Response
    {
        $numeroCommande = $request->request->get('numeroCommande');
        $postNb = $request->request->get('postNb');

        $urgencesMatching = !empty($numeroCommande)
            ? $urgenceService->matchingEmergencies(
                $arrival,
                $numeroCommande,
                $postNb,
                true
            )
            : [];

        $success = !empty($urgencesMatching);

        if ($success) {
            $arrivageDataService->setArrivalUrgent($entityManager, $arrival, true, $urgencesMatching);
            $entityManager->flush();
        }

        $response = [
            'success' => $success,
            'alertConfigs' => $success
                ? [$arrivageDataService->createArrivalAlertConfig($arrival, false, $urgencesMatching)]
                : null
        ];

        return new JsonResponse($response);
    }

    /**
     * @Route("/{arrival}/tracking-movements", name="post_arrival_tracking_movements", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @Entity("arrival", expr="repository.find(arrival) ?: repository.findOneBy({'numeroArrivage': arrival})")
     */
    public function postArrivalTrackingMovements(Arrivage                $arrival,
                                                 TrackingMovementService $trackingMovementService,
                                                 EntityManagerInterface  $entityManager): Response
    {
        $location = $arrival->getDropLocation();
        if (isset($location)) {
            /** @var Utilisateur $user */
            $user = $this->getUser();
            $now = new DateTime('now');
            foreach ($arrival->getPacks() as $pack) {
                $trackingMovementService->persistTrackingForArrivalPack(
                    $entityManager,
                    $pack,
                    $location,
                    $user,
                    $now,
                    $arrival
                );
            }

            $entityManager->flush();
        }
        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/modifier", name="arrivage_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request                $request,
                         SpecificService        $specificService,
                         ArrivageService        $arrivageDataService,
                         FreeFieldService       $champLibreService,
                         EntityManagerInterface $entityManager,
                         AttachmentService      $attachmentService): Response
    {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);

        $post = $request->request;

        $arrivage = $arrivageRepository->find($post->get('id'));

        $receivers = Stream::from($arrivage->getReceivers())
            ->map(static fn(Utilisateur $receiver) => $receiver->getId())
            ->toArray();
        $postedReceivers = $post->has('receivers')
            ? explode(",", $post->get('receivers'))
            : [];

        $dropLocation = $post->has('dropLocation')
            ? $emplacementRepository->find($post->get('dropLocation'))
            : $arrivage->getDropLocation();

        $sendMail = $settingRepository->getOneParamByLabel(Setting::SEND_MAIL_AFTER_NEW_ARRIVAL);

        $oldSupplierId = $arrivage->getFournisseur() ? $arrivage->getFournisseur()->getId() : null;

        $arrivage->setDropLocation($dropLocation);

        if($post->has('commentaire')){
            $arrivage->setCommentaire($post->get('commentaire'));
        }

        if($post->has('noTracking')){
            $arrivage->setNoTracking(substr($post->get('noTracking'), 0, 64));
        }

        if($post->has('numeroCommandeList')){
            $arrivage->setNumeroCommandeList(explode(',', $post->get('numeroCommandeList')));
        }

        if($post->has('fournisseur')){
            $fournisseur = $post->get('fournisseur') ? $fournisseurRepository->find($post->get('fournisseur')) : null;
            $arrivage->setFournisseur($fournisseur);
        }

        if($post->has('transporteur')){
            $transporteur = $post->get('transporteur') ? $transporteurRepository->find($post->get('transporteur')) : null;
            $arrivage->setTransporteur($transporteur);
        }

        if($post->has('chauffeur')){
            $chauffeur = $post->get('chauffeur') ? $chauffeurRepository->find($post->get('chauffeur')) : null;
            $arrivage->setChauffeur($chauffeur);
        }

        if($post->has('statut')){
            $statut = $post->get('statut') ? $statutRepository->find($post->get('statut')) : null;
            $arrivage->setStatut($statut);
        }

        if($post->has('customs')){
            $arrivage->setCustoms($post->getBoolean('customs'));
        }

        if($post->has('frozen')){
            $arrivage->setFrozen($post->getBoolean('frozen'));
        }

        if($post->has('receivers')) {
            $ids = $post->get('receivers')
                ? explode(",", $post->get('receivers') ?? '')
                : [];

            $existingReceivers = $arrivage->getReceivers();
            foreach ($existingReceivers as $receiver) {
                $arrivage->removeReceiver($receiver);
            }

            $receivers = $utilisateurRepository->findBy(['id' => $ids]);
            foreach ($receivers as $receiver) {
                $arrivage->addReceiver($receiver);
            }
        }

        if($post->has('businessUnit')){
            $arrivage->setBusinessUnit($post->get('businessUnit'));
        }

        if($post->has('noProject')){
            $arrivage->setProjectNumber($post->get('noProject'));
        }

        if($post->has('type')){
            $type = $post->get('type') ? $typeRepository->find($post->get('type')) : null;
            $arrivage->setType($type);
        }

        $newSupplierId = $arrivage->getFournisseur() ? $arrivage->getFournisseur()->getId() : null;


        if($post->has('acheteurs')){
            $acheteursEntities = $post->get('acheteurs') ? $utilisateurRepository->findBy(['username' => explode(',', $post->get('acheteurs'))]) : null;

            $arrivage->removeAllAcheteur();
            if (!empty($post->get('acheteurs'))) {
                foreach ($acheteursEntities as $acheteursEntity) {
                    $arrivage->addAcheteur($acheteursEntity);
                }
            }
        }

        $entityManager->flush();

        $hasNewReceivers = !Stream::diff($postedReceivers, $receivers, true)->isEmpty();
        if ($sendMail && $hasNewReceivers) {
            $arrivageDataService->sendArrivalEmails($entityManager, $arrivage);
        }

        $attachmentService->removeAttachments($entityManager, $arrivage, $post->all('files') ?: []);
        $attachmentService->persistAttachments($entityManager, $arrivage, $request->files);

        $champLibreService->manageFreeFields($arrivage, $post->all(), $entityManager, $this->getUser());
        $entityManager->flush();

        $supplierEmergencyAlert = ($oldSupplierId !== $newSupplierId && $newSupplierId)
            ? $arrivageDataService->createSupplierEmergencyAlert($arrivage)
            : null;
        $isArrivalUrgent = isset($supplierEmergencyAlert);

        $settingRepository = $entityManager->getRepository(Setting::class);
        $confirmEmergency = boolval($settingRepository->getOneParamByLabel(Setting::CONFIRM_EMERGENCY_ON_ARRIVAL));
        $alertConfig = $isArrivalUrgent
            ? [
                $supplierEmergencyAlert,
                $arrivageDataService->createArrivalAlertConfig($arrivage, false)
            ]
            : $arrivageDataService->createArrivalAlertConfig($arrivage, $confirmEmergency);

        if ($isArrivalUrgent && !$confirmEmergency) {
            $arrivageDataService->setArrivalUrgent($entityManager, $arrivage, true);
            $entityManager->flush();
        }

        $response = [
            'success' => true,
            'msg' => "L'arrivage a bien été modifié",
            'entete' => $this->renderView('arrivage/arrivage-show-header.html.twig', [
                'arrivage' => $arrivage,
                'canBeDeleted' => $arrivageRepository->countUnsolvedDisputesByArrivage($arrivage) == 0,
                'showDetails' => $arrivageDataService->createHeaderDetailsConfig($arrivage),
                'allPacksAlreadyInDispatch' => $arrivage->getPacks()->count() <= $arrivageRepository->countArrivalPacksInDispatch($arrivage)
            ]),
            'alertConfigs' => $alertConfig
        ];
        return new JsonResponse($response);
    }

    /**
     * @Route("/supprimer", name="arrivage_delete", options={"expose"=true}, methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::DELETE_ARRI}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request,
                           AttachmentService $attachmentService,
                           EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);

            /** @var Arrivage $arrivage */
            $arrivage = $arrivageRepository->find($data['arrivage']);

            $canBeDeleted = ($arrivageRepository->countUnsolvedDisputesByArrivage($arrivage) == 0);

            if ($canBeDeleted) {
                foreach ($arrivage->getPacks() as $pack) {
                    foreach ($pack->getTrackingMovements() as $arrivageMvtTraca) {
                        $entityManager->remove($arrivageMvtTraca);
                    }

                    $pack->getTrackingMovements()->clear();

                    $disputes = $pack->getDisputes();
                    foreach ($disputes as $dispute) {
                        $entityManager->remove($dispute);
                    }
                    $pack->getDisputes()->clear();

                    $entityManager->remove($pack);
                }
                $arrivage->getPacks()->clear();
                $attachmentService->removeAttachments($entityManager, $arrivage);

                foreach ($arrivage->getUrgences() as $urgence) {
                    $urgence->setLastArrival(null);
                }

                $entityManager->remove($arrivage);
                $entityManager->flush();
                $data = [
                    "redirect" => $this->generateUrl('arrivage_index')
                ];
            } else {
                $data = false;
            }
            return new JsonResponse($data);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/lister-UL", name="arrivage_list_packs_api", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function listPacksByArrivage(Request $request,
                                        EntityManagerInterface $entityManager)
    {
        if ($data = json_decode($request->getContent(), true)) {
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $arrivage = $arrivageRepository->find($data['id']);

            $html = $this->renderView('arrivage/modalListPacksContent.html.twig', [
                'arrivage' => $arrivage
            ]);

            return new JsonResponse($html);

        } else {
            throw new BadRequestHttpException();
        }
    }

    /**
     * @Route("/csv", name="get_arrivages_csv", options={"expose"=true}, methods={"GET"})
     */
    public function exportArrivals(Request                $request,
                                   EntityManagerInterface $entityManager,
                                   CSVExportService       $csvService,
                                   DataExportService      $dataExportService,
                                   ArrivageService        $arrivalService) {
        $FORMAT = "Y-m-d H:i:s";

        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        try {
            $from = DateTime::createFromFormat($FORMAT, $request->query->get("dateMin") . " 00:00:00");
            $to = DateTime::createFromFormat($FORMAT, $request->query->get("dateMax") . " 23:59:59");
        } catch (Throwable) {
            return $this->json([
                "success" => false,
                "msg" => "Dates invalides"
            ]);
        }

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        $arrivalService->launchExportCache($entityManager, $from, $to);

        $exportableColumns = $arrivalService->getArrivalExportableColumns($entityManager);
        $header = Stream::from($exportableColumns)
            ->map(fn(array $column) => $column['label'] ?? '')
            ->toArray();

        // same order than header column
        $exportableColumns = Stream::from($exportableColumns)
            ->map(fn(array $column) => $column['code'] ?? '')
            ->toArray();

        $arrivalsIterator = $arrivageRepository->iterateArrivals($from, $to);
        return $csvService->streamResponse(function ($output) use ($dataExportService, $arrivalsIterator, $exportableColumns) {
            $dataExportService->exportArrivages($arrivalsIterator, $output, $exportableColumns);
        }, "export-arrivages_$today.csv", $header);
    }

    #[Route("/voir/{id}", name: "arrivage_show", options: ["expose" => true], methods: [self::GET, self::POST])]
    public function show(EntityManagerInterface $entityManager,
                         ArrivageService        $arrivageDataService,
                         PackService            $packService,
                         Request                $request,
                         TagTemplateService     $tagTemplateService,
                         Arrivage               $arrivage): Response
    {
        // HasPermission annotation impossible
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::LIST_ALL)
            && !in_array($this->getUser(), $arrivage->getAcheteurs()->toArray())) {
            return $this->render('securite/access_denied.html.twig');
        }
        $printPacks = $request->query->get('printPacks');
        $printArrivage = $request->query->get('printArrivage');
        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);
        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $projectRepository = $entityManager->getRepository(Project::class);
        $acheteursNames = [];
        foreach ($arrivage->getAcheteurs() as $user) {
            $acheteursNames[] = $user->getUsername();
        }

        $fieldsParam = $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_ARRIVAGE);

        $defaultDisputeStatus = $statutRepository->getIdDefaultsByCategoryName(CategorieStatut::DISPUTE_ARR);

        $natures = Stream::from($natureRepository->findByAllowedForms([Nature::ARRIVAL_CODE]))
            ->map(fn(Nature $nature) => [
                'id' => $nature->getId(),
                'label' => $this->getFormatter()->nature($nature),
                'defaultQuantity' => $nature->getDefaultQuantity(),
            ])
            ->toArray();

        $fields = $packService->getColumnVisibleConfig($this->getUser());

        return $this->render("arrivage/show.html.twig", [
            'arrivage' => $arrivage,
            'disputeTypes' => $typeRepository->findByCategoryLabels([CategoryType::DISPUTE]),
            'acheteurs' => $acheteursNames,
            'disputeStatuses' => $statutRepository->findByCategorieName(CategorieStatut::DISPUTE_ARR, 'displayOrder'),
            'natures' => $natures,
            'printPacks' => $printPacks,
            'printArrivage' => $printArrivage,
            'canBeDeleted' => $arrivageRepository->countUnsolvedDisputesByArrivage($arrivage) == 0,
            'fieldsParam' => $fieldsParam,
            'showDetails' => $arrivageDataService->createHeaderDetailsConfig($arrivage),
            "tag_templates" => $tagTemplateService->serializeTagTemplates($entityManager, CategoryType::ARRIVAGE),
            'defaultDisputeStatusId' => $defaultDisputeStatus[0] ?? null,
            "projects" => $projectRepository->findActive(),
            'fields' => $fields,
        ]);
    }

    /**
     * @Route("/creer-litige", name="dispute_new", options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function newDispute(Request                $request,
                               ArrivageService        $arrivageDataService,
                               DisputeService         $disputeService,
                               EntityManagerInterface $entityManager,
                               UniqueNumberService    $uniqueNumberService,
                               TranslationService     $translation,
                               AttachmentService      $attachmentService): Response
    {
        $post = $request->request;

        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $packRepository = $entityManager->getRepository(Pack::class);
        $usersRepository = $entityManager->getRepository(Utilisateur::class);

        $now = new DateTime('now');

        $disputeNumber = $uniqueNumberService->create($entityManager, Dispute::DISPUTE_ARRIVAL_PREFIX, Dispute::class, UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT);

        $dispute = new Dispute();
        $dispute
            ->setReporter($usersRepository->find($post->get('disputeReporter')))
            ->setStatus($statutRepository->find($post->get('disputeStatus')))
            ->setType($typeRepository->find($post->get('disputeType')))
            ->setCreationDate($now)
            ->setNumber($disputeNumber);

        $arrivage = null;
        if (!empty($packsStr = $post->get('disputePacks'))) {
            $packIds = explode(',', $packsStr);
            foreach ($packIds as $packId) {
                $pack = $packRepository->find($packId);
                if ($pack) {
                    $dispute->addPack($pack);
                    $arrivage = $pack->getArrivage();
                }
            }
        }
        if ($post->get('emergency')) {
            $dispute->setEmergencyTriggered($post->get('emergency') === 'true');
        }
        if ((!$dispute->getStatus() || !$dispute->getStatus()->isTreated()) && $arrivage) {
            $typeStatuses = $statutRepository->findStatusByType(CategorieStatut::ARRIVAGE, $arrivage->getType());
            $disputeStatus = array_reduce(
                $typeStatuses,
                function(?Statut $disputeStatus, Statut $status) {
                    return $disputeStatus
                        ?? ($status->isDispute() ? $status : null);
                },
                null
            );
            $arrivage->setStatut($disputeStatus);
        }

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $entityManager->persist($dispute);

        $historyRecord = $disputeService->createDisputeHistoryRecord(
            $dispute,
            $currentUser,
            [
                $post->get('commentaire'),
                $dispute->getType()->getDescription()
            ]
        );

        $entityManager->persist($historyRecord);

        $attachmentService->persistAttachments($entityManager, $dispute, $request->files);
        try {
            $entityManager->flush();
        }
        /** @noinspection PhpRedundantCatchClauseInspection */
        catch (UniqueConstraintViolationException $e) {
            return new JsonResponse([
                'success' => false,
                'msg' => $translation->translate('Arrivages UL', 'Divers', 'Un autre litige d\'arrivage est en cours de création, veuillez réessayer').'.'
            ]);
        }

        $disputeService->sendMailToAcheteursOrDeclarant($dispute, DisputeService::CATEGORY_ARRIVAGE);

        $response = $this->getResponseReloadArrivage($entityManager, $arrivageDataService, $request->query->get('reloadArrivage')) ?? [];
        $response['success'] = true;
        $response['msg'] = $translation->translate('Qualité', 'Litiges', "Le litige {1} a bien été créée", [
            1 => $dispute->getNumber()
        ]);
        return new JsonResponse($response);
    }

    /**
     * @Route("/supprimer-litige", name="litige_delete_arrivage", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function deleteDispute(Request $request,
                                  EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $disputeRepository = $entityManager->getRepository(Dispute::class);
            $dispute = $disputeRepository->find($data['litige']);

            $dispute->setLastHistoryRecord(null);
            //required before removing dispute or next flush will fail
            $entityManager->flush();

            foreach($dispute->getDisputeHistory() as $history) {
                $entityManager->remove($history);
            }

            $entityManager->remove($dispute);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/ajouter-UL", name="arrivage_add_pack", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function addPack(Request $request,
                             EntityManagerInterface $entityManager,
                             PackService $packService)
    {
        if ($data = json_decode($request->getContent(), true)) {
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $projectRepository = $entityManager->getRepository(Project::class);

            $arrivage = $arrivageRepository->find($data['arrivageId']);
            $project = isset($data['project']) ? $projectRepository->find($data['project']) : null;

            $natures = json_decode($data['pack'], true);

            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();

            $response = [];
            $persistedPack = [];
            if ($reception = $arrivage->getReception()) {
                $statusCode = $reception->getStatut()->getCode();
                if ($statusCode === Reception::STATUT_EN_ATTENTE) {
                    $persistedPack = $packService->createMultiplePacks($entityManager, $arrivage, $natures, $currentUser, true, $project, $reception);
                    $entityManager->flush();
                } elseif ($statusCode === Reception::STATUT_RECEPTION_TOTALE) {
                    $response = [
                        'success' => false,
                        'msg' => "Vous ne pouvez pas ajouter d'unité(s) logistique(s) à un arrivage receptionné."
                    ];
                }
            } else {
                $persistedPack = $packService->createMultiplePacks($entityManager, $arrivage, $natures, $currentUser, true, $project);
                $entityManager->flush();
            }

            if ($response === []) {
                $response = [
                    'success' => true,
                    'packs' => array_map(function (Pack $pack) {
                        return [
                            'id' => $pack->getId(),
                            'code' => $pack->getCode()
                        ];
                    }, $persistedPack),
                    'arrivageId' => $arrivage->getId(),
                    'arrivage' => $arrivage->getNumeroArrivage()
                ];
            }

            return new JsonResponse($response);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/litiges/api/{arrivage}", name="arrivageLitiges_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function apiArrivageLitiges(EntityManagerInterface $entityManager,
                                       Arrivage $arrivage): Response
    {
        $disputeRepository = $entityManager->getRepository(Dispute::class);
        $disputes = $disputeRepository->findByArrivage($arrivage);
        $rows = [];
        /** @var Utilisateur $user */
        $user = $this->getUser();

        foreach ($disputes as $dispute) {
            $rows[] = [
                'firstDate' => $dispute->getCreationDate()->format($user->getDateFormat() ? $user->getDateFormat() . ' H:i' : 'd/m/Y H:i'),
                'status' => $this->getFormatter()->status($dispute->getStatus()),
                'type' => $this->getFormatter()->type($dispute->getType()),
                'updateDate' => $dispute->getUpdateDate() ? $dispute->getUpdateDate()->format($user->getDateFormat() ? $user->getDateFormat() . ' H:i' : 'd/m/Y H:i') : '',
                'Actions' => $this->renderView('arrivage/datatableLitigesRow.html.twig', [
                    'arrivageId' => $arrivage->getId(),
                    'url' => [
                        'edit' => $this->generateUrl('litige_api_edit', ['id' => $dispute->getId()])
                    ],
                    'disputeId' => $dispute->getId(),
                    'disputeNumber' => $dispute->getNumber()
                ]),
                'urgence' => $dispute->getEmergencyTriggered()
            ];
        }

        $data['data'] = $rows;

        return new JsonResponse($data);
    }

    /**
     * @Route("/api-modifier-litige", name="litige_api_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function apiEditLitige(Request $request,
                                  EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $disputeRepository = $entityManager->getRepository(Dispute::class);
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $attachmentRepository = $entityManager->getRepository(Attachment::class);

            $dispute = $disputeRepository->find($data['disputeId']);

            $packCode = [];
            foreach ($dispute->getPacks() as $pack) {
                $packCode[] = $pack->getId();
            }

            $arrivage = $arrivageRepository->find($data['arrivageId']);

            $disputeStatuses = Stream::from($statutRepository->findByCategorieName(CategorieStatut::DISPUTE_ARR, 'displayOrder'))
                ->map(fn(Statut $statut) => [
                    'id' => $statut->getId(),
                    'type' => $statut->getType(),
                    'nom' => $this->getFormatter()->status($statut),
                    'treated' => $statut->isTreated(),
                ])
                ->toArray();

            $html = $this->renderView('arrivage/modalEditLitigeContent.html.twig', [
                'dispute' => $dispute,
                'disputeTypes' => $typeRepository->findByCategoryLabels([CategoryType::DISPUTE]),
                'disputeStatuses' => $disputeStatuses,
                'attachments' => $attachmentRepository->findBy(['dispute' => $dispute]),
                'packs' => $arrivage->getPacks(),
            ]);

            return new JsonResponse(['html' => $html, 'packs' => $packCode]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier-litige", name="litige_edit_arrivage",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editLitige(Request                $request,
                               ArrivageService        $arrivageDataService,
                               EntityManagerInterface $entityManager,
                               DisputeService         $disputeService,
                               Twig_Environment       $templating,
                               AttachmentService      $attachmentService): Response
    {
        $post = $request->request;

        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $packRepository = $entityManager->getRepository(Pack::class);
        $disputeRepository = $entityManager->getRepository(Dispute::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

        $dispute = $disputeRepository->find($post->get('id'));
        $typeBefore = $dispute->getType()->getId();
        $typeAfter = (int)$post->get('disputeType');
        $statutBefore = $dispute->getStatus()->getId();
        $statutAfter = (int)$post->get('disputeStatus');
        $dispute
            ->setReporter($utilisateurRepository->find($post->get('disputeReporter')))
            ->setUpdateDate(new DateTime('now'));
        $this->templating = $templating;
        $newStatus = $statutRepository->find($statutAfter);
        $hasRightToTreatLitige = $this->userService->hasRightFunction(Menu::QUALI, Action::TREAT_DISPUTE);

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        if ($hasRightToTreatLitige || !$newStatus->isTreated()) {
            $dispute->setStatus($newStatus);
        }

        if ($hasRightToTreatLitige) {
            $dispute->setType($typeRepository->find($typeAfter));
        }

        if (!empty($newPack = $post->get('pack'))) {
            // on détache les UL existants...
            $existingPacks = $dispute->getPacks();
            foreach ($existingPacks as $existingPack) {
                $dispute->removePack($existingPack);
            }
            // ... et on ajoute ceux sélectionnés
            $listPacks = explode(',', $newPack);
            foreach ($listPacks as $packId) {
                $dispute->addPack($packRepository->find($packId));
            }
        }

        $entityManager->flush();

        if ($post->get('emergency')) {
            $dispute->setEmergencyTriggered($post->get('emergency') === 'true');
        }

        $comment = trim($post->get('commentaire', ''));
        $typeDescription = $dispute->getType()->getDescription();
        if ($statutBefore !== $statutAfter
            || $typeBefore !== $typeAfter
            || $comment) {

            $historyRecord = $disputeService->createDisputeHistoryRecord(
                $dispute,
                $currentUser,
                [$comment, $typeDescription]
            );

            $entityManager->persist($historyRecord);
            $entityManager->flush();
        }

        $attachmentService->removeAttachments($entityManager, $dispute, $post->all('files') ?: []);
        $attachmentService->persistAttachments($entityManager, $dispute, $request->files);

        $entityManager->flush();
        $isStatutChange = ($statutBefore !== $statutAfter);
        if ($isStatutChange) {
            $disputeService->sendMailToAcheteursOrDeclarant($dispute, DisputeService::CATEGORY_ARRIVAGE, true);
        }

        $response = $this->getResponseReloadArrivage($entityManager, $arrivageDataService, $request->query->get('reloadArrivage')) ?? [];

        $response['success'] = true;

        return new JsonResponse($response);
    }

    /**
     * @Route("/packs/api/{arrivage}", name="packs_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function apiPacks(Arrivage $arrivage): Response
    {
        $packs = $arrivage->getPacks()->toArray();
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $rows = [];
        /** @var Pack $pack */
        foreach ($packs as $pack) {
            $mouvement = $pack->getLastTracking();
            $rows[] = [
                'nature' => $this->getFormatter()->nature($pack->getNature()),
                'code' => $pack->getCode(),
                'lastMvtDate' => $mouvement ? ($mouvement->getDatetime() ? $mouvement->getDatetime()->format($user->getDateFormat() ? $user->getDateFormat() . ' H:i' : 'd/m/Y H:i') : '') : '',
                'lastLocation' => $mouvement ? ($mouvement->getEmplacement() ? $mouvement->getEmplacement()->getLabel() : '') : '',
                'operator' => $mouvement ? ($mouvement->getOperateur() ? $mouvement->getOperateur()->getUsername() : '') : '',
                'project' => $pack->getProject() ? $pack->getProject()->getCode() : '',
                'actions' => $this->renderView('arrivage/datatablePackRow.html.twig', [
                    'arrivageId' => $arrivage->getId(),
                    'packId' => $pack->getId()
                ])
            ];
        }
        $data['data'] = $rows;

        return new JsonResponse($data);
    }

    #[Route("/{arrivage}/UL/{pack}/etiquette", name: "print_arrivage_single_pack_bar_codes", options: ["expose" => true], methods: [self::GET])]
    public function printArrivagePackBarCodes(Arrivage               $arrivage,
                                              Request                $request,
                                              EntityManagerInterface $entityManager,
                                              PDFGeneratorService    $PDFGeneratorService,
                                              PackService            $packService,
                                              Pack                   $pack = null,
                                              array                  $packIdsFilter = [],
                                              TagTemplate            $tagTemplate = null,
                                              bool                   $forceTagEmpty = false): Response {
        if (!$tagTemplate) {
            $tagTemplate = $request->query->get('template')
                ? $entityManager->getRepository(TagTemplate::class)->find($request->query->get('template'))
                : null;
        }
        $forceTagEmpty = !$forceTagEmpty ? $request->query->get('forceTagEmpty', false) : $forceTagEmpty;

        if ($pack && !$tagTemplate) {
            $tagTemplate = $pack->getNature()?->getTags()?->first() ?: null;
        }
        $barcodeConfigs = [];
        $settingRepository = $entityManager->getRepository(Setting::class);
        $usernameParamIsDefined = $settingRepository->getOneParamByLabel(Setting::INCLUDE_RECIPIENT_IN_LABEL);
        $dropzoneParamIsDefined = $settingRepository->getOneParamByLabel(Setting::INCLUDE_DZ_LOCATION_IN_LABEL);
        $typeArrivalParamIsDefined = $settingRepository->getOneParamByLabel(Setting::INCLUDE_ARRIVAL_TYPE_IN_LABEL);
        $packCountParamIsDefined = $settingRepository->getOneParamByLabel(Setting::INCLUDE_PACK_COUNT_IN_LABEL);
        $commandAndProjectNumberIsDefined = $settingRepository->getOneParamByLabel(Setting::INCLUDE_COMMAND_AND_PROJECT_NUMBER_IN_LABEL);
        $printTwiceIfCustoms = $settingRepository->getOneParamByLabel(Setting::PRINT_TWICE_CUSTOMS);
        $businessUnitParam = $settingRepository->getOneParamByLabel(Setting::INCLUDE_BUSINESS_UNIT_IN_LABEL);
        $projectParam = $settingRepository->getOneParamByLabel(Setting::INCLUDE_PROJECT_IN_LABEL);
        $showDateAndHourArrivalUl = $settingRepository->getOneParamByLabel(Setting::INCLUDE_SHOW_DATE_AND_HOUR_ARRIVAL_UL);
        $showTypeLogoArrivalUl = $settingRepository->getOneParamByLabel(Setting::INCLUDE_TYPE_LOGO_ON_TAG);


        $firstCustomIconInclude = $settingRepository->getOneParamByLabel(Setting::INCLUDE_CUSTOMS_IN_LABEL);
        $firstCustomIconName = $settingRepository->getOneParamByLabel(Setting::CUSTOM_ICON);
        $firstCustomIconText = $settingRepository->getOneParamByLabel(Setting::CUSTOM_TEXT_LABEL);

        $firstCustomIconConfig = ($firstCustomIconInclude && $firstCustomIconName && $firstCustomIconText)
            ? [
                'icon' => $firstCustomIconName,
                'text' => $firstCustomIconText
            ]
            : null;

        $firstCustomIconInclude = $settingRepository->getOneParamByLabel(Setting::INCLUDE_EMERGENCY_IN_LABEL);
        $secondCustomIconName = $settingRepository->getOneParamByLabel(Setting::EMERGENCY_ICON);;
        $secondCustomIconText = $settingRepository->getOneParamByLabel(Setting::EMERGENCY_TEXT_LABEL);

        $secondCustomIconConfig = ($firstCustomIconInclude && $secondCustomIconName && $secondCustomIconText)
            ? [
                'icon' => $secondCustomIconName,
                'text' => $secondCustomIconText
            ]
            : null;

        if (!isset($pack)) {
            $printPacks = $request->query->getBoolean('printPacks');
            $printArrivage = $request->query->getBoolean('printArrivage');

            if ($printPacks) {
                $barcodeConfigs = $this->getBarcodeConfigPrintAllPacks(
                    $arrivage,
                    $packService,
                    $typeArrivalParamIsDefined,
                    $usernameParamIsDefined,
                    $dropzoneParamIsDefined,
                    $packCountParamIsDefined,
                    $commandAndProjectNumberIsDefined,
                    $firstCustomIconConfig,
                    $secondCustomIconConfig,
                    $showTypeLogoArrivalUl,
                    $packIdsFilter,
                    $businessUnitParam,
                    $projectParam,
                    $showDateAndHourArrivalUl,
                    $forceTagEmpty ? null : $tagTemplate,
                    $forceTagEmpty
                );
            }

            if (empty($barcodeConfigs) && $printPacks) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Aucune étiquette à imprimer'
                ]);
            }

            if ($printArrivage) {
                $barcodeConfigs[] = [
                    'code' => $arrivage->getNumeroArrivage()
                ];
            }
        } else {
            if (!$pack->getArrivage() || $pack->getArrivage()->getId() !== $arrivage->getId()) {
                throw new BadRequestHttpException();
            }

            $total = $arrivage->getPacks()->count();
            $position = $arrivage->getPacks()->indexOf($pack) + 1;

            $barcodeConfigs[] = $packService->getBarcodePackConfig(
                $pack,
                $arrivage->getReceivers()->toArray(),
                "$position/$total",
                $typeArrivalParamIsDefined,
                $usernameParamIsDefined,
                $dropzoneParamIsDefined,
                $packCountParamIsDefined,
                $commandAndProjectNumberIsDefined,
                $firstCustomIconConfig,
                $secondCustomIconConfig,
                $showTypeLogoArrivalUl,
                $businessUnitParam,
                $projectParam,
                $showDateAndHourArrivalUl,
            );
        }

        $printTwice = ($printTwiceIfCustoms && $arrivage->getCustoms());

        if($printTwice) {
            $barcodeConfigs = Stream::from($barcodeConfigs, $barcodeConfigs)
                ->toArray();
        }

        if (empty($barcodeConfigs)) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'Aucune étiquette à imprimer'
            ]);
        }

        $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'arrivage', $tagTemplate ? $tagTemplate->getPrefix() : 'ETQ');

        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs, false, $forceTagEmpty ? null : $tagTemplate),
            $fileName
        );
    }

    #[Route("/{arrivage}/etiquettes", name: "print_arrivage_bar_codes", options: ["expose" => true], methods: [self::GET])]
    public function printArrivageAlias(Arrivage               $arrivage,
                                       Request                $request,
                                       PackService            $packService,
                                       EntityManagerInterface $entityManager,
                                       PDFGeneratorService    $PDFGeneratorService): Response {
        $template = $request->query->get('template')
            ? $entityManager->getRepository(TagTemplate::class)->find($request->query->get('template'))
            : null;
        $packIdsFilter = $request->query->all('packs') ?: [];
        $forceTagEmpty = $request->query->get('forceTagEmpty', false);
        return $this->printArrivagePackBarCodes($arrivage, $request, $entityManager, $PDFGeneratorService, $packService, null, $packIdsFilter, $template, $forceTagEmpty);
    }

    private function getBarcodeConfigPrintAllPacks(Arrivage     $arrivage,
                                                   PackService  $packService,
                                                   ?bool        $typeArrivalParamIsDefined = false,
                                                   ?bool        $usernameParamIsDefined = false,
                                                   ?bool        $dropzoneParamIsDefined = false,
                                                   ?bool        $packCountParamIsDefined = false,
                                                   ?bool        $commandAndProjectNumberIsDefined = false,
                                                   ?array       $firstCustomIconConfig = null,
                                                   ?array       $secondCustomIconConfig = null,
                                                   ?bool        $showTypeLogoArrivalUl = null,
                                                   array        $packIdsFilter = [],
                                                   ?bool        $businessUnitParam = false,
                                                   ?bool        $projectParam = false,
                                                   ?bool        $showDateAndHourArrivalUl = false,
                                                   ?TagTemplate $tagTemplate = null,
                                                   bool         $forceTagEmpty = false): array {
        $packs = Stream::from($arrivage->getPacks());
        $total = $packs->count();

        return $packs
            ->filterMap(static function (Pack $pack, int $index) use (  $forceTagEmpty,
                                                                        $packIdsFilter,
                                                                        $tagTemplate,
                                                                        $showDateAndHourArrivalUl,
                                                                        $projectParam,
                                                                        $businessUnitParam,
                                                                        $showTypeLogoArrivalUl,
                                                                        $secondCustomIconConfig,
                                                                        $firstCustomIconConfig,
                                                                        $commandAndProjectNumberIsDefined,
                                                                        $packCountParamIsDefined,
                                                                        $dropzoneParamIsDefined,
                                                                        $usernameParamIsDefined,
                                                                        $typeArrivalParamIsDefined,
                                                                        $total,
                                                                        $arrivage,
                                                                        $packService): ?array {
                $position = $index + 1;
                if (
                    (!$forceTagEmpty || $pack->getNature()?->getTags()?->isEmpty()) &&
                    (empty($packIdsFilter) || in_array($pack->getId(), $packIdsFilter)) &&
                    (empty($tagTemplate) || in_array($pack->getNature(), $tagTemplate->getNatures()->toArray()))
                ) {
                    return $packService->getBarcodePackConfig(
                        $pack,
                        $arrivage->getReceivers()->toArray(),
                        "$position/$total",
                        $typeArrivalParamIsDefined,
                        $usernameParamIsDefined,
                        $dropzoneParamIsDefined,
                        $packCountParamIsDefined,
                        $commandAndProjectNumberIsDefined,
                        $firstCustomIconConfig,
                        $secondCustomIconConfig,
                        $showTypeLogoArrivalUl,
                        $businessUnitParam,
                        $projectParam,
                        $showDateAndHourArrivalUl,
                    );
                }
                return null;
            })
            ->values();
    }

    private function getResponseReloadArrivage(EntityManagerInterface $entityManager,
                                               ArrivageService        $arrivageDataService,
                                                                      $reloadArrivageId): ?array
    {
        $response = null;
        if (isset($reloadArrivageId)) {
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $arrivageToReload = $arrivageRepository->find($reloadArrivageId);
            if ($arrivageToReload) {
                $response = [
                    'entete' => $this->renderView('arrivage/arrivage-show-header.html.twig', [
                        'arrivage' => $arrivageToReload,
                        'canBeDeleted' => $arrivageRepository->countUnsolvedDisputesByArrivage($arrivageToReload) == 0,
                        'showDetails' => $arrivageDataService->createHeaderDetailsConfig($arrivageToReload)
                    ]),
                ];
            }
        }

        return $response;
    }

    /**
     * @Route("/api-columns", name="arrival_api_columns", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::DISPLAY_ARRI}, mode=HasPermission::IN_JSON)
     */
    public function apiColumns(ArrivageService        $arrivageDataService,
                               EntityManagerInterface $entityManager,
                               Request $request): Response
    {
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $dispatchMode = $request->query->getBoolean('dispatchMode');

        $columns = $arrivageDataService->getColumnVisibleConfig($entityManager, $currentUser, $dispatchMode);
        return new JsonResponse($columns);
    }

    /**
     * @Route("/new-dispute-template", name="new_dispute_template", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function newDisputeTemplate(Request $request, EntityManagerInterface $manager): Response {
        $statusRepository = $manager->getRepository(Statut::class);
        $typeRepository = $manager->getRepository(Type::class);

        $arrival = $manager->find(Arrivage::class, $request->query->get('id'));
        $disputeStatuses = Stream::from($statusRepository->findByCategorieName(CategorieStatut::DISPUTE_ARR, 'displayOrder'))
            ->map(fn(Statut $statut) => [
                'id' => $statut->getId(),
                'type' => $statut->getType(),
                'nom' => $this->getFormatter()->status($statut),
            ])
            ->toArray();

        $defaultDisputeStatus = $statusRepository->getIdDefaultsByCategoryName(CategorieStatut::DISPUTE_ARR);
        $disputeTypes = $typeRepository->findByCategoryLabels([CategoryType::DISPUTE]);
        $fixedFields = $manager->getRepository(FixedFieldStandard::class)->getByEntity(FixedFieldStandard::ENTITY_CODE_ARRIVAGE);

        $buyers = Stream::from($arrival->getAcheteurs())->map(fn(Utilisateur $buyer) => $buyer->getUsername())->join(',');
        $orderNumers = Stream::from($arrival->getNumeroCommandeList())->join(',');

        return $this->json([
            'success' => true,
            'content' => $this->renderView('arrivage/modalNewDisputeContent.html.twig', [
                'arrivage' => $arrival,
                'disputeTypes' => $disputeTypes,
                'disputeStatuses' => $disputeStatuses,
                'buyers' => $buyers,
                'orderNumers' => $orderNumers,
                'defaultDisputeStatusId' => $defaultDisputeStatus[0] ?? null,
                'packs' => $arrival->getPacks(),
                'fieldsParam' => $fixedFields
            ])
        ]);
    }

    private function getDefaultLanguageSlug(): ?string {
        if (!isset($this->defaultLanguageSlug)) {
            $this->defaultLanguageSlug = $this->languageService->getDefaultSlug();
        }
        return $this->defaultLanguageSlug;
    }

    /**
     * @Route("/list-pack-api-columns", name="arrival_list_packs_api_columns", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::DISPLAY_ARRI}, mode=HasPermission::IN_JSON)
     */
    public function listPackApiColumns(PackService $packService): Response
    {
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $columns = $packService->getColumnVisibleConfig($currentUser);
        return new JsonResponse($columns);
    }
}
