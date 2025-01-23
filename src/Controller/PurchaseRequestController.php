<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\Attachment;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Fournisseur;
use App\Entity\Menu;
use App\Entity\PurchaseRequest;
use App\Entity\PurchaseRequestLine;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Service\AttachmentService;
use App\Service\CSVExportService;
use App\Service\PurchaseRequestService;
use App\Service\RefArticleDataService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Iterator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;


#[Route('/achat/demande')]
class PurchaseRequestController extends AbstractController
{
    #[Route('/liste', name: 'purchase_request_index')]
    #[HasPermission([Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $statusRepository = $entityManager->getRepository(Statut::class);

        return $this->render('purchase_request/index.html.twig', [
            'statuses' => $statusRepository->findByCategorieName(CategorieStatut::PURCHASE_REQUEST),
            'purchaseRequest' => new PurchaseRequest(),
        ]);
    }

    #[Route('/api', name: 'purchase_request_api', options: ['expose' => true], methods: ['POST'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS], mode: HasPermission::IN_JSON)]
    public function api(Request                $request,
                        PurchaseRequestService $purchaseRequestService): Response
    {
        $data = $purchaseRequestService->getDataForDatatable($request->request);
        return new JsonResponse($data);
    }

    #[Route('/voir/{id}', name: 'purchase_request_show', options: ['expose' => true], methods: ['GET'])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS])]
    public function show(PurchaseRequest        $request,
                         PurchaseRequestService $purchaseRequestService,
                         EntityManagerInterface $entityManager): Response
    {
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

    #[Route('/csv', name: 'purchase_request_export', options: ['expose' => true], methods: 'GET|POST')]
    public function export(Request                $request,
                           EntityManagerInterface $entityManager,
                           PurchaseRequestService $purchaseRequestService,
                           CSVExportService       $CSVExportService): Response
    {
        $dateMin = $request->query->get("dateMin");
        $dateMax = $request->query->get("dateMax");

        $dateTimeMin = DateTime::createFromFormat("Y-m-d H:i:s", $dateMin . " 00:00:00");
        $dateTimeMax = DateTime::createFromFormat("Y-m-d H:i:s", $dateMax . " 23:59:59");

        if (isset($dateTimeMin, $dateTimeMax)) {
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
                "Fournisseur",
                "Prix unitaire",
                "Frais de livraison",
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

    #[Route('/supprimer', name: 'purchase_request_delete', options: ['expose' => true], methods: ['GET', 'POST'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::DEM, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function delete(Request                $request,
                           UserService            $userService,
                           RefArticleDataService  $refArticleDataService,
                           EntityManagerInterface $entityManager): Response
    {

        if ($data = json_decode($request->getContent(), true)) {
            $requestRepository = $entityManager->getRepository(PurchaseRequest::class);
            $purchaseRequest = $requestRepository->find($data['request']);

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
                $refArticleDataService->setStateAccordingToRelations($entityManager, $reference);
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

    #[Route('/{purchaseRequest}/line/api', name: 'purchase_request_lines_api', options: ['expose' => true], methods: ['GET'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS], mode: HasPermission::IN_JSON)]
    public function purchaseRequestLinesApi(PurchaseRequest $purchaseRequest, EntityManagerInterface $entityManager): Response
    {
        $articleRepository = $entityManager->getRepository(Article::class);
        $requestLines = $purchaseRequest->getPurchaseRequestLines();
        $rowsRC = [];
        foreach ($requestLines as $requestLine) {
            $reference = $requestLine->getReference();
            $rowsRC[] = [
                'reference' => isset($reference) ? $reference->getReference() : "",
                'label' => isset($reference) ? $reference->getLibelle() : "",
                'requestedQuantity' => $requestLine->getRequestedQuantity(),
                'stockQuantity' => isset($reference)
                    ? ($reference->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE && $requestLine->getLocation()
                        ? $articleRepository->quantityForRefOnLocation($reference, $requestLine->getLocation())
                        : $reference->getQuantiteStock()
                    )
                    : "",
                'orderedQuantity' => $requestLine->getOrderedQuantity(),
                'orderNumber' => $requestLine->getOrderNumber(),
                'supplier' => $this->formatService->supplier($requestLine->getSupplier()),
                'location' => $requestLine->getLocation()
                    ? ($requestLine->getLocation()->getZone()
                        ? $this->formatService->location($requestLine->getLocation()) . " (" . $this->formatService->zone($requestLine->getLocation()->getZone()) . ")"
                        : $this->formatService->location($requestLine->getLocation())
                    )
                    : '',
                FixedFieldEnum::unitPrice->name => $requestLine->getUnitPrice(),
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

    #[Route('/{purchaseRequest}/ajouter-ligne', name: 'purchase_request_add_line', options: ['expose' => true], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::DEM, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function addPurchaseRequestLine(Request                $request,
                                           PurchaseRequestService $purchaseRequestService,
                                           EntityManagerInterface $entityManager,
                                           PurchaseRequest        $purchaseRequest): Response
    {

        $data = json_decode($request->getContent(), true);

        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $reference = $referenceArticleRepository->find($data['reference']);
        $requestedQuantity = $data['requestedQuantity'];
        $location = null;
        if (!empty($data['location'])) {
            $location = $locationRepository->find($data['location']);
        }

        if ($reference == null) {
            $errorMessage = "La référence n'existe pas";
        } else if ($requestedQuantity == null || $requestedQuantity < 1) {
            $errorMessage = "La quantité ajoutée n'est pas valide";
        } else {

            $linesWithSameRef = $purchaseRequest->getPurchaseRequestLines()
                ->filter(fn(PurchaseRequestLine $line) => $line->getReference() === $reference && ($location?->getId() === $line->getLocation()?->getId()))
                ->toArray();
            if (!empty($linesWithSameRef)) {
                $errorMessage = "Le couple référence emplacement a déjà été ajoutée à la demande d'achat";
            } else if (!$reference->getBuyer()) {
                $errorMessage = "La référence doit avoir un acheteur";
            } else if ($purchaseRequest->getBuyer() && $reference->getBuyer() !== $purchaseRequest->getBuyer()) {
                $errorMessage = "La référence doit avoir un acheteur identique à la demande d'achat";
            }
        }

        if (!empty($errorMessage)) {
            return $this->json([
                'success' => false,
                'msg' => $errorMessage
            ]);
        }

        $purchaseRequestLine = $purchaseRequestService->createPurchaseRequestLine($reference, $requestedQuantity, [
            "purchaseRequest" => $purchaseRequest,
            "location" => $location
        ]);

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

    #[Route('/creer', name: 'purchase_request_new', options: ['expose' => true], methods: ['POST'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::DEM, Action::CREATE_PURCHASE_REQUESTS], mode: HasPermission::IN_JSON)]
    public function new(EntityManagerInterface $entityManager,
                        Request                $request,
                        AttachmentService      $attachmentService,
                        PurchaseRequestService $purchaseRequestService): Response
    {
        $data = $request->request->all();
        $statusRepository = $entityManager->getRepository(Statut::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $supplierRepository = $entityManager->getRepository(Fournisseur::class);

        $status = $statusRepository->find($data['status']);
        $requester = $userRepository->find($data['requester']);
        $supplier = isset($data['supplier']) ? $supplierRepository->find($data['supplier']) : null;

        if ($status->isPreventStatusChangeWithoutDeliveryFees() && ($data['deliveryFee'] === null)) {
            throw new FormException("Les frais de livraisons doivent être renseignés.");
        }

        $purchaseRequest = $purchaseRequestService->createPurchaseRequest(
            $status,
            $requester,
            [
                "comment" => $data['comment'] ?? null,
                "supplier" => $supplier,
                "deliveryFee" => $data['deliveryFee'] ?? null,
            ]
        );

        $entityManager->persist($purchaseRequest);
        $attachmentService->manageAttachments($entityManager, $purchaseRequest, $request->files);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'redirect' => $this->generateUrl('purchase_request_show', ['id' => $purchaseRequest->getId()]),
        ]);
    }

    #[Route('/ligne/api-modifier', name: 'purchase_request_line_edit_api', options: ['expose' => true], methods: 'GET|POST', condition: 'request.isXmlHttpRequest()')]
    public function editLineApi(Request                $request,
                                EntityManagerInterface $entityManager,
                                UserService            $userService): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            if ($userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                $purchaseRequestLineRepository = $entityManager->getRepository(PurchaseRequestLine::class);
                $purchaseRequestLine = $purchaseRequestLineRepository->find($data['id']);

                $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);
                $fieldsParam = $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_RECEPTION);
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

    #[Route("/ligne/modifier", name: "purchase_request_line_edit", options: ["expose" => true], methods: [self::POST], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function editLine(Request                $request,
                             EntityManagerInterface $entityManager): Response
    {
        $data = json_decode($request->getContent(), true);
        $response = [];
        $purchaseRequestLineRepository = $entityManager->getRepository(PurchaseRequestLine::class);
        /** @var PurchaseRequestLine $purchaseRequestLine */
        $purchaseRequestLine = $purchaseRequestLineRepository->find($data['lineId']);

        if (!empty($purchaseRequestLine)) {
            if (isset($data['supplier'])) {
                $supplierRepository = $entityManager->getRepository(Fournisseur::class);
                $supplier = $supplierRepository->find($data['supplier']);
            }

            if (isset($data['orderDate']) && $data['orderDate']) {
                $orderDate = $this->getFormatter()->parseDatetime($data['orderDate']) ?: null;
            }

            if (isset($data['expectedDate']) && $data['expectedDate']) {
                $expectedDate = $this->getFormatter()->parseDatetime($data['expectedDate']) ?: null;
            }

            $purchaseRequestLine
                ->setSupplier($supplier ?? null)
                ->setOrderNumber($data['orderNumber'] ?? null)
                ->setOrderedQuantity((int)$data['orderedQuantity'] ?? null)
                ->setOrderDate($orderDate ?? null)
                ->setExpectedDate($expectedDate ?? null)
                ->setUnitPrice(floatval($data[FixedFieldEnum::unitPrice->name]));

            $entityManager->flush();
            $response = [
                'success' => true,
                'msg' => "La ligne de demande d'achat a bien été modifiée"
            ];
        }
        return new JsonResponse($response);
    }

    #[Route('/api-modifier', name: 'purchase_request_api_edit', options: ['expose' => true], methods: 'GET|POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::DEM, Action::EDIT_DRAFT_PURCHASE_REQUEST], mode: HasPermission::IN_JSON)]
    public function editApi(Request                $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $purchaseRequestRepository = $entityManager->getRepository(PurchaseRequest::class);
            $statusRepository = $entityManager->getRepository(Statut::class);

            $purchaseRequest = $purchaseRequestRepository->find($data['id']);

            $currentStatus = $purchaseRequest->getStatus();
            $statuses = $currentStatus
                ? $statusRepository->findByCategoryAndStates(CategorieStatut::PURCHASE_REQUEST, [$currentStatus->getState()])
                : [];

            $json = $this->renderView('purchase_request/form_content_modal.html.twig', [
                'purchaseRequest' => $purchaseRequest,
                'statuses' => $statuses,
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    #[Route('/modifier', name: 'purchase_request_edit', options: ['expose' => true], methods: ['GET', 'POST'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::DEM, Action::EDIT_DRAFT_PURCHASE_REQUEST], mode: HasPermission::IN_JSON)]
    public function edit(EntityManagerInterface $entityManager,
                         Request                $request,
                         PurchaseRequestService $purchaseRequestService,
                         AttachmentService      $attachmentService): Response
    {

        $statusRepository = $entityManager->getRepository(Statut::class);
        $purchaseRequestRepository = $entityManager->getRepository(PurchaseRequest::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $supplierRepository = $entityManager->getRepository(Fournisseur::class);;
        $post = $request->request;

        $purchaseRequest = $purchaseRequestRepository->find($post->get('id'));

        /** @var Utilisateur $requester */
        $requester = $post->has('requester') ? $userRepository->find($post->get('requester')) : $purchaseRequest->getRequester();
        $comment = $post->get('comment') ?: '';
        $newStatus = $statusRepository->find($post->get('status'));
        $supplier = $post->get('supplier') ? $supplierRepository->find($post->get('supplier')) : null;

        if ($newStatus->isPreventStatusChangeWithoutDeliveryFees() && $post->get('deliveryFee') === null) {
            throw new FormException("Les frais de livraisons doivent être renseignés.");
        }

        $currentStatus = $purchaseRequest->getStatus();
        if (!$currentStatus
            || !$newStatus
            || $newStatus->getState() === $currentStatus->getState()) {
            $purchaseRequest->setStatus($newStatus);
        }

        $purchaseRequest
            ->setComment($comment)
            ->setRequester($requester)
            ->setSupplier($supplier)
            ->setDeliveryFee($post->get('deliveryFee') ?? null);


        $attachmentService->removeAttachments($entityManager, $purchaseRequest, $post->all()['files'] ?? []);
        $attachmentService->manageAttachments($entityManager, $purchaseRequest, $request->files);

        $entityManager->flush();

        $number = $purchaseRequest->getNumber();
        $purchaseRequestStatus = $purchaseRequest->getStatus();

        return $this->json([
            'success' => true,
            'msg' => "La demande d'achat <strong>{$number}</strong> a bien été modifiée",
            'entete' => $this->renderView('purchase_request/show_header.html.twig', [
                'request' => $purchaseRequest,
                'modifiable' => $purchaseRequestStatus && $purchaseRequestStatus->isDraft(),
                'showDetails' => $purchaseRequestService->createHeaderDetailsConfig($purchaseRequest)
            ]),
        ]);
    }

    #[Route('/line/remove-line', name: 'purchase_request_line_remove_line', options: ['expose' => true], methods: ['GET', 'POST'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::DEM, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function removeLine(Request                $request,
                               EntityManagerInterface $entityManager)
    {
        if ($data = json_decode($request->getContent())) {
            $purchaseRequestRepository = $entityManager->getRepository(PurchaseRequest::class);
            $purchaseRequestLineRepository = $entityManager->getRepository(PurchaseRequestLine::class);

            $purchaseRequest = $purchaseRequestRepository->find($data->request);
            $purchaseRequestLine = $purchaseRequestLineRepository->find($data->lineId);

            if ($purchaseRequestLine && $purchaseRequest) {
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

    #[Route("/{id}/consider", name: "consider_purchase_request", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::EDIT_ONGOING_PURCHASE_REQUESTS], mode: HasPermission::IN_JSON)]
    public function consider(Request                $request,
                             EntityManagerInterface $entityManager,
                             PurchaseRequest        $purchaseRequest,
                             PurchaseRequestService $purchaseRequestService): Response
    {

        $data = json_decode($request->getContent(), true);
        $statusRepository = $entityManager->getRepository(Statut::class);

        $status = $data['status'];
        $inProgressStatus = $statusRepository->find($status);

        if ($inProgressStatus->isPreventStatusChangeWithoutDeliveryFees() && $purchaseRequest->getDeliveryFee() === null) {
            throw new FormException("Les frais de livraisons doivent être renseignés.");
        }

        $purchaseRequest
            ->setStatus($inProgressStatus)
            ->setConsiderationDate(new DateTime('now'));

        $entityManager->flush();
        $purchaseRequestService->sendMailsAccordingToStatus($entityManager, $purchaseRequest);

        return $this->json([
            'success' => true,
            'msg' => 'La demande d\'achat a bien été prise en compte',
            'redirect' => $this->generateUrl('purchase_request_show', ['id' => $purchaseRequest->getId()])
        ]);
    }

    #[Route('/{id}/treat', name: 'treat_purchase_request', options: ['expose' => true], methods: [self::GET, self::POST], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::DEM, Action::EDIT_ONGOING_PURCHASE_REQUESTS], mode: HasPermission::IN_JSON)]
    public function treat(Request                $request,
                          EntityManagerInterface $entityManager,
                          PurchaseRequest        $purchaseRequest,
                          PurchaseRequestService $purchaseRequestService): Response
    {

        $data = json_decode($request->getContent(), true);
        $statusRepository = $entityManager->getRepository(Statut::class);

        /** @var Statut $status */
        $status = $data['status'];
        $treatedStatus = $statusRepository->find($status);

        if ($treatedStatus->isPreventStatusChangeWithoutDeliveryFees() && $purchaseRequest->getDeliveryFee() === null) {
            throw new FormException("Les frais de livraisons doivent être renseignés.");
        }

        if (!$purchaseRequest->isPurchaseRequestLinesFilled()) {
            throw new FormException('Des informations sont manquantes sur une ou plusieurs lignes d\'achat. <br> Impossible de terminer la demande d\'achat.');
        }

        if ($treatedStatus->getAutomaticReceptionCreation()) {
            $purchaseRequestService->persistAutomaticReceptionWithStatus($entityManager, $purchaseRequest);
        }

        $purchaseRequest
            ->setStatus($treatedStatus)
            ->setProcessingDate(new DateTime('now'));
        $entityManager->flush();
        $purchaseRequestService->sendMailsAccordingToStatus($entityManager, $purchaseRequest);

        return $this->json([
            'success' => true,
            'msg' => 'La demande d\'achat a bien été traitée',
            'redirect' => $this->generateUrl('purchase_request_show', ['id' => $purchaseRequest->getId()])
        ]);
    }

    #[Route('/{id}/valider', name: 'purchase_request_validate', options: ['expose' => true], methods: ['GET', 'POST'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::DEM, Action::EDIT_DRAFT_PURCHASE_REQUEST], mode: HasPermission::IN_JSON)]
    public function validate(PurchaseRequest        $purchaseRequest,
                             EntityManagerInterface $entityManager,
                             Request                $request,
                             PurchaseRequestService $purchaseRequestService): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $statusRepository = $entityManager->getRepository(Statut::class);

            $validationDate = new DateTime("now");
            $status = $statusRepository->find($data['status']);
            if (!$status) {
                $message = "Le statut sélectionné n'existe pas.";
            }

            // if status prevent status change without delivery fees and delivery fee is not set (set 0 is allowed)
            if ($status->isPreventStatusChangeWithoutDeliveryFees() && $purchaseRequest->getDeliveryFee() === null) {
                $message = "Les frais de livraisons doivent être renseignés.";
            }

            if ($purchaseRequest->getPurchaseRequestLines()->isEmpty()) {
                $message = "Vous ne pouvez pas valider une demande d'achat vide.";
            }

            if (!empty($message)) {
                throw new FormException($message);
            }

            $purchaseRequest
                ->setStatus($status)
                ->setValidationDate($validationDate);

            $entityManager->flush();
            $purchaseRequestService->sendMailsAccordingToStatus($entityManager, $purchaseRequest);

            $number = $purchaseRequest->getNumber();

            return $this->json([
                'success' => true,
                'msg' => "La demande d'achat <strong>{$number}</strong> a bien été validée",
                'entete' => $this->renderView('purchase_request/show_header.html.twig', [
                    'modifiable' => $status->isDraft(),
                    'request' => $purchaseRequest,
                    'showDetails' => $purchaseRequestService->createHeaderDetailsConfig($purchaseRequest)
                ]),
            ]);
        }
        throw new BadRequestHttpException();
    }

    #[Route('/api-references', name: 'purchase_api_references', options: ['expose' => true], methods: ['POST'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS], mode: HasPermission::IN_JSON)]
    public function apiReferences(Request                $request,
                                  PurchaseRequestService $service): Response
    {

        return $this->json($service->getDataForReferencesDatatable($request->request->get('purchaseId')));
    }

    #[Route('/generatePurchaseOrder/{purchaseRequest}', name: 'generate_purchase_order', options: ['expose' => true], methods: [self::GET], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS], mode: HasPermission::IN_JSON)]
    public function generatePurchaseOrder(PurchaseRequest        $purchaseRequest,
                                          EntityManagerInterface $entityManager,
                                          PurchaseRequestService $purchaseRequestService): JsonResponse
    {

        $statusRepository = $entityManager->getRepository(Statut::class);
        $now = new DateTime();

        // génère seulement si en cours et tableau remplis
        if (!$purchaseRequest->isPurchaseRequestLinesFilled()) {
            throw new FormException("La demande d'achat ne peut pas être traitée si les références n'ont pas été remplies");
        }

        if (!$purchaseRequest->getStatus()->isTreated()) {
            /** @var Statut $nextStatus */
            $nextStatus = Stream::from($statusRepository->findByCategorieName(CategorieStatut::PURCHASE_REQUEST))
                ->filter(static fn(Statut $status) => $status->isPassStatusAtPurchaseOrderGeneration())
                ->first();

            // if the next status is different from current status, then change status
            if ($nextStatus && $nextStatus->getId() !== $purchaseRequest->getStatus()?->getId()) {

                // if the parameter "blocage du changement de statut si frais de livraison non rempli" is enabled, check if the purchase request has a delivery fee before changing status
                if ($nextStatus->isPreventStatusChangeWithoutDeliveryFees() && $purchaseRequest->getDeliveryFee() === null) {
                    throw new FormException("Les frais de livraisons doivent être renseignés. Vous ne pouvez pas passer de statut sans frais de livraison défini.");
                }

                $purchaseRequest->setStatus($nextStatus);

                // create automatic reception if parameter is enabled
                if ($nextStatus->getAutomaticReceptionCreation()) {
                    $purchaseRequestService->persistAutomaticReceptionWithStatus($entityManager, $purchaseRequest);
                }

                // set date according to status
                switch ($purchaseRequest->getStatus()->getState()) {
                    case Statut::IN_PROGRESS:
                        if (!$purchaseRequest->getConsiderationDate()) {
                            $purchaseRequest->setConsiderationDate($now);
                        }
                        break;
                    case Statut::TREATED:
                        if (!$purchaseRequest->getProcessingDate()) {
                            $purchaseRequest->setProcessingDate($now);
                        }
                        break;
                }
            }
        }

        $purchaseRequestService->sendMailsAccordingToStatus($entityManager, $purchaseRequest);
        $purchaseRequestOrderAttachment = $purchaseRequestService->getPurchaseRequestOrderData($entityManager, $purchaseRequest);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'msg' => 'Le téléchargement de votre bon de commande va commencer...',
            'attachmentId' => $purchaseRequestOrderAttachment->getId(),
        ]);
    }

    #[Route("/{purchaseRequest}/purchaseOrder/{attachment}", name: "print_purchase_order", options: ["expose" => true], methods: [self::GET], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS], mode: HasPermission::IN_JSON)]
    public function printDeliverySlip(Attachment      $attachment,
                                      KernelInterface $kernel): Response
    {

        $response = new BinaryFileResponse(($kernel->getProjectDir() . '/public/uploads/attachments/' . $attachment->getFileName()));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $attachment->getOriginalName());

        return $response;
    }


}

