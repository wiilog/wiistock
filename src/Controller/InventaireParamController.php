<?php


namespace App\Controller;

use App\Repository\UtilisateurRepository;

use App\Service\UserService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\Menu;
use App\Entity\Utilisateur;

/**
 * @Route("/parametres_inventaire")
 */
class InventaireParamController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;
    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    public function __construct(UserService $userService, UtilisateurRepository $utilisateurRepository)
    {
        $this->userService = $userService;
        $this->utilisateurRepository = $utilisateurRepository;
    }

    /**
     * @Route("/", name="inventaire_param")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::PARAM)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('inventaire_param/index.html.twig', [

        ]);
    }

    /**
     * @Route("/api", name="invParam_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $users = $this->utilisateurRepository->findAll();
            $rows = [];
            foreach ($users as $user) {
//                $url['edit'] = $this->generateUrl('role_api_edit', ['id' => $role->getId()]);

                $rows[] =
                    [
                        'Label' => $user->getLabel() ? $role->getLabel() : "Non défini",
                        'Actif' => $role->getActive() ? 'oui' : 'non',
                        'Actions' => $this->renderView('role/datatableRoleRow.html.twig', [
                            'url' => $url,
                            'roleId' => $role->getId(),
                        ]),
                    ];
            }
        }
    }
}
