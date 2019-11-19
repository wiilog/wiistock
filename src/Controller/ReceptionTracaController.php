<?php

namespace App\Controller;
use App\Entity\Action;
use App\Entity\Menu;
use App\Repository\ReceptionTracaRepository;
use App\Repository\UtilisateurRepository;
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
     * ReceptionTracaController constructor.
     * @param UtilisateurRepository $utilisateurRepository
     * @param UserService $userService
     */
    public function __construct(UtilisateurRepository $utilisateurRepository, UserService $userService, ReceptionTracaRepository $receptionTracaRepository) {
        $this->userService = $userService;
        $this->utilisateurRepository= $utilisateurRepository;
        $this->receptionTracaRepository = $receptionTracaRepository;
    }

    /**
     * @Route("/", name="accueil_receptions_traca", methods={"GET"})
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('reception_traca/index.html.twig', [
            'utilisateurs' => $this->utilisateurRepository->findAllSorted(),
        ]);
    }

    /**
     * @Route("/api", name="recep_traca_api", options={"expose"=true}, methods="GET|POST")
     * @throws \Exception
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $receptions = $this->receptionTracaRepository->findAll();

            $rows = [];
            foreach ($receptions as $reception) {
                $rows[] = [
                    'id' => $reception->getId(),
                    'date' => $reception->getDateCreation()->format('d/m/Y H:i:s'),
                    'Arrivage' => $reception->getArrivage()->getNumeroArrivage(),
                    'Réception' => $reception->getNumber(),
                    'Utilisateur' => $reception->getUser()->getUsername(),
                    'Actions' => $this->renderView('reception_traca/datatableRecepTracaRow.html.twig', [
                        'recep' => $reception,
                    ])
                ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }


}
