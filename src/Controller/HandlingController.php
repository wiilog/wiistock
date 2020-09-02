<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Emplacement;
use App\Entity\Menu;
use App\Entity\Handling;

use App\Entity\Statut;
use App\Entity\Utilisateur;

use App\Service\MailerService;
use App\Service\UserService;
use App\Service\HandlingService;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @Route("/service")
 */
class HandlingController extends AbstractController
{

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var MailerService
     */
    private $mailerService;


    public function __construct(UserService $userService,
                                MailerService $mailerService)
    {
        $this->userService = $userService;
        $this->mailerService = $mailerService;;
    }

    /**
     * @Route("/api", name="handling_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param HandlingService $handlingService
     * @return Response
     */
    public function api(Request $request, HandlingService $handlingService): Response
    {
		if ($request->isXmlHttpRequest()) {

			if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_HAND)) {
				return $this->redirectToRoute('access_denied');
			}

			// cas d'un filtre statut depuis page d'accueil
			$filterStatus = $request->request->get('filterStatus');
			$data = $handlingService->getDataForDatatable($request->request, $filterStatus);

			return new JsonResponse($data);
		} else {
			throw new NotFoundHttpException('404');
		}
    }

    /**
     * @Route("/liste/{filter}", name="handling_index", options={"expose"=true}, methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param string|null $filter
     * @return Response
     */
    public function index(EntityManagerInterface $entityManager,
                          $filter = null): Response
    {
        if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_HAND)) {
            return $this->redirectToRoute('access_denied');
        }

        $statutRepository = $entityManager->getRepository(Statut::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

        return $this->render('handling/index.html.twig', [
            'utilisateurs' => $utilisateurRepository->findAll(),
            'statuts' => $statutRepository->findByCategorieName(Handling::CATEGORIE),
			'filterStatus' => $filter
		]);
    }

    /**
     * @Route("/voir", name="handling_show", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function show(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_HAND)) {
				return $this->redirectToRoute('access_denied');
			}

			$handlingRepository = $entityManager->getRepository(Handling::class);
            $handling = $handlingRepository->find($data);
            $json = $this->renderView('handling/modalShowHandlingContent.html.twig', [
                'handling' => $handling,
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }


    /**
     * @Route("/creer", name="handling_new", options={"expose"=true}, methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function new(EntityManagerInterface $entityManager,
                        Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $status = $statutRepository->findOneByCategorieNameAndStatutCode(Handling::CATEGORIE, Handling::STATUT_A_TRAITER);
            $handling = new Handling();
            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));

            $handling
                ->setDate($date)
                ->setLibelle(substr($data['Libelle'], 0, 64))
                ->setSource($data['source'])
                ->setDestination($data['destination'])
                ->setStatut($status)
                ->setDemandeur($utilisateurRepository->find($data['demandeur']))
				->setDateAttendue($data['date-attendue'] ? new \DateTime($data['date-attendue']) : null)
				->setCommentaire($data['commentaire']);

            $em = $this->getDoctrine()->getManager();
            $em->persist($handling);

            $em->flush();

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/api-modifier", name="handling_edit_api", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
    public function editApi(EntityManagerInterface $entityManager,
                            Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $statutRepository = $entityManager->getRepository(Statut::class);
            $handlingRepository = $entityManager->getRepository(Handling::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $handling = $handlingRepository->find($data['id']);
            $json = $this->renderView('handling/modalEditHandlingContent.html.twig', [
                'handling' => $handling,
                'utilisateurs' => $utilisateurRepository->findAll(),
                'emplacements' => $emplacementRepository->findAll(),
                'statusTreated' => ($handling->getStatut()->getNom() === Handling::STATUT_A_TRAITER) ? 1 : 0,
                'statuts' => $statutRepository->findByCategorieName(Handling::CATEGORIE),
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="handling_edit", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param HandlingService $handlingService
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function edit(EntityManagerInterface $entityManager,
                         HandlingService $handlingService,
                         Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $handlingRepository = $entityManager->getRepository(Handling::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $handling = $handlingRepository->find($data['id']);

            $statutLabel = (intval($data['statut']) === 1) ? Handling::STATUT_A_TRAITER : Handling::STATUT_TRAITE;
            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(Handling::CATEGORIE, $statutLabel);
            if ($statut->getNom() === Handling::STATUT_TRAITE
                && $statut !== $handling->getStatut()) {
                $handlingService->sendTreatedEmail($handling);
                $handling->setDateEnd(new DateTime('now', new \DateTimeZone('Europe/Paris')));
            }

            $handling
                ->setStatut($statut)
                ->setLibelle(substr($data['Libelle'], 0, 64))
                ->setSource($data['source'])
                ->setDestination($data['destination'])
                ->setDemandeur($utilisateurRepository->find($data['demandeur']))
				->setDateAttendue($data['date-attendue'] ? new \DateTime($data['date-attendue']) : null)
				->setCommentaire($data['commentaire']);
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="handling_delete", options={"expose"=true},methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			if (!$this->userService->hasRightFunction(Menu::DEM, Action::DELETE)) {
				return $this->redirectToRoute('access_denied');
			}
            $handlingRepository = $entityManager->getRepository(Handling::class);
            $handling = $handlingRepository->find($data['handling']);

            if ($handling->getStatut()->getNom() == Handling::STATUT_A_TRAITER) {
				$entityManager = $this->getDoctrine()->getManager();
				$entityManager->remove($handling);
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

    /**
     * @Route("/infos", name="get_handlings_for_csv", options={"expose"=true}, methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function getOrdreLivraisonIntels(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $dateMin = $data['dateMin'] . ' 00:00:00';
            $dateMax = $data['dateMax'] . ' 23:59:59';

            $dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
            $dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);

            $handlingRepository = $entityManager->getRepository(Handling::class);
            $handlings = $handlingRepository->findByDates($dateTimeMin, $dateTimeMax);

            $headers = [
                'date création',
                'demandeur',
                'chargement',
                'déchargement',
                'date attendue',
                'date de réalisation',
                'statut',
            ];

            $data = [];
            $data[] = $headers;

            foreach ($handlings as $handling) {
                $this->buildInfos($handling, $data);
            }
            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }


    private function buildInfos(Handling $handling, &$data)
    {
        $data[] =
            [
                $handling->getDate()->format('d/m/Y H:i'),
                $handling->getDemandeur()->getUsername(),
                $handling->getSource(),
                $handling->getDestination(),
                $handling->getDateAttendue()->format('d/m/Y H:i'),
                $handling->getDateEnd() ? $handling->getDateEnd()->format('d/m/Y H:i') : '',
                $handling->getStatut() ? $handling->getStatut()->getNom() : '',
            ];
    }
}
