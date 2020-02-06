<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;

use App\Entity\Type;
use App\Repository\ReferenceArticleRepository;
use App\Repository\TypeRepository;
use App\Repository\CategoryTypeRepository;

use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parametres-types")
 */
class ParamTypesController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var CategoryTypeRepository
     */
    private $categoryTypeRepository;

    public function __construct(UserService $userService, ReferenceArticleRepository $referenceArticleRepository, TypeRepository $typeRepository, CategoryTypeRepository $categoryTypeRepository)
    {
        $this->userService = $userService;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->typeRepository = $typeRepository;
        $this->categoryTypeRepository = $categoryTypeRepository;
    }

    /**
     * @Route("/", name="types_param_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_TYPE)) {
            return $this->redirectToRoute('access_denied');
        }

        $categories = $this->categoryTypeRepository->findAll();

        return $this->render('types/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    /**
     * @Route("/api", name="types_param_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_TYPE)) {
                return $this->redirectToRoute('access_denied');
            }

            $types = $this->typeRepository->findAll();
            $rows = [];
            foreach ($types as $type) {
                $url['edit'] = $this->generateUrl('types_api_edit', ['id' => $type->getId()]);

                $rows[] =
                    [
                        'Label' => $type->getLabel(),
                        'Categorie' => $type->getCategory() ? $type->getCategory()->getLabel() : '',
                        'Description' => $type->getDescription(),
                        'Actions' => $type->getId(),
                        'Actions' => $this->renderView('types/datatableTypeRow.html.twig', [
                            'url' => $url,
                            'typeId' => $type->getId(),
                        ]),
                    ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="types_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getManager();

            // on vérifie que le label n'est pas déjà utilisé
            $labelExist = $this->typeRepository->countByLabelAndCategory($data['label'], $data['category']);

            if (!$labelExist) {
                $category = $this->categoryTypeRepository->find($data['category']);
                $type = new Type();
                $type
                    ->setLabel($data['label'])
                    ->setDescription($data['description'])
                    ->setCategory($category);

                $em->persist($type);
                $em->flush();

				return new JsonResponse([
					'success' => true,
					'msg' => 'Le type ' . $data['label'] . ' a bien été créé.'
				]);
            } else {
				return new JsonResponse([
					'success' => false,
					'msg' => 'Le type ' . $data['label'] . ' existe déjà pour cette catégorie. Veuillez en choisir un autre.'
				]);
            }
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="types_api_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function apiEdit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $type = $this->typeRepository->find($data['id']);
            $categories = $this->categoryTypeRepository->findAll();

            $json = $this->renderView('types/modalEditTypeContent.html.twig', [
                'type' => $type,
                'categories' => $categories,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="types_edit",  options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getManager();
            $type = $this->typeRepository->find($data['type']);
            $typeLabel = $type->getLabel();

            // on vérifie que le label n'est pas déjà utilisé
            $labelExist = $this->typeRepository->countByLabelDiff($data['label'], $typeLabel, $data['category']);

            if (!$labelExist) {
                $category = $this->categoryTypeRepository->find($data['category']);
                $type
                    ->setLabel($data['label'])
                    ->setCategory($category)
                    ->setDescription($data['description']);

                $em->persist($type);
                $em->flush();

                return new JsonResponse([
                	'success' => true,
					'msg' => 'Le type ' . $typeLabel . ' a bien été modifié.'
				]);
            } else {
                return new JsonResponse([
                	'success' => false,
					'msg' => 'Le type ' . $data['label'] . ' existe déjà pour cette catégorie. Veuillez en choisir un autre.'
				]);
            }
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/verification", name="types_check_delete", options={"expose"=true})
     */
    public function checkTypeCanBeDeleted(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $typeId = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $typeIsUsed = $this->typeRepository->countUsedById($typeId);

            if (!$typeIsUsed) {
                $delete = true;
                $html = $this->renderView('types/modalDeleteTypeRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('types/modalDeleteTypeWrong.html.twig');
            }

            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="types_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $type = $this->typeRepository->find($data['type']);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($type);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }
}
