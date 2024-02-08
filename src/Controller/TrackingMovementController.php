<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\Pack;
use App\Entity\TrackingMovement;
use App\Entity\Setting;
use App\Entity\Attachment;

use App\Entity\Statut;
use App\Entity\Utilisateur;

use App\Service\AttachmentService;
use App\Service\CSVExportService;
use App\Service\FilterSupService;
use App\Service\FreeFieldService;
use App\Service\TrackingMovementService;
use App\Service\SpecificService;
use App\Service\TranslationService;
use App\Service\UserService;

use App\Service\VisibleColumnService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use DateTime;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;

#[Route("/mouvement-traca")]
class TrackingMovementController extends AbstractController
{

    #[Required]
    public TranslationService $translationService;

    #[Route("/", name: "mvt_traca_index", options: ["expose" => true])]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_MOUV])]
    public function index(Request $request,
                          EntityManagerInterface $entityManager,
                          FilterSupService $filterSupService,
                          TrackingMovementService $trackingMovementService): Response {
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $champLibreRepository = $entityManager->getRepository(FreeField::class);

        $packFilter = $request->query->get('pack');
        $article = null;
        $filterArticle = $request->query->get('article');
        if($filterArticle) {
            $article = $entityManager->getRepository(Article::class)->find($filterArticle);
            $request->request->add(['article' => $filterArticle]);
        }

        if (!empty($packFilter)) {
            /** @var Utilisateur $loggedUser */
            $loggedUser = $this->getUser();
            $filtreSupRepository->clearFiltersByUserAndPage($loggedUser, FiltreSup::PAGE_MVT_TRACA);
            $entityManager->flush();
            $filter = $filterSupService->createFiltreSup(FiltreSup::PAGE_MVT_TRACA, FiltreSup::FIELD_PACK, $packFilter, $loggedUser);
            $entityManager->persist($filter);
            $entityManager->flush();
        }

        $currentUser = $this->getUser();
        $fields = $trackingMovementService->getVisibleColumnsConfig($entityManager, $currentUser);

        $redirectAfterTrackingMovementCreation = $settingRepository->getOneParamByLabel(Setting::CLEAR_AND_KEEP_MODAL_AFTER_NEW_MVT);
        $mvtStatuses = $statutRepository->findByCategorieName(CategorieStatut::MVT_TRACA);
        $statuses = Stream::from($mvtStatuses)
            ->filter(static fn(Statut $statut) => $statut->getCode() !== TrackingMovement::TYPE_PRISE_DEPOSE)
            ->toArray();

        $request->request->add(['length' => 10]);
        return $this->render('mouvement_traca/index.html.twig', [
            'statuts' => $statuses,
            'form_statuses' => Stream::from($mvtStatuses)
                ->filter(fn(Statut $status) => $status->getCode() !== TrackingMovement::TYPE_PICK_LU)
                ->toArray(),
            'redirectAfterTrackingMovementCreation' => $redirectAfterTrackingMovementCreation,
            'champsLibres' => $champLibreRepository->findByCategoryTypeLabels([CategoryType::MOUVEMENT_TRACA]),
            'fields' => $fields,
            'filterArticle' => $article,
            "initial_tracking_movements" => $this->api($request, $trackingMovementService)->getContent(),
            "initial_visible_columns" => $this->apiColumns($entityManager, $trackingMovementService)->getContent(),
            "initial_filters" => json_encode($filterSupService->getFilters($entityManager, FiltreSup::PAGE_MVT_TRACA)),
        ]);
    }

    #[Route("/api-columns", name: "tracking_movement_api_columns", options: ["expose" => true], methods: ["GET","POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_MOUV], mode: HasPermission::IN_JSON)]
    public function apiColumns(EntityManagerInterface $entityManager, TrackingMovementService $trackingMovementService): Response {

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $columns = $trackingMovementService->getVisibleColumnsConfig($entityManager, $currentUser);

        return $this->json(array_values($columns));
    }

    #[Route("/colonne-visible", name: "save_column_visible_for_tracking_movement", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_MOUV], mode: HasPermission::IN_JSON)]
    public function saveColumnVisible(Request $request,
                                      EntityManagerInterface $entityManager,
                                      VisibleColumnService $visibleColumnService): Response {

        $data = json_decode($request->getContent(), true);
        $fields = array_keys($data);
        $fields[] = "actions";
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $visibleColumnService->setVisibleColumns('trackingMovement', $fields, $currentUser);

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => 'Vos préférences de colonnes à afficher ont bien été sauvegardées'
        ]);
    }

    #[Route("/creer", name: "mvt_traca_new", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::CREATE], mode: HasPermission::IN_JSON)]
    public function new(Request $request,
                        AttachmentService $attachmentService,
                        TrackingMovementService $trackingMovementService,
                        FreeFieldService $freeFieldService,
                        EntityManagerInterface $entityManager): Response {

        $post = $request->request;
        $forced = $post->get('forced', false);
        $isNow = $post->getBoolean('now');

        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $articleRepository = $entityManager->getRepository(Article::class);

        $operatorId = $post->get('operator');
        if (!empty($operatorId)) {
            $operator = $utilisateurRepository->find($operatorId);
        }
        if (empty($operator)) {
            /** @var Utilisateur $operator */
            $operator = $this->getUser();
        }

        $packCode = $post->get('pack');
        $commentaire = $post->get('commentaire');
        $quantity = $post->getInt('quantity') ?: 1;
        $articles = $post->get('articles') ?: null;
        if($articles) {
            $articles = $articleRepository->findBy([
                "id" => explode(",", $articles),
            ]);
        }

        if ($quantity < 1) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'La quantité doit être supérieure à 0.'
            ]);
        }

        if($isNow) {
            $date = new DateTime();
        } else {
            $user = $this->getUser();
            $format = $user && $user->getDateFormat() ? "{$user->getDateFormat()} H:i" : "d/m/Y H:i";
            $date = $this->formatService->parseDatetime($post->get("datetime"), [$format]) ?: new DateTime();
        }

        $fileBag = $request->files->count() > 0 ? $request->files : null;

        $codeToPack = [];
        $createdMouvements = [];
        try {
            if (!empty($post->get('is-group'))) {
                $groupTreatment = $trackingMovementService->handleGroups($post->all(), $entityManager, $operator, $date);
                if (!$groupTreatment['success']) {
                    return $this->json($groupTreatment);
                }

                $createdMouvements = $groupTreatment['createdMovements'];
            }
            else if (empty($post->get('is-mass'))) {
                $location = $emplacementRepository->find($post->get('emplacement'));

                $res = $trackingMovementService->persistTrackingMovementForPackOrGroup(
                    $entityManager,
                    $packCode,
                    $location,
                    $operator,
                    $date,
                    null,
                    $post->getInt('type'),
                    $forced,
                    [
                        'commentaire' => $commentaire,
                        'quantity' => $quantity,
                        'articles' => $articles,
                    ]
                );

                if ($res['success']) {
                    if (empty($res['multiple'])) {
                        $createdMouvements[] = $res['movement'];
                    }
                    else {
                        array_push($createdMouvements, ...$res['movements']);
                    }
                }
                else {
                    return $this->json($this->treatPersistTrackingError($res));
                }
            }
            else {
                $packArrayFiltered = Stream::explode(',', $packCode)
                    ->filterMap(fn(string $code) => $code ? trim($code) : $code);
                $pickingLocation = $emplacementRepository->find($post->get('emplacement-prise'));
                $dropLocation = $emplacementRepository->find($post->get('emplacement-depose'));
                foreach ($packArrayFiltered as $pack) {
                    $pickingRes = $trackingMovementService->persistTrackingMovementForPackOrGroup(
                        $entityManager,
                        $codeToPack[$pack] ?? $pack,
                        $pickingLocation,
                        $operator,
                        $date,
                        true,
                        TrackingMovement::TYPE_PRISE,
                        $forced,
                        [
                            'commentaire' => $commentaire,
                            'quantity' => $quantity,
                        ]
                    );

                    if ($pickingRes['success']) {
                        if (empty($pickingRes['multiple'])) {
                            $createdMouvements[] = $pickingRes['movement'];
                            $mainPack = $pickingRes['movement']->getPack();
                        }
                        else {
                            array_push($createdMouvements, ...$pickingRes['movements']);
                            $mainPack = $pickingRes['parent'] ?? null;
                        }
                    }
                    else {
                        return $this->json($this->treatPersistTrackingError($pickingRes));
                    }
 /// Modification test
                    $dropRes = $trackingMovementService->persistTrackingMovementForPackOrGroup(
                        $entityManager,
                        $mainPack ?? $pack,
                        $dropLocation,
                        $operator,
                        $date,
                        true,
                        TrackingMovement::TYPE_DEPOSE,
                        $forced,
                        [
                            'commentaire' => $commentaire,
                            'quantity' => $quantity,
                        ]
                    );

                    if ($dropRes['success']) {
                        if (empty($dropRes['multiple'])) {
                            $createdMouvements[] = $dropRes['movement'];
                            $createdPack = $dropRes['movement']->getPack();
                        }
                        else {
                            array_push($createdMouvements, ...$dropRes['movements']);
                            $createdPack = $dropRes['parent'] ?? null;
                        }
                    }
                    else {
                        return $this->json($this->treatPersistTrackingError($dropRes));
                    }

                    $codeToPack[$pack] = $createdPack;
                }
            }
        } catch (Exception $exception) {
            if($exception->getMessage() === Pack::PACK_IS_GROUP) {
                return $this->json([
                    "success" => false,
                    "msg" => "L'unité logistique scannée est un groupe",
                ]);
            } else {
                // uncomment following line to debug
                // throw $exception;

                return $this->json([
                    "success" => false,
                    "msg" => "Une erreur est survenue lors du traitement de la requête",
                ]);
            }
        }

        if (isset($fileBag)) {
            $fileNames = [];
            foreach ($fileBag->all() as $file) {
                $fileNames = array_merge(
                    $fileNames,
                    $attachmentService->saveFile($file)
                );
            }
            foreach ($createdMouvements as $mouvement) {
                $this->persistAttachments($mouvement, $attachmentService, $fileNames, $entityManager);
            }
        }

        foreach ($createdMouvements as $mouvement) {
            $freeFieldService->manageFreeFields($mouvement, $post->all(), $entityManager, $this->getUser());
        }
        $countCreatedMouvements = count($createdMouvements);
        $entityManager->flush();

        return new JsonResponse([
            'success' => $countCreatedMouvements > 0,
            'group' => null,
            'trackingMovementsCounter' => $countCreatedMouvements,
            'packs' => 3
        ]);
    }

    #[Route("/api", name: "tracking_movement_api", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_MOUV], mode: HasPermission::IN_JSON)]
    public function api(Request $request, TrackingMovementService $trackingMovementService): Response
    {
        $data = $trackingMovementService->getDataForDatatable($request->request);

        return new JsonResponse($data);
    }

    #[Route("/est-dans-ul/{barcode}", name: "tracking_movement_is_in_lu", options: ["expose" => true], methods: ["GET"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_MOUV], mode: HasPermission::IN_JSON)]
    public function isInLU(EntityManagerInterface $manager, string $barcode): Response
    {
        $article = $manager->getRepository(Article::class)->isInLogisticUnit($barcode);

        return $this->json([
            "success" => true,
            "in_logistic_unit" => !empty($article),
            "logistic_unit" => $article?->getCurrentLogisticUnit()?->getCode(),
        ]);
    }

    #[Route("/api-modifier", name: "tracking_movement_api_edit", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function editApi(EntityManagerInterface $entityManager,
                            UserService $userService,
                            Request $request): Response {

        if ($data = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
            $champLibreRepository = $entityManager->getRepository(FreeField::class);

            $trackingMovement = $trackingMovementRepository->find($data['id']);
            $statuses = $statutRepository->findByCategorieName(CategorieStatut::MVT_TRACA);

            $json = $this->renderView('mouvement_traca/modalEditMvtTracaContent.html.twig', [
                'mvt' => $trackingMovement,
                'statuts' => Stream::from($statuses)
                    ->filter(fn(Statut $status) => $status->getCode() !== TrackingMovement::TYPE_PICK_LU)
                    ->toArray(),
                'attachments' => $trackingMovement->getAttachments(),
                'champsLibres' => $champLibreRepository->findByCategoryTypeLabels([CategoryType::MOUVEMENT_TRACA]),
                'editAttachments' => $userService->hasRightFunction(Menu::TRACA, Action::EDIT),
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    #[Route("/modifier", name: "mvt_traca_edit", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function edit(EntityManagerInterface $entityManager,
                         FreeFieldService $freeFieldService,
                         AttachmentService $attachmentService,
                         TrackingMovementService $trackingMovementService,
                         UserService $userService,
                         Request $request): Response {

        $post = $request->request;

        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        $operator = $utilisateurRepository->find($post->get('operator'));
        $newLocation = $locationRepository->find($post->get('location'));

        $quantity = $post->getInt('quantity') ?: 1;

        if ($quantity < 1) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'La quantité doit être supérieure à 0.'
            ]);
        }
        $mvt = $trackingMovementRepository->find($post->get('id'));
        $pack = $mvt->getPack();

        $newDate = $this->formatService->parseDatetime($post->get('date'));
        $newCode = $post->get('pack');
        $currentDate = clone $mvt->getDatetime();
        $currentDate = $currentDate->setTime($currentDate->format('H'), $currentDate->format('i'), 0);

        $hasChanged = (
            $mvt->getEmplacement()?->getLabel() !== $newLocation?->getLabel()
            || $currentDate != $newDate // required != comparison
            || $pack->getCode() !== $newCode
        );

        $mainMvt = $mvt->getMainMovement();
        $linkedMouvements = $trackingMovementRepository->findBy(['mainMovement' => $mvt]);

        if ($userService->hasRightFunction(Menu::TRACA, Action::FULLY_EDIT_TRACKING_MOVEMENTS) && $hasChanged) {
            $response = $trackingMovementService->persistTrackingMovement(
                $entityManager,
                $post->get('pack'),
                $newLocation,
                $operator,
                $newDate,
                true,
                $mvt->getType(),
                false,
                ['disableUngrouping'=> true, 'ignoreProjectChange' => true, 'mainMovement'=>$mainMvt],
                true,
            );
            if ($response['success']) {
                /** @var TrackingMovement $new */
                $new = $response['movement'];
                $trackingMovementService->manageLinksForClonedMovement($mvt, $new);

                foreach ($linkedMouvements as $linkedMvt) {
                    $linkedMvt->setMainMovement($new);
                }

                $entityManager->persist($new);
                $entityManager->remove($mvt);
                $entityManager->flush();

                $pack->setLastTracking($trackingMovementRepository->findLastTrackingMovement($pack, null));
                $dropType =  $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, TrackingMovement::TYPE_DEPOSE);
                $pack->setLastDrop($trackingMovementRepository->findLastTrackingMovement($pack, $dropType));

                $mvt = $new;
            } else {
                return $this->json($response);
            }

        }
        /** @var TrackingMovement $mvt */
        $mvt
            ->setOperateur($operator)
            ->setQuantity($quantity)
            ->setCommentaire($post->get('commentaire'));

        $entityManager->flush();

        $listAttachmentIdToKeep = $post->all('files');
        $attachments = $mvt->getAttachments()->toArray();
        foreach ($attachments as $attachment) {
            /** @var Attachment $attachment */
            if (!$listAttachmentIdToKeep || !in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                $attachmentService->removeAndDeleteAttachment($attachment, $mvt);
            }
        }
        $this->persistAttachments($mvt, $attachmentService, $request->files, $entityManager, ['addToDispatch' => true]);
        $freeFieldService->manageFreeFields($mvt, $post->all(), $entityManager);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true
        ]);
    }

    #[Route("/supprimer", name: "mvt_traca_delete", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
            /** @var TrackingMovement $trackingMovement */
            $trackingMovement = $trackingMovementRepository->find($data['mvt']);

            if($trackingMovement) {
                $entityManager->remove($trackingMovement);
                $entityManager->flush();
            }

            return $this->json([
                "success" => true,
            ]);
        }

        throw new BadRequestHttpException();
    }

    #[Route("/csv", name: "get_mouvements_traca_csv", options: ["expose" => true], methods: ["GET"])]
    public function getTrackingMovementCSV(Request                 $request,
                                           CSVExportService        $CSVExportService,
                                           TrackingMovementService $trackingMovementService,
                                           FreeFieldService        $freeFieldService,
                                           TranslationService      $translationService,
                                           EntityManagerInterface  $entityManager): Response {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
        $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');


        if (isset($dateTimeMin) && isset($dateTimeMax)) {
            $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);

            $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::MVT_TRACA]);

            if (!empty($dateTimeMin) && !empty($dateTimeMax)) {
                $csvHeader = array_merge([
                    $translationService->translate('Traçabilité', 'Général', 'Date', false),
                    $translationService->translate('Traçabilité', 'Général', 'Unité logistique', false),
                    $translationService->translate('Traçabilité', 'Général', 'Emplacement', false),
                    $translationService->translate('Traçabilité', 'Général', 'Quantité', false),
                    $translationService->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Type', false),
                    $translationService->translate('Traçabilité', 'Général', 'Opérateur', false),
                    $translationService->translate('Général', null, 'Modale', 'Commentaire', false),
                    $translationService->translate('Général', null, 'Modale', 'Pièces jointes', false),
                    $translationService->translate('Traçabilité', 'Général', 'Issu de', false),
                    $translationService->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'N° commande / BL', false),
                    $translationService->translate('Traçabilité', 'Arrivages UL', 'Divers', 'Urgence', false),
                    $translationService->translate('Traçabilité', 'Unités logistiques', "Onglet \"Groupes\"", 'Groupe', false),
                ], $freeFieldsConfig['freeFieldsHeader']);

                $trackingMovements = $trackingMovementRepository->iterateByDates($dateTimeMin, $dateTimeMax);

                return $CSVExportService->streamResponse(
                    function ($output) use ($trackingMovements, $CSVExportService, $trackingMovementService, $freeFieldsConfig) {
                        foreach ($trackingMovements as $movement) {
                            $trackingMovementService->putMovementLine(
                                $output,
                                $CSVExportService,
                                $movement,
                                $freeFieldsConfig
                            );
                        }
                    }, 'Export_Mouvement_Traca.csv',
                    $csvHeader
                );

            }
        }

        throw new BadRequestHttpException();
    }

    #[Route("/voir", name: "mvt_traca_show", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_MOUV], mode: HasPermission::IN_JSON)]
    public function show(EntityManagerInterface $entityManager, Request $request): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $trackingMovement = $entityManager->find(TrackingMovement::class, $data);

            return $this->json($this->renderView('mouvement_traca/modalShowMvtTracaContent.html.twig', [
                "mvt" => $trackingMovement,
            ]));
        }

        throw new BadRequestHttpException();
    }

    #[Route("/obtenir-corps-modal-nouveau", name: "mouvement_traca_get_appropriate_html", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_MOUV], mode: HasPermission::IN_JSON)]
    public function getAppropriateHtml(Request $request, EntityManagerInterface $entityManager, SpecificService $specificService): Response
    {
        if ($typeId = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);

            $templateDirectory = "mouvement_traca";

            $appropriateType = $statutRepository->find($typeId);
            $fileToRender = match($appropriateType?->getCode()) {
                TrackingMovement::TYPE_PRISE_DEPOSE => "$templateDirectory/newMassMvtTraca.html.twig",
                TrackingMovement::TYPE_GROUP => "$templateDirectory/newGroupMvtTraca.html.twig",
                TrackingMovement::TYPE_DROP_LU => "$templateDirectory/newLUMvtTraca.html.twig",
                default => "$templateDirectory/newSingleMvtTraca.html.twig"
            };

            return $this->json([
                "modalBody" => $fileToRender === 'mouvement_traca/' ? false : $this->renderView($fileToRender),
            ]);
        }

        throw new BadRequestHttpException();
    }

    #[Route("/tracking-movement-logistic-unit-location", name: "tracking_movement_logistic_unit_location", options: ["expose" => true], methods: ["GET"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_MOUV], mode: HasPermission::IN_JSON)]
    public function getLULocation(EntityManagerInterface $entityManager, TranslationService $translationService, Request $request): Response
    {
        $packRepository = $entityManager->getRepository(Pack::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $code = $request->query->get('code');

        /** @var Pack $pack */
        $pack = $packRepository->findOneBy(["code" => $code]);
        $article = $pack?->getArticle() ?? $articleRepository->findOneBy(["barCode" => $code]);

        if($article) {
            return $this->json([
                "success" => true,
                "error" => $translationService->translate("Traçabilité", "Mouvements", "L'unité logistique ne doit pas correspondre à un article"),
            ]);
        }

        $location = $pack?->getLastTracking()?->getEmplacement();

        return $this->json([
            "success" => true,
            "error" => false,
            "location" => $location ? [
                "id" => $location->getId(),
                "label" => $location->getLabel(),
            ] : null,
        ]);
    }

    #[Route("/tracking-movement-logistic-unit-quantity", name: "tracking_movement_logistic_unit_quantity", options: ["expose" => true], methods: ["GET"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_MOUV], mode: HasPermission::IN_JSON)]
    public function getLUQuantity(EntityManagerInterface $entityManager, TranslationService $translationService, Request $request): Response
    {
        $packRepository = $entityManager->getRepository(Pack::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $code = $request->query->get('code');

        /** @var Pack $pack */
        $pack = $packRepository->findOneBy(["code" => $code]);
        $articles = $pack?->getChildArticles() ?? $articleRepository->findBy(["barCode" => $code]);
        $quantity = Stream::from($articles)
            ->map(fn (Article $article) => ($article->getQuantite()))
            ->sum();

        return $this->json([
            "success" => true,
            "error" => false,
            "quantity" => $quantity > 0 ? $quantity : null, //regle de gestion : l'UL doit contenir au moins un article pour qu'on grise le champ
        ]);
    }

    private function persistAttachments(TrackingMovement $trackingMovement, AttachmentService $attachmentService, $files, EntityManagerInterface $entityManager ,  array $options = [])
    {
        $isAddToDispatch = $options['addToDispatch'] ?? false;
        $attachments = $attachmentService->createAttachments($files);
        foreach ($attachments as $attachment) {
            $entityManager->persist($attachment);
            $trackingMovement->addAttachment($attachment);
            if ($isAddToDispatch && $trackingMovement->getDispatch()) {
                $trackingMovement->getDispatch()->addAttachment($attachment);
            }
        }
    }

    private function treatPersistTrackingError(array $res): array {
        if (isset($res["error"])) {
            if ($res["error"] === Pack::CONFIRM_CREATE_GROUP) {
                return [
                    "success" => true,
                    "group" => $res["group"],
                ];
            } else if ($res['error'] === Pack::IN_ONGOING_RECEPTION) {
                return [
                    "success" => false,
                    "msg" => $this->translationService->translate("Traçabilité", "Mouvements", "L'unité logistique est dans une réception en attente et ne peut pas être mouvementé"),
                ];
            }

            throw new Exception('untreated error');
        }
        else {
            return $res;
        }
    }
}
