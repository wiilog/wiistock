<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Service;
use App\Repository\UtilisateurRepository;
use App\Repository\EmplacementRepository;
use App\Repository\ServiceRepository;
use App\Repository\StatutRepository;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/manutention")
 */
class ServiceController extends AbstractController
{
    /**
     * @var ServiceRepository
     */
    private $serviceRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var UserService
     */
    private $userService;


    public function __construct(ServiceRepository $serviceRepository, EmplacementRepository $emplacementRepository, StatutRepository $statutRepository, UtilisateurRepository $utilisateurRepository, UserService $userService)
    {
        $this->serviceRepository = $serviceRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->statutRepository = $statutRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->userService = $userService;
    }

    /**
     * @Route("/api", name="service_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::MANUT, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $services = $this->serviceRepository->findAll();

            $rows = [];
            foreach ($services as $service) {
                $url['edit'] = $this->generateUrl('service_edit', ['id' => $service->getId()]);

                $rows[] = [
                    'id' => ($service->getId() ? $service->getId() : 'Non défini'),
                    'Date' => ($service->getDate() ? $service->getDate()->format('d/m/Y') : null),
                    'Demandeur' => ($service->getDemandeur() ? $service->getDemandeur()->getUserName() : null),
                    'Libellé' => ($service->getlibelle() ? $service->getLibelle() : null),
                    'Statut' => ($service->getStatut()->getNom() ? $service->getStatut()->getNom() : null),
                    'Actions' => $this->renderView('service/datatableServiceRow.html.twig', [
                        'url' => $url,
                        'service' => $service,
                        'serviceId' => $service->getId(),
                    ]),
                ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/", name="service_index", methods={"GET", "POST"})
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::MANUT, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('service/index.html.twig', [
            'utilisateurs' => $this->utilisateurRepository->findAll(),
            'statuts' => $this->statutRepository->findByCategorieName(Service::CATEGORIE),
        ]);
    }

    /**
     * @Route("/creer", name="service_new", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::MANUT, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $status = $this->statutRepository->findOneByCategorieAndStatut(Service::CATEGORIE, Service::STATUT_BROUILLON);
            $service = new Service();
            $date = new \DateTime('now');
            dump($data['commentaire']);
            $service
                ->setDate($date)
                ->setLibelle($data['Libelle'])
                ->setSource($data['source'])
                ->setDestination($data['destination'])
                ->setStatut($status)
                ->setDemandeur($this->utilisateurRepository->find($data['demandeur']))
                ->setCommentaire($data['commentaire']);

            $em = $this->getDoctrine()->getEntityManager();
            $em->persist($service);

            $em->flush();

            return new JsonResponse($data);
        }
        throw new XmlHttpException('404 not found');
    }

    /**
     * @Route("/api-modifier", name="service_edit_api", options={"expose"=true}, methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::MANUT, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $service = $this->serviceRepository->find($data);
            $statut = 2;
            if ($service->getStatut()->getNom() === Service::STATUT_A_TRAITER) {
                $statut = 1;
            } elseif ($service->getStatut()->getNom() === Service::STATUT_TRAITE) {
                $statut = 0;
            }
            $json = $this->renderView('service/modalEditServiceContent.html.twig', [
                'service' => $service,
                'utilisateurs' => $this->utilisateurRepository->findAll(),
                'emplacements' => $this->emplacementRepository->findAll(),
                'statut' => $statut,
                'statuts' => $this->statutRepository->findByCategorieName(Service::CATEGORIE),
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="service_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::MANUT, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }
                 dump($data);

            $service = $this->serviceRepository->find($data['id']);
            $statutLabel = Service::STATUT_BROUILLON;
            if (intval($data['statut']) === 1) {
                $statutLabel = Service::STATUT_A_TRAITER;
            } elseif (intval($data['statut']) === 0) {
                $statutLabel = Service::STATUT_TRAITE;
            }
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Service::CATEGORIE, $statutLabel);
            $service->setStatut($statut);
            $service
                ->setLibelle($data['Libelle'])
                ->setSource($data['source'])
                ->setDestination($data['destination'])
                ->setDemandeur($this->utilisateurRepository->find($data['demandeur']))
                ->setCommentaire($data['commentaire']);
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="service_delete", options={"expose"=true},methods={"GET","POST"})
     */

    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::MANUT, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            $service = $this->serviceRepository->find($data['service']);

            if ($service->getStatut()->getNom() == Service::STATUT_TRAITE) {
                return $this->redirectToRoute('access_denied');
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($service);
            $entityManager->flush();
            return new JsonResponse();
        }
     
        throw new NotFoundHttpException("404");
    }
}
