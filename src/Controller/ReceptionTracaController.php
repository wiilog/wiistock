<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\ReceptionTraca;
use App\Repository\ReceptionTracaRepository;
use App\Repository\UtilisateurRepository;
use App\Service\DashboardService;
use App\Service\ReceptionTracaService;
use App\Service\UserService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/receptions_traca")
 */
class ReceptionTracaController extends AbstractController
{

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var ReceptionTracaRepository
     */
    private $receptionTracaRepository;

    /**
     * @var DashboardService
     */
    private $dashboardService;

    /**
     * @var ReceptionTracaService
     */
    private $receptionTracaService;

    /**
     * ReceptionTracaController constructor.
     * @param UtilisateurRepository $utilisateurRepository
     * @param UserService $userService
     */
    public function __construct(DashboardService $dashboardService, UtilisateurRepository $utilisateurRepository, UserService $userService, ReceptionTracaRepository $receptionTracaRepository, ReceptionTracaService $receptionTracaService)
    {
        $this->dashboardService = $dashboardService;
        $this->userService = $userService;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->receptionTracaRepository = $receptionTracaRepository;
        $this->receptionTracaService = $receptionTracaService;
    }

    /**
     * @Route("/", name="reception_traca_index", methods={"GET"})
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ASSO)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('reception_traca/index.html.twig', [
            'utilisateurs' => $this->utilisateurRepository->findAllSorted(),
        ]);
    }

    /**
     * @Route("/api", name="reception_traca_api", options={"expose"=true}, methods="GET|POST")
     * @throws \Exception
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ASSO)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $this->receptionTracaService->getDataForDatatable($request->request);

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="reception_traca_delete", options={"expose"=true},methods={"GET","POST"})
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $recep = $this->receptionTracaRepository->find($data['recep']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($recep);
            $entityManager->flush();
            return new JsonResponse();
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="reception_traca_new", options={"expose"=true},methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
		if (!$this->userService->hasRightFunction(Menu::TRACA, Action::CREATE)) {
			return $this->redirectToRoute('access_denied');
		}

        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $entityManager = $this->getDoctrine()->getManager();
            if (isset($data['numero_arrivage']) && strpos($data['numero_arrivage'], ';')) {
                foreach (explode(';', $data['numero_arrivage']) as $arrivage) {
                    $recep = new ReceptionTraca();
                    $recep
                        ->setArrivage($arrivage)
                        ->setNumber($data['numero_réception'])
                        ->setDateCreation(new DateTime('now'))
                        ->setUser($this->getUser());
                    $entityManager->persist($recep);
                }
            } else {
                $recep = new ReceptionTraca();
                $recep
                    ->setArrivage(isset($data['numero_arrivage']) ? $data['numero_arrivage'] : '')
                    ->setNumber($data['numero_réception'])
                    ->setDateCreation(new DateTime('now'))
                    ->setUser($this->getUser());
                $entityManager->persist($recep);
            }

            $entityManager->flush();
            return new JsonResponse();
        }

        throw new NotFoundHttpException("404");
    }
}
