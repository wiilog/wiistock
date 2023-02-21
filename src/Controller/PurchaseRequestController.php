<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\FieldsParam;
use App\Entity\Fournisseur;
use App\Entity\Menu;
use App\Entity\PurchaseRequest;
use App\Entity\PurchaseRequestLine;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Service\AttachmentService;
use App\Service\PurchaseRequestService;
use App\Service\ReceptionLineService;
use App\Service\ReceptionService;
use App\Service\RefArticleDataService;
use DateTime;
use App\Service\CSVExportService;
use App\Service\UserService;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Iterator;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;


/**
 * @Route("/achat/demande")
 */
class PurchaseRequestController extends AbstractController
{
    /**
     * @Route("/liste", name="purchase_request_index")
     * @HasPermission({Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS})
     */
    public function index(EntityManagerInterface $entityManager): Response
    {
        $statusRepository = $entityManager->getRepository(Statut::class);

        return $this->render('purchase_request/index.html.twig', [
            'statuts' => $statusRepository->findByCategorieName(CategorieStatut::PURCHASE_REQUEST),
        ]);
    }

    /**
     * @Route("/api", name="purchase_request_api", options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request,
                        PurchaseRequestService $purchaseRequestService): Response {
        $data = $purchaseRequestService->getDataForDatatable($request->request);
        return new JsonResponse($data);
    }

    /**
     * @Route("/voir/{id}", name="purchase_request_show", options={"expose"=true}, methods={"GET"})
     * @HasPermission({Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS})
     */
    public function show(PurchaseRequest $request,
                         PurchaseRequestService $purchaseRequestService,
                         EntityManagerInterface $entityManager): Response {
        $status = $request->getStatus();
        $statusRepository = $entityManager->getRepository(Statut::class);

        $inProgressStatuses = $statusRepository->findByCategoryAndStates(CategorieStatut::PURCHASE_REQUEST, [Statut::IN_PROGRESS]);
        $treatedStatuses = $statusRepository->findByCategoryAndStates(CategorieStatut::PURCHASE_REQUEST, [Statut::TREATED]);
        $notTreatedStatuses = $statusRepository->findByCategoryAndStates(CategorieStatut::PURCHASE_REQUEST, [Statut::NOT_TREATED]);
        return $this->render('purchase_request/show.html.twig', [
            'request' => $request,
            'modifiable' => $status && $status->isDraft(),
            'detailsConfig' => $purchaseRequestService->createHeaderDetailsConfig($request),
            'consider' => [
                'statuses' => $inProgressStatuses
            ],
            'treat' => [
                'statuses' => $treatedStatuses
            ],
            'validate' => [
                'statuses' => $notTreatedStatuses
            ]
        ]);
    }

    /**
     * @Route("/csv", name="purchase_request_export",options={"expose"=true}, methods="GET|POST")
     */
    public function export(Request $request,
                           EntityManagerInterface $entityManager,
                           PurchaseRequestService $purchaseRequestService,
                           CSVExportService $CSVExportService): Response {
        $dateMin = $request->query->get("dateMin");
        $dateMax = $request->query->get("dateMax");

        $dateTimeMin = DateTime::createFromFormat("Y-m-d H:i:s", $dateMin . " 00:00:00");
        $dateTimeMax = DateTime::createFromFormat("Y-m-d H:i:s", $dateMax . " 23:59:59");

        if(isset($dateTimeMin, $dateTimeMax)) {
            $now = (new DateTime('now'))->format("d-m-Y-H-i-s");;

            $purchaseRequestRepository = $entityManager->getRepository(PurchaseRequest::class);
            $purchaseRequestLineRepository = $entityManager->getRepository(PurchaseRequestLine::class);

            $requests = $purchaseRequestRepository->iterateByDates($dateTimeMin, $dateTimeMax);

            $lines = $purchaseRequestLineRepository->iterateByPurchaseRequest($dateTimeMin, $dateTimeMax);

            $header = [
                "Numéro demande",
                "Statut",
                "Demandeur",
                "Acheteur",
                "Date de création",
                "Date de validation",
                "Date de prise en compte",
                "Date de traitement",
                "Commentaire",
                "Référence",
                "Code barre",
                "Libellé",
                "Fournisseur"
            ];

            return $CSVExportService->streamResponse(
                function ($output) use ($requests, $lines, $purchaseRequestService, $CSVExportService) {
                    foreach ($requests as $request) {
                        $lineAddedForRequest = false;
                        if ($lines instanceof Iterator && $lines->valid()) {
                            $line = $lines->current();
                            while ($lines->valid()
                                && $line
                                && $line['purchaseRequestId'] === $request['id']) {
                                $purchaseRequestService->putPurchaseRequestLine($output, $CSVExportService, $request, $line);
                                $lines->next();
                                $line = $lines->current();

                                if (!$lineAddedForRequest) {
                                    $lineAddedForRequest = true;
                                }
                            }
                        }

                        if (!$lineAddedForRequest) {
                            $purchaseRequestService->putPurchaseRequestLine($output, $CSVExportService, $request);
                        }
                    }
                },
                "export_demande_achat_$now.csv",
                $header
            );
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="purchase_request_delete", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request,
                           UserService $userService,
                           RefArticleDataService $refArticleDataService,
                           EntityManagerInterface $entityManager): Response {

        if($data = json_decode($request->getContent(), true)) {
            $requestRepository = $entityManager->getRepository(PurchaseRequest::class);
            $purchaseRequest = $requestRepository->find($data['request']);

            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);
            $purchaseRequestLineRepository = $entityManager->getRepository(PurchaseRequestLine::class);

            $status = $purchaseRequest->getStatus();
            if (!$status ||
                ($status->isDraft() && !$userService->hasRightFunction(Menu::DEM, Action::DELETE_DRAFT_PURCHASE_REQUEST)) ||
                ($status->isNotTreated() && !$userService->hasRightFunction(Menu::DEM, Action::DELETE_ONGOING_PURCHASE_REQUESTS)) ||
                ($status->isInProgress() && !$userService->hasRightFunction(Menu::DEM, Action::DELETE_ONGOING_PURCHASE_REQUESTS)) ||
                ($status->isTreated() && !$userService->hasRightFunction(Menu::DEM, Action::DELETE_TREATED_PURCHASE_REQUESTS))) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => "Vous n'avez pas le droit de supprimer cette demande"
                ]);
            }
            $refsToUpdate = [];
            foreach ($purchaseRequest->getPurchaseRequestLines() as $receptionArticle) {
                $reference = $receptionArticle->getReference();
                $refsToUpdate[] = $reference;
                $entityManager->remove($receptionArticle);
            }
            $entityManager->flush();
            foreach ($refsToUpdate as $reference) {
                $refArticleDataService->setStateAccordingToRelations($reference, $purchaseRequestLineRepository, $receptionReferenceArticleRepository);
            }
            $entityManager->remove($purchaseRequest);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'redirect' => $this->generateUrl('purchase_request_index'),
                'msg' => "La demande d'achat a bien été supprimée"
            ]);

        }
        throw new BadRequestHttpException();

    }

    /**
     * @Route("/{purchaseRequest}/line/api", name="purchase_request_lines_api", options={"expose"=true}, methods={"GET"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS}, mode=HasPermission::IN_JSON)
     */
    public function purchaseRequestLinesApi(PurchaseRequest $purchaseRequest): Response {
        $requestLines = $purchaseRequest->getPurchaseRequestLines();
        $rowsRC = [];
        foreach($requestLines as $requestLine) {
            $reference = $requestLine->getReference();
            $rowsRC[] = [
                'reference' => isset($reference) ? $reference->getReference() : "",
                'label'=> isset($reference) ? $reference->getLibelle() : "",
                'requestedQuantity' => $requestLine->getRequestedQuantity(),
                'stockQuantity' => isset($reference) ? $reference->getQuantiteStock() : "",
                'orderedQuantity' => $requestLine->getOrderedQuantity(),
                'orderNumber' => $requestLine->getOrderNumber(),
                'supplier' => $this->formatService->supplier($requestLine->getSupplier()),
                'actions' => $this->renderView('purchase_request/line/actions.html.twig', [
                    'lineId' => $requestLine->getId(),
                    'requestStatus' => $purchaseRequest->getStatus()
                ]),
            ];
        }

        return new JsonResponse([
            "data" => $rowsRC,
            "recordsFiltered" => 0,
            "recordsTotal" => count($rowsRC),
        ]);
    }

    /**
     * @Route("/{purchaseRequest}/ajouter-ligne", name="purchase_request_add_line", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function addPurchaseRequestLine(Request $request,
                                           PurchaseRequestService $purchaseRequestService,
                                           EntityManagerInterface $entityManager,
                                           PurchaseRequest $purchaseRequest): Response {

        $data = json_decode($request->getContent(), true);

        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $reference = $referenceArticleRepository->find($data['reference']);
        $requestedQuantity = $data['requestedQuantity'];


        if($reference == null){
            $errorMessage = "La référence n'existe pas";
        }
        else if ($requestedQuantity == null || $requestedQuantity < 1) {
            $errorMessage = "La quantité ajoutée n'est pas valide";
        }
        else {
            $linesWithSameRef = $purchaseRequest->getPurchaseRequestLines()
                ->filter(fn (PurchaseRequestLine $line) => $line->getReference() === $reference)
                ->toArray();
            if (!empty($linesWithSameRef)) {
                $errorMessage = "La référence a déjà été ajoutée à la demande d'achat";
            }
            else if (!$reference->getBuyer()) {
                $errorMessage = "La référence doit avoir un acheteur";
            }
            else if ($purchaseRequest->getBuyer() && $reference->getBuyer() !== $purchaseRequest->getBuyer()) {
                $errorMessage = "La référence doit avoir un acheteur identique à la demande d'achat";
            }
        }

        if (!empty($errorMessage)) {
            return $this->json([
                'success' => false,
                'msg' => $errorMessage
            ]);
        }

        $purchaseRequestLine = new PurchaseRequestLine();
        $purchaseRequestLine
            ->setReference($reference)
            ->setRequestedQuantity($requestedQuantity)
            ->setPurchaseRequest($purchaseRequest);

        $purchaseRequest->setBuyer($reference->getBuyer());

        $entityManager->persist($purchaseRequestLine);
        $entityManager->flush();

        $purchaseRequestStatus = $purchaseRequest->getStatus();

        return $this->json([
            "success" => true,
            'msg' => "La référence a bien été ajoutée à la demande d'achat",
            'entete' => $this->renderView('purchase_request/show_header.html.twig', [
                'request' => $purchaseRequest,
                'modifiable' => $purchaseRequestStatus && $purchaseRequestStatus->isDraft(),
                'showDetails' => $purchaseRequestService->createHeaderDetailsConfig($purchaseRequest)
            ]),
        ]);
    }

    /**
     * @Route("/creer", name="purchase_request_new", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::CREATE_PURCHASE_REQUESTS}, mode=HasPermission::IN_JSON)
     */
    public function new(PurchaseRequestService $purchaseRequestService,
                        EntityManagerInterface $entityManager): Response
    {

        /** @var Utilisateur $requester */
        $requester = $this->getUser();

        $statusRepository = $entityManager->getRepository(Statut::class);
        $statuses = $statusRepository->findByCategoryAndStates(CategorieStatut::PURCHASE_REQUEST, [Statut::DRAFT]);
        $status = $statuses[0] ?? null;
        if (!$status) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'Aucun statut brouillon créé pour les demandes d\'achat. Veuillez en paramétrer un.'
            ]);
        }
        $purchaseRequest = $purchaseRequestService->createPurchaseRequest($entityManager, $status, $requester);

        $entityManager->persist($purchaseRequest);

        try {
            $entityManager->flush();
        } /** @noinspection PhpRedundantCatchClauseInspection */
        catch (UniqueConstraintViolationException $e) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'Une autre demande d\'achat est en cours de création, veuillez réessayer.'
            ]);
        }
        $number = $purchaseRequest->getNumber();
        return $this->json([
            'success' => true,
            'redirect' => $this->generateUrl('purchase_request_show', ['id' => $purchaseRequest->getId()]),
            'msg' => "La demande d'achat <strong>${number}</strong> a bien été créée"
        ]);
    }

    /**
     * @Route("/ligne/api-modifier", name="purchase_request_line_edit_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function editLineApi(Request $request,
                                EntityManagerInterface $entityManager,
                                UserService $userService): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            if ($userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                $purchaseRequestLineRepository = $entityManager->getRepository(PurchaseRequestLine::class);
                $purchaseRequestLine = $purchaseRequestLineRepository->find($data['id']);

                $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
                $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_RECEPTION);
                $html = $this->renderView('purchase_request/line/edit_content_modal.html.twig', [
                    'line' => $purchaseRequestLine,
                    'fieldsParam' => $fieldsParam
                ]);
            } else {
                $html = '';
            }

            return new JsonResponse($html);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/ligne/modifier", name="purchase_request_line_edit", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editLine(Request $request,
                             EntityManagerInterface $entityManager): Response
    {
        $data = json_decode($request->getContent(), true);
        $response = [];
        $purchaseRequestLineRepository = $entityManager->getRepository(PurchaseRequestLine::class);
        /** @var PurchaseRequestLine $purchaseRequestLine */
        $purchaseRequestLine = $purchaseRequestLineRepository->find($data['lineId']);

        if (!empty($purchaseRequestLine)) {
            if(isset($data['supplier'])){
                $supplierRepository = $entityManager->getRepository(Fournisseur::class);
                $supplier = $supplierRepository->find($data['supplier']);
            }

            if(isset($data['orderDate']) && $data['orderDate']) {
                $orderDate = DateTime::createFromFormat('d/m/Y H:i', $data['orderDate']) ?: null;
            }

            if(isset($data['expectedDate']) && $data['expectedDate']) {
                $expectedDate = DateTime::createFromFormat('d/m/Y', $data['expectedDate']) ?: null;
            }

            $purchaseRequestLine
                ->setSupplier($supplier ?? null)
                ->setOrderNumber($data['orderNumber'] ?? null)
                ->setOrderedQuantity((int) $data['orderedQuantity'] ?? null)
                ->setOrderDate($orderDate ?? null)
                ->setExpectedDate($expectedDate ?? null);

            $entityManager->flush();
            $response = [
                'success' => true,
                'msg' => "La ligne de demande d'achat a bien été modifiée"
            ];
        }
        return new JsonResponse($response);
    }
    /**
     * @Route("/api-modifier", name="purchase_request_api_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT_DRAFT_PURCHASE_REQUEST}, mode=HasPermission::IN_JSON)
     */
    public function editApi(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $purchaseRequestRepository = $entityManager->getRepository(PurchaseRequest::class);
            $statusRepository = $entityManager->getRepository(Statut::class);

            $purchaseRequest = $purchaseRequestRepository->find($data['id']);

            $currentStatus = $purchaseRequest->getStatus();
            $statuses = $currentStatus
                ? $statusRepository->findByCategoryAndStates(CategorieStatut::PURCHASE_REQUEST, [$currentStatus->getState()])
                : [];

            $json = $this->renderView('purchase_request/edit_content_modal.html.twig', [
                'purchaseRequest' => $purchaseRequest,
                'statuses' => $statuses
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="purchase_request_edit", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT_DRAFT_PURCHASE_REQUEST}, mode=HasPermission::IN_JSON)
     */
    public function edit(EntityManagerInterface $entityManager,
                         Request $request,
                         PurchaseRequestService $purchaseRequestService,
                         AttachmentService $attachmentService): Response {

        $statusRepository = $entityManager->getRepository(Statut::class);
        $purchaseRequestRepository = $entityManager->getRepository(PurchaseRequest::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);

        $post = $request->request;

        $purchaseRequest = $purchaseRequestRepository->find($post->get('id'));

        /** @var Utilisateur $requester */
        $requester = $post->has('requester') ? $userRepository->find($post->get('requester')) : $purchaseRequest->getRequester();
        $comment = $post->get('comment') ?: '';
        $newStatus = $statusRepository->find($post->get('status'));

        $currentStatus = $purchaseRequest->getStatus();
        if (!$currentStatus
            || !$newStatus
            || $newStatus->getState() === $currentStatus->getState()) {
            $purchaseRequest->setStatus($newStatus);
        }

        $purchaseRequest
            ->setComment(StringHelper::cleanedComment($comment))
            ->setRequester($requester);

        $purchaseRequest->removeIfNotIn($data['files'] ?? []);
        $attachmentService->manageAttachments($entityManager, $purchaseRequest, $request->files);

        $entityManager->flush();

        $number = $purchaseRequest->getNumber();
        $purchaseRequestStatus = $purchaseRequest->getStatus();

        return $this->json([
            'success' => true,
            'msg' => "La demande d'achat <strong>${number}</strong> a bien été modifiée",
            'entete' => $this->renderView('purchase_request/show_header.html.twig', [
                'request' => $purchaseRequest,
                'modifiable' => $purchaseRequestStatus && $purchaseRequestStatus->isDraft(),
                'showDetails' => $purchaseRequestService->createHeaderDetailsConfig($purchaseRequest)
            ]),
        ]);
    }

    /**
     * @Route("/line/remove-line", name="purchase_request_line_remove_line", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function removeLine(Request $request, EntityManagerInterface $entityManager) {
        if($data = json_decode($request->getContent())) {
            $purchaseRequestRepository = $entityManager->getRepository(PurchaseRequest::class);
            $purchaseRequestLineRepository = $entityManager->getRepository(PurchaseRequestLine::class);

            $purchaseRequest = $purchaseRequestRepository->find($data->request);
            $purchaseRequestLine = $purchaseRequestLineRepository->find($data->lineId);

            if($purchaseRequestLine && $purchaseRequest){
                $purchaseRequest->removePurchaseRequestLine($purchaseRequestLine);
                $entityManager->remove($purchaseRequestLine);
            }

            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => "La ligne a bien été supprimée de la demande d'achat"
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
    * @Route("/{id}/consider", name="consider_purchase_request", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
    */
    public function consider(Request $request,
                             EntityManagerInterface $entityManager,
                             PurchaseRequest $purchaseRequest,
                             PurchaseRequestService $purchaseRequestService): Response {

        $data = json_decode($request->getContent(), true);
        $statusRepository = $entityManager->getRepository(Statut::class);

        $status = $data['status'];
        $inProgressStatus = $statusRepository->find($status);

        $purchaseRequest
            ->setStatus($inProgressStatus)
            ->setConsiderationDate(new DateTime('now'));

        $entityManager->flush();
        $purchaseRequestService->sendMailsAccordingToStatus($purchaseRequest);

        return $this->json([
            'success' => true,
            'msg' => 'La demande d\'achat a bien été prise en compte',
            'redirect' => $this->generateUrl('purchase_request_show', ['id' => $purchaseRequest->getId()])
        ]);
    }

    /**
     * @Route("/{id}/treat", name="treat_purchase_request", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function treat(Request                $request,
                          EntityManagerInterface $entityManager,
                          PurchaseRequest        $purchaseRequest,
                          PurchaseRequestService $purchaseRequestService,
                          ReceptionLineService   $receptionLineService,
                          ReceptionService       $receptionService): Response {

        $data = json_decode($request->getContent(), true);
        $statusRepository = $entityManager->getRepository(Statut::class);

        /** @var Statut $status */
        $status = $data['status'];
        $treatedStatus = $statusRepository->find($status);

        if($treatedStatus->getAutomaticReceptionCreation()) {
            $unfilledLines = Stream::from($purchaseRequest->getPurchaseRequestLines()->toArray())
                ->filter(fn (PurchaseRequestLine $line) => (!$line->getOrderedQuantity() || $line->getOrderedQuantity() == 0))
                ->filterMap(fn (PurchaseRequestLine $line) => $line->getReference()?->getReference())
                ->values();

            if (!empty($unfilledLines)) {
                return $this->json([
                    'success' => false,
                    'msg' => count($unfilledLines) > 1
                        ? 'Des informations sont manquantes sur les lignes d\'achat  <strong>' . join(', ', $unfilledLines) . '</strong>.<br> Impossible de créer la réception liée ni de terminer la demande d\'achat.'
                        : 'Des informations sont manquantes sur la ligne d\'achat  <strong>' . $unfilledLines[0] . '</strong>.<br> Impossible de créer la réception liée ni de terminer la demande d\'achat.'
                ]);
            }

            $receptionsWithCommand = [];

            foreach ($purchaseRequest->getPurchaseRequestLines() as $purchaseRequestLine) {
                $orderNumber = $purchaseRequestLine->getOrderNumber() ?? null;
                $expectedDate = $this->formatService->date($purchaseRequestLine->getExpectedDate());

                $uniqueReceptionConstraint = [
                    'orderNumber' => $orderNumber,
                    'expectedDate' => $expectedDate,
                ];

                $orderDate = $this->formatService->date($purchaseRequestLine->getOrderDate());
                $reception = $receptionService->getAlreadySavedReception($entityManager, $receptionsWithCommand, $uniqueReceptionConstraint);
                $receptionData = [
                    'fournisseur' => $purchaseRequestLine->getSupplier() ? $purchaseRequestLine->getSupplier()->getId() : '',
                    'orderNumber' => $orderNumber,
                    'commentaire' => $purchaseRequest->getComment() ?? '',
                    'dateAttendue' => $expectedDate,
                    'dateCommande' => $orderDate,
                ];
                if (!$reception) {
                    $reception = $receptionService->persistReception($entityManager, $this->getUser(), $receptionData);
                    $receptionService->setAlreadySavedReception($receptionsWithCommand, $uniqueReceptionConstraint, $reception);
                } else if($reception->getFournisseur() !== $purchaseRequestLine->getSupplier()) {
                    $reception = $receptionService->persistReception($entityManager, $this->getUser(), $receptionData);
                }

                $receptionLine = $reception->getLine(null)
                    ?? $receptionLineService->persistReceptionLine($entityManager, $reception, null);

                $receptionReferenceArticle = new ReceptionReferenceArticle();
                $receptionReferenceArticle
                    ->setReceptionLine($receptionLine)
                    ->setReferenceArticle($purchaseRequestLine->getReference())
                    ->setQuantiteAR($purchaseRequestLine->getOrderedQuantity())
                    ->setCommande($orderNumber)
                    ->setQuantite(0);

                $entityManager->persist($receptionReferenceArticle);
                $purchaseRequestLine->setReception($reception);

                $entityManager->flush();
            }
        }

        $purchaseRequest
            ->setStatus($treatedStatus)
            ->setProcessingDate(new DateTime('now'));
        $entityManager->flush();
        $purchaseRequestService->sendMailsAccordingToStatus($purchaseRequest);

        return $this->json([
            'success' => true,
            'msg' => 'La demande d\'achat a bien été traitée',
            'redirect' => $this->generateUrl('purchase_request_show', ['id' => $purchaseRequest->getId()])
        ]);
    }

    /**
     * @Route("/{id}/valider", name="purchase_request_validate", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT_DRAFT_PURCHASE_REQUEST}, mode=HasPermission::IN_JSON)
     */
    public function validate(PurchaseRequest $purchaseRequest,
                             EntityManagerInterface $entityManager,
                             Request $request,
                             PurchaseRequestService $purchaseRequestService): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $statusRepository = $entityManager->getRepository(Statut::class);

            $validationDate = new DateTime("now");
            $status = $statusRepository->find($data['status']);
            if (!$status) {
                return $this->json([
                    'success' => false,
                    'msg' => 'Le statut sélectionné n\'existe pas.'
                ]);
            }

            if ($purchaseRequest->getPurchaseRequestLines()->isEmpty()) {
                return $this->json([
                    'success' => false,
                    'msg' => "Vous ne pouvez pas valider une demande d'achat vide."
                ]);
            }

            $purchaseRequest
                ->setStatus($status)
                ->setValidationDate($validationDate);

            $entityManager->flush();
            $purchaseRequestService->sendMailsAccordingToStatus($purchaseRequest);

            $number = $purchaseRequest->getNumber();

            return $this->json([
                'success' => true,
                'msg' => "La demande d'achat <strong>${number}</strong> a bien été validée",
                'entete' => $this->renderView('purchase_request/show_header.html.twig', [
                    'modifiable' => $status->isDraft(),
                    'request' => $purchaseRequest,
                    'showDetails' => $purchaseRequestService->createHeaderDetailsConfig($purchaseRequest)
                ]),
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-references", options={"expose"=true}, name="purchase_api_references", methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS}, mode=HasPermission::IN_JSON)
     */
    public function apiReferences(Request $request, PurchaseRequestService $service): Response {

        return $this->json($service->getDataForReferencesDatatable($request->request->get('purchaseId')));
    }

}

