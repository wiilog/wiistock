<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\FreeField\FreeField;
use App\Entity\Menu;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Service\AttachmentService;
use App\Service\CSVExportService;
use App\Service\DataExportService;
use App\Service\FilterSupService;
use App\Service\FreeFieldService;
use App\Service\SettingsService;
use App\Service\TrackingMovementService;
use App\Service\TranslationService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

#[Route("/mouvement-traca")]
class TrackingMovementController extends AbstractController
{

    #[Required]
    public TranslationService $translationService;

    #[Route("/", name: "mvt_traca_index", options: ["expose" => true])]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_MOUV])]
    public function index(Request                 $request,
                          EntityManagerInterface  $entityManager,
                          FilterSupService        $filterSupService,
                          TrackingMovementService $trackingMovementService): Response {
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository =  $entityManager->getRepository(Type::class);

        $currentUser = $this->getUser();
        $packFilter = $request->query->get('pack');
        $article = null;
        $filterArticle = $request->query->get('article');
        if($filterArticle) {
            $article = $entityManager->getRepository(Article::class)->find($filterArticle);
            $request->request->add(['article' => $filterArticle]);
        }

        if (!empty($packFilter)) {
            $packRepository = $entityManager->getRepository(Pack::class);
            $filtreSupRepository->clearFiltersByUserAndPage($currentUser, FiltreSup::PAGE_MVT_TRACA);
            $packId = $packRepository->findOneBy(["code" => $packFilter])?->getId();
            if ($packId) {
                $packFilter = $packId . ':' . $packFilter;
                $filter = $filterSupService->createFiltreSup(FiltreSup::PAGE_MVT_TRACA, FiltreSup::FIELD_LOGISTIC_UNITS, $packFilter, $currentUser);

                $entityManager->persist($filter);
            }

            $entityManager->flush();
        }

        $fields = $trackingMovementService->getVisibleColumnsConfig($entityManager, $currentUser);

        $mvtStatuses = $statutRepository->findByCategorieName(CategorieStatut::MVT_TRACA);
        $statuses = Stream::from($mvtStatuses)
            ->filter(static fn(Statut $statut) => $statut->getCode() !== TrackingMovement::TYPE_PRISE_DEPOSE)
            ->toArray();

        $request->request->add(['length' => 10]);
        return $this->render('tracking_movement/index.html.twig', [
            'statuts' => $statuses,
            'form_statuses' => Stream::from($mvtStatuses)
                ->filter(fn(Statut $status) => !in_array($status->getCode(), [TrackingMovement::TYPE_PICK_LU, TrackingMovement::TYPE_INIT_TRACKING_DELAY]))
                ->toArray(),
            'fields' => $fields,
            'filterArticle' => $article,
            'type' => $typeRepository->findOneByLabel(Type::LABEL_MVT_TRACA),
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

        $user = $this->getUser();
        $format = $user && $user->getDateFormat() ? "{$user->getDateFormat()} H:i" : "d/m/Y H:i";

        if ($quantity < 1) {
            throw new FormException("La quantité doit être supérieure à 0.");
        }

        if($isNow) {
            $date = new DateTime();
        } else {
            $date = $this->formatService->parseDatetime($post->get("datetime"), [$format]) ?: new DateTime();
        }

        $createdMovements = [];
        try {
            if (!empty($post->get('is-group'))) {
                $groupTreatment = $trackingMovementService->handleGroups($post->all(), $entityManager, $operator, $date);
                if (!$groupTreatment['success'] || ($groupTreatment["error"] ?? false)) {
                    return $this->json($groupTreatment);
                }

                $createdMovements = $groupTreatment['createdMovements'];
            }
            else if (empty($post->get( 'is-mass'))) {
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
                    array_push($createdMovements, ...$res['movements']);
                }
                else {
                    return $this->json($trackingMovementService->treatPersistTrackingError($res));
                }
            }
            else {
                $codeToPack = [];
                $packArrayFiltered = Stream::explode(',', $packCode)
                    ->filterMap(fn(string $code) => $code ? trim($code) : $code);
                $type = $trackingMovementService->getTrackingType($entityManager, $post->getInt('type'));
                $pickingLocationId = in_array($type->getCode(), [TrackingMovement::TYPE_PRISE, TrackingMovement::TYPE_PRISE_DEPOSE])
                    ? $post->get('emplacement-prise')
                    : $post->get('emplacement');
                $pickingLocation = $emplacementRepository->find($pickingLocationId);

                if ($type->getCode() !== TrackingMovement::TYPE_PRISE){
                    $dropLocationId = $type->getCode() === TrackingMovement::TYPE_PRISE_DEPOSE
                        ? $post->get('emplacement-depose')
                        : $post->get('emplacement');
                    $dropLocation = $emplacementRepository->find($dropLocationId);
                }

                foreach ($packArrayFiltered as $pack) {
                    // allow to prevent the creation of a pack with a comma in its code
                    if($pack === '') {
                        return new JsonResponse([
                            'success' => false,
                            'msg' => 'Le code d\'unité logistique ne peut pas être vide.'
                        ]);
                    }
                    if(in_array($type->getCode(), [TrackingMovement::TYPE_PRISE, TrackingMovement::TYPE_PRISE_DEPOSE])){
                        $manualDelayStart = $post->get('manualDelayStart') ?
                            $this->formatService->parseDatetime($post->get('manualDelayStart'), ["Y-m-d"])
                            : null;

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
                                    'manualDelayStart' => $manualDelayStart,
                                ]
                            );


                        if ($pickingRes['success']) {
                            array_push($createdMovements, ...$pickingRes['movements']);

                            $codeToPack = Stream::from($pickingRes['movements'])
                                ->keymap(static function (TrackingMovement $movement) {
                                    $pack = $movement->getPack();

                                    return [$pack->getCode(), $pack];
                                })
                                ->concat($codeToPack, true)
                                ->toArray();
                        } else {
                            return $this->json($trackingMovementService->treatPersistTrackingError($pickingRes));
                        }
                    }

                    if(in_array($type->getCode(), [TrackingMovement::TYPE_DEPOSE, TrackingMovement::TYPE_PRISE_DEPOSE])){
                        $dropRes = $trackingMovementService->persistTrackingMovementForPackOrGroup(
                            $entityManager,
                            $codeToPack[$pack] ?? $pack,
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
                            array_push($createdMovements, ...$dropRes['movements']);

                            $codeToPack = Stream::from($dropRes['movements'])
                                ->keymap(static function(TrackingMovement $movement) {
                                    $pack = $movement->getPack();

                                    return [$pack->getCode(), $pack];
                                })
                                ->concat($codeToPack, true)
                                ->toArray();
                        }
                        else {
                            return $this->json($trackingMovementService->treatPersistTrackingError($dropRes));
                        }
                    }
                }
            }
        } catch (Exception $exception) {
            if($exception->getMessage() === Pack::PACK_IS_GROUP) {
                throw new FormException("L'unité logistique scannée est un groupe");
            } else {
                // uncomment following line to debug
                // throw $exception;
                throw new FormException("Une erreur est survenue lors du traitement de la requête");
            }
        }

        /** @var TrackingMovement[] $createdMovements */
        foreach ($createdMovements as $movement) {
            $freeFieldService->manageFreeFields($movement, $post->all(), $entityManager, $this->getUser());
            if (!isset($trackingAttachments)) {
                // first time we upload attachments
                $trackingAttachments = $attachmentService->persistAttachments($entityManager, $request->files, ["attachmentContainer" => $movement]);
            }
            else {
                // next attachment are already saved, we link them to the movement
                $movement->setAttachments($trackingAttachments);
            }
        }

        $countCreatedMouvements = count($createdMovements);
        $entityManager->flush();

        return $this->json([
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

    #[Route("/api-modifier/{trackingMovement}", name: "tracking_movement_api_edit", options: ["expose" => true], methods: [self::GET], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function editApi(EntityManagerInterface $entityManager,
                            UserService            $userService,
                            TrackingMovement       $trackingMovement): Response {
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $typeRepository =  $entityManager->getRepository(Type::class);

        $html = $this->renderView('tracking_movement/form/edit.html.twig', [
            'mvt' => $trackingMovement,
            'type' => $typeRepository->findOneByLabel(Type::LABEL_MVT_TRACA),
            'attachments' => $trackingMovement->getAttachments(),
            'champsLibres' => $champLibreRepository->findByCategoryTypeLabels([CategoryType::MOUVEMENT_TRACA]),
            'editAttachments' => $userService->hasRightFunction(Menu::TRACA, Action::EDIT),
        ]);

        return new JsonResponse([
            "success" => true,
            "html" => $html,
        ]);
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
        $trackingMovement = $trackingMovementRepository->find($post->get('id'));
        $pack = $trackingMovement->getPack();

        $newDate = $this->formatService->parseDatetime($post->get('date'));
        $newCode = $post->get('pack');
        $currentDate = clone $trackingMovement->getDatetime();
        $currentDate = $currentDate->setTime($currentDate->format('H'), $currentDate->format('i'), 0);

        $hasChanged = (
            $trackingMovement->getEmplacement()?->getLabel() !== $newLocation?->getLabel()
            || $currentDate != $newDate // required != comparison
            || $pack->getCode() !== $newCode
        );

        $mainMvt = $trackingMovement->getMainMovement();
        $linkedMouvements = $trackingMovementRepository->findBy(['mainMovement' => $trackingMovement]);

        if ($userService->hasRightFunction(Menu::TRACA, Action::FULLY_EDIT_TRACKING_MOVEMENTS) && $hasChanged) {
            $response = $trackingMovementService->persistTrackingMovement(
                $entityManager,
                $post->get('pack'),
                $newLocation,
                $operator,
                $newDate,
                true,
                $trackingMovement->getType(),
                false,
                ['disableUngrouping'=> true, 'ignoreProjectChange' => true, 'mainMovement'=>$mainMvt],
                true,
            );
            if ($response['success']) {
                /** @var TrackingMovement $new */
                $new = $response['movement'];
                $trackingMovementService->manageLinksForClonedMovement($trackingMovement, $new);

                foreach ($linkedMouvements as $linkedMvt) {
                    $linkedMvt->setMainMovement($new);
                }

                $entityManager->persist($new);
                $entityManager->flush();

                $entityManager->remove($trackingMovement);
                $trackingMovement = $new;
            } else {
                return $this->json($response);
            }

        }
        /** @var TrackingMovement $trackingMovement */
        $trackingMovement
            ->setOperateur($operator)
            ->setQuantity($quantity)
            ->setCommentaire($post->get('commentaire'));

        $attachmentService->removeAttachments($entityManager, $trackingMovement, $post->all('files') ?: []);
        $attachments = $attachmentService->persistAttachments($entityManager, $request->files, ["attachmentContainer" => $trackingMovement]);

        $movementDispatch = $trackingMovement->getDispatch();
        if ($movementDispatch) {
            foreach ($attachments as $attachment) {
                $movementDispatch->addAttachment($attachment);
            }
        }


        $freeFieldService->manageFreeFields($trackingMovement, $post->all(), $entityManager);

        $entityManager->flush();

        return new JsonResponse([
            'success' => true
        ]);
    }

    #[Route("/supprimer/{trackingMovement}", name: "tracking-movement_delete", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function delete(TrackingMovement $trackingMovement,
                           EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($trackingMovement);
        $entityManager->flush();

        return $this->json([
            "success" => true,
        ]);

    }

    #[Route("/csv", name: "get_mouvements_traca_csv", options: ["expose" => true], methods: ["GET"])]
    public function getTrackingMovementCSV(Request                 $request,
                                           CSVExportService        $CSVExportService,
                                           DataExportService       $dataExportService,
                                           TrackingMovementService $trackingMovementService,
                                           FreeFieldService        $freeFieldService,
                                           EntityManagerInterface  $entityManager): Response {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
        $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');

        if (!empty($dateTimeMin) && !empty($dateTimeMax)) {
            $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);

            $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::MVT_TRACA]);
            $loggedUser = $this->getUser();
            $userDateFormat = $this->getUser()->getDateFormat();

            $trackingMovements = $trackingMovementRepository->getByDates($dateTimeMin, $dateTimeMax, $userDateFormat);

            $exportableColumns = Stream::from($trackingMovementService->getTrackingMovementExportableColumns($entityManager))
                ->reduce(
                    static function (array $carry, array $column) {
                        $carry["labels"][] = $column["label"] ?? '';
                        $carry["codes"][] = $column["code"] ?? '';
                        return $carry;
                    },
                    ["labels" => [], "codes" => []]
                );

            return $CSVExportService->streamResponse(
                function ($output) use ($trackingMovements, $dataExportService, $freeFieldsConfig, $exportableColumns, $loggedUser) {
                    $dataExportService->exportTrackingMovements($trackingMovements, $output, $exportableColumns["codes"], $freeFieldsConfig, $loggedUser);
                },
                'Export_Mouvement_Traca.csv',
                $exportableColumns["labels"]
            );
        }

        throw new BadRequestHttpException();
    }

    #[Route("/voir", name: "mvt_traca_show", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_MOUV], mode: HasPermission::IN_JSON)]
    public function show(EntityManagerInterface $entityManager, Request $request): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $trackingMovement = $entityManager->find(TrackingMovement::class, $data);

            return $this->json($this->renderView('tracking_movement/show.html.twig', [
                "mvt" => $trackingMovement,
            ]));
        }

        throw new BadRequestHttpException();
    }

    #[Route("/obtenir-corps-modal-nouveau", name: "mouvement_traca_get_appropriate_html", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_MOUV], mode: HasPermission::IN_JSON)]
    public function getAppropriateHtml(Request                $request,
                                       EntityManagerInterface $entityManager,
                                       SettingsService        $settingsService): Response
    {
        if ($typeId = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);

            $templateDirectory = "tracking_movement/form";
            $appropriateType = $statutRepository->find($typeId);

            $fileToRender = match($appropriateType?->getCode()) {
                TrackingMovement::TYPE_PRISE_DEPOSE => "$templateDirectory/newMassPickAndDrop.html.twig",
                TrackingMovement::TYPE_GROUP => "$templateDirectory/newGroup.html.twig",
                TrackingMovement::TYPE_DROP_LU => "$templateDirectory/newLU.html.twig",
                TrackingMovement::TYPE_PRISE, TrackingMovement::TYPE_DEPOSE => "$templateDirectory/newMass.html.twig",
                default => "$templateDirectory/newSingle.html.twig"
            };

            return $this->json([
                "modalBody" => $fileToRender === "tracking_movement/" ? false : $this->renderView($fileToRender, [
                    'isPickMovement' => $appropriateType?->getCode() === TrackingMovement::TYPE_PRISE,
                    'displayManualDelayStart' => $settingsService->getValue($entityManager, Setting::DISPLAY_MANUAL_DELAY_START),
                ]),
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

        $location = $pack?->getLastAction()?->getEmplacement();

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
}
