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
use App\Helper\FormatHelper;
use App\Service\PurchaseRequestService;
use DateTime;
use App\Service\CSVExportService;
use App\Service\UserService;

use DateTimeZone;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Generator;
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
     * @Route("/api", name="purchase_request_api", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
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
    public function show(PurchaseRequest $request, PurchaseRequestService $purchaseRequestService): Response {

        $status = $request->getStatus();
        return $this->render('purchase_request/show.html.twig', [
            'request' => $request,
            'modifiable' => isset($status) ? $request->getStatus()->isDraft() : "",
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
                        if ($lines instanceof Generator && $lines->valid()) {
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
     * @HasPermission({Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS})
     */
    public function delete(Request $request, EntityManagerInterface $entityManager, UserService $userService): Response {

        if($data = json_decode($request->getContent(), true)) {

            $requestRepository = $entityManager->getRepository(PurchaseRequest::class);
            $purchaseRequest =$requestRepository->find($data['request']);
            $status = $purchaseRequest->getStatus();

            if( !$status ||
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
                'msg' => "La demande dachat a bien été supprimé"
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
                                 EntityManagerInterface $entityManager,
                                 PurchaseRequest $purchaseRequest): Response {

        $data = json_decode($request->getContent(), true);

        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $reference = $referenceArticleRepository->find($data['reference']);
        $requestedQuantity = $data['requestedQuantity'];

        $lineWithSameRef = $purchaseRequest->getPurchaseRequestLines()
            ->filter(fn (PurchaseRequestLine $line) => $line->getReference() === $reference)
            ->toArray();

        if($reference == null){
            $errorMessage = "La référence n'existe pas";
        }
        else if ($requestedQuantity == null || $requestedQuantity < 1) {
            $errorMessage = "La quantité ajoutée n'est pas valide";
        }
        else if (!empty($lineWithSameRef)) {
            $errorMessage = "La référence a déjà était ajoutée à la demande d'achat";
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

        $entityManager->persist($purchaseRequestLine);

        $entityManager->flush();

        return $this->json([
            "success" => true,
            'msg' => "La référence a bien était ajoutée à la demande d'achat"
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
                        EntityManagerInterface $entityManager): Response {

        /** @var Utilisateur $requester */
        $requester = $this->getUser();

        $status = $entityManager->getRepository(Statut::class)->findOneByCategorieNameAndStatutState(CategorieStatut::PURCHASE_REQUEST, Statut::DRAFT);
        if (!$status) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'Aucun statut brouillon crée pour les demandes d\'achat. Veuillez en paramétrer un.'
            ]);
        }
        $purchaseRequest = $purchaseRequestService->createPurchaseRequest($entityManager, $status, $requester);

        $entityManager->persist($purchaseRequest);

        try {
            $entityManager->flush();
        }
            /** @noinspection PhpRedundantCatchClauseInspection */
        catch (UniqueConstraintViolationException $e) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'Une autre demande d\'achat est en cours de création, veuillez réessayer.'
            ]);
        }
        $number = $purchaseRequest->getNumber();
        return $this->json([
            'success' => true,
            'msg' => "La demande d'achat <strong>${number}</strong> a bien été créée"
        ]);
    }
}
