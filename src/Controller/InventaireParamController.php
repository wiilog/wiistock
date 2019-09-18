<?php


namespace App\Controller;

use App\Repository\InventoryFrequencyRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\InventoryCategoryRepository;
use App\Repository\ReferenceArticleRepository;

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
use App\Entity\ReferenceArticle;

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

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    public function __construct(UserService $userService, UtilisateurRepository $utilisateurRepository, InventoryCategoryRepository $inventoryCategoryRepository, InventoryFrequencyRepository $inventoryFrequencyRepository, ReferenceArticleRepository $referenceArticleRepository)
    {
        $this->userService = $userService;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->inventoryCategoryRepository = $inventoryCategoryRepository;
        $this->inventoryFrequencyRepository = $inventoryFrequencyRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
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
                $url['edit'] = $this->generateUrl('category_api_edit', ['id' => $category->getId()]);
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
                        'Actions' => $this->renderView('inventaire_param/datatableCategoryRow.html.twig', [
                            'url' => $url,
                            'categoryId' => $category->getId(),
                        ]),
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

    /**
     * @Route("/api-modifier", name="category_api_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function apiEdit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $category = $this->inventoryCategoryRepository->find($data['id']);
            $frequencies = $this->inventoryFrequencyRepository->findAll();
            $categoryFrequency = $category->getFrequency();
            if ($permanentCheck = $category->getPermanent() == true) {
                $permanent = true;
            } else {
                $permanent = false;
            }
            $json = $this->renderView('inventaire_param/modalEditCategoryContent.html.twig', [
                'category' => $category,
                'frequencies' => $frequencies,
                'categoryFrequency' => $categoryFrequency,
                'permanent' => $permanent,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="category_edit",  options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getEntityManager();
            $category = $this->inventoryCategoryRepository->find($data['category']);
            $categoryLabel = $category->getLabel();

            // on vérifie que le label n'est pas déjà utilisé
            $labelExist = $this->inventoryCategoryRepository->countByLabelDiff($data['label'], $categoryLabel);

            if (!$labelExist) {
                $frequency = $this->inventoryFrequencyRepository->find($data['frequency']);
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
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/verification", name="category_check_delete", options={"expose"=true})
     */
    public function checkUserCanBeDeleted(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $categoryId = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $userIsUsed = $this->referenceArticleRepository->countByCategory($categoryId);

            if (!$userIsUsed) {
                $delete = true;
                $html = $this->renderView('inventaire_param/modalDeleteCategoryRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('inventaire_param/modalDeleteCategoryWrong.html.twig');
            }

            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="category_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $category = $this->inventoryCategoryRepository->find($data['category']);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($category);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }
}
