<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\ReceptionTraca;
use App\Entity\Utilisateur;
use App\Repository\ReceptionTracaRepository;
use App\Service\ReceptionTracaService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
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
     * @var UserService
     */
    private $userService;

    /**
     * @var ReceptionTracaRepository
     */
    private $receptionTracaRepository;

    /**
     * @var ReceptionTracaService
     */
    private $receptionTracaService;

    /**
     * ReceptionTracaController constructor.
     * @param UserService $userService
     * @param ReceptionTracaRepository $receptionTracaRepository
     * @param ReceptionTracaService $receptionTracaService
     */
    public function __construct(UserService $userService,
                                ReceptionTracaRepository $receptionTracaRepository,
                                ReceptionTracaService $receptionTracaService)
    {
        $this->userService = $userService;
        $this->receptionTracaRepository = $receptionTracaRepository;
        $this->receptionTracaService = $receptionTracaService;
    }

    /**
     * @Route("/", name="reception_traca_index", methods={"GET"})
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function index(EntityManagerInterface $entityManager): Response
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ASSO)) {
            return $this->redirectToRoute('access_denied');
        }

        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

        return $this->render('reception_traca/index.html.twig', [
            'utilisateurs' => $utilisateurRepository->findBy(['status' => true], ['username' => 'ASC']),
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
