<?php


namespace App\Controller;

use App\Repository\InventoryFrequencyRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\InventoryCategoryRepository;

use App\Service\UserService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\Menu;
use App\Entity\Utilisateur;
use App\Entity\InventoryCategory;
use App\Entity\InventoryFrequency;

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

    /**
     * @var InventoryCategoryRepository
     */
    private $inventoryCategoryRepository;

    /**
     * @var InventoryFrequencyRepository
     */
    private $inventoryFrequencyRepository;

    public function __construct(UserService $userService, UtilisateurRepository $utilisateurRepository, InventoryCategoryRepository $inventoryCategoryRepository, InventoryFrequencyRepository $inventoryFrequencyRepository)
    {
        $this->userService = $userService;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->inventoryCategoryRepository = $inventoryCategoryRepository;
        $this->inventoryFrequencyRepository = $inventoryFrequencyRepository;
    }

    /**
     * @Route("/", name="inventaire_param")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::PARAM)) {
            return $this->redirectToRoute('access_denied');
        }

        $frequences = $this->inventoryFrequencyRepository->findAll();

        return $this->render('inventaire_param/index.html.twig', [
            'frequencies' => $frequences
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

            $categories = $this->inventoryCategoryRepository->findAll();
            $rows = [];
            foreach ($categories as $category) {
 //               $url['edit'] = $this->generateUrl('role_api_edit', ['id' => $category->getId()]);
                if ($category->getPermanent() == true) {
                    $permanent = 'oui';
                } else {
                    $permanent = 'non';
                }
                $rows[] =
                    [
                        'Label' => $category->getLabel(),
                        'Frequence' => $category->getFrequency()->getLabel(),
                        'Permanent' => $permanent,
                        'Actions' => $category->getId(),
//                        'Actions' => $this->renderView('role/datatableRoleRow.html.twig', [
//                            'url' => $url,
//                            'roleId' => $role->getId(),
//                        ]),
                    ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="categorie_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getEntityManager();

            // on vérifie que le label n'est pas déjà utilisé
            $labelExist = $this->inventoryCategoryRepository->countByLabel($data['label']);

            dump($labelExist);

            if (!$labelExist) {
                $frequency = $this->inventoryFrequencyRepository->find($data['frequency']);
                $category = new InventoryCategory();
                $category
                    ->setLabel($data['label'])
                    ->setFrequency($frequency)
                    ->setPermanent($data['permanent']);

                $em->persist($category);
                $em->flush();

                return new JsonResponse();
            } else {
                return new JsonResponse(false);
            }
        }
        throw new NotFoundHttpException("404");
    }
}
