<?php

namespace App\Controller;

use App\Entity\ReferenceArticle;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/panier")
 */
class CartController extends AbstractController {

    /**
     * @Route("/", name="cart")
     */
    public function cart(Request $request): Response {
        return $this->render("cart/index.html.twig", [

        ]);
    }

    /**
     * @Route("/api", name="cart_api", options={"expose"=true})
     */
    public function api(Request $request, CartService $service): Response {
        return $this->json($service->getDataForDatatable($request->request->all()));
    }

    /**
     * @Route("/remove-reference/{reference}", name="cart_remove_reference", options={"expose"=true})
     */
    public function removeReference(EntityManagerInterface $manager, ReferenceArticle $reference): Response {
        $this->getUser()->getCart()->removeRefArticle($reference);
        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "La référence a bien été retirée du panier",
        ]);
    }

}
