<?php

namespace App\Controller\ShippingRequest;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Menu;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Service\ShippingRequest\ShippingRequestService;
use App\Service\StatusHistoryService;
use App\Service\TranslationService;
use App\Service\UniqueNumberService;
use App\Service\VisibleColumnService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route("/expeditions")]
class ShippingRequestController extends AbstractController {

    #[Route("/", name: "shipping_request_index")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function index(ShippingRequestService $service) {
        $currentUser = $this->getUser();
        $fields = $service->getVisibleColumnsConfig($currentUser);

        return $this->render('shipping_request/index.html.twig', [
            "fields" => $fields,
            "initial_visible_columns" => $this->apiColumns($service)->getContent(),
        ]);
    }

    #[Route("/api-columns", name: "shipping_request_api_columns", options: ["expose" => true], methods: ['GET'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING], mode: HasPermission::IN_JSON)]
    public function apiColumns(ShippingRequestService $service): Response {
        $currentUser = $this->getUser();
        $columns = $service->getVisibleColumnsConfig($currentUser);

        return new JsonResponse($columns);
    }

    #[Route("/api", name: "shipping_request_api", options: ["expose" => true], methods: ['GET'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING], mode: HasPermission::IN_JSON)]
    public function api(Request                $request,
                        ShippingRequestService $service,
                        EntityManagerInterface $entityManager) {
        return $this->json($service->getDataForDatatable( $entityManager, $request));
    }

    #[Route("/colonne-visible", name: "save_column_visible_for_shipping_request", options: ["expose" => true], methods: ['POST'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING], mode: HasPermission::IN_JSON)]
    public function saveColumnVisible(Request                $request,
                                      EntityManagerInterface $entityManager,
                                      VisibleColumnService   $visibleColumnService,
                                      TranslationService     $translationService): Response {
        $data = json_decode($request->getContent(), true);
        $fields = array_keys($data);
        $fields[] = "actions";

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $visibleColumnService->setVisibleColumns('shippingRequest', $fields, $currentUser);

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => $translationService->translate('Général', null, 'Zone liste', 'Vos préférences de colonnes à afficher ont bien été sauvegardées', false)
        ]);
    }

    #[Route("/form-submit", name: "shipping_request_form_submit", options: ["expose" => true], methods: ['POST'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::CREATE_SHIPPING], mode: HasPermission::IN_JSON)]
    public function formSubmit(Request                $request,
                               EntityManagerInterface $entityManager,
                               ShippingRequestService $shippingRequestService,
                               UniqueNumberService    $uniqueNumberService,
                               StatusHistoryService   $statusHistoryService): JsonResponse {
        $data = $request->request->all();

        if($shippingRequestId = $data['shippingRequestId'] ?? false) {
            $shippingRequestRepository = $entityManager->getRepository(ShippingRequest::class);
            $shippingRequest = $shippingRequestRepository->find($shippingRequestId);
        } else {
            $statusRepository = $entityManager->getRepository(Statut::class);
            $shippingRequest = new ShippingRequest();
            $shippingRequest
                ->setNumber($uniqueNumberService->create($entityManager, ShippingRequest::NUMBER_PREFIX, ShippingRequest::class, UniqueNumberService::DATE_COUNTER_FORMAT_TRANSPORT))
                ->setCreatedAt(new \DateTime('now'))
                ->setCreatedBy($this->getUser());

            $statusHistoryService->updateStatus(
                $entityManager,
                $shippingRequest,
                $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::SHIPMENT, ShippingRequest::STATUS_DRAFT),
                ['setStatus' => true],
            );

            $entityManager->persist($shippingRequest);
        }

        if($shippingRequestService->updateShippingRequest($entityManager, $shippingRequest, $data)){
            $entityManager->flush();
            return $this->json([
                'success' => true,
                'msg' => 'Votre demande d\'expédition a bien été enregistrée',
                'shippingRequestId' => $shippingRequest->getId(),
            ]);
        } else {
            throw new FormException();
        }
    }
}
