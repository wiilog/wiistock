<?php


namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Menu;
use App\Entity\Statut;

use App\Entity\Type;
use App\Service\StatusService;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/statuts")
 */
class StatusController extends AbstractController {

    /**
     * @Route("/", name="status_param_index")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_STATU_LITI})
     */
    public function index(EntityManagerInterface $entityManager,
                          StatusService $statusService) {

        $categoryStatusRepository = $entityManager->getRepository(CategorieStatut::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $categories = $categoryStatusRepository->findByLabelLike([
            CategorieStatut::DISPATCH,
            CategorieStatut::HANDLING,
            CategorieStatut::DISPUTE_ARR,
            CategorieStatut::LITIGE_RECEPT,
            CategorieStatut::ARRIVAGE,
            CategorieStatut::PURCHASE_REQUEST
        ]);
        $types = $typeRepository->findByCategoryLabels([
            CategoryType::DEMANDE_DISPATCH,
            CategoryType::DEMANDE_HANDLING,
            CategoryType::ARRIVAGE
        ]);

        $categoryStatusDispatchIds = array_filter($categories, function ($category) {
            return $category['nom'] === CategorieStatut::DISPATCH;
        });

        $categoryStatusHandlingIds = array_filter($categories, function ($category) {
            return $category['nom'] === CategorieStatut::HANDLING;
        });

        $categoryStatusArrivalIds = array_filter($categories, function ($category) {
            return $category['nom'] === CategorieStatut::ARRIVAGE;
        });

        $categoryStatusPurchaseRequestIds = array_filter($categories, function ($category) {
            return $category['nom'] === CategorieStatut::PURCHASE_REQUEST;
        });

        return $this->render('status/index.html.twig', [
            'categoryStatusDispatchId' => array_values($categoryStatusDispatchIds)[0]['id'] ?? 0,
            'categoryStatusHandlingId' => array_values($categoryStatusHandlingIds)[0]['id'] ?? 0,
            'categoryStatusArrivalId' => array_values($categoryStatusArrivalIds)[0]['id'] ?? 0,
            'categoryStatusPurchaseRequestId' => array_values($categoryStatusPurchaseRequestIds)[0]['id'] ?? 0,
            'categories' => $categories,
            'types' => $types,
            'statusStates' => $statusService->getStatusStatesValues()
        ]);
    }

    /**
     * @Route("/api", name="status_param_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_STATU_LITI}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request,
                        StatusService $statusService): Response {
        $data = $statusService->getDataForDatatable($request->request);
        return new JsonResponse($data);
    }

    /**
     * @Route("/creer", name="status_new", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager,
                        StatusService $statusService): Response {
        if ($data = json_decode($request->getContent(), true)) {

            $statusRepository = $entityManager->getRepository(Statut::class);
            $categoryStatusRepository = $entityManager->getRepository(CategorieStatut::class);
            $typeRepository = $entityManager->getRepository(Type::class);

            $category = $categoryStatusRepository->find($data['category']);
            $type = $typeRepository->find($data['type']);

            $defaults = $statusRepository->countDefaults($category, $type);
            $drafts = $statusRepository->countDrafts($category, $type);
            $disputes = $statusRepository->countDisputes($category, $type);

            if ($statusRepository->countSimilarLabels($category, $data['label'], $data['type'])) {
                $success = false;
                $message = 'Le statut "' . $data['label'] . '" existe déjà pour cette catégorie. Veuillez en choisir un autre.';
            } else if ($data['defaultForCategory'] && $defaults > 0) {
                $success = false;
                $message = 'Vous ne pouvez pas créer un statut par défaut pour cette entité et ce type, il en existe déjà un.';
            } else if (((int) $data['state']) === Statut::DRAFT && $drafts > 0) {
                $success = false;
                $message = 'Vous ne pouvez pas créer un statut brouillon pour cette entité et ce type, il en existe déjà un.';
            } else if (((int) $data['state']) === Statut::DISPUTE && $disputes > 0) {
                $success = false;
                $message = 'Vous ne pouvez pas créer un statut litige pour cette entité et ce type, il en existe déjà un.';
            } else {
                $status = $statusService->updateStatus(new Statut(), $type, $data);
                $status->setCategorie($category);

                $entityManager->persist($status);
                $entityManager->flush();

                $success = true;
                $message = 'Le statut <strong>' . $data['label'] . '</strong> a bien été créé.';
            }

            return new JsonResponse([
                'success' => $success,
                'msg' => $message
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="status_api_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function apiEdit(Request $request,
                            StatusService $statusService,
                            EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {

            $statutRepository = $entityManager->getRepository(Statut::class);
            $typeRepository = $entityManager->getRepository(Type::class);

            $status = $statutRepository->find($data['id']);

            $statusCategory = $status->getCategorie();
            $categoryTypeToGet = (
                ($statusCategory->getNom() === CategorieStatut::HANDLING) ? CategoryType::DEMANDE_HANDLING :
                (($statusCategory->getNom() === CategorieStatut::DISPATCH) ? CategoryType::DEMANDE_DISPATCH :
                (($statusCategory->getNom() === CategorieStatut::ARRIVAGE) ? CategoryType::ARRIVAGE :
                    null))
            );

            $types = isset($categoryTypeToGet)
                ? $typeRepository->findByCategoryLabels([$categoryTypeToGet])
                : [];

            $json = $this->renderView('status/modalEditStatusContent.html.twig', [
                'states' => $statusService->getStatusStatesValues(),
                'status' => $status,
                'types' => $types
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="status_edit",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(EntityManagerInterface $entityManager,
                         Request $request,
                         StatusService $statusService): Response {
        if ($data = json_decode($request->getContent(), true)) {

            $statusRepository = $entityManager->getRepository(Statut::class);
            $typeRepository = $entityManager->getRepository(Type::class);

            /** @var Statut $status */
            $status = $statusRepository->find($data['status']);
            $statusLabel = $status->getNom();

            // on vérifie que le label n'est pas déjà utilisé
            $category = $status->getCategorie();

            $type = $typeRepository->find($data['type']);

            $defaults = $statusRepository->countDefaults($category, $type, $status);
            $drafts = $statusRepository->countDrafts($category, $type, $status);

            if ($statusRepository->countSimilarLabels($category, $data['label'], $data['type'], $status)) {
                $success = false;
                $message = 'Le statut "' . $data['label'] . '" existe déjà pour cette catégorie. Veuillez en choisir un autre.';
            } else if ($data['defaultForCategory'] && $defaults > 0) {
                $success = false;
                $message = 'Vous ne pouvez pas créer un statut par défaut pour cette entité et ce type, il en existe déjà un.';
            } else if (((int) $data['state']) === Statut::DRAFT && $drafts > 0) {
                $success = false;
                $message = 'Vous ne pouvez pas ajouter un statut brouillon pour cette entité et ce type, il en existe déjà un.';
            } else {
                $status = $statusService->updateStatus($status, $type, $data);

                $entityManager->persist($status);
                $entityManager->flush();

                $success = true;
                $message = 'Le statut <strong>' . $statusLabel . '</strong> a bien été modifié.';
            }

            return new JsonResponse([
                'success' => $success,
                'msg' => $message
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/verification", name="status_check_delete", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function checkStatusCanBeDeleted(Request $request, EntityManagerInterface $entityManager): Response {
        if ($statusId = json_decode($request->getContent(), true)) {

            $statutRepository = $entityManager->getRepository(Statut::class);

            $statusIsUsed = $statutRepository->countUsedById($statusId);
            $statut = $statutRepository->find($statusId);

            if ($statut->isDefaultForCategory()) {
                $defaults = $statutRepository->countDefaults($statut->getCategorie(), $statut->getType(), $statut);
                if (empty($defaults)) {
                    return $this->json([
                        'delete' => false,
                        'html' => $this->renderView('status/modalDeleteStatusWrong.html.twig')
                    ]);
                }
            }
            if (!$statusIsUsed) {
                $delete = true;
                $html = $this->renderView('status/modalDeleteStatusRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('status/modalDeleteStatusWrong.html.twig');
            }

            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="status_delete", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(EntityManagerInterface $entityManager, Request $request): Response {
        if ($data = json_decode($request->getContent(), true)) {

            $statutRepository = $entityManager->getRepository(Statut::class);

            $status = $statutRepository->find($data['status']);
            $statusLabel = $status->getNom();
            $statusIsUsed = $statutRepository->countUsedById($status->getId());
            if ($statusIsUsed) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Ce statut est utilisé, vous ne pouvez pas le supprimer.'
                ]);
            }

            $entityManager->remove($status);
            $entityManager->flush();
            return new JsonResponse([
                'success' => true,
                'msg' => 'Le statut <strong>' . $statusLabel . '</strong> a bien été supprimé.'
            ]);
        }
        throw new BadRequestHttpException();
    }

}
