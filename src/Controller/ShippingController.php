<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Menu;
use Symfony\Contracts\Service\Attribute\Required;
use App\Service\AttachmentService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/expeditions")
 */
class ShippingController extends AbstractController {

    #[Required]
    public UserService $userService;

    #[Required]
    public AttachmentService $attachmentService;

    #[Route("/", name: "shipping_request_index")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function index(EntityManagerInterface $entityManager) {
        return $this->render('shipping/index.html.twig', []);
    }

    #[Route("/api-columns", name: "shipping_api_columns", options: ["expose" => true], methods: "GET|POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING], mode: HasPermission::IN_JSON)]
    public function apiColumns(Request $request, EntityManagerInterface $entityManager): Response {
            return new Response();
    }
}
