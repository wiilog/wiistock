<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Collecte;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use App\Entity\Emplacement;
use App\Entity\FreeField;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Menu;
use App\Entity\Cart;
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

        $defaultDeliveryLocations = $globalParamService->getDefaultDeliveryLocationsByTypeId();
        $deliveryRequests = Stream::from($manager->getRepository(Demande::class)->getDeliveryRequestForSelect($currentUser))
            ->filter(fn(Demande $request) => $request->getType() && $request->getDestination())
            ->keymap(fn(Demande $request) => [
                $request->getId(),
                "{$request->getNumero()} - {$request->getType()->getLabel()} - {$request->getDestination()->getLabel()} - Créée le {$request->getDate()->format('d/m/Y H:i')}"
            ]);

        $collectRequests = Stream::from($manager->getRepository(Collecte::class)->getCollectRequestForSelect($currentUser))
            ->filter(fn(Collecte $collecte) => $collecte->getType() && $collecte->getPointCollecte())
            ->keymap(fn(Collecte $request) => [
                $request->getId(),
                "{$request->getNumero()} - {$request->getType()->getLabel()} - {$request->getPointCollecte()->getLabel()} - Créée le {$request->getDate()->format('d/m/Y H:i')}"
            ]);

        return $this->render("cart/index.html.twig", [
            "deliveryRequests" => $deliveryRequests,
            "collectRequests" => $collectRequests,
            "defaultDeliveryLocations" => $defaultDeliveryLocations,
            "deliveryFreeFieldsTypes" => $deliveryFreeFields,
            "collectFreeFieldsTypes" => $collectFreeFields,
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
     * @Route("/obtenir-html/{type}", name="cart_get_appropriate_html", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function renderAppropriateHtml(?int $type, CartService $cartService, EntityManagerInterface $entityManager)
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $cart = $user->getCart();

        switch ($type) {
            case 0:
                return $this->json($cartService->renderDeliveryTypeModal($cart, $entityManager));
            case 1:
                return $this->json($cartService->renderCollectTypeModal($cart, $entityManager));
            case 2:
                return $this->json($cartService->renderTransferTypeModal($cart, $entityManager));
            case 3:
                return $this->json($cartService->renderPurchaseTypeModal($cart, $entityManager, $user));
            default:
                return null;
        }
    }

    /**
     * @Route("/ajouter-demande", name="cart_add_to_request", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     */
    public function addToRequest(Request $request,
                                 CartService $cartService,
                                 DemandeLivraisonService $demandeLivraisonService,
                                 PurchaseRequestService $purchaseRequestService,
                                 RefArticleDataService $refArticleDataService,
                                 UniqueNumberService $uniqueNumberService,
                                 EntityManagerInterface $entityManager)
    {
        if ($data = json_decode($request->getContent(), true)) {
            $cartRepository = $entityManager->getRepository(Cart::class);

            $cart = $cartRepository->findOneBy([
                'user' => $this->getUser()
            ]);

            $type = intval($data['requestType']);
            switch ($type) {
                case 0:
                    $delivery = $cartService->manageDeliveryRequest(
                        $data,
                        $demandeLivraisonService,
                        $this->getUser(),
                        $refArticleDataService,
                        $entityManager,
                        $cart
                    );
                    return $this->json(['redirect' => $this->generateUrl('demande_show', ['id' => $delivery->getId()])]);
                case 1:
                    $collect = $cartService->manageCollectRequest(
                        $data,
                        $this->getUser(),
                        $entityManager,
                        $cart
                    );
                    return $this->json(['redirect' => $this->generateUrl('collecte_show', ['id' => $collect->getId()])]);
                case 2:
                    $transfer = $cartService->manageTransferRequest(
                        $data,
                        $uniqueNumberService,
                        $this->getUser(),
                        $entityManager,
                        $cart
                    );
                    return $this->json(['redirect' => $this->generateUrl('transfer_request_show', ['id' => $transfer->getId()])]);
                case 3:
                    $purchases = $cartService->managePurchaseRequest(
                        $data,
                        $this->getUser(),
                        $purchaseRequestService,
                        $entityManager,
                        $cart
                    );

                    if(count($purchases) !== 1) {
                        $path = $this->generateUrl("purchase_request_index");
                    } else {
                        $path = $this->generateUrl("purchase_request_show", [
                            "id" => $purchases[array_key_first($purchases)]->getId()
                        ]);
                    }

                    return $this->json(['redirect' => $path]);
                default:
                    return $this->json(null);
            }
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/validate-cart", name="cart_validate", options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     */
    public function validateCart(Request $request,
                                 EntityManagerInterface $entityManager,
                                 DemandeLivraisonService $demandeLivraisonService,
                                 FreeFieldService $freeFieldService)
    {
        $deliveryRepository = $entityManager->getRepository(Demande::class);
        $data = json_decode($request->getContent(), true);
        $referencesQuantities = json_decode($data['quantities'], true);

        if ($data['addOrCreate'] === "add") {
            /** @var Demande $deliveryRequest */
            $deliveryRequest = $deliveryRepository->find($data['existingDelivery']);
            $this->addReferencesToCurrentUserCart($entityManager, $deliveryRequest, $referencesQuantities);
            $msg = " les Références ont bien étées ajoutées dans votre panier";
        }
        else if ($data['addOrCreate'] === "create") {
            $typeRepository = $entityManager->getRepository(Type::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $locationRepository = $entityManager->getRepository(Emplacement::class);
            $destination = $locationRepository->find($data['destination']);
            $draft = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSFER_REQUEST, TransferRequest::DRAFT);
            $type = $typeRepository->find($data['deliveryType']);
            $deliveryRequest = new Demande();

            $deliveryRequest
                ->setNumero($demandeLivraisonService->generateNumeroForNewDL($entityManager))
                ->setUtilisateur($this->getUser())
                ->setType($type)
                ->setFilled(false)
                ->setDate(new DateTime('now'))
                ->setDestination($destination)
                ->setStatut($draft);

            $freeFieldService->manageFreeFields($deliveryRequest, $data, $entityManager);
            $entityManager->persist($deliveryRequest);

            $this->addReferencesToCurrentUserCart($entityManager, $deliveryRequest, $referencesQuantities);
            $msg = "Les references ont bien étées ajoutées dans un nouvelle demande de livraison";
        }
        $entityManager->flush();
        if (isset($deliveryRequest)) {
            $link = $this->generateUrl('demande_show',['id' => $deliveryRequest->getId()]);
        }
        $this->addFlash('success', $msg);
        return $this->json([
            "success" => true,
            "msg" => $msg,
            'link' => $link
        ]);
    }

    private function addReferencesToCurrentUserCart(EntityManagerInterface $entityManager,
                                                    Demande $demande,
                                                    array $referencesQuantities)
    {
        $referenceRepository = $entityManager->getRepository(ReferenceArticle::class);
        $references = $referenceRepository->findById(array_keys($referencesQuantities));
        foreach ($referencesQuantities as $reference => $referencesQuantity) {
            $reference = $references[$reference];
            $deliveryRequestLine = new DeliveryRequestReferenceLine();
            $deliveryRequestLine->setReference($reference);
            $deliveryRequestLine->setQuantityToPick($referencesQuantity['quantity']);
            $demande->addReferenceLine($deliveryRequestLine);
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
