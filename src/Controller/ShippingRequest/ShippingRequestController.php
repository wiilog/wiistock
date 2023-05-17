<?php

namespace App\Controller\ShippingRequest;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\Setting;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\ShippingRequest\ShippingRequestExpectedLine;
use App\Entity\ShippingRequest\ShippingRequestLine;
use App\Entity\ShippingRequest\ShippingRequestPack;
use App\Entity\StatusHistory;
use App\Entity\Statut;
use App\Entity\TrackingMovement;
use App\Entity\Transporteur;
use App\Entity\Utilisateur;
use App\Repository\MouvementStockRepository;
use App\Service\MouvementStockService;
use App\Service\ShippingRequest\ShippingRequestService;
use App\Service\StatusHistoryService;
use App\Service\TranslationService;
use App\Service\UserService;
use App\Service\VisibleColumnService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

#[Route("/expeditions")]
class ShippingRequestController extends AbstractController {

    #[Route("/", name: "shipping_request_index", options: ["expose" => true])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function index(EntityManagerInterface $entityManager,
                          ShippingRequestService $service,
                          TranslationService $translationService): Response {
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);

        $currentUser = $this->getUser();
        $fields = $service->getVisibleColumnsConfig($currentUser);


        $statutRepository = $entityManager->getRepository(Statut::class);
        $carrierRepository = $entityManager->getRepository(Transporteur::class);

        $dateChoice = [
            [
                'name' => 'createdAt',
                'label' => $translationService->translate('Général', null, 'Zone liste', 'Date de création'),
            ],
            [
                'name' => 'requestCaredAt',
                'label' => $translationService->translate('Demande', 'Expédition', 'Date de prise en charge souhaitée'),
            ],
            [
                'name' => 'validatedAt',
                'label' => $translationService->translate('Demande', 'Expédition', 'Date de validation'),
            ],
            [
                'name' => 'plannedAt',
                'label' => $translationService->translate('Demande', 'Expédition', 'Date de planification'),
            ],
            [
                'name' => 'expectedPickedAt',
                'label' => $translationService->translate('Demande', 'Expédition', 'Date d\'enlèvement prévu'),
            ],
            [
                'name' => 'treatedAt',
                'label' => $translationService->translate('Demande', 'Expédition', 'Date d\'expédition'),
            ],
        ];
        foreach ($dateChoice as &$choice) {
            $choice['default'] = (bool)$filtreSupRepository->findOnebyFieldAndPageAndUser('date-choice_'.$choice['name'], 'expedition', $currentUser);
        }
        if (Stream::from($dateChoice)->every(function ($choice) { return !$choice['default']; })) {
            $dateChoice[0]['default'] = true;
        }

        return $this->render('shipping_request/index.html.twig', [
            "fields" => $fields,
            "initial_visible_columns" => $this->apiColumns($service)->getContent(),
            "statuses" => $statutRepository->findByCategorieName(ShippingRequest::CATEGORIE, 'displayOrder'),
            "dateChoices" =>$dateChoice,
            "carriersForFilter" => $carrierRepository->findAll(),
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
        return $this->json($service->getDataForDatatable($request, $entityManager));
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

    #[Route("/voir/{id}", name:"shipping_show_page", options:["expose"=>true])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function showPage(Request                $request,
                             ShippingRequest        $shippingRequest,
                             ShippingRequestService $shippingRequestService,
                             EntityManagerInterface $entityManager): Response {


        return $this->render('shipping_request/show.html.twig', [
            'shipping'=> $shippingRequest,
            'detailsTransportConfig' => $shippingRequestService->createHeaderTransportDetailsConfig($shippingRequest)
        ]);
    }

    #[Route("/delete-shipping-request/{id}", name: "delete_shipping_request", options: ["expose" => true], methods: ['DELETE'])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function deleteShippingRequest(ShippingRequest        $shippingRequest,
                                          EntityManagerInterface $entityManager,
                                          UserService            $userService,
                                          MouvementStockService  $mouvementStockService): Response
    {

        $user = $this->getUser();
        $statusHistoryRepository = $entityManager->getRepository(StatusHistory::class);
        //todo: statut expédié ?

        // remove status to treat only if user has right and shipping request is to treat
        if ($shippingRequest->getStatus()->getCode() === ShippingRequest::STATUS_TO_TREAT) {
            if ($userService->hasRightFunction(Menu::DEM, Action::DELETE_TO_TREAT_SHIPPING, $user)) {

                /* @var ShippingRequestExpectedLine $expectedLines */
                foreach ($shippingRequest->getExpectedLines() as $expectedLines) {
                    $entityManager->remove($expectedLines);
                }

                // remove status_history where shipping_request_id equals $shippingRequest.id
                $statusHistoryToRemove = $statusHistoryRepository->findBy(['shippingRequest' => $shippingRequest->getId()]);
                foreach ($statusHistoryToRemove as $status) {
                    $entityManager->remove($status);
                }

                $entityManager->remove($shippingRequest);
            } else {
                return $this->json([
                    'success' => false,
                    'msg' => 'Vous n\'avez pas pas la permission pour supprimer cette expédition (' . ShippingRequest::STATUS_TO_TREAT . ')',
                ]);
            }
            //$entityManager->flush();
            return $this->json([
                "success" => true,
            ]);
        }

        // remove status scheduled only if user has right and shipping request is scheduled
        if ($shippingRequest->getStatus()->getCode() === ShippingRequest::STATUS_SCHEDULED) {
            if ($userService->hasRightFunction(Menu::DEM, Action::DELETE_PLANIFIED_SHIPPING, $user)) {

                // remove status_history where shipping_request_id equals $shippingRequest.id
                $statusHistoryToRemove = $statusHistoryRepository->findBy(['shippingRequest' => $shippingRequest->getId()]);
                foreach ($statusHistoryToRemove as $status) {
                    $entityManager->remove($status);
                }

                /* @var ShippingRequestPack $packLine */
                foreach ($shippingRequest->getPackLines() as $packLine) {
                    $pack = $packLine->getPack();

                    /* @var ShippingRequestLine $requestLine */
                    foreach ($packLine->getLines() as $requestLine) {
                        $article = $requestLine->getArticle();

                        // remove mvt track (article)
                        foreach ($article->getTrackingMovements()->toArray() as $trackingMovement) {
                            $entityManager->remove($trackingMovement);
                        }
                        //remove mvt stock (article)
                        foreach ($article->getMouvements()->toArray() as $stockMovement) {
                            $mouvementStockService->manageMouvementStockPreRemove($stockMovement, $entityManager);
                            $article->removeMouvement($stockMovement);
                            $entityManager->remove($stockMovement);
                        }

                        $packLine->removeLine($requestLine);
                        $entityManager->remove($requestLine);
                        $requestLine->getExpectedLine()->removeLine($requestLine);
                    }

                    // remove mvt track (pack)
                    foreach ($pack->getTrackingMovements()->toArray() as $trackingMovement) {
                        $entityManager->remove($trackingMovement);
                    }
                    $entityManager->remove($pack); // cascade remove article todo : bien vérif qu'il y a sup de l'article
                    $entityManager->remove($packLine);
                }

                // remove 'ShippingRequesExpectedtLine'
                foreach ($shippingRequest->getExpectedLines() as $expectedLine) {
                    $shippingRequest->removeExpectedLine($expectedLine);
                    $entityManager->remove($expectedLine);
                }

                $entityManager->remove($shippingRequest);
                $entityManager->flush();
            } else {
                return $this->json([
                    'success' => false,
                    'msg' => 'Vous n\'avez pas pas la permission pour supprimer cette expédition (' . ShippingRequest::STATUS_TO_TREAT . ')',
                ]);
            }

            return $this->json([
                "success" => true,
            ]);
        }

        return $this->json([
            'success' => false,
        ]);
    }

    #[Route("/validateShippingRequest/{id}", name:'shipping_request_validation', options:["expose"=>true], methods: ['GET'])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function shippingRequestValidation(ShippingRequest        $shippingRequest,
                                              StatusHistoryService   $statusHistoryService,
                                              EntityManagerInterface $entityManager): JsonResponse
    {
        $currentUser = $this->getUser();

        // shippingRequest need at least 1 expectedLines (ref)
        if($shippingRequest->getExpectedLines()->count() <= 0){
            return $this->json([
                'success'=>false,
                'msg'=> 'Veuillez ajouter au moins une référence.',
            ]);
        }

        $newStatusForShippingRequest = $entityManager->getRepository(Statut::class)
                                                     ->findOneByCategorieNameAndStatutCode(
                                                         CategorieStatut::SHIPMENT,
                                                         ShippingRequest::STATUS_TO_TREAT
                                                     );

        $shippingRequest
            ->setValidatedAt(new \DateTime())
            ->setValidatedBy($currentUser)
        ;

        $statusHistoryService->updateStatus(
            $entityManager,
            $shippingRequest,
            $newStatusForShippingRequest,
            ['setStatus'=> true],
        );

        // Check that the status has been updated
        if($shippingRequest->getStatus() !== $newStatusForShippingRequest){
            return $this->json([
                'success'=>false,
                'msg'=> 'Une erreur est survenue lors du changement de statut.',
            ]);
        }

        $entityManager->flush();

        return $this->json([
            "success"=> true,
            'msg'=> 'La validation de votre demande d\'expédition a bien été prise en compte. ',
        ]);
    }

    #[Route("/get-transport-header-config/{id}", name:"get_transport_header_config", methods: ['GET', 'POST'], options:["expose"=>true])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function getTransportHeaderConfig(ShippingRequest        $shippingRequest,
                                             ShippingRequestService $shippingRequestService): Response {
        return $this->json([
            'detailsTransportConfig' => $shippingRequestService->createHeaderTransportDetailsConfig($shippingRequest)
        ]);
    }
}
