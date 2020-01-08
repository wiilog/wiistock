<?php

namespace App\Controller;


use App\Entity\Action;
use App\Entity\Manutention;
use App\Entity\Menu;
use App\Entity\Urgence;
use App\Repository\DaysWorkedRepository;
use App\Repository\UrgenceRepository;
use App\Service\UrgenceService;
use App\Service\UserService;
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
        if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::LIST)) {
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
            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }
            $data = $this->urgenceService->getDataForDatatable($request->request);
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="urgence_new", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $urgence = new Urgence();
            $urgence
                ->setCommande($data['commande'])
                ->setDateStart(new \DateTime($data['dateStart'], new \DateTimeZone("Europe/Paris")))
                ->setDateEnd(new \DateTime($data['dateEnd'], new \DateTimeZone("Europe/Paris")));

            $em = $this->getDoctrine()->getManager();
            $em->persist($urgence);

            $em->flush();
            return new JsonResponse($data);
        }
        throw new XmlHttpException('404 not found');
    }

    /**
     * @Route("/supprimer", name="urgence_delete", options={"expose"=true},methods={"GET","POST"})
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $urgence = $this->urgenceRepository->find($data['urgence']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($urgence);
            $entityManager->flush();
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
            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::CREATE_EDIT)) {
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
     * @Route("/modifier", name="urgence_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::MANUT, Action::EDIT_DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            $urgence = $this->urgenceRepository->find($data['id']);
            $urgence
                ->setCommande($data['commande'])
                ->setDateStart(new \DateTime($data['dateStart'], new \DateTimeZone("Europe/Paris")))
                ->setDateEnd(new \DateTime($data['dateEnd'], new \DateTimeZone("Europe/Paris")));
            $em = $this->getDoctrine()->getManager();
            $em->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }
}