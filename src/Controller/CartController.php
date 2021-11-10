<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Collecte;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Menu;
use App\Entity\Cart;
use App\Entity\ReferenceArticle;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Service\CartService;
use App\Service\DemandeLivraisonService;
use App\Service\GlobalParamService;
use App\Service\PurchaseRequestService;
use App\Service\RefArticleDataService;
use App\Service\UniqueNumberService;
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
            ->keymap(fn(Demande $request) => [
                $request->getId(),
                "{$request->getNumero()} - {$request->getType()->getLabel()} - {$request->getDestination()->getLabel()} - Créée le {$request->getDate()->format('d/m/Y H:i')}"
            ]);

        $collectRequests = Stream::from($manager->getRepository(Collecte::class)->getCollectRequestForSelect($currentUser))
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
}
