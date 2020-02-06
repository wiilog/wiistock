<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Transporteur;
use App\Entity\Menu;
use App\Service\UserService;
use App\Repository\TransporteurRepository;
use App\Repository\ChauffeurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/transporteur")
 */
class TransporteurController extends AbstractController
{
    /**
     * @var TranspoteurRepository
     */
    private $transporteurRepository;

    /**
     * @var ChauffeurRepository
     */
    private $chauffeurRepository;

	/**
	 * @var UserService
	 */
    private $userService;

    public function __construct(TransporteurRepository $transporteurRepository, ChauffeurRepository $chauffeurRepository, UserService $userService)
    {
        $this->transporteurRepository = $transporteurRepository;
        $this->chauffeurRepository = $chauffeurRepository;
        $this->userService = $userService;
    }

    /**
     * @Route("/api", name="transporteur_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DISPLAY_TRAN)) {
                return $this->redirectToRoute('access_denied');
            }

            $transporteurs = $this->transporteurRepository->findAll();

            $rows = [];
            foreach ($transporteurs as $transporteur) {

                $rows[] = [
                    'Label' => $transporteur->getLabel() ? $transporteur->getLabel() : null,
                    'Code' => $transporteur->getCode() ? $transporteur->getCode(): null,
                    'Nombre_chauffeurs' => $this->chauffeurRepository->countByTransporteur($transporteur) ,
                    'Actions' => $this->renderView('transporteur/datatableTransporteurRow.html.twig', [
                        'transporteur' => $transporteur
                    ]),
                ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }
    /**
     * @Route("/", name="transporteur_index", methods={"GET"})
     */
    public function index(): Response
    {
        return $this->render('transporteur/index.html.twig', [
            'transporteurs' => $this->transporteurRepository->findAll(),
        ]);
    }

    /**
     * @Route("/creer", name="transporteur_new", options={"expose"=true}, methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $transporteur = new Transporteur();

            $transporteur
                ->setLabel($data['label'])
                ->setCode($data['code']);

            $em = $this->getDoctrine()->getManager();
            $em->persist($transporteur);

            $em->flush();
            $data['id'] = $transporteur->getId();
            $data['text'] = $transporteur->getLabel();
            return new JsonResponse($data);
        }
        throw new XmlHttpException('404 not found');
    }

    /**
     * @Route("/api-modifier", name="transporteur_edit_api", options={"expose"=true}, methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $transporteur = $this->transporteurRepository->find($data['id']);
            $json = $this->renderView('transporteur/modalEditTransporteurContent.html.twig', [
                'transporteur' => $transporteur,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="transporteur_edit", options={"expose"=true}, methods={"GET","POST"})
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $transporteur = $this->transporteurRepository->find($data['id']);

            $transporteur
                ->setLabel($data['label'])
                ->setCode($data['code']);
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="transporteur_delete", options={"expose"=true}, methods={"GET","POST"})
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $transporteur = $this->transporteurRepository->find($data['transporteur']);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($transporteur);
            $entityManager->flush();
            return new JsonResponse();
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/{id}", name="transporteur_show", methods={"GET"})
     */
    public function show(Transporteur $transporteur): Response
    {
        return $this->render('transporteur/show.html.twig', [
            'transporteur' => $transporteur,
        ]);
    }
}
