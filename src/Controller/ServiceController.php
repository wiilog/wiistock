<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Service;
use App\Repository\ParamClientRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\EmplacementRepository;
use App\Repository\ServiceRepository;
use App\Repository\StatutRepository;
use App\Service\MailerService;
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
	 * @var ParamClientRepository
	 */
    private $paramClientRepository;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var MailerService
     */
    private $mailerService;


    public function __construct(ParamClientRepository $paramClientRepository, ServiceRepository $serviceRepository, EmplacementRepository $emplacementRepository, StatutRepository $statutRepository, UtilisateurRepository $utilisateurRepository, UserService $userService, MailerService $mailerService)
    {
        $this->serviceRepository = $serviceRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->statutRepository = $statutRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->userService = $userService;
        $this->mailerService = $mailerService;
        $this->paramClientRepository = $paramClientRepository;
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

                $rows[] = [
                    'id' => ($service->getId() ? $service->getId() : 'Non défini'),
                    'Date' => ($service->getDate() ? $service->getDate()->format('d/m/Y') : null),
                    'Demandeur' => ($service->getDemandeur() ? $service->getDemandeur()->getUserName() : null),
                    'Libellé' => ($service->getlibelle() ? $service->getLibelle() : null),
                    'Statut' => ($service->getStatut()->getNom() ? $service->getStatut()->getNom() : null),
                    'Actions' => $this->renderView('service/datatableServiceRow.html.twig', [
                        'service' => $service
                    ]),
                ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/liste/{filter}", name="service_index", options={"expose"=true}, methods={"GET", "POST"})
	 * @param string|null $filter
	 * @return Response
     */
    public function index($filter = null): Response
    {
        if (!$this->userService->hasRightFunction(Menu::MANUT, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

		switch ($filter) {
			case 'a-traiter':
				$filter = Service::STATUT_A_TRAITER;
				break;
		}

        return $this->render('service/index.html.twig', [
            'utilisateurs' => $this->utilisateurRepository->findAll(),
            'statuts' => $this->statutRepository->findByCategorieName(Service::CATEGORIE),
			'filterStatus' => $filter
		]);
    }

    /**
     * @Route("/voir", name="service_show", options={"expose"=true}, methods="GET|POST")
     */
    public function show(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			if (!$this->userService->hasRightFunction(Menu::MANUT, Action::LIST)) {
				return $this->redirectToRoute('access_denied');
			}

            $service = $this->serviceRepository->find($data);
            $json = $this->renderView('service/modalShowServiceContent.html.twig', [
                'service' => $service,
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
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

            $status = $this->statutRepository->findOneByCategorieAndStatut(Service::CATEGORIE, Service::STATUT_A_TRAITER);
            $service = new Service();
            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));

            $service
                ->setDate($date)
                ->setLibelle(substr($data['Libelle'], 0, 64))
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
            if (!$this->userService->hasRightFunction(Menu::MANUT, Action::EDIT_DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            $service = $this->serviceRepository->find($data['id']);
            $json = $this->renderView('service/modalEditServiceContent.html.twig', [
                'service' => $service,
                'utilisateurs' => $this->utilisateurRepository->findAll(),
                'emplacements' => $this->emplacementRepository->findAll(),
                'statut' => (($service->getStatut()->getNom() === Service::STATUT_A_TRAITER) ? 1 : 0),
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
            if (!$this->userService->hasRightFunction(Menu::MANUT, Action::EDIT_DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            $service = $this->serviceRepository->find($data['id']);
            $statutLabel = (intval($data['statut']) === 1) ? Service::STATUT_A_TRAITER : Service::STATUT_TRAITE;
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Service::CATEGORIE, $statutLabel);
            $service->setStatut($statut);
            $service
                ->setLibelle(substr($data['Libelle'], 0, 64))
                ->setSource($data['source'])
                ->setDestination($data['destination'])
                ->setDemandeur($this->utilisateurRepository->find($data['demandeur']))
                ->setCommentaire($data['commentaire']);
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            if ($statutLabel == Service::STATUT_TRAITE) {
                $this->mailerService->sendMail(
                    'FOLLOW GT // Manutention effectuée',
                    $this->renderView('mails/mailManutentionDone.html.twig', [
                    	'manut' => $service,
						'title' => 'Votre demande de manutention a bien été effectuée.',
					]),
                    $service->getDemandeur()->getEmail()
                );
            }

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
			if (!$this->userService->hasRightFunction(Menu::MANUT, Action::EDIT_DELETE)) {
				return $this->redirectToRoute('access_denied');
			}

            $service = $this->serviceRepository->find($data['service']);

            if ($service->getStatut()->getNom() == Service::STATUT_A_TRAITER) {
				$entityManager = $this->getDoctrine()->getManager();
				$entityManager->remove($service);
				$entityManager->flush();
				$response = true;
            } else {
            	$response = false;
			}
            //TODO gérer retour message erreur

            return new JsonResponse($response);
        }

        throw new NotFoundHttpException("404");
    }
}
