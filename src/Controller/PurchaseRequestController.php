<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Menu;
use App\Entity\PurchaseRequest;
use App\Entity\PurchaseRequestLine;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Service\AttachmentService;
use App\Service\MailerService;
use App\Service\PurchaseRequestService;
use DateTime;
use App\Service\CSVExportService;
use App\Service\UserService;

use DateTimeZone;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Iterator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;


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
     * @HasPermission({Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS})
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
                         PurchaseRequestService $purchaseRequestService): Response {
        $status = $request->getStatus();
        return $this->render('purchase_request/show.html.twig', [
            'request' => $request,
            'modifiable' => $status && $status->isDraft(),
            'detailsConfig' => $purchaseRequestService->createHeaderDetailsConfig($request)
        ]);
    }

    /**
     * @Route("/csv", name="purchase_request_export",options={"expose"=true}, methods="GET|POST" )
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
            $now = new DateTime("now", new DateTimeZone("Europe/Paris"));

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
                "Commentaire",
                "Référence",
                "Code barre",
                "Libellé"
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
                "export_demande_achat" . $now->format("d_m_Y") . ".csv",
                $header
            );
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="purchase_request_delete", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DELETE})
     */
    public function delete(Request $request,
                           UserService $userService,
                           EntityManagerInterface $entityManager): Response {

        if($data = json_decode($request->getContent(), true)) {
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
     * @Route("/{purchaseRequest}/reference/api", name="purchase_request_lines_api", options={"expose"=true}, methods={"GET"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS})
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
                'reservedQuantity' => isset($reference) ? $reference->getQuantiteReservee() : "",
                'orderNumber' => $requestLine->getOrderNumber(),
                'actions' => $this->renderView('purchase_request/line/actions.html.twig'),
            ];
        }

        return new JsonResponse([
            "data" => $rowsRC,
            "recordsFiltered" => 0,
            "recordsTotal" => count($rowsRC),
        ]);
    }

    /**
     * @Route("/{purchaseRequest}/ajouter-article", name="purchase_request_add_reference", options={"expose"=true})
     * @HasPermission({Menu::DEM, Action::EDIT})
     */
    public function addReference(Request $request,
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
            'msg' => "La référence a bien était ajoutée à la demande d'achat",
            'entete' => $this->renderView('purchase_request/show_header.html.twig', [
                'request' => $purchaseRequest,
                'modifiable' => $purchaseRequestStatus && $purchaseRequestStatus->isDraft(),
                'showDetails' => $purchaseRequestService->createHeaderDetailsConfig($purchaseRequest)
            ]),
        ]);
    }

    /**
     * @Route("/retirer-article", name="purchase_request_remove_reference", options={"expose"=true}, methods={"GET", "POST"})
     * @HasPermission({Menu::DEM, Action::EDIT})
     */
    public function removeArticle(Request $request, EntityManagerInterface $manager) {
        /*if($request->isXmlHttpRequest() && $data = json_decode($request->getContent())) {

            $purchaseRepository = $manager->getRepository(PurchaseRequest::class);
            $purchaseRequest = $purchaseRepository->find($data->request);

            if(array_key_exists(ReferenceArticle::TYPE_QUANTITE_REFERENCE, $data)) {
                $purchaseRequest->removeReference($manager
                    ->getRepository(ReferenceArticle::class)
                    ->find($data->reference));
            } elseif(array_key_exists(ReferenceArticle::TYPE_QUANTITE_ARTICLE, $data)) {
                $purchaseRequest->removeArticle($manager
                    ->getRepository(Article::class)
                    ->find($data->article));
            }

            $manager->flush();*/

            return new JsonResponse([
                'success' => true,
                'msg' => 'La référence a bien été supprimée de la demande dachat.'
            ]);


        //throw new BadRequestHttpException();
    }

    /**
     * @Route("/creer", name="purchase_request_new", options={"expose"=true}, methods={"GET", "POST"})
     * @HasPermission({Menu::DEM, Action::CREATE_PURCHASE_REQUESTS})
     */
    public function new(PurchaseRequestService $purchaseRequestService,
                        EntityManagerInterface $entityManager): Response
    {

        /** @var Utilisateur $requester */
        $requester = $this->getUser();

        $status = $entityManager->getRepository(Statut::class)->findOneByCategorieNameAndStatutState(CategorieStatut::PURCHASE_REQUEST, Statut::DRAFT);
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
            'redirect' => $this->generateUrl('purchase_request_show', ['id' => $purchaseRequest-> getId()]),
            'msg' => "La demande d'achat <strong>${number}</strong> a bien été créée"
        ]);
    }

    /**
     * @Route("/api-modifier", name="purchase_request_api_edit", options={"expose"=true},  methods="GET|POST")
     * @HasPermission({Menu::DEM, Action::EDIT_DRAFT_PURCHASE_REQUEST})
     */
    public function editApi(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
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
     * @Route("/modifier", name="purchase_request_edit", options={"expose"=true}, methods={"GET", "POST"})
     * @HasPermission({Menu::DEM, Action::EDIT_DRAFT_PURCHASE_REQUEST})
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
            ->setComment($comment)
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
     * @Route("/api-valider", name="purchase_request_validate_api", options={"expose"=true}, methods={"GET", "POST"})
     * @HasPermission({Menu::DEM, Action::EDIT_DRAFT_PURCHASE_REQUEST})
     */
    public function apiValidate(EntityManagerInterface $entityManager): Response {

        $statusRepository = $entityManager->getRepository(Statut::class);
        $statuses = $statusRepository->findByCategoryAndStates(CategorieStatut::PURCHASE_REQUEST, [Statut::NOT_TREATED]);

        $json = $this->renderView('purchase_request/validate_content_modal.html.twig', [
            'statuses' => $statuses
        ]);
        return new JsonResponse($json);
    }

    /**
     * @Route("/valider", name="purchase_request_validate", options={"expose"=true}, methods={"GET", "POST"})
     * @HasPermission({Menu::DEM, Action::EDIT_DRAFT_PURCHASE_REQUEST})
     */
    public function validate(EntityManagerInterface $entityManager,
                             Request $request,
                             MailerService $mailerService,
                             PurchaseRequestService $purchaseRequestService): Response
    {

        $statusRepository = $entityManager->getRepository(Statut::class);
        $purchaseRequestRepository = $entityManager->getRepository(PurchaseRequest::class);
        $data = json_decode($request->getContent(), true);
        $purchaseRequest = $purchaseRequestRepository->find($data['id']);

        $validationDate = new DateTime("now", new DateTimeZone("Europe/Paris"));
        $status = $statusRepository->find($data['status']);
        if (!$status) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'Le statut sélectionné n\'existe pas.'
            ]);
        }

        $purchaseRequest
            ->setStatus($status)
            ->setValidationDate($validationDate);

        $entityManager->flush();


        $subject = "Création d'une demande d'achat";
        $buyer = $purchaseRequest->getBuyer();
        $title = "Une demande d'achat vous concerne";
        if( $status->getSendNotifToBuyer() && $buyer != null){
            $mailerService->sendMail(
                'FOLLOW GT // ' . $subject,
                $this->renderView('mails/contents/mailPurchaseRequestValidate.html.twig', [
                    'purchaseRequest' => $purchaseRequest,
                    'title' => $title
                ]),
                $buyer
            );
        }


        $number = $purchaseRequest->getNumber();

        return $this->json([
            'success' => true,
            'msg' => "La demande d'achat <strong>${number}</strong> a bien été validé",
            'entete' => $this->renderView('purchase_request/show_header.html.twig', [
                'modifiable' => $status->isDraft(),
                'request' => $purchaseRequest,
                'showDetails' => $purchaseRequestService->createHeaderDetailsConfig($purchaseRequest)
            ]),
        ]);
    }
}
