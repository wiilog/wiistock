<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Collecte;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\CollecteReference;
use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use App\Entity\Emplacement;
use App\Entity\FreeField;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Menu;
use App\Entity\Cart;
use App\Entity\PurchaseRequest;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\TransferRequest;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Service\CartService;
use App\Service\DemandeLivraisonService;
use App\Service\FreeFieldService;
use App\Service\GlobalParamService;
use App\Service\PurchaseRequestService;
use App\Service\RefArticleDataService;
use App\Service\UniqueNumberService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Entity;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

/**
 * @Route("/panier")
 */
class CartController extends AbstractController
{

    /**
     * @Route("/", name="cart")
     */
    public function cart(EntityManagerInterface $manager, GlobalParamService $globalParamService): Response {
        $typeRepository = $manager->getRepository(Type::class);
        $freeFieldRepository = $manager->getRepository(FreeField::class);

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]);

        $deliveryFreeFields = [];
        foreach ($types as $type) {
            $champsLibres = $freeFieldRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_LIVRAISON);

            $deliveryFreeFields[] = [
                "typeLabel" => $type->getLabel(),
                "typeId" => $type->getId(),
                "champsLibres" => $champsLibres,
            ];
        }

        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_COLLECTE]);

        $collectFreeFields = [];
        foreach ($types as $type) {
            $champsLibres = $freeFieldRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_COLLECTE);

            $collectFreeFields[] = [
                "typeLabel" => $type->getLabel(),
                "typeId" => $type->getId(),
                "champsLibres" => $champsLibres,
            ];
        }

        $referencesByBuyer = [];
        foreach($currentUser->getCart()->getReferences() as $reference) {
            $buyerId = $reference->getBuyer() ? $reference->getBuyer()->getId() : null;
            if(!isset($referencesByBuyer[$buyerId])) {
                $referencesByBuyer[$buyerId] = [
                    "buyer" => $reference->getBuyer(),
                    "references" => [],
                ];
            }

            $referencesByBuyer[$buyerId]["references"][] = $reference;
        }

        $defaultDeliveryLocations = $globalParamService->getDefaultDeliveryLocationsByTypeId();
        $deliveryRequests = Stream::from($manager->getRepository(Demande::class)->getDeliveryRequestForSelect($currentUser))
            ->filter(fn(Demande $request) => $request->getType() && $request->getDestination())
            ->map(fn(Demande $request) => [
                "value" => $request->getId(),
                "text" => "{$request->getNumero()} - {$request->getType()->getLabel()} - {$request->getDestination()->getLabel()} - Créée le {$request->getCreatedAt()->format('d/m/Y H:i')}"
            ]);

        $collectRequests = Stream::from($manager->getRepository(Collecte::class)->getCollectRequestForSelect($currentUser))
            ->filter(fn(Collecte $request) => $request->getType() && $request->getPointCollecte())
            ->map(fn(Collecte $request) => [
                "value" => $request->getId(),
                "text" => "{$request->getNumero()} - {$request->getType()->getLabel()} - {$request->getPointCollecte()->getLabel()} - Créée le {$request->getDate()->format('d/m/Y H:i')}"
            ]);

        $purchaseRequests = Stream::from($manager->getRepository(PurchaseRequest::class)->getPurchaseRequestForSelect($currentUser))
            ->map(fn(PurchaseRequest $request) => [
                "value" => $request->getId(),
                "text" => "{$request->getNumber()} - Créée le {$request->getCreationDate()->format('d/m/Y H:i')}",
                "number" => $request->getNumber(),
                "requester" => $request->getRequester(),
            ]);

        return $this->render("cart/index.html.twig", [
            "deliveryRequests" => $deliveryRequests,
            "collectRequests" => $collectRequests,
            "purchaseRequests" => $purchaseRequests,
            "defaultDeliveryLocations" => $defaultDeliveryLocations,
            "deliveryFreeFieldsTypes" => $deliveryFreeFields,
            "collectFreeFieldsTypes" => $collectFreeFields,
            "referencesByBuyer" => $referencesByBuyer,
        ]);
    }

    /**
     * @Route("/api", name="cart_api", options={"expose"=true})
     */
    public function api(Request $request, CartService $service): Response
    {
        return $this->json($service->getDataForDatatable($request->request->all()));
    }

    /**
     * @Route("/ajouter/{reference}", name="cart_add_reference", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_REFE}, mode=HasPermission::IN_JSON)
     */
    public function addToCart(ReferenceArticle $reference, EntityManagerInterface $entityManager): JsonResponse {
        /** @var Cart $cart */
        $cart = $this->getUser()->getCart();
        if($cart->getReferences()->contains($reference)) {
            $referenceLabel = $reference->getReference();
            return $this->json([
                'success' => false,
                'msg' => "La référence <strong>${referenceLabel}</strong> est déjà présente dans votre panier"
            ]);
        }
        $cart->addReference($reference);
        $entityManager->flush();

        return $this->json([
            "success" => true,
            "msg" => "La référence a bien été ajoutée au panier",
            "count" => $cart->getReferences()->count()
        ]);
    }

    /**
     * @Route("/infos/livraison/{request}", name="cart_delivery_data", options={"expose"=true}, methods="GET")
     */
    public function deliveryRequestData(Demande $request): JsonResponse {
        return $this->json([
            "success" => true,
            "comment" => $request->getCommentaire(),
        ]);
    }

    /**
     * @Route("/infos/collecte/{request}", name="cart_collect_data", options={"expose"=true}, methods="GET")
     */
    public function collectRequestData(Collecte $request): JsonResponse {
        return $this->json([
            "success" => true,
            "destination" => $request->isDestruct() ? "Destruction" : "Mise en stock",
            "object" => $request->getObjet(),
            "comment" => $request->getCommentaire(),
        ]);
    }

    /**
     * @Route("/retirer/{reference}", name="cart_remove_reference", options={"expose"=true})
     */
    public function removeReference(EntityManagerInterface $manager, ReferenceArticle $reference): Response {
        $cart = $this->getUser()->getCart();
        $cart->removeReference($reference);
        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "La référence a bien été retirée du panier",
            "count" => $cart->getReferences()->count(),
        ]);
    }

    /**
     * @Route("/validate-cart", name="cart_validate", options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     */
    public function validateCart(Request $request,
                                 EntityManagerInterface $entityManager,
                                 DemandeLivraisonService $deliveryRequestService,
                                 CartService $cartService,
                                 FreeFieldService $freeFieldService,
                                 PurchaseRequestService $purchaseRequestService)
    {
        $data = json_decode($request->getContent(), true);
        switch ($data["requestType"]) {
            case "delivery":
                $response = $this->manageDeliveryRequest($data, $entityManager, $deliveryRequestService, $freeFieldService);
                break;
            case "collect":
                $response = $this->manageCollectRequest($data, $entityManager, $freeFieldService);
                break;
            case "purchase":
                $response = $cartService->managePurchaseRequest($data, $this->getUser(), $purchaseRequestService, $entityManager);
                break;
            default:
                throw new RuntimeException("Unsupported request type");
        }

        if($response["success"]) {
            $this->addFlash("success", $response['msg']);
        }

        return $this->json($response);
    }

    private function manageDeliveryRequest(array $data,
                                           EntityManagerInterface $manager,
                                           DemandeLivraisonService $demandeLivraisonService,
                                           FreeFieldService $freeFieldService): array {
        $referencesQuantities = json_decode($data['quantities'], true);

        $msg = '';
        $link = '';
        if ($data['addOrCreate'] === "add") {
            $deliveryRequest = $manager->find(Demande::class, $data['existingDelivery']);
            $this->addReferencesToCurrentUserCart($manager, $deliveryRequest, $referencesQuantities);

            $manager->flush();

            $link = $this->generateUrl('demande_show', ['id' => $deliveryRequest->getId()]);
            $msg = "Les références ont bien été ajoutées dans la demande existante";
        }
        else if ($data['addOrCreate'] === "create") {
            $statutRepository = $manager->getRepository(Statut::class);
            $destination = $manager->find(Emplacement::class, $data['destination']);
            $type = $manager->find(Type::class, $data['deliveryType']);
            $draft = $statutRepository->findOneByCategorieNameAndStatutCode(
                CategorieStatut::DEM_LIVRAISON,
                Demande::STATUT_BROUILLON
            );
            $deliveryRequest = new Demande();

            $deliveryRequest
                ->setNumero($demandeLivraisonService->generateNumeroForNewDL($manager))
                ->setUtilisateur($this->getUser())
                ->setType($type)
                ->setFilled(false)
                ->setCreatedAt(new DateTime('now'))
                ->setDestination($destination)
                ->setStatut($draft);

            $freeFieldService->manageFreeFields($deliveryRequest, $data, $manager);
            $manager->persist($deliveryRequest);

            $this->addReferencesToCurrentUserCart($manager, $deliveryRequest, $referencesQuantities);

            $manager->flush();

            $link = $this->generateUrl('demande_show', ['id' => $deliveryRequest->getId()]);
            $msg = "Les references ont bien été ajoutées dans une nouvelle demande de livraison";
        }

        return [
            "success" => true,
            "msg" => $msg,
            "link" => $link
        ];
    }

    private function manageCollectRequest(array $data,
                                          EntityManagerInterface $manager, $freeFieldService) {
        $referencesQuantities = json_decode($data['cart'], true);

        $msg = '';
        $link = '';
        if ($data['addOrCreate'] === "add") {
            $collectRequest = $manager->find(Collecte::class, $data['existingCollect']);
            $this->addReferencesToCurrentUserCart($manager, $collectRequest, $referencesQuantities);

            $link = $this->generateUrl('collecte_show', ['id' => $collectRequest->getId()]);
            $msg = "Les références ont bien été ajoutées dans la demande existante";
        }
        else if ($data['addOrCreate'] === "create") {
            $draftStatus = $manager->getRepository(Statut::class)->findOneByCategorieNameAndStatutCode(
                CategorieStatut::DEM_COLLECTE,
                Collecte::STATUT_BROUILLON
            );
            $type = $manager->find(Type::class, $data['collectType']);
            $collectLocation = $manager->find(Emplacement::class, $data['location']);
            $number = 'C-' . (new DateTime('now'))->format('YmdHis');
            $collectRequest = new Collecte();

            $collectRequest
                ->setNumero($number)
                ->setDate(new DateTime('now'))
                ->setType($type)
                ->setStatut($draftStatus)
                ->setObjet($data['object'])
                ->setStockOrDestruct(!($data['destination'] == 0))
                ->setPointCollecte($collectLocation)
                ->setCommentaire($data['comment'])
                ->setDemandeur($this->getUser());

            $freeFieldService->manageFreeFields($collectRequest, $data, $manager);
            $manager->persist($collectRequest);

            $this->addReferencesToCurrentUserCart($manager, $collectRequest, $referencesQuantities);

            $manager->flush();

            $link = $this->generateUrl('collecte_show', ['id' => $collectRequest->getId()]);
            $msg = "Les references ont bien été ajoutées dans une nouvelle demande de collecte";
        }

        return [
            "success" => true,
            "msg" => $msg,
            "link" => $link
        ];
    }

    private function addReferencesToCurrentUserCart(EntityManagerInterface $entityManager,
                                                                           $request,
                                                    array                  $cart)
    {
        $referenceRepository = $entityManager->getRepository(ReferenceArticle::class);
        $references = $referenceRepository->findByIds(
            Stream::from($cart)
                ->map(fn($referenceData) => $referenceData['reference'])
                ->toArray()
        );
        foreach ($cart as $referenceData) {
            $referenceId = $referenceData['reference'];
            $quantity = $referenceData['quantity'];
            $reference = $references[$referenceId];
            if($request instanceof Demande) {
                /** @var DeliveryRequestReferenceLine|null $alreadyInRequest */
                $alreadyInRequest = Stream::from($request->getReferenceLines())
                    ->filter(fn(DeliveryRequestReferenceLine $line) => $line->getReference() === $reference)
                    ->first();

                if(!empty($alreadyInRequest)) {
                    $alreadyInRequest
                        ->setQuantityToPick($cart[$reference->getId()]['quantity']);
                } else {
                    $deliveryRequestLine = (new DeliveryRequestReferenceLine())
                        ->setReference($reference)
                        ->setQuantityToPick($quantity);

                    $request->addReferenceLine($deliveryRequestLine);
                }
            }
            else if($request instanceof Collecte) {
                /** @var CollecteReference|null $alreadyInRequest */
                $alreadyInRequest = Stream::from($request->getCollecteReferences())
                    ->filter(fn(CollecteReference $line) => $line->getReferenceArticle() === $reference)
                    ->first();

                if (!empty($alreadyInRequest)) {
                    $alreadyInRequest->setQuantite($cart[$reference->getId()]['quantity']);
                } else {
                    $collectRequestLine = new CollecteReference();
                    $collectRequestLine
                        ->setReferenceArticle($reference)
                        ->setQuantite($quantity);
                    $request->addCollecteReference($collectRequestLine);
                }
            }
        }

        /**
         * @var Utilisateur $currentUser
         */
        $currentUser = $this->getUser();
        foreach ($currentUser->getCart()->getReferences() as $reference) {
            $currentUser->getCart()->removeReference($reference);
        }
    }
}
