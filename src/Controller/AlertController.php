<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;

use App\Repository\ReferenceArticleRepository;

use App\Service\RefArticleDataService;
use App\Service\UserService;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @Route("/Alerte")
 */
class AlertController extends Controller
{

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var RefArticleDataService
     */
    private $refArticleDataService;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var object|string
     */
    private $user;

    public function __construct(TokenStorageInterface $tokenStorage,ReferenceArticleRepository $referenceArticleRepository, RefArticleDataService $refArticleDataService, UserService $userService)
    {
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->refArticleDataService = $refArticleDataService;
        $this->userService = $userService;
        $this->user = $tokenStorage->getToken()->getUser();
    }

    /**
     * @Route("/alerte", name="alerte_reference_index", methods="GET|POST", options={"expose"=true})
     */
    public function indexAlerte(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('alerte_reference/index.html.twig');
    }

    /**
     * @Route("/api", name="alerte_ref_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }
            $data = $this->refArticleDataService->getDataForDatatableAlerte($request->request);
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }
}
