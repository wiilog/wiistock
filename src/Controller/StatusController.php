<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Menu;
use App\Entity\Statut;

use App\Service\UserService;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
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

    public function __construct(UserService $userService) {
        $this->userService = $userService;
    }

    /**
     * @Route("/", name="status_param_index")
     * @param EntityManagerInterface $entityManager
     * @return RedirectResponse|Response
     */
    public function index(EntityManagerInterface $entityManager)
    {
        if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_STATU_LITI)) {
            return $this->redirectToRoute('access_denied');
        }

        $categoryStatusRepository = $entityManager->getRepository(CategorieStatut::class);
		$categoriesStatusLitigeArr = $categoryStatusRepository->findByLabelLike('litige');

        return $this->render('status/index.html.twig', [
            'categories' => $categoriesStatusLitigeArr,
        ]);
    }

    /**
     * @Route("/api", name="status_param_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function api(Request $request,
                        EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_STATU_LITI)) {
                return $this->redirectToRoute('access_denied');
            }

            $statusRepository = $entityManager->getRepository(Statut::class);

            $listStatusLitigeArr = $statusRepository->findByCategorieName(CategorieStatut::LITIGE_ARR);
            $listStatusLitigeRecep = $statusRepository->findByCategorieName(CategorieStatut::LITIGE_RECEPT);

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
                        'NotifToDeclarant' => $status->getSendNotifToDeclarant() ? 'oui' : 'non',
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
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $categoryStatusRepository = $entityManager->getRepository(CategorieStatut::class);

            // on vérifie que le label n'est pas déjà utilisé
            $labelExist = $statutRepository->countByLabelAndCategory($data['label'], $data['category']);

            if (!$labelExist) {
                $category = $categoryStatusRepository->find($data['category']);
                $status = new Statut();
				$status
                    ->setNom($data['label'])
                    ->setComment($data['description'])
					->setTreated($data['treated'])
                    ->setSendNotifToBuyer($data['sendMails'])
                    ->setSendNotifToDeclarant($data['sendMailsDeclarant'])
					->setDisplayOrder((int)$data['displayOrder'])
                    ->setCategorie($category);

                $entityManager->persist($status);
                $entityManager->flush();

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
    public function apiEdit(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $categoryStatusRepository = $entityManager->getRepository(CategorieStatut::class);

            $status = $statutRepository->find($data['id']);
            $categories = $categoryStatusRepository->findByLabelLike('litige');

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
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
    public function edit(EntityManagerInterface $entityManager,
                         Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $categoryStatusRepository = $entityManager->getRepository(CategorieStatut::class);

			$status = $statutRepository->find($data['status']);
            $statusLabel = $status->getNom();

            // on vérifie que le label n'est pas déjà utilisé
            $labelExist = $statutRepository->countByLabelDiff($data['label'], $statusLabel, $data['category']);

            if (!$labelExist) {
                $category = $categoryStatusRepository->find($data['category']);
                $status
                    ->setNom($data['label'])
                    ->setCategorie($category)
					->setTreated($data['treated'])
                    ->setSendNotifToBuyer($data['sendMails'])
                    ->setSendNotifToDeclarant($data['sendMailsDeclarant'])
					->setDisplayOrder((int)$data['displayOrder'])
                    ->setComment($data['comment']);

                $entityManager->persist($status);
                $entityManager->flush();

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
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function checkStatusCanBeDeleted(Request $request,
                                            EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $typeId = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);

            $statusIsUsed = $statutRepository->countUsedById($typeId);

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
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
    public function delete(EntityManagerInterface $entityManager,
                           Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);

            $status = $statutRepository->find($data['status']);

            $entityManager->remove($status);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }
}
