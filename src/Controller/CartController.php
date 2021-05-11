<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\ReferenceArticle;
use App\Service\CartService;
use App\Service\DemandeLivraisonService;
use App\Service\RefArticleDataService;
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
     * @Route("/remove-reference/{reference}", name="cart_remove_reference", options={"expose"=true})
     */
    public function removeReference(EntityManagerInterface $manager, ReferenceArticle $reference): Response
    {
        $this->getUser()->getCart()->removeRefArticle($reference);
        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "La référence a bien été retirée du panier",
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
            case 2:
            case 3:
        }
    }

    /**
     * @Route("/ajouter-demande", name="cart_add_to_request", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function addToRequest(Request $request,
                                 CartService $cartService,
                                 DemandeLivraisonService $demandeLivraisonService,
                                 RefArticleDataService $refArticleDataService,
                                 EntityManagerInterface $entityManager)
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $type = intval($data['requestType']);
            switch ($type) {
                case 0:
                    $delivery = $cartService->manageDeliveryRequest(
                        $data,
                        $demandeLivraisonService,
                        $this->getUser(),
                        $refArticleDataService,
                        $entityManager
                    );
                    return $this->redirectToRoute('demande_show', ['id' => $delivery->getId()]);
                case 1:
                case 2:
                case 3:
            }
        }
        throw new BadRequestHttpException();
    }
}
