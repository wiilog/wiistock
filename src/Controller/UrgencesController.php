<?php

namespace App\Controller;


use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Urgence;
use App\Repository\UrgenceRepository;
use App\Service\UrgenceService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/urgences")
 */
class UrgencesController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var UrgenceRepository
     */
    private $urgenceRepository;

    /**
     * @var UrgenceService
     */
    private $urgenceService;

    public function __construct(UserService $userService, UrgenceRepository $urgenceRepository, UrgenceService $urgenceService)
    {
        $this->userService = $userService;
        $this->urgenceRepository = $urgenceRepository;
        $this->urgenceService = $urgenceService;
    }

    /**
     * @Route("/", name="urgence_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_URGE)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('urgence/index.html.twig', [

        ]);
    }

    /**
     * @Route("/api", name="urgence_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_URGE)) {
                return $this->redirectToRoute('access_denied');
            }
            $data = $this->urgenceService->getDataForDatatable($request->request);
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="urgence_new", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param UrgenceService $urgenceService
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager,
                        UrgenceService $urgenceService): Response
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::CREATE)) {
            return $this->redirectToRoute('access_denied');
        }

        $data = json_decode($request->getContent(), true);

        $urgenceRepository = $entityManager->getRepository(Urgence::class);
        $urgence = new Urgence();
        $urgenceService->updateUrgence($urgence, $data);

        $response = [];

        $sameUrgentCounter = $urgenceRepository->countUrgenceMatching(
            $urgence->getDateStart(),
            $urgence->getDateEnd(),
            $urgence->getProvider(),
            $urgence->getCommande()
        );

        if ($sameUrgentCounter > 0) {
            $response['success'] = false;
            $response['message'] = "Une urgence sur la même période, avec le même fournisseur et le même numéro de commande existe déjà.";
        }
        else {
            $entityManager->persist($urgence);
            $entityManager->flush();
            $response['success'] = true;
            $response['message'] = "L'urgence a été créée avec succès.";
        }
        return new JsonResponse($response);
    }

    /**
     * @Route("/supprimer", name="urgence_delete", options={"expose"=true},methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $urgence = $this->urgenceRepository->find($data['urgence']);
            $canDeleteUrgence = !$urgence->getLastArrival();
            if ($canDeleteUrgence) {
                $entityManager->remove($urgence);
                $entityManager->flush();
            }

            return new JsonResponse();
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="urgence_edit_api", options={"expose"=true}, methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $urgence = $this->urgenceRepository->find($data['id']);
            $json = $this->renderView('urgence/modalEditUrgenceContent.html.twig', [
                'urgence' => $urgence,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="urgence_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param UrgenceService $urgenceService
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function edit(Request $request,
                         EntityManagerInterface $entityManager,
                         UrgenceService $urgenceService): Response
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        $data = json_decode($request->getContent(), true);

        $urgenceRepository = $entityManager->getRepository(Urgence::class);
        $urgence = $this->urgenceRepository->find($data['id']);
        $response = [];

        if ($urgence) {
            $urgenceService->updateUrgence($urgence, $data);
            $sameUrgentCounter = $urgenceRepository->countUrgenceMatching(
                $urgence->getDateStart(),
                $urgence->getDateEnd(),
                $urgence->getProvider(),
                $urgence->getCommande(),
                [$urgence->getId()]
            );

            if ($sameUrgentCounter > 0) {
                $response['success'] = false;
                $response['message'] = "Une urgence sur la même période, avec le même fournisseur et le même numéro de commande existe déjà.";
            }
            else {
                $entityManager->flush();
                $response['success'] = true;
                $response['message'] = "L'urgence a été modifiée avec succès.";
            }
        }
        else {
            $response['success'] = false;
            $response['message'] = "Une erreur est survenue lors de la modification de l'urgence.";
        }

        return new JsonResponse($response);
    }

    /**
     * @Route("/verification", name="urgence_check_delete", options={"expose"=true}, methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
	public function checkUrgenceCanBeDeleted(Request $request, EntityManagerInterface $entityManager): Response
	{
		$urgenceId = json_decode($request->getContent(), true);
		$urgenceRepository = $entityManager->getRepository(Urgence::class);

		$urgence = $urgenceRepository->find($urgenceId);

		// on vérifie que l'urgence n'a pas été déclenchée
		$urgenceUsed = !empty($urgence->getLastArrival());

		if (!$urgenceUsed) {
			$delete = true;
			$html = $this->renderView('urgence/modalDeleteUrgenceRight.html.twig');
		} else {
			$delete = false;
			$html = $this->renderView('urgence/modalDeleteUrgenceWrong.html.twig');
		}

		return new JsonResponse(['delete' => $delete, 'html' => $html]);
	}
}
