<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Menu;
use App\Entity\Statut;

use App\Entity\Type;
use App\Service\StatusService;
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
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/statuts")
 */
class StatusController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;
    private $statusService;
    private $translator;

    public function __construct(UserService $userService,
                                TranslatorInterface $translator,
                                StatusService $statusService) {
        $this->userService = $userService;
        $this->translator = $translator;
        $this->statusService = $statusService;
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
        $typeRepository = $entityManager->getRepository(Type::class);
        $categories = $categoryStatusRepository->findByLabelLike(
            [
                CategorieStatut::DISPATCH,
                CategorieStatut::LITIGE_ARR,
                CategorieStatut::LITIGE_RECEPT
            ]
        );
		$types = $typeRepository->findByCategoryLabel(CategoryType::DEMANDE_DISPATCH);

        $categoryStatusDispatchIds = array_filter($categories, function ($category) {
            return $category['nom'] === CategorieStatut::DISPATCH;
        });

        return $this->render('status/index.html.twig', [
            'categories' => $categories,
            'categoryStatusDispatchId' => array_values($categoryStatusDispatchIds)[0]['id'] ?? 0,
            'types' => $types
        ]);
    }

    /**
     * @Route("/api", name="status_param_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @return Response
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_STATU_LITI)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $this->statusService->getDataForDatatable($request->request);
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="status_new", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param StatusService $statusService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function new(Request $request,
                        StatusService $statusService,
                        EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $categoryStatusRepository = $entityManager->getRepository(CategorieStatut::class);
            $typeRepository = $entityManager->getRepository(Type::class);

            // on vérifie que le label n'est pas déjà utilisé
            $labelExist = $statutRepository->countByLabelAndCategory($data['label'], $data['category']);

            if (!$labelExist) {
                $category = $categoryStatusRepository->find($data['category']);
                $type = $typeRepository->find($data['type']);
                $defaultForCategory = $data['defaultForCategory'] ?? false;
                $statusCanBeCreated = (
                    !$defaultForCategory
                    || $statusService->canStatusBeDefault($entityManager, $category->getNom(), $type)
                );
                if (!$statusCanBeCreated) {
                    $success = false;
                    $message = 'Vous ne pouvez pas créer un statut par défaut pour cette entité et ce type, il en existe déjà un.';
                }
                else {
                    $type = $typeRepository->find($data['type']);
                    $status = new Statut();
                    $status
                        ->setNom($data['label'])
                        ->setComment($data['description'])
                        ->setTreated($data['treated'] ?? false)
                        ->setDefaultForCategory($defaultForCategory)
                        ->setSendNotifToBuyer((bool)$data['sendMails'])
                        ->setSendNotifToDeclarant((bool)$data['sendMailsDeclarant'])
                        ->setSendNotifToRecipient((bool)$data['sendMailsRecipient'])
                        ->setNeedsMobileSync((bool)$data['needsMobileSync'])
                        ->setDisplayOrder((int)$data['displayOrder'])
                        ->setCategorie($category)
                        ->setType($type ?? null);

                    $entityManager->persist($status);
                    $entityManager->flush();

                    $success = true;
                    $message = 'Le statut "' . $data['label'] . '" a bien été créé.';
                }
            } else {
                $success = false;
                $message = 'Le statut "' . $data['label'] . '" existe déjà pour cette catégorie. Veuillez en choisir un autre.';
            }
            return new JsonResponse([
                'success' => $success,
                'msg' => $message
            ]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="status_api_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
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
            $typeRepository = $entityManager->getRepository(Type::class);
            $categories = $categoryStatusRepository->findByLabelLike([
                CategorieStatut::DISPATCH,
                CategorieStatut::LITIGE_ARR,
                CategorieStatut::LITIGE_RECEPT
            ]);
            $types = $typeRepository->findByCategoryLabel(CategoryType::DEMANDE_DISPATCH);

            $transCategories = array_map(
                function (array $category) {
                    return [
                        'id' => $category['id'],
                        'nom' => $category['nom'] === 'acheminement'
                            ? $this->translator->trans('acheminement.acheminements')
                            : $category['nom']
                    ];
                },
                $categories
            );

            $json = $this->renderView('status/modalEditStatusContent.html.twig', [
                'status' => $status,
                'categories' => $transCategories,
                'types' => $types
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="status_edit",  options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param StatusService $statusService
     * @param Request $request
     * @return Response
     */
    public function edit(EntityManagerInterface $entityManager,
                         StatusService $statusService,
                         Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $typeRepository = $entityManager->getRepository(Type::class);

			$status = $statutRepository->find($data['status']);
            $statusLabel = $status->getNom();

            // on vérifie que le label n'est pas déjà utilisé
            $categoryName = $status->getCategorie() ? $status->getCategorie()->getNom() : '';
            $labelExist = $statutRepository->countByLabelDiff($data['label'], $statusLabel, $categoryName);

            if (!$labelExist) {
                $defaultForCategory = $data['defaultForCategory'] ?? false;
                $statusCanBeCreated = (
                    !$defaultForCategory
                    || $statusService->canStatusBeDefault($entityManager, $categoryName, $status->getType(), $status)
                );
                if (!$statusCanBeCreated) {
                    $success = false;
                    $message = 'Vous ne pouvez pas ajouter de statut par défaut pour cette entité et ce type, il en existe déjà un.';
                }
                else {
                    $type = $typeRepository->find($data['type']);
                    $status
                        ->setNom($data['label'])
                        ->setTreated($data['treated'] ?? false)
                        ->setDefaultForCategory($defaultForCategory)
                        ->setSendNotifToBuyer((bool)$data['sendMails'])
                        ->setSendNotifToDeclarant((bool)$data['sendMailsDeclarant'])
                        ->setSendNotifToRecipient((bool)$data['sendMailsRecipient'])
                        ->setNeedsMobileSync((bool)$data['needsMobileSync'])
                        ->setDisplayOrder((int)$data['displayOrder'])
                        ->setComment($data['comment'])
                        ->setType($type ?? null);

                    $entityManager->persist($status);
                    $entityManager->flush();

                    $success = true;
                    $message = 'Le statut "' . $statusLabel . '" a bien été modifié.';
                }
            } else {
                $success = false;
                $message = 'Le statut "' . $data['label'] . '" existe déjà pour cette catégorie. Veuillez en choisir un autre.';
            }
            return new JsonResponse([
                'success' => $success,
                'msg' => $message
            ]);
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

            if (!$status->getDispatches()->isEmpty()) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Ce statut est utilisé, vous ne pouvez pas le supprimer.'
                ]);
            }

            $entityManager->remove($status);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }
}
