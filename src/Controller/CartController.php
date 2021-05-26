<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Cart;
use App\Entity\ReferenceArticle;
use App\Service\CartService;
use App\Service\DemandeLivraisonService;
use App\Service\PurchaseRequestService;
use App\Service\RefArticleDataService;
use App\Service\UniqueNumberService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/panier")
 */
class CartController extends AbstractController
{

    /**
     * @Route("/", name="cart")
     */
    public function cart(Request $request): Response
    {
        return $this->render("cart/index.html.twig", [

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
        $cartRepository = $entityManager->getRepository(Cart::class);

        $cart = $cartRepository->findOneBy([
            'user' => $this->getUser()
        ]);

        switch ($type) {
            case 0:
                return $this->json($cartService->renderDeliveryTypeModal($cart, $entityManager));
            case 1:
                return $this->json($cartService->renderCollectTypeModal($cart, $entityManager));
            case 2:
                return $this->json($cartService->renderTransferTypeModal($cart, $entityManager));
            case 3:
                return $this->json($cartService->renderPurchaseTypeModal($cart, $entityManager));
            default:
                return null;
        }
    }

    /**
     * @Route("/ajouter-demande", name="cart_add_to_request", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function addToRequest(Request $request,
                                 CartService $cartService,
                                 DemandeLivraisonService $demandeLivraisonService,
                                 PurchaseRequestService $purchaseRequestService,
                                 RefArticleDataService $refArticleDataService,
                                 UniqueNumberService $uniqueNumberService,
                                 EntityManagerInterface $entityManager)
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
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
