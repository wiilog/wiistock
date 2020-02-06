<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Menu;
use App\Entity\Statut;

use App\Repository\CategorieStatutRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\TypeRepository;

use App\Service\UserService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/statuts")
 */
class StatusController extends AbstractController
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
     * @var StatutRepository
     */
    private $statusRepository;

	/**
	 * @var CategorieStatutRepository
	 */
    private $categoryStatusRepository;

    public function __construct(CategorieStatutRepository $categoryStatusRepository, UserService $userService, ReferenceArticleRepository $referenceArticleRepository, TypeRepository $typeRepository, StatutRepository $statusRepository)
    {
        $this->userService = $userService;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->typeRepository = $typeRepository;
        $this->statusRepository = $statusRepository;
        $this->categoryStatusRepository = $categoryStatusRepository;
    }

    /**
     * @Route("/", name="status_param_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_STATU_LITI)) {
            return $this->redirectToRoute('access_denied');
        }

		$categoriesStatusLitigeArr = $this->categoryStatusRepository->findByLabelLike('litige');

        return $this->render('status/index.html.twig', [
            'categories' => $categoriesStatusLitigeArr,
        ]);
    }

    /**
     * @Route("/api", name="status_param_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_STATU_LITI)) {
                return $this->redirectToRoute('access_denied');
            }

            $listStatusLitigeArr = $this->statusRepository->findByCategorieName(CategorieStatut::LITIGE_ARR);
            $listStatusLitigeRecep = $this->statusRepository->findByCategorieName(CategorieStatut::LITIGE_RECEPT);
            $rows = [];
            foreach (array_merge($listStatusLitigeArr, $listStatusLitigeRecep) as $status) {
                $url['edit'] = $this->generateUrl('status_api_edit', ['id' => $status->getId()]);

                $rows[] =
                    [
                        'Label' => $status->getNom(),
                        'Categorie' => $status->getCategorie() ? $status->getCategorie()->getNom() : '',
                        'Comment' => $status->getComment(),
                        'Treated' => $status->isTreated() ? 'oui' : 'non',
                        'NotifToBuyer' => $status->getSendNotifToBuyer() ? 'oui' : 'non',
                        'Order' => $status->getDisplayOrder() ?? '',
                        'Actions' => $this->renderView('status/datatableStatusRow.html.twig', [
                            'url' => $url,
                            'statusId' => $status->getId(),
                        ]),
                    ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="status_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getManager();

            // on vérifie que le label n'est pas déjà utilisé
            $labelExist = $this->statusRepository->countByLabelAndCategory($data['label'], $data['category']);

            if (!$labelExist) {
                $category = $this->categoryStatusRepository->find($data['category']);
                $status = new Statut();
				$status
                    ->setNom($data['label'])
                    ->setComment($data['description'])
					->setTreated($data['treated'])
                    ->setSendNotifToBuyer($data['sendMails'])
					->setDisplayOrder((int)$data['displayOrder'])
                    ->setCategorie($category);

                $em->persist($status);
                $em->flush();

				return new JsonResponse([
					'success' => true,
					'msg' => 'Le statut "' . $data['label'] . '" a bien été créé.'
				]);
            } else {
				return new JsonResponse([
					'success' => false,
					'msg' => 'Le statut "' . $data['label'] . '" existe déjà pour cette catégorie. Veuillez en choisir un autre.'
				]);
            }
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="status_api_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function apiEdit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $status = $this->statusRepository->find($data['id']);
            $categories = $this->categoryStatusRepository->findByLabelLike('litige');

            $json = $this->renderView('status/modalEditStatusContent.html.twig', [
                'status' => $status,
                'categories' => $categories,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="status_edit",  options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getManager();
			$status = $this->statusRepository->find($data['status']);
            $statusLabel = $status->getNom();

            // on vérifie que le label n'est pas déjà utilisé
            $labelExist = $this->statusRepository->countByLabelDiff($data['label'], $statusLabel, $data['category']);

            if (!$labelExist) {
                $category = $this->categoryStatusRepository->find($data['category']);
                $status
                    ->setNom($data['label'])
                    ->setCategorie($category)
					->setTreated($data['treated'])
                    ->setSendNotifToBuyer($data['sendMails'])
					->setDisplayOrder((int)$data['displayOrder'])
                    ->setComment($data['comment']);

                $em->persist($status);
                $em->flush();

                return new JsonResponse([
                	'success' => true,
					'msg' => 'Le statut "' . $statusLabel . '" a bien été modifié.'
				]);
            } else {
                return new JsonResponse([
                	'success' => false,
					'msg' => 'Le statut "' . $data['label'] . '" existe déjà pour cette catégorie. Veuillez en choisir un autre.'
				]);
            }
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/verification", name="status_check_delete", options={"expose"=true})
     */
    public function checkStatusCanBeDeleted(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $typeId = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $statusIsUsed = $this->statusRepository->countUsedById($typeId);

            if (!$statusIsUsed) {
                $delete = true;
                $html = $this->renderView('status/modalDeleteStatusRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('status/modalDeleteStatusWrong.html.twig');
            }

            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="status_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $status = $this->statusRepository->find($data['status']);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($status);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }
}
