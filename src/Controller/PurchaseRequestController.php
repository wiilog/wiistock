<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Menu;
use App\Entity\Statut;
use App\Entity\PurchaseRequest;
use App\Entity\Utilisateur;
use App\Service\AttachmentService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\PurchaseRequestService;


/**
 * @Route("/achat")c
 */
class PurchaseRequestController extends AbstractController
{
    /**
     * @var PurchaseRequest
     */
    private $purchaseRequestService;

    public function __construct(PurchaseRequestService $purchaseRequestService)
    {
        $this->purchaseRequestService = $purchaseRequestService;
    }

    /**
     * @Route("/", name="purchase_request_index")
     * @HasPermission({Menu::DEM, Action::SHOW_PURCHASE_REQUESTS})
     */
    public function index(EntityManagerInterface $entityManager): Response
    {
        $statusRepository = $entityManager->getRepository(Statut::class);

        return $this->render('purchase_request/request/index.html.twig', [
            'statuts' => $statusRepository->findByCategorieName(CategorieStatut::PURCHASE_REQUEST),
        ]);
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

        $status = $entityManager->getRepository(Statut::class)->findOneByCategorieNameAndStatutCode(CategorieStatut::PURCHASE_REQUEST, Statut::DRAFT);
        $purchaseRequest = $purchaseRequestService->createPurchaseRequest($entityManager, $status, $requester, null);
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
            $status = $statusRepository->findByCategoryNameAndStatusCodes(CategorieStatut::PURCHASE_REQUEST,[Statut::IN_PROGRESS]);
            $purchaseRequest = $purchaseRequestRepository->find($data['id']);
            $json = $this->renderView('handling/modalEditPurchaseRequestContent.html.twig', [
                'purchaseRequest' => $purchaseRequest,
                'statuses' => $status
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
                         AttachmentService $attachmentService): Response {

        $statusRepository = $entityManager->getRepository(Statut::class);
        $purchaseRequestRepository = $entityManager->getRepository(PurchaseRequest::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);

        $post = $request->request;

        $purchaseRequest = $purchaseRequestRepository->find($post->get('id'));

        /** @var Utilisateur $requester */
        $requester = $post->has('requester') ? $userRepository->find($post->get('requester')) : $purchaseRequest->getRequester();
        $comment = $post->get('comment') ?: '';
        $status = $statusRepository->find($post->get('status'));

        $purchaseRequest
            ->setComment($comment)
            ->setStatus($status)
            ->setRequester($requester);

        $purchaseRequest->removeIfNotIn($data['files'] ?? []);
        $attachmentService->manageAttachments($entityManager, $purchaseRequest, $request->files);

        $entityManager->flush();

        $number = $purchaseRequest->getNumber();

        return $this->json([
            'success' => true,
            'msg' => "La demande d'achat <strong>${number}</strong> a bien été modifiée"
        ]);
    }
}
