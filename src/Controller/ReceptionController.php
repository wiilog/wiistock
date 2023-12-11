<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\Attachment;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Dispute;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Fournisseur;
use App\Entity\FreeField;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\Pack;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\PurchaseRequest;
use App\Entity\PurchaseRequestLine;
use App\Entity\Reception;
use App\Entity\ReceptionLine;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\TagTemplate;
use App\Entity\TrackingMovement;
use App\Entity\TransferOrder;
use App\Entity\TransferRequest;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Service\ArticleDataService;
use App\Service\AttachmentService;
use App\Service\CSVExportService;
use App\Service\DeliveryRequestService;
use App\Service\DisputeService;
use App\Service\FormatService;
use App\Service\FreeFieldService;
use App\Service\LivraisonsManagerService;
use App\Service\MailerService;
use App\Service\MouvementStockService;
use App\Service\NotificationService;
use App\Service\PDFGeneratorService;
use App\Service\PreparationsManagerService;
use App\Service\ReceptionLineService;
use App\Service\ReceptionReferenceArticleService;
use App\Service\ReceptionService;
use App\Service\RefArticleDataService;
use App\Service\SettingsService;
use App\Service\TagTemplateService;
use App\Service\TrackingMovementService;
use App\Service\TransferOrderService;
use App\Service\TransferRequestService;
use App\Service\TranslationService;
use App\Service\UniqueNumberService;
use App\Service\VisibleColumnService;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;
use WiiCommon\Helper\Stream;

/**
 * @Route("/reception")
 */
class ReceptionController extends AbstractController {

    #[Required]
    public NotificationService $notificationService;

    #[Required]
    public FreeFieldService $freeFieldService;

    /**
     * @Route("/new", name="reception_new", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(EntityManagerInterface $entityManager,
                        FreeFieldService $champLibreService,
                        ReceptionService $receptionService,
                        AttachmentService $attachmentService,
                        Request $request,
                        TranslationService $translation): Response {
        if ($request->request) {
            $data = $request->request->all();
            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();
            $reception = $receptionService->persistReception($entityManager, $currentUser, $data);

            try {
                $entityManager->flush();
            }
            /** @noinspection PhpRedundantCatchClauseInspection */
            catch (UniqueConstraintViolationException $e) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => $translation->translate('Ordre', 'Réceptions', 'Une autre réception est en cours de création, veuillez réessayer.', false),
                ]);
            }

            $champLibreService->manageFreeFields($reception, $data, $entityManager);
            $attachmentService->manageAttachments($entityManager, $reception, $request->files);
            $entityManager->flush();

            $data = [
                "redirect" => $this->generateUrl('reception_show', [
                    'id' => $reception->getId(),
                ]),
            ];
            return new JsonResponse($data);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="reception_edit", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(EntityManagerInterface $entityManager,
                         FreeFieldService $champLibreService,
                         ReceptionService $receptionService,
                         AttachmentService $attachmentService,
                         FormatService $formatService,
                         Request $request): Response {
        $data = $request->request;
        if($data->all()) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $receptionRepository = $entityManager->getRepository(Reception::class);
            $transporteurRepository = $entityManager->getRepository(Transporteur::class);

            $reception = $receptionRepository->find($data->getInt('receptionId'));

            $statut = $statutRepository->find($data->getInt('statut'));
            $reception->setStatut($statut);

            if ($data->has('fournisseur')) {
                $fournisseur = $data->getInt('fournisseur') ? $fournisseurRepository->find($data->getInt('fournisseur')) : null;
                $reception->setFournisseur($fournisseur);
            }

            if ($data->has('utilisateur')) {
                $utilisateur = $data->getInt('utilisateur') ? $utilisateurRepository->find($data->getInt('utilisateur')) : null;
                $reception->setUtilisateur($utilisateur);
            }

            if ($data->has('transporteur')) {
                $transporteur = $data->getInt('transporteur') ? $transporteurRepository->find($data->getInt('transporteur')) : null;
                $reception->setTransporteur($transporteur);
            }

            if ($data->has('location')) {
                $location = $data->getInt('location') ? $emplacementRepository->find($data->getInt('location')) : null;
                $reception->setLocation($location);
            }

            if ($data->has('storageLocation')) {
                $storageLocation = $data->getInt('storageLocation') ? $emplacementRepository->find($data->getInt('storageLocation')) : null;
                $reception->setStorageLocation($storageLocation);
            }

            if ($data->has('emergency')) {
                $emergency = $data->getBoolean('emergency');
                $reception->setManualUrgent($emergency);
            }

            if ($data->has('orderNumber')) {
                $reception->setOrderNumber($data->get('orderNumber'));
            }

            if ($data->has('dateAttendue')) {
                $dateAttendue = $formatService->parseDatetime($data->get('dateAttendue'));
                $reception->setDateAttendue($dateAttendue);
            }

            if ($data->has('dateCommande')) {
                $dateCommande = $formatService->parseDatetime($data->get('dateCommande'));
                $reception->setDateCommande($dateCommande);
            }

            if ($data->has('commentaire')) {
                $reception->setCommentaire($data->get('commentaire'));
            }

            if ($data->has('files')) {
                $reception->removeIfNotIn($data->get('files') ?: []);
            }

            $entityManager->flush();

            $champLibreService->manageFreeFields($reception, $data->all(), $entityManager);
            $attachmentService->manageAttachments($entityManager, $reception, $request->files);

            $entityManager->flush();
            $json = [
                'entete' => $this->renderView('reception/show/header.html.twig', [
                    'modifiable' => $reception->getStatut()->getCode() !== Reception::STATUT_RECEPTION_TOTALE,
                    'reception' => $reception,
                    'showDetails' => $receptionService->createHeaderDetailsConfig($reception),
                ]),
                'success' => true,
                'msg' => 'La réception <strong>' . $reception->getNumber() . '</strong> a bien été modifiée.',
            ];
            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }


    /**
     * @Route("/api-modifier", name="api_reception_edit", options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function apiEdit(EntityManagerInterface $entityManager,
                            Request $request): Response {
        if($data = json_decode($request->getContent(), true)) {
            $typeRepository = $entityManager->getRepository(Type::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $champLibreRepository = $entityManager->getRepository(FreeField::class);
            $receptionRepository = $entityManager->getRepository(Reception::class);
            $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);

            $reception = $receptionRepository->find($data['id']);

            $listType = $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::RECEPTION);

            $typeChampLibre = [];
            $champsLibresEntity = [];
            foreach($listType as $type) {
                $champsLibresComplet = $champLibreRepository->findByType($type['id']);
                $champsLibres = [];
                //création array edit pour vue
                foreach($champsLibresComplet as $champLibre) {
                    $champsLibres[] = [
                        'id' => $champLibre->getId(),
                        'label' => $champLibre->getLabel(),
                        'typage' => $champLibre->getTypage(),
                        'elements' => ($champLibre->getElements() ? $champLibre->getElements() : ''),
                        'defaultValue' => $champLibre->getDefaultValue(),
                        'requiredEdit' => $champLibre->isRequiredEdit(),
                    ];
                    $champsLibresEntity[] = $champLibre;
                }

                $typeChampLibre[] = [
                    'typeLabel' => $type['label'],
                    'typeId' => $type['id'],
                    'champsLibres' => $champsLibres,
                ];
            }

            $fieldsParam = $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_RECEPTION);
            $json = $this->renderView('reception/show/modalEditReceptionContent.html.twig', [
                'reception' => $reception,
                'statuts' => $statutRepository->findByCategorieName(CategorieStatut::RECEPTION),
                'typeChampsLibres' => $typeChampLibre,
                'fieldsParam' => $fieldsParam,
                'freeFieldsGroupedByTypes' => $champsLibresEntity,
            ]);
            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api", name="reception_api", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_RECE}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request,
                        ReceptionService $receptionService,
                        EntityManagerInterface $entityManager): Response {
        $purchaseRequestFilter = $request->request->get('purchaseRequestFilter');
        $arrivalFilter = $request->request->get('arrivalFilter');

        /** @var Utilisateur $user */
        $user = $this->getUser();

        $data = $receptionService->getDataForDatatable($user, $request->request, $arrivalFilter, $purchaseRequestFilter);

        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);
        $fieldsParam = $fieldsParamRepository->getHiddenByEntity(FixedFieldStandard::ENTITY_CODE_RECEPTION);
        $data['columnsToHide'] = $fieldsParam;

        return new JsonResponse($data);
    }

    /**
     * @Route("/colonne-visible", name="save_column_visible_for_reception", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_RECE}, mode=HasPermission::IN_JSON)
     */
    public function saveColumnVisible(Request $request,
                                      EntityManagerInterface $entityManager,
                                      VisibleColumnService $visibleColumnService): Response {
        $data = json_decode($request->getContent(), true);

        $fields = array_keys($data);
        /** @var $user Utilisateur */
        $user = $this->getUser();
        $fields[] = "actions";

        $visibleColumnService->setVisibleColumns('reception', $fields, $user);

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => 'Vos préférences de colonnes à afficher ont bien été sauvegardées',
        ]);
    }

    /**
     * @Route("/api-columns", name="reception_api_columns", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_RECE}, mode=HasPermission::IN_JSON)
     */
    public function apiColumns(ReceptionService $receptionService): Response {
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $columns = $receptionService->getColumnVisibleConfig($currentUser);
        return $this->json($columns);
    }

    /**
     * @Route("/liste/{purchaseRequest}", name="reception_index", methods={"GET", "POST"}, options={"expose"=true})
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_RECE})
     * @return Response
     */
    public function index(EntityManagerInterface $entityManager,
                          ReceptionService       $receptionService,
                          SettingsService        $settingsService,
                          Request                $request,
                          PurchaseRequest        $purchaseRequest = null): Response
    {
        $arrivageData = null;

        if ($arrivageId = $request->query->get('arrivage')) {
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $arrivage = $arrivageRepository->find($arrivageId);
            if ($arrivage) {
                $arrivageData = [
                    'id' => $arrivageId,
                    'fournisseur' => $arrivage->getFournisseur(),
                    'transporteur' => $arrivage->getTransporteur(),
                    'numCommande' => $arrivage->getNumeroCommandeList(),
                ];
            }
        }
        $purchaseRequestLinesOrderNumbers = [];
        if ($purchaseRequest) {
            $purchaseRequestLinesOrderNumbers = $purchaseRequest->getPurchaseRequestLines()
                ->map(function (PurchaseRequestLine $purchaseRequestLine) {
                    return $purchaseRequestLine->getOrderNumber();
                })->toArray();
        }
        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);

        //TODO à modifier si plusieurs types possibles pour une réception
        $listType = $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::RECEPTION);
        $fieldsParam = $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_RECEPTION);

        $typeChampLibre = [];
        foreach($listType as $type) {
            $champsLibres = $champLibreRepository->findByType($type['id']);
            $typeChampLibre[] = [
                'typeLabel' => $type['label'],
                'typeId' => $type['id'],
                'champsLibres' => $champsLibres,
            ];
        }
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $fields = $receptionService->getColumnVisibleConfig($user);

        if($arrivageData){
            $arrivalFilter = implode(',', $arrivageData['numCommande']);
            $arrivageData['arrivalFilter'] = $arrivalFilter;
        }

        return $this->render('reception/index.html.twig', [
            'typeChampLibres' => $typeChampLibre,
            'fieldsParam' => $fieldsParam,
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::RECEPTION),
            'receptionLocation' => $settingsService->getParamLocation(Setting::DEFAULT_LOCATION_RECEPTION),
            'purchaseRequestFilter' => $purchaseRequest ? implode(',', $purchaseRequestLinesOrderNumbers) : 0,
            'purchaseRequest' => $purchaseRequest ? $purchaseRequest->getId() : '',
            'fields' => $fields,
            'arrivageToReception' => $arrivageData,
        ]);
    }

    /**
     * @Route("/supprimer", name="reception_delete", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request,
                           RefArticleDataService $refArticleDataService,
                           EntityManagerInterface $entityManager): Response {
        if($data = json_decode($request->getContent(), true)) {
            $articleRepository = $entityManager->getRepository(Article::class);
            $receptionRepository = $entityManager->getRepository(Reception::class);
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);
            $purchaseRequestLineRepository = $entityManager->getRepository(PurchaseRequestLine::class);

            /** @var Reception $reception */
            $reception = $receptionRepository->find($data['receptionId']);

            if ($reception) {
                $refsToUpdate = [];
                /** @var ReceptionLine $line */
                foreach ($reception->getLines()->toArray() as $line) {
                    /** @var ReceptionReferenceArticle $receptionReferenceArticle */
                    foreach ($line->getReceptionReferenceArticles() as $receptionReferenceArticle) {
                        $reference = $receptionReferenceArticle->getReferenceArticle();
                        $refsToUpdate[] = $reference;
                        $entityManager->remove($receptionReferenceArticle);
                        $articleRepository->setNullByReception($receptionReferenceArticle);
                    }
                    $reception->removeLine($line);
                    $entityManager->remove($line);
                }

                foreach ($reception->getPurchaseRequestLines() as $line) {
                    $line->setReception(null);
                }

                foreach ($reception->getTrackingMovements() as $receptionMvtTraca) {
                    $entityManager->remove($receptionMvtTraca);
                }
                $entityManager->flush();
                foreach ($refsToUpdate as $reference) {
                    $refArticleDataService->setStateAccordingToRelations($reference, $purchaseRequestLineRepository, $receptionReferenceArticleRepository);
                }
                $entityManager->remove($reception);
                $entityManager->flush();
            }

            return $this->json([
                "redirect" => $this->generateUrl('reception_index'),
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/annuler", name="reception_cancel", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function cancel(Request $request,
                           EntityManagerInterface $entityManager): Response {
        if($data = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $receptionRepository = $entityManager->getRepository(Reception::class);

            $statutPartialReception = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::RECEPTION, Reception::STATUT_RECEPTION_PARTIELLE);
            $reception = $receptionRepository->find($data['receptionId']);
            if($reception->getStatut()->getCode() === Reception::STATUT_RECEPTION_TOTALE) {
                $reception->setStatut($statutPartialReception);
                $entityManager->flush();
            }
            $data = [
                "redirect" => $this->generateUrl('reception_show', [
                    'id' => $reception->getId(),
                ]),
            ];
            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/remove-reception-line", name="reception_line_remove",  options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function removeReceptionReferenceArticle(EntityManagerInterface $entityManager,
                                                    ReceptionService       $receptionService,
                                                    RefArticleDataService  $refArticleDataService,
                                                    Request                $request): Response {
        $data = $request->request->get('id');
        if($data) {
            $receptionLineRepository = $entityManager->getRepository(ReceptionLine::class);
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);
            $purchaseRequestLineRepository = $entityManager->getRepository(PurchaseRequestLine::class);
            $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);

            /** @var ReceptionLine $receptionLine */
            $receptionLine = $receptionLineRepository->find($data);

            if ($receptionLine->getPack()) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Vous ne pouvez pas supprimer cette ligne car elle est liée à des colis',
                ]);
            }

            $reception = $receptionLine->getReception();

            if(!$reception) {
                throw new FormException('La référence est introuvable');
            }

            /** @var ReceptionReferenceArticle $receptionReferenceArticle */
            $receptionReferenceArticle = $receptionLine->getReceptionReferenceArticles()->first();

            $receptionReferenceArticleLabel = $receptionReferenceArticle?->getReferenceArticle() ? $receptionReferenceArticle?->getReferenceArticle()->getReference() : '';

            $associatedMovements = $trackingMovementRepository->findBy([
                'receptionReferenceArticle' => $receptionReferenceArticle,
            ]);

            if ($associatedMovements) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Cette référence est référencée dans un ou plusieurs mouvements de traçabilité',
                ]);
            }



            $reference = $receptionReferenceArticle->getReferenceArticle();

            // if receptionReferenceArticle is not attached to a pack
            // then we delete reception line if it doesn't have any reference articles linked
            $receptionLine = $receptionReferenceArticle->getReceptionLine();
            if (!$receptionLine->getPack()) {
                $reception->removeLine($receptionLine);
                $receptionLine->removeReceptionReferenceArticle($receptionReferenceArticle);
                $entityManager->remove($receptionReferenceArticle);
                $entityManager->remove($receptionLine);
                $entityManager->flush();
            }

            $entityManager->remove($receptionReferenceArticle);
            $entityManager->flush();
            $refArticleDataService->setStateAccordingToRelations($reference, $purchaseRequestLineRepository, $receptionReferenceArticleRepository);

            $reception->setStatut($receptionService->getNewStatus($reception));

            return new JsonResponse([
                'success' => true,
                'msg' => 'La référence <strong>' . $receptionReferenceArticleLabel . '</strong> a bien été supprimée.',
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/new-reception-line", name="reception_line_new", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function newReceptionLine(EntityManagerInterface           $entityManager,
                                     ReceptionService                 $receptionService,
                                     Request                          $request): Response {
        $contentData = $request->request;
        if ($contentData) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $receptionRepository = $entityManager->getRepository(Reception::class);

            $refArticleId = (int)$contentData->get('referenceArticle');
            $refArticle = $refArticleId ? $referenceArticleRepository->find($refArticleId) : null;
            $reception = $receptionRepository->find($contentData->get('reception'));

            $refAlreadyExists = Stream::from($reception->getLines())
                ->some(fn(ReceptionLine $receptionLine) => $refArticle
                    && $receptionLine->getReceptionReferenceArticles()->first()
                    && $receptionLine->getReceptionReferenceArticles()->first()?->getReferenceArticle()?->getReference() === $refArticle->getReference());

            if(!$refAlreadyExists) {
                $updateLineRes = $receptionService->updateReceptionLine($entityManager, $reception, $refArticle, $contentData);
                if (!($updateLineRes['success'] ?? false)) {
                    return new JsonResponse($updateLineRes);
                }

                $status = $reception->getStatut() ? $reception->getStatut()->getCode() : null;

                if ($status === Reception::STATUT_EN_ATTENTE || $status === Reception::STATUT_RECEPTION_PARTIELLE) {
                    $refArticle->setOrderState(ReferenceArticle::WAIT_FOR_RECEPTION_ORDER_STATE);
                }

                $receptionService->amendReceptionStatus($entityManager, $reception);
                $entityManager->flush();

                return new JsonResponse([
                    'success' => true,
                    'msg' => 'La référence <strong>' . $refArticle->getReference() . '</strong> a bien été ajoutée.',
                ]);
            }  else {
                return $this->json([
                    "success" => false,
                    "msg" => "La référence existe déjà pour cette réception"
                ]);
            }
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-edit-reception-line", name="reception_line_edit_api", options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function apiEditReceptionReferenceArticle(EntityManagerInterface $entityManager,
                                                     Request                $request): Response {
        $data = $request->query->get('lineId');
        if ($data) {
            $receptionLineRepository = $entityManager->getRepository(ReceptionLine::class);

            $receptionLine = $receptionLineRepository->find($data);
            $reception = $receptionLine->getReception();
            $receptionReferenceArticle = $receptionLine->getReceptionReferenceArticles()->first();

            return new JsonResponse([
                'success' => true,
                'html' => $this->renderView(
                    'reception/show/modalReceptionLineContent.html.twig',
                    [
                        'isEdit' => true,
                        'receptionLine' => $receptionLine,
                        'reception' => $reception,
                        'receptionReferenceArticle' => $receptionReferenceArticle,
                        'articles' => Stream::from($receptionReferenceArticle->getArticles())
                            ->map(fn(Article $article) => [
                                'label' => $article->getLabel(),
                                'value' => $article->getLabel(),
                                'selected' => true,
                            ])
                            ->toArray(),
                    ]),
                ]
            );
        }
        throw new BadRequestHttpException();
    }

    #[Route("/edit-reception-line", name: "reception_line_edit", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::ORDRE, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function editReceptionLine(EntityManagerInterface  $entityManager,
                                                  ReceptionService        $receptionService,
                                                  Request                 $request): Response {
        $data = $request->request;
        if ($data) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $receptionLineRepository = $entityManager->getRepository(ReceptionLine::class);
            $receptionRepository = $entityManager->getRepository(Reception::class);

            $refArticleId = (int)$data->get('referenceArticle');
            $refArticle = $refArticleId ? $referenceArticleRepository->find($refArticleId) : null;
            $reception = $receptionRepository->find($data->get('reception'));

            $updateLineRes = $receptionService->updateReceptionLine($entityManager, $reception, $refArticle, $data);
            if (!($updateLineRes['success'] ?? false)) {
                return new JsonResponse($updateLineRes);
            }

            $status = $reception->getStatut() ? $reception->getStatut()->getCode() : null;

            if ($status === Reception::STATUT_EN_ATTENTE || $status === Reception::STATUT_RECEPTION_PARTIELLE) {
                $refArticle->setOrderState(ReferenceArticle::WAIT_FOR_RECEPTION_ORDER_STATE);
            }

            $receptionService->amendReceptionStatus($entityManager, $reception);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'La référence <strong>' . $refArticle->getReference() . '</strong> a bien été ajoutée.',
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/voir/{id}", name="reception_show", methods={"GET", "POST"})
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_RECE})
     */
    public function show(EntityManagerInterface     $entityManager,
                         SettingsService            $settingsService,
                         ReceptionService           $receptionService,
                         TagTemplateService         $tagTemplateService,
                         Reception                  $reception,
                         TranslationService         $translation): Response {
        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);

        $listTypesDL = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]);
        $typeChampLibreDL = [];

        foreach($listTypesDL as $typeDL) {
            $champsLibresDL = $champLibreRepository->findByTypeAndCategorieCLLabel($typeDL, CategorieCL::DEMANDE_LIVRAISON);

            $typeChampLibreDL[] = [
                'typeLabel' => $typeDL->getLabel(),
                'typeId' => $typeDL->getId(),
                'champsLibres' => $champsLibresDL,
            ];
        }

        $precheckedDelivery = $settingRepository->getOneParamByLabel(Setting::CREATE_DL_AFTER_RECEPTION);
        $needsCurrentUser = $settingRepository->getOneParamByLabel(Setting::REQUESTER_IN_DELIVERY);
        $restrictedLocations = $settingRepository->getOneParamByLabel(Setting::MANAGE_LOCATION_DELIVERY_DROPDOWN_LIST);

        $defaultDisputeStatus = $statutRepository->getIdDefaultsByCategoryName(CategorieStatut::LITIGE_RECEPT);
        $deliveryRequestBehaviorSettingLabel = $settingRepository->findOneBy([
            'label' => [Setting::DIRECT_DELIVERY, Setting::CREATE_PREPA_AFTER_DL, Setting::CREATE_DELIVERY_ONLY],
            'value' => 1,
        ])?->getLabel();

        $deliverySwitchLabel = match ($deliveryRequestBehaviorSettingLabel) {
            Setting::CREATE_DELIVERY_ONLY => $translation->translate("Demande", "Livraison", "Demande de livraison", false) . ' seule',
            Setting::DIRECT_DELIVERY => $translation->translate("Ordre", "Livraison", "Ordre de livraison", false),
            default => $translation->translate("Demande", "Livraison", "Livraison", false),
        };

        return $this->render("reception/show/index.html.twig", [
            'reception' => $reception,
            'modifiable' => $reception->getStatut()->getCode() !== Reception::STATUT_RECEPTION_TOTALE,
            'disputeStatuses' => $statutRepository->findByCategorieName(CategorieStatut::LITIGE_RECEPT, 'displayOrder'),
            'disputeTypes' => $typeRepository->findByCategoryLabels([CategoryType::DISPUTE]),
            'typeChampsLibres' => $typeChampLibreDL,
            'precheckedDelivery' => $precheckedDelivery,
            'defaultDeliveryLocations' => $settingsService->getDefaultDeliveryLocationsByTypeId($entityManager),
            'deliverySwitchLabel' => $deliverySwitchLabel,
            'defaultDisputeStatusId' => $defaultDisputeStatus[0] ?? null,
            'needsCurrentUser' => $needsCurrentUser,
            'detailsHeader' => $receptionService->createHeaderDetailsConfig($reception),
            'restrictedLocations' => $restrictedLocations,
            'fieldsParam' => $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_DEMANDE),
            "tag_templates" => $tagTemplateService->serializeTagTemplates($entityManager, CategoryType::ARTICLE),
        ]);
    }

    /**
     * @Route("/autocomplete-art{reception}", name="get_article_reception", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function getArticles(ArticleDataService $articleDataService,
                                Reception $reception): JsonResponse {
        $articles = [];
        foreach ($reception->getLines() as $line) {
            foreach($line->getReceptionReferenceArticles() as $rra) {
                foreach($rra->getArticles() as $article) {
                    if($articleDataService->articleCanBeAddedInDispute($article)) {
                        $articles[] = [
                            'id' => $article->getId(),
                            'text' => $article->getBarCode(),
                            'numReception' => $article->getReceptionReferenceArticle(),
                            'isUrgent' => $article->getReceptionReferenceArticle()->getEmergencyTriggered() ?? false,
                        ];
                    }
                }
            }
        }


        return new JsonResponse([
            'results' => $articles,
        ]);
    }

    /**
     * @Route("/autocomplete-ref-art/{reception}", name="get_ref_article_reception", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     */
    public function getRefTypeQtyArticle(Reception                        $reception,
                                         ReceptionReferenceArticleService $receptionReferenceArticleService) {

        return $this->json([
            'results' => Stream::from($reception->getLines())
                ->flatMap(fn(ReceptionLine $line) => $line->getReceptionReferenceArticles())
                ->filter(fn(ReceptionReferenceArticle $receptionReferenceArticle) => (
                    $receptionReferenceArticle->getReferenceArticle()->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE
                    && (
                        !$receptionReferenceArticle->getQuantite()
                        ||$receptionReferenceArticle->getQuantiteAR() > $receptionReferenceArticle->getQuantite()
                    )
                ))
                ->keymap(fn(ReceptionReferenceArticle $receptionReferenceArticle) => [
                    "{$receptionReferenceArticle->getReferenceArticle()->getReference()}-{$receptionReferenceArticle->getCommande()}",
                    $receptionReferenceArticleService->serializeForSelect($receptionReferenceArticle)
                ], true)
                ->map(function(array $references) {
                    $reference = $references[0];
                    if (count($references) > 1) {
                        unset($reference['pack']);
                    }
                    return $reference;
                })
                ->values()
        ]);
    }

    /**
     * @Route("/modifier-litige", name="litige_edit_reception",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editDispute(EntityManagerInterface $entityManager,
                                ArticleDataService     $articleDataService,
                                DisputeService         $disputeService,
                                AttachmentService      $attachmentService,
                                Request                $request): Response {
        $post = $request->request;

        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $disputeRepository = $entityManager->getRepository(Dispute::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

        $dispute = $disputeRepository->find($post->get('id'));
        $typeBefore = $dispute->getType()->getId();
        $typeBeforeName = $dispute->getType()->getLabel();
        $typeAfter = (int)$post->get('disputeType');
        $statutBeforeId = $dispute->getStatus()->getId();
        $statutAfterId = (int)$post->get('disputeStatus');
        $statutAfter = $statutRepository->find($statutAfterId);

        $articlesNotAvailableCounter = $dispute
            ->getArticles()
            ->filter(function(Article $article) {
                // articles non disponibles
                return in_array(
                    $article->getStatut()?->getCode(),
                    [
                        Article::STATUT_EN_TRANSIT,
                        Article::STATUT_INACTIF,
                    ]
                );
            })
            ->count();

        if(!$statutAfter->isTreated()
            && $articlesNotAvailableCounter > 0) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'Vous ne pouvez pas passer le litige dans un statut non traité car il concerne des articles non disponibles.',
            ]);
        }

        $dispute
            ->setReporter($utilisateurRepository->find($post->get('disputeReporter')))
            ->setUpdateDate(new DateTime('now'))
            ->setType($typeRepository->find($post->get('disputeType')))
            ->setStatus($statutAfter);

        $errorResponse = $this->addArticleIntoDispute($entityManager, $articleDataService, $post->get('pack'), $dispute);
        if($errorResponse) {
            return $errorResponse;
        }

        if($post->get('emergency')) {
            $dispute->setEmergencyTriggered($post->get('emergency') === 'true');
        }
        if(!empty($buyers = $post->get('acheteursLitige'))) {
            // on détache les UL existants...
            $existingBuyers = $dispute->getBuyers();
            foreach($existingBuyers as $buyer) {
                $dispute->removeBuyer($buyer);
            }
            // ... et on ajoute ceux sélectionnés
            $listBuyer = explode(',', $buyers);
            foreach($listBuyer as $buyerId) {
                $dispute->addBuyer($utilisateurRepository->find($buyerId));
            }
        }
        $entityManager->flush();

        $commentStatut = $dispute->getStatus()
            ? $dispute->getStatus()->getComment()
            : '';

        $trimCommentStatut = trim($commentStatut);
        $userComment = trim($post->get('commentaire'));

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        if ($typeBefore !== $typeAfter
            || $statutBeforeId !== $statutAfterId
            || $userComment) {

            $historyRecord = $disputeService->createDisputeHistoryRecord(
                $dispute,
                $currentUser,
                [
                    $userComment,
                    $trimCommentStatut,
                ]
            );

            $entityManager->persist($historyRecord);
            $entityManager->flush();
        }

        $listAttachmentIdToKeep = $post->all('files') ?? [];
        $attachments = $dispute->getAttachments()->toArray();
        foreach($attachments as $attachment) {
            /** @var Attachment $attachment */
            if(!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                $attachmentService->removeAndDeleteAttachment($attachment, $dispute);
            }
        }

        $disputeService->createDisputeAttachments($dispute, $request, $entityManager);
        $entityManager->flush();
        $isStatutChange = ($statutBeforeId !== $statutAfterId);
        if($isStatutChange) {
            $disputeService->sendMailToAcheteursOrDeclarant($dispute, DisputeService::CATEGORY_RECEPTION, true);
        }
        return new JsonResponse([
            'success' => true,
            'msg' => 'Le litige <strong>' . $dispute->getNumber() . '</strong> a bien été modifié.',
        ]);
    }

    /**
     * @Route("/creer-litige", name="dispute_new_reception", options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function newLitige(EntityManagerInterface $entityManager,
                              DisputeService         $disputeService,
                              ArticleDataService     $articleDataService,
                              Request                $request,
                              UniqueNumberService    $uniqueNumberService,
                              TranslationService    $translation): Response
    {
        $post = $request->request;

        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

        $dispute = new Dispute();

        $now = new DateTime('now');

        $disputeNumber = $uniqueNumberService->create($entityManager, Dispute::DISPUTE_RECEPTION_PREFIX, Dispute::class, UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT);

        $dispute
            ->setStatus($statutRepository->find($post->get('disputeStatus')))
            ->setType($typeRepository->find($post->get('disputeType')))
            ->setReporter($utilisateurRepository->find($post->get('disputeReporter')))
            ->setCreationDate($now)
            ->setNumber($disputeNumber);

        $errorResponse = $this->addArticleIntoDispute($entityManager, $articleDataService, $post->get('disputePacks'), $dispute);
        if($errorResponse) {
            return $errorResponse;
        }

        if($post->get('emergency')) {
            $dispute->setEmergencyTriggered($post->get('emergency') === 'true');
        }
        if(!empty($buyers = $post->get('acheteursLitige'))) {
            $listBuyers = explode(',', $buyers);
            foreach($listBuyers as $buyer) {
                $dispute->addBuyer($utilisateurRepository->find($buyer));
            }
        }

        $commentStatut = $dispute->getStatus()
            ? $dispute->getStatus()->getComment()
            : '';

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $historyRecord = $disputeService->createDisputeHistoryRecord(
            $dispute,
            $currentUser,
            [
                trim($post->get('commentaire')),
                trim($commentStatut),
            ]
        );

        $entityManager->persist($dispute);
        $entityManager->persist($historyRecord);

        try {
            $entityManager->flush();
        }
        /** @noinspection PhpRedundantCatchClauseInspection */
        catch (UniqueConstraintViolationException $e) {
            return new JsonResponse([
                'success' => false,
                'msg' => $translation->translate('Ordre', 'Réception', 'Un autre litige de réception est en cours de création, veuillez réessayer.', false),
            ]);
        }

        $disputeService->createDisputeAttachments($dispute, $request, $entityManager);
        $entityManager->flush();
        $disputeService->sendMailToAcheteursOrDeclarant($dispute, DisputeService::CATEGORY_RECEPTION);

        return new JsonResponse([
            'success' => true,
            'msg' => 'Le litige <strong>' . $dispute->getNumber() . '</strong> a bien été créé.',
        ]);
    }

    /**
     * @Route("/api-modifier-litige", name="litige_api_edit_reception", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function apiEditLitige(EntityManagerInterface $entityManager,
                                  Request $request): Response {
        if($data = json_decode($request->getContent(), true)) {
            $typeRepository = $entityManager->getRepository(Type::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $disputeRepository = $entityManager->getRepository(Dispute::class);
            $attachmentRepository = $entityManager->getRepository(Attachment::class);

            $dispute = $disputeRepository->find($data['disputeId']);
            $packsCode = [];
            $acheteursCode = [];

            foreach($dispute->getArticles() as $pack) {
                $packsCode[] = [
                    'id' => $pack->getId(),
                    'text' => $pack->getBarCode(),
                ];
            }
            foreach($dispute->getBuyers() as $buyer) {
                $acheteursCode[] = $buyer->getId();
            }

            $disputeStatuses = Stream::from($statutRepository->findByCategorieName(CategorieStatut::LITIGE_RECEPT, 'displayOrder'))
                ->map(fn(Statut $statut) => [
                    'id' => $statut->getId(),
                    'type' => $statut->getType(),
                    'nom' => $this->getFormatter()->status($statut),
                    'treated' => $statut->isTreated(),
                ])
                ->toArray();

            $html = $this->renderView('reception/show/modalEditLitigeContent.html.twig', [
                'dispute' => $dispute,
                'disputeTypes' => $typeRepository->findByCategoryLabels([CategoryType::DISPUTE]),
                'disputeStatuses' => $disputeStatuses,
                'attachments' => $attachmentRepository->findBy(['dispute' => $dispute]),
            ]);

            return new JsonResponse(['html' => $html, 'packs' => $packsCode, 'acheteurs' => $acheteursCode]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer-litige", name="litige_delete_reception", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function deleteDispute(Request $request,
                                 EntityManagerInterface $entityManager, TranslationService $translation): Response {
        if($data = json_decode($request->getContent(), true)) {
            $disputeRepository = $entityManager->getRepository(Dispute::class);
            $statutRepository = $entityManager->getRepository(Statut::class);

            $dispute = $disputeRepository->find($data['litige']);
            $disputeNumber = $dispute->getNumber();
            $articlesInDispute = $dispute->getArticles()->toArray();

            $dispute->setLastHistoryRecord(null);
            //required before removing dispute or next flush will fail
            $entityManager->flush();

            foreach($dispute->getDisputeHistory() as $history) {
                $entityManager->remove($history);
            }

            $articleStatusAvailable = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_ACTIF);
            /** @var Article $article */
            foreach($articlesInDispute as $article) {
                $article->removeDispute($dispute);
                $this->setArticleStatusForTreatedDispute($article, $articleStatusAvailable);
            }

            $entityManager->remove($dispute);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => $translation->translate('Qualité', 'Litiges', 'Le litige {1} a bien été supprimé', [1 => $disputeNumber]),
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/litiges/api/{reception}", name="litige_reception_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function apiReceptionLitiges(EntityManagerInterface $entityManager,
                                        Reception $reception): Response {

        $disputeRepository = $entityManager->getRepository(Dispute::class);

        /** @var Dispute[] $disputes */
        $disputes = $disputeRepository->findByReception($reception);

        $rows = [];

        foreach($disputes as $dispute) {
            $buyers = [];
            $articles = [];
            foreach($dispute->getBuyers() as $buyer) {
                $buyers[] = $buyer->getUsername();
            }
            foreach($dispute->getArticles() as $article) {
                $articles[] = $article->getBarCode();
            }
            $rows[] = [
                'type' => $dispute->getType()->getLabel(),
                'status' => $this->getFormatter()->status($dispute->getStatus()),
                'lastHistoryRecord' => $dispute->getLastHistoryRecord() ? $dispute->getLastHistoryRecord()->getComment() : null,
                'date' => $dispute->getCreationDate()->format('d/m/Y H:i'),
                'actions' => $this->renderView('reception/datatableLitigesRow.html.twig', [
                    'receptionId' => $reception->getId(),
                    'url' => [
                        'edit' => $this->generateUrl('litige_edit_reception', ['id' => $dispute->getId()]),
                    ],
                    'disputeId' => $dispute->getId(),
                    'disputeNumber' => $dispute->getNumber(),
                ]),
                'urgence' => $dispute->getEmergencyTriggered(),
            ];
        }

        $data['data'] = $rows;

        return new JsonResponse($data);
    }

    /**
     * @Route("/finir", name="reception_finish", methods={"GET", "POST"}, options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function finish(Request $request,
                           EntityManagerInterface $entityManager): Response {
        if($data = json_decode($request->getContent(), true)) {
            $receptionRepository = $entityManager->getRepository(Reception::class);

            $reception = $receptionRepository->find($data['id']);

            $listReceptionReferenceArticle = Stream::from($reception->getLines())->flatMap(fn(ReceptionLine $line) => $line->getReceptionReferenceArticles());

            if($listReceptionReferenceArticle->isEmpty()) {
                return new JsonResponse('Vous ne pouvez pas finir une réception sans article.');
            } else {
                if($data['confirmed'] === true) {
                    $this->validateReception($entityManager, $reception);
                    return new JsonResponse([
                        'code' => 1,
                        'redirect' => $this->generateUrl('reception_index'),
                    ]);
                } else {
                    $partialReception = $listReceptionReferenceArticle
                        ->some(fn(ReceptionReferenceArticle $reference) => $reference->getQuantite() !== $reference->getQuantiteAR());

                    if(!$partialReception) {
                        $this->validateReception($entityManager, $reception);
                    }

                    return new JsonResponse([
                        'code' => $partialReception ? 0 : 1,
                        'redirect' => $this->generateUrl('reception_index'),
                    ]);
                }
            }
        }
        throw new BadRequestHttpException();
    }

    private function validateReception(EntityManagerInterface $entityManager,
                                       Reception $reception) {
        $statutRepository = $entityManager->getRepository(Statut::class);

        $statut = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::RECEPTION, Reception::STATUT_RECEPTION_TOTALE);
        $now = new DateTime('now');

        $reception
            ->setStatut($statut)
            ->setDateFinReception($now)
            ->setDateCommande($now);

        $entityManager->flush();
    }

    /**
     * @Route("/verif-avant-suppression", name="reception_reference_article_check_delete", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     */
    public function checkBeforeReceptionReferenceArticleDelete(EntityManagerInterface $entityManager,
                                                               TranslationService     $translationService,
                                                               Request                $request) {
        $id = $request->request->get('id');
        if ($id) {
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);
            $ligneArticle = $receptionReferenceArticleRepository->find($id);
            $nbArticles = $ligneArticle->getArticles()->count();

            $reference = $ligneArticle->getReferenceArticle();
            $newRefQuantity = $reference->getQuantiteStock() - $ligneArticle->getQuantite();
            $newRefAvailableQuantity = $newRefQuantity - $reference->getQuantiteReservee();
            if ($nbArticles === 0
                && ($newRefAvailableQuantity >= 0 || $reference->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE)) {
                $delete = true;
                $html = "
                    <p>Voulez-vous réellement supprimer cette ligne ?</p>
                    <div class='error-msg mt-2'></div>
                ";
            } else {
                $delete = false;
                if($nbArticles > 0) {
                    $html = "
                        <p class='error-msg'>
                            Vous ne pouvez pas supprimer cette ligne.<br>
                            En effet, il y a eu réception {$translationService->translate('Ordre', 'Réceptions', 'd\'articles')} sur
                            {$translationService->translate('Ordre', 'Réceptions', 'cette réception')}.
                        </p>
                    ";
                } else {
                    $html = "
                        <p class='error-msg'>
                            Vous ne pouvez pas supprimer cette ligne.<br>
                            En effet, cela décrémenterait le stock de {$ligneArticle->getQuantite()}
                            alors que la quantité disponible de la référence est de {$reference->getQuantiteDisponible()}.
                        </p>
                    ";
                }
            }
            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
    }


    /**
     * @Route("/{reception}/etiquettes", name="reception_bar_codes_print", options={"expose"=true})
     */
    public function getReceptionBarCodes(Request $request,
                                         Reception $reception,
                                         EntityManagerInterface $entityManager,
                                         RefArticleDataService $refArticleDataService,
                                         ArticleDataService $articleDataService,
                                         PDFGeneratorService $PDFGeneratorService): Response {
        $articleIds = json_decode($request->query->get('articleIds'), true);
        $tag = $request->query->get('template')
                ? $entityManager->getRepository(TagTemplate::class)->find($request->query->get('template'))
                : null;
        if(empty($articleIds)) {
            $barcodeConfigs = Stream::from($reception->getReceptionReferenceArticles())
                ->flatMap(function(ReceptionReferenceArticle $recepRef) use ($request, $refArticleDataService, $articleDataService, $reception, $tag) {
                    $referenceArticle = $recepRef->getReferenceArticle();

                    if ($referenceArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
                        return [$refArticleDataService->getBarcodeConfig($referenceArticle)];
                    } else {
                        $articlesReception = $recepRef->getArticles()->toArray();
                        if (!empty($articlesReception)) {
                            return Stream::from($articlesReception)
                                ->filter(function(Article $article) use ($tag) {
                                    return
                                        $article->getType()?->getTags()?->isEmpty() &&
                                        (empty($tag) || in_array($article->getType(), $tag->getTypes()->toArray()));
                                })
                                ->map(fn(Article $article) => $articleDataService->getBarcodeConfig($article, $reception))
                                ->toArray();
                        }
                    }

                    return [];
                })
                ->toArray();
        } else {
            $articles = $entityManager->getRepository(Article::class)->findBy(['id' => $articleIds]);
            $barcodeConfigs = Stream::from($articles)
                ->filter(function(Article $article) use ($tag) {
                    return
                        $article->getType()?->getTags()?->isEmpty() &&
                        (empty($tag) || in_array($article->getType(), $tag->getTypes()->toArray()));
                })
                ->map(fn(Article $article) => $articleDataService->getBarcodeConfig($article, $article->getReceptionReferenceArticle()->getReceptionLine()->getReception()))
                ->toArray();
        }

        if(!empty($barcodeConfigs)) {
            $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'articles_reception',$tag ? $tag->getPrefix() : 'ETQ');
            $pdf = $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs, false, $tag);
            return new PdfResponse($pdf, $fileName);
        } else {
            throw new NotFoundHttpException('Aucune étiquette à imprimer');
        }
    }


    /**
     * @Route("/{reception}/ligne-article/{receptionReferenceArticle}/etiquette", name="reception_ligne_article_bar_code_print", options={"expose"=true})
     */
    public function getReceptionLigneArticleBarCode(Reception                 $reception,
                                                    ReceptionReferenceArticle $receptionReferenceArticle,
                                                    RefArticleDataService     $refArticleDataService,
                                                    PDFGeneratorService       $PDFGeneratorService): Response {
        $receptionContainsReference = Stream::from($reception->getLines())
            ->some(fn (ReceptionLine $line) => (
                Stream::from($line->getReceptionReferenceArticles())
                    ->some(fn(ReceptionReferenceArticle $r) => $r->getId() === $receptionReferenceArticle->getId())
            ));
        if($receptionContainsReference && $receptionReferenceArticle->getReferenceArticle()) {
            $barcodeConfigs = [$refArticleDataService->getBarcodeConfig($receptionReferenceArticle->getReferenceArticle())];
            $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'articles_reception');
            $pdf = $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs);
            return new PdfResponse($pdf, $fileName);
        } else {
            throw new NotFoundHttpException('Aucune étiquette à imprimer');
        }
    }

    /**
     * @Route("/apiArticle", name="article_by_reception_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_RECE}, mode=HasPermission::IN_JSON)
     */
    public function apiArticle(EntityManagerInterface $entityManager,
                               ArticleDataService $articleDataService,
                               Request $request): Response {
        if($ligne = $request->request->get('ligne')) {
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);
            $ligne = $receptionReferenceArticleRepository->find(intval($ligne));
            $data = $articleDataService->getArticleDataByReceptionLigne($ligne);

            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/verification", name="reception_check_delete", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_RECE}, mode=HasPermission::IN_JSON)
     */
    public function checkReceptionCanBeDeleted(EntityManagerInterface $entityManager,
                                               TranslationService $translationService,
                                               Request $request): Response {
        if($receptionId = json_decode($request->getContent(), true)) {
            $receptionRepository = $entityManager->getRepository(Reception::class);
            $reception = $receptionRepository->find($receptionId);
            $receptionReferenceArticles = $reception?->getReceptionReferenceArticles();

            if(empty($receptionReferenceArticles)) {
                $delete = true;
                $html = "
                    <p>{$translationService->translate('Ordre', 'Réceptions', 'Voulez-vous réellement supprimer cette réception')}</p>
                    <div class='error-msg mt-2'></div>
                ";
            } else {
                $delete = false;
                $html = "
                    <p class='error-msg'>
                        {$translationService->translate('Ordre', 'Réceptions', 'Cette réception contient des articles.')}<br>
                        {$translationService->translate('Ordre', 'Réceptions', 'Vous devez d\'abord les supprimer.')}<br>
                    </p>
                ";
            }

            return new JsonResponse([
                'delete' => $delete,
                'html' => $html,
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/csv", name="get_receptions_csv", options={"expose"=true}, methods={"GET"})
     */
    public function getReceptionCSV(EntityManagerInterface $entityManager,
                                    TranslationService $translation,
                                    CSVExportService $CSVExportService,
                                    Request $request): Response {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch(Throwable $throwable) {
        }

        if(isset($dateTimeMin) && isset($dateTimeMax)) {
            $receptionRepository = $entityManager->getRepository(Reception::class);
            $deliveryRequestRepository = $entityManager->getRepository(Demande::class);
            $receptions = $receptionRepository->getByDates($dateTimeMin, $dateTimeMax);

            $requesters = $deliveryRequestRepository->getRequestersForReceptionExport();

            $csvHeader = [
                $translation->translate('Ordre', 'Réceptions', 'n° de réception', false),
                'n° de commande',
                'fournisseur',
                'utilisateur',
                'statut',
                'date de création',
                'date de fin',
                'commentaire',
                'quantité à recevoir',
                'quantité reçue',
                'emplacement de stockage',
                'réception urgente',
                'référence urgente',
                'destinataire',
                'référence',
                'libellé',
                'quantité stock',
                'type',
                'code-barre reference',
                'code-barre article',
                'unité logistique',
            ];
            $nowStr = (new DateTime('now'))->format("d-m-Y-H-i-s");
            $addedRefs = [];

            return $CSVExportService->createBinaryResponseFromData(
                "export-" . str_replace(["/", "\\"], "-", $translation->translate('Ordre', 'Réception', 'réception', false)) . "-$nowStr.csv",
                $receptions,
                $csvHeader,
                function($reception) use (&$addedRefs, $requesters) {
                    $rows = [];
                    if($reception['articleId'] || $reception['referenceArticleId']) {
                        if($reception['articleId']) {
                            $row = $this->serializeReception($reception);

                            $row[] = $requesters[$reception['id'] ."-". $reception['articleId']] ?? "";
                            $row[] = $reception['articleReference'] ?: '';
                            $row[] = $reception['articleLabel'] ?: '';
                            $row[] = $reception['articleQuantity'] ?: '';
                            $row[] = $reception['articleTypeLabel'] ?: '';
                            $row[] = $reception['articleReferenceArticleBarcode'] ?: '';
                            $row[] = $reception['articleBarcode'] ?: '';
                            $row[] = $reception['currentLogisticUnit'] ?: '';

                            $rows[] = $row;
                        }

                        else {
                            if (!isset($addedRefs[$reception['referenceArticleId']])) {
                                $addedRefs[$reception['referenceArticleId']] = true;
                                $row = $this->serializeReception($reception);

                                $row[] = '';
                                $row[] = $reception['referenceArticleReference'] ?: '';
                                $row[] = $reception['referenceArticleLibelle'] ?: '';
                                $row[] = $reception['referenceArticleQuantiteStock'] ?: '';
                                $row[] = $reception['referenceArticleTypeLabel'] ?: '';
                                $row[] = $reception['referenceArticleBarcode'] ?: '';
                                $row[] = $reception['currentLogisticUnit'] ?: '';

                                $rows[] = $row;
                            }
                        }
                    } else {
                        $rows[] = $this->serializeReception($reception);
                    }
                    return $rows;
                }
            );

        } else {
            throw new BadRequestHttpException();
        }
    }

    private function serializeReception(array $reception): array {
        return [
            $reception['number'] ?: '',
            $reception['orderNumber'] ?: '',
            $reception['providerName'] ?: '',
            $reception['userUsername'] ?: '',
            $reception['statusName'] ?: '',
            $this->formatService->datetime($reception['date']),
            $this->formatService->datetime($reception['dateFinReception']),
            $reception['commentaire'] ? strip_tags($reception['commentaire']) : '',
            $reception['receptionRefArticleQuantiteAR'] ?: '',
            (!$reception['referenceArticleId'] && !$reception['articleId']
                ? ''
                : ($reception['receptionRefArticleQuantite']
                    ?: 0)),
            $reception['storageLocation'] ?: '',
            $this->formatService->bool($reception['receptionEmergency']),
            $this->formatService->bool($reception['referenceEmergency']),
        ];
    }

    #[Route("/avec-conditionnement/{reception}", name: "reception_new_with_packing", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function newWithPacking(Request                    $request,
                                   MailerService              $mailerService,
                                   TransferRequestService     $transferRequestService,
                                   TransferOrderService       $transferOrderService,
                                   DeliveryRequestService     $demandeLivraisonService,
                                   TranslationService         $translation,
                                   EntityManagerInterface     $entityManager,
                                   Reception                  $reception,
                                   ArticleDataService         $articleDataService,
                                   TrackingMovementService    $trackingMovementService,
                                   MouvementStockService      $mouvementStockService,
                                   PreparationsManagerService $preparationsManagerService,
                                   LivraisonsManagerService   $livraisonsManagerService,
                                   ReceptionService           $receptionService): Response {
        $now = new DateTime('now');

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $articles = json_decode($request->request->get('packingArticles'), true);
        $data = $request->request->all();

        $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $packRepository = $entityManager->getRepository(Pack::class);
        $createDirectDelivery = $settingRepository->getOneParamByLabel(Setting::DIRECT_DELIVERY);
        $paramCreatePrepa = $settingRepository->findOneBy(['label' => Setting::CREATE_PREPA_AFTER_DL]);

        $transferRequest = null;
        $demande = null;

        $totalQuantities = [];
        $cache = ['receptionReferenceArticles' => [],];

        //checking values and retrieves entities
        foreach($articles as &$articleArray) {
            $receptionReferenceArticleId = $articleArray['receptionReferenceArticle'];

            if (!isset($cache['receptionReferenceArticles'][$receptionReferenceArticleId])) {
                $receptionReferenceArticle = $receptionReferenceArticleRepository->find($receptionReferenceArticleId);
                $cache['receptionReferenceArticles'][$receptionReferenceArticleId] = $receptionReferenceArticle;
            }
            else {
                $receptionReferenceArticle = $cache['receptionReferenceArticles'][$receptionReferenceArticleId];
            }

            $articleArray['receptionReferenceArticle'] = $receptionReferenceArticle;
            $articleArray['refArticle'] = $receptionReferenceArticle->getReferenceArticle();
            $articleArray['conform'] = !$receptionReferenceArticle->getAnomalie();

            if(!isset($totalQuantities[$receptionReferenceArticle->getId()])) {
                $totalQuantities[$receptionReferenceArticle->getId()] = [
                    "receptionReferenceArticle" => $receptionReferenceArticle,
                    "count" => $receptionReferenceArticle->getQuantite() ?? 0,
                ];
            }

            $articleArray['quantityReceived'] = max($articleArray['quantityToReceive'] * $articleArray['quantite'], 0);
            $totalQuantities[$receptionReferenceArticle->getId()]["count"] += max($articleArray['quantityToReceive'] * $articleArray['quantite'], 0);
        }

        $wrongQuantitiesReference = Stream::from($totalQuantities)
            ->find(fn (array $quantities) => (
                $quantities["count"] > $quantities["receptionReferenceArticle"]->getQuantiteAR()
                || $quantities["count"] < 0
            ));

        if ($wrongQuantitiesReference) {
            /** @var ReceptionReferenceArticle $receptionReferenceArticle */
            $receptionReferenceArticle = $wrongQuantitiesReference['receptionReferenceArticle'];
            $quantityToReceive = $receptionReferenceArticle->getQuantiteAR();
            $reference = $receptionReferenceArticle->getReferenceArticle()->getReference();
            $orderNumber = $receptionReferenceArticle->getCommande();
            $plural = $quantityToReceive > 1 ? 's' : '';
            throw new FormException("Vous ne pouvez pas conditionner plus de <strong>{$quantityToReceive}</strong> article{$plural} pour la référence <strong>{$reference} - {$orderNumber}</strong>");
        }

        foreach ($totalQuantities as $total) {
            /** @var ReceptionReferenceArticle $receptionReferenceArticle */
            $receptionReferenceArticle = $total["receptionReferenceArticle"];
            $receptionReferenceArticle->setQuantite($total["count"]);
        }

        if(isset($data['requestType']) && $data['requestType'] !== 'none') {
            // optionnel : crée la demande de livraison
            $needCreateLivraison = $data['requestType'] === 'delivery';
            $needCreateTransfer = $data['requestType'] === 'transfer';

            if ($needCreateLivraison) {
                // optionnel : crée l'ordre de prépa
                $needCreatePrepa = $paramCreatePrepa && $paramCreatePrepa->getValue();
                $data['needPrepa'] = $needCreatePrepa && !$createDirectDelivery;

                $demande = $demandeLivraisonService->newDemande($data, $entityManager);
                if ($demande instanceof Demande) {
                    $entityManager->persist($demande);

                    if ($createDirectDelivery) {
                        $validateResponse = $demandeLivraisonService->validateDLAfterCheck($entityManager, $demande, false, true, true, true, ['sendNotification' => false]);
                        if ($validateResponse['success']) {
                            $preparation = $demande->getPreparations()->first();

                            /** @var Utilisateur $currentUser */
                            $currentUser = $this->getUser();
                            $articlesNotPicked = $preparationsManagerService->createMouvementsPrepaAndSplit($preparation, $currentUser, $entityManager);

                            $dateEnd = new DateTime('now');
                            $delivery = $livraisonsManagerService->createLivraison($dateEnd, $preparation, $entityManager);

                            $locationEndPreparation = $demande->getDestination();

                            $preparationsManagerService->treatPreparation($preparation, $this->getUser(), $locationEndPreparation, ["articleLinesToKeep" => $articlesNotPicked]);
                            $preparationsManagerService->closePreparationMovements($preparation, $dateEnd, $locationEndPreparation);

                                $mouvementRepository = $entityManager->getRepository(MouvementStock::class);
                                $mouvements = $mouvementRepository->findByPreparation($preparation);

                                try {
                                    $entityManager->flush();
                                    if ($delivery->getDemande()->getType()->isNotificationsEnabled()) {
                                        $this->notificationService->toTreat($delivery);
                                    }
                                } /** @noinspection PhpRedundantCatchClauseInspection */
                                catch (UniqueConstraintViolationException $e) {
                                    return new JsonResponse([
                                        'success' => false,
                                        'msg' => 'Une autre ' . mb_strtolower($translation->translate("Demande", "Livraison", "Demande de livraison", false)) . ' est en cours de création, veuillez réessayer.'
                                    ]);
                                }
                                foreach ($mouvements as $mouvement) {
                                    $preparationsManagerService->createMovementLivraison(
                                        $entityManager,
                                        $mouvement->getQuantity(),
                                        $currentUser,
                                        $delivery,
                                        !empty($mouvement->getRefArticle()),
                                        $mouvement->getRefArticle() ?? $mouvement->getArticle(),
                                        $preparation,
                                        false,
                                        $locationEndPreparation
                                    );
                                }
                            }
                        }
                    }

                if (!isset($demande)
                    || !($demande instanceof Demande)) {
                    if (isset($demande)
                        && is_array($demande)) {
                        $demande = $demande['demande'];
                    }
                    else {
                        return new JsonResponse([
                            'success' => false,
                            'msg' => 'Erreur lors de la création de la ' . mb_strtolower($translation->translate("Demande", "Livraison", "Demande de livraison", false)) . '.',
                        ]);
                    }
                }
            }
            else if ($needCreateTransfer) {
                $toTreatRequest = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSFER_REQUEST, TransferRequest::TO_TREAT);
                $toTreatOrder = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSFER_ORDER, TransferOrder::TO_TREAT);
                $origin = $emplacementRepository->find($data['origin']);
                $destination = $emplacementRepository->find($data['storage']);

                /** @var Utilisateur $requester */
                $requester = $this->getUser();
                $transferRequest = $transferRequestService->createTransferRequest($entityManager, $toTreatRequest, $origin, $destination, $requester);

                $transferRequest
                    ->setReception($reception)
                    ->setValidationDate($now);

                $order = $transferOrderService->createTransferOrder($entityManager, $toTreatOrder, $transferRequest);;

                $entityManager->persist($transferRequest);
                $entityManager->persist($order);

                try {
                    $entityManager->flush();
                    if ($transferRequest->getType()->isNotificationsEnabled()) {
                        $this->notificationService->toTreat($order);
                    }
                } /** @noinspection PhpRedundantCatchClauseInspection */
                catch (UniqueConstraintViolationException $e) {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => 'Une autre demande de transfert est en cours de création, veuillez réessayer.',
                    ]);
                }
            }
        }

        $receptionLocation = $reception->getLocation();
        // crée les articles et les ajoute à la demande, à la réception, crée les urgences
        $receptionLocationId = $receptionLocation?->getId();
        $emergencies = [];

        $createdArticles = [];
        foreach($articles as &$articleArray) {
            $createdLoopArticles = [];

            /** @var ReceptionReferenceArticle $receptionReferenceArticle */
            $receptionReferenceArticle = $articleArray['receptionReferenceArticle'];
            /** @var Pack|null $pack */
            $pack = $receptionReferenceArticle->getReceptionLine()->getPack();
            /** @var ReferenceArticle $referenceArticle */
            $referenceArticle = $articleArray['refArticle'];

            $location = isset($data['pack']) ? $pack?->getLastDrop()?->getEmplacement() : null;
            if (isset($location) && !$location->ableToBeDropOff($pack)) {
                throw new FormException("Les objets ne disposent pas des natures requises pour être déposés sur l'emplacement " . $location->getLabel());
            }

            if(isset($receptionLocationId)) {
                $articleArray['emplacement'] = $receptionLocationId;
            }

            if ($transferRequest ?? false) {
                $articleArray['statut'] = Article::STATUT_EN_TRANSIT;
            }

            $quantityToReceive = intval($articleArray['quantityToReceive']);
            unset($articleArray['quantityToReceive']);

            // we create articles
            for($i = 0; $i < $quantityToReceive; $i++) {
                $article = $articleDataService->newArticle($entityManager, $articleArray);

                if ($demande ?? false) {
                    $deliveryArticleLine = $demandeLivraisonService->createArticleLine($article, $demande, [
                        'quantityToPick' => $article->getQuantite(),
                        'pack' => $pack
                    ]);
                    $entityManager->persist($deliveryArticleLine);

                    /** @var Preparation $preparation */
                    $preparation = $demande->getPreparations()->first();
                    if ($preparation) {

                        $preparationArticleLine = $deliveryArticleLine->createPreparationOrderLine()
                            ->setPickedQuantity($preparation->getStatut()->getCode() === Preparation::STATUT_PREPARE ? $article->getQuantite() : 0)
                            ->setPreparation($preparation);

                        $entityManager->persist($preparationArticleLine);

                        $article->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_EN_TRANSIT));
                    }
                }

                $transferRequest?->addArticle($article);

                $article->setReceptionReferenceArticle($receptionReferenceArticle);

                if($referenceArticle->getIsUrgent()) {
                    $emergencies[$article->getId()] = [$article, $articleArray['quantityReceived']];
                }

                $mouvementStock = $mouvementStockService->createMouvementStock(
                    $currentUser,
                    null,
                    $article->getQuantite(),
                    $article,
                    MouvementStock::TYPE_ENTREE
                );
                $mouvementStock->setReceptionOrder($reception);

                $mouvementStockService->finishStockMovement($mouvementStock, $now, $receptionLocation);

                $entityManager->persist($mouvementStock);

                $entityManager->flush();
                $createdArticles[] = $article;
                $createdLoopArticles[] = $article;
            }

            if ($pack && $pack->getLastDrop()?->getEmplacement()) {
                $luMovements = $trackingMovementService->persistLogisticUnitMovements(
                    $entityManager,
                    $pack,
                    $pack->getLastDrop()?->getEmplacement(),
                    $createdLoopArticles,
                    $currentUser,
                    [
                        'from' => $reception,
                        'trackingDate' => $now
                    ]
                );
                if (isset($createdMvt) && $luMovements['success'] && isset($luMovements['movements'])) {
                    $mainMovement = stream::from($luMovements['movements'])
                        ->filter(fn(TrackingMovement $mvt) => $mvt->getMainMovement() === null)
                        ->first();
                    $createdMvt->setMainMovement($mainMovement);
                }
                if ($createDirectDelivery && isset($delivery) && isset($preparation)) {
                    foreach ($createdLoopArticles as $article) {
                        $preparationsManagerService->createMovementLivraison(
                            $entityManager,
                            $article->getQuantite(),
                            $currentUser,
                            $delivery,
                            false,
                            $article,
                            $preparation,
                            false,
                            $pack->getLastDrop()?->getEmplacement()
                        );
                    }
                }
            } else if ($receptionLocation) {
                $createdMvt = $trackingMovementService->createTrackingMovement(
                    $article->getTrackingPack() ?? $article->getBarCode(),
                    $receptionLocation,
                    $currentUser,
                    $now,
                    false,
                    true,
                    TrackingMovement::TYPE_DEPOSE,
                    [
                        'mouvementStock' => $mouvementStock,
                        'quantity' => $mouvementStock->getQuantity(),
                        'from' => $reception,
                        "refOrArticle" => $article
                    ]
                );
                $trackingMovementService->persistSubEntities($entityManager, $createdMvt);
                $entityManager->persist($createdMvt);
            }
            $entityManager->flush();
        }

        foreach($emergencies as $emergency) {
            $article =  $emergency[0];
            $referenceArticle = $article->getReceptionReferenceArticle()->getReferenceArticle();
            $newEmergencyQuantity = $referenceArticle->getEmergencyQuantity() - $emergency[1];

            $mailContent = $this->renderView('mails/contents/mailArticleUrgentReceived.html.twig', [
                'emergency' => $referenceArticle->getEmergencyComment(),
                'article' => $article,
                'title' => 'Votre article urgent a bien été réceptionné.',
                'newEmergencyQuantity' => $newEmergencyQuantity,
            ]);

            $destinataires = '';
            $userThatTriggeredEmergency = $referenceArticle->getUserThatTriggeredEmergency();
            if($userThatTriggeredEmergency) {
                if(isset($demande) && $demande->getUtilisateur()) {
                    $destinataires = [
                        $userThatTriggeredEmergency,
                        $demande->getUtilisateur(),
                    ];
                } else {
                    $destinataires = [$userThatTriggeredEmergency];
                }
            } else {
                if(isset($demande) && $demande->getUtilisateur()) {
                    $destinataires = [$demande->getUtilisateur()];
                }
            }

            if(!empty($destinataires)) {
                // on envoie un mail aux demandeurs
                $mailerService->sendMail(
                    'FOLLOW GT // Article urgent réceptionné', $mailContent,
                    $destinataires
                );
            }

            if (!$referenceArticle->getEmergencyQuantity() || $referenceArticle->getEmergencyQuantity() === 0) {
                $referenceArticle
                    ->setIsUrgent(false)
                    ->setUserThatTriggeredEmergency(null)
                    ->setEmergencyComment('');
            } else {
                if ($newEmergencyQuantity <= 0) {
                    $referenceArticle
                        ->setIsUrgent(false)
                        ->setEmergencyQuantity(null)
                        ->setUserThatTriggeredEmergency(null)
                        ->setEmergencyComment('');
                } else {
                    $referenceArticle->setEmergencyQuantity($newEmergencyQuantity);
                }
            }
            $entityManager->flush();
        }

        if(isset($demande) && ($demande->getType()->getSendMailRequester() || $demande->getType()->getSendMailReceiver())) {
            $to = [];
            if ($demande->getType()->getSendMailRequester()) {
                $to[] = $demande->getUtilisateur();
            }
            if ($demande->getType()->getSendMailReceiver() && $demande->getReceiver()) {
                $to[] = $demande->getReceiver();
            }

            $nowDate = new DateTime('now');
            $mailerService->sendMail(
                'FOLLOW GT // Réception d\'une unité logistique ' . 'de type «' . $demande->getType()->getLabel() . '».',
                $this->renderView('mails/contents/mailDemandeLivraisonValidate.html.twig', [
                    'demande' => $demande,
                    'fournisseur' => $reception->getFournisseur(),
                    'isReception' => true,
                    'reception' => $reception,
                    'title' => 'Une ' . $translation->translate('Ordre', 'Réception', 'réception', false)
                        . ' '
                        . $reception->getNumber()
                        . ' de type «'
                        . $demande->getType()->getLabel()
                        . '» a été réceptionnée le '
                        . $nowDate->format('d/m/Y \à H:i')
                        . '.',
                ]),
                $to
            );
        }
        $reception->setStatut($receptionService->getNewStatus($reception));
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'msg' => 'La réception a bien été effectuée.',
            'articleIds' => Stream::from($createdArticles)
                ->map(fn(Article $article) => $article->getId())
                ->json(),
        ]);
    }

    private function addArticleIntoDispute(EntityManagerInterface $entityManager,
                                           ArticleDataService     $articleDataService,
                                           string                 $articlesParamStr,
                                           Dispute                $dispute): ?Response {
        if(!empty($articlesParamStr)) {
            $articleRepository = $entityManager->getRepository(Article::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $articleStatusAvailable = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_ACTIF);
            $articleStatusDispute = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_EN_LITIGE);

            // on détache les UL existants...
            $existingArticles = $dispute->getArticles();
            foreach($existingArticles as $article) {
                $article->removeDispute($dispute);
                $this->setArticleStatusForTreatedDispute($article, $articleStatusAvailable);
            }

            // ... et on ajoute ceux sélectionnés
            $listArticlesId = explode(',', $articlesParamStr);
            foreach($listArticlesId as $articleId) {
                $article = $articleRepository->find($articleId);
                $dispute->addArticle($article);
                $ligneIsUrgent = $article->getReceptionReferenceArticle() && $article->getReceptionReferenceArticle()->getEmergencyTriggered();
                if($ligneIsUrgent) {
                    $dispute->setEmergencyTriggered(true);
                }

                if(!$articleDataService->articleCanBeAddedInDispute($article)) {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => 'Les articles doivent être en statut "disponible" ou "en litige".',
                    ]);
                } else {
                    if($dispute->getStatus()->isTreated()) {
                        $this->setArticleStatusForTreatedDispute($article, $articleStatusAvailable);
                    } else { // !$dispute->getStatus()->isTreated()
                        $article->setStatut($articleStatusDispute);
                    }
                }
            }
        }
        return null;
    }

    public function setArticleStatusForTreatedDispute(Article $article,
                                                      Statut $articleStatusAvailable) {
        // on check si l'article a des
        $currentDisputesCounter = $article
            ->getDisputes()
            ->filter(function(Dispute $articleDispute) {
                return !$articleDispute->getStatus()->isTreated();
            })
            ->count();

        if($currentDisputesCounter === 0) {
            $article->setStatut($articleStatusAvailable);
        }
    }

    /**
     * @Route("/{reception}/etiquette/{article}", name="reception_article_single_bar_code_print", options={"expose"=true})
     */
    public function getSingleReceptionArticleBarCode(Article $article,
                                                    Reception $reception,
                                                    ArticleDataService $articleDataService,
                                                    PDFGeneratorService $PDFGeneratorService): Response {
        $tag = $article->getType()->getTags()->isEmpty() ? null : $article->getType()->getTags()->first();
        $barcodeConfigs = [$articleDataService->getBarcodeConfig($article, $reception)];
        $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'article', $tag ? $tag->getPrefix() : 'ETQ');

        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs, false, $tag),
            $fileName
        );
    }

    #[Route("/{reception}/packing-article-form-template", name: "packing_article_form_template", options: ['expose' => true], methods: "GET")]
    public function packingTemplate(Request                $request,
                                    Reception              $reception,
                                    EntityManagerInterface $entityManager): Response {
        $status = $reception->getStatut();
        if($status->getState() === Statut::TREATED && $status->getCode() !== Reception::STATUT_RECEPTION_PARTIELLE) {
            return $this->json([
                'success' => false,
                'msg' => 'La réception est terminée, vous ne pouvez plus ajouter de références',
            ]);
        }

        $reference = $request->query->get('reference');
        $orderNumber = $request->query->get('orderNumber');
        $packId = $request->query->get('pack');
        $supplierReference = $request->query->get('supplierReference');

        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $supplierReferenceRepository = $entityManager->getRepository(ArticleFournisseur::class);
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $packRepository = $entityManager->getRepository(Pack::class);

        // pack could be equal to -1
        $pack = $packId > 0 ? $packRepository->find($packId) : null;
        $referenceArticle = $reference ? $referenceArticleRepository->findOneBy(['reference' => $reference]) : null;
        $supplierReference = $supplierReferenceRepository->find($supplierReference);

        $receptionLine = $reception->getLine($pack);
        $receptionReferenceArticle = $receptionLine?->getReceptionReferenceArticle($referenceArticle, $orderNumber);

        if (!$referenceArticle || !$receptionReferenceArticle || !$supplierReference) {
            return $this->json([
                'success' => true, // success: we do not display error to the user
                'template' => '',
            ]);
        }

        $type = $referenceArticle->getType();
        $freeFields = $freeFieldRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::ARTICLE);

        return $this->json([
            'success' => true,
            'template' => $this->renderView('reception/show/packing/article-form.html.twig', [
                'freeFields' => $freeFields,
                'receptionReferenceArticle' => $receptionReferenceArticle,
                'selectedPack' => $pack,
                'supplierReference' => $supplierReference,
            ]),
        ]);
    }

    #[Route("/packing-articles-template", name: "get_packing_articles_template", options: ['expose' => true], methods: "GET")]
    public function getPackingArticlesTemplate(Request                $request,
                                               EntityManagerInterface $manager): Response {
        $data = json_decode($request->query->get('params'), true);

        $freeFieldRepository = $manager->getRepository(FreeField::class);
        $supplierArticleRepository = $manager->getRepository(ArticleFournisseur::class);
        $receptionReferenceArticleRepository = $manager->getRepository(ReceptionReferenceArticle::class);
        $packRepository = $manager->getRepository(Pack::class);

        $receptionReferenceArticle = $receptionReferenceArticleRepository->find($data['receptionReferenceArticle']);
        $receptionLine = $receptionReferenceArticle->getReceptionLine();
        $reception = $receptionLine->getReception();

        $status = $reception->getStatut();
        if($status->getState() === Statut::TREATED && $status->getCode() !== Reception::STATUT_RECEPTION_PARTIELLE) {
            return $this->json([
                'success' => false,
                'msg' => 'La réception est terminée, vous ne pouvez plus ajouter de références',
            ]);
        }

        $pack = $receptionLine->getPack()
            ?? (!empty($data['pack']) ? $packRepository->find($data['pack']) : null);

        $supplierReference = isset($data['supplierReference'])
            ? $supplierArticleRepository->findOneBy(['reference' => $data['supplierReference']])
            : '';

        $expiryDate = isset($data['expiry']) ? DateTime::createFromFormat('Y-m-d', $data['expiry']) : '';

        $freeFieldsValues = Stream::from($data)
            ->filter(fn($val, $key) => is_int($key))
            ->toArray();

        $freeFields = Stream::from($freeFieldsValues)
            ->filter(fn($freeField) => $freeField !== null)
            ->keymap(function(?string $value, int $key) use ($freeFieldRepository) {
                $freeField = $freeFieldRepository->find($key);
                $value = DateTime::createFromFormat('Y-m-d', $value) ?: $value;
                $formattedValue = ($value instanceof DateTime
                    ? $value->format('d/m/Y')
                    : ($freeField->getTypage() === FreeField::TYPE_BOOL && $value == 1
                        ? 'oui'
                        : (FreeField::TYPE_BOOL && $value == 0
                            ? 'non'
                            : $value)));
                return [$freeField->getLabel(), $formattedValue];
            })
            ->ksort()
            ->toArray();

        $receptionLocation = $reception->getLocation()?->getId();
        $packLocation = $pack?->getLastDrop()?->getEmplacement()?->getId();

        return $this->json([
           'template' => $this->renderView('reception/show/packing/articles_template.html.twig', [
               'pack' => $pack,
               'receptionReferenceArticle' => $receptionReferenceArticle,
               'quantityToReceive' => $data['quantityToReceive'],
               'supplierReferenceId' => $supplierReference ? $supplierReference->getId() : '',
               'supplierReferenceLabel' => $supplierReference ? $supplierReference->getReference() : '',
               'batch' => $data['batch'] ?? '',
               'expiry' => $expiryDate ? $expiryDate->format('d/m/Y') : null,
               'quantity' => $data['quantity'],
               'freeFields' => $freeFields,
               'dropLocationIsReceptionLocation' => !$pack || $receptionLocation === $packLocation,
               'data' =>
                   [
                       'quantityToReceive' => $data['quantityToReceive'],
                       'quantite' => $data['quantity'],
                       'batch' => $data['batch'] ?? '',
                       'expiry' => $expiryDate ? $expiryDate->format('d/m/Y') : null,
                       'receptionReferenceArticle' => $receptionReferenceArticle->getId(),
                       'pack' => $pack?->getId(),
                       'articleFournisseur' => $supplierReference ? $supplierReference->getId() : '',
                   ]
                   + $freeFieldsValues,
           ]),
        ]);
    }

    #[Route("/{reception}/reception-lines-api", name: "reception_lines_api", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::ORDRE, Action::DISPLAY_RECE], mode: HasPermission::IN_JSON)]
    public function getReceptionLinesApi(EntityManagerInterface $entityManager,
                                         Reception              $reception,
                                         Request                $request,
                                         ReceptionLineService   $receptionLineService): JsonResponse
    {
        $data = $receptionLineService->getDataForDatatable($entityManager, $request->query, $reception);
        return new JsonResponse($data);
    }
}
