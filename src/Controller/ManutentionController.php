<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Emplacement;
use App\Entity\Menu;
use App\Entity\Manutention;

use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Repository\ManutentionRepository;

use App\Service\MailerService;
use App\Service\UserService;
use App\Service\ManutentionService;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/manutention")
 */
class ManutentionController extends AbstractController
{
    /**
     * @var ManutentionRepository
     */
    private $manutentionRepository;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var MailerService
     */
    private $mailerService;

    /**
     * @var ManutentionService
     */
    private $manutentionService;


    public function __construct(ManutentionRepository $manutentionRepository, UtilisateurRepository $utilisateurRepository, UserService $userService, MailerService $mailerService, ManutentionService $manutentionService)
    {
        $this->manutentionRepository = $manutentionRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->userService = $userService;
        $this->mailerService = $mailerService;
        $this->manutentionService = $manutentionService;
    }

    /**
     * @Route("/api", name="manutention_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
		if ($request->isXmlHttpRequest()) {

			if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_MANU)) {
				return $this->redirectToRoute('access_denied');
			}

			// cas d'un filtre statut depuis page d'accueil
			$filterStatus = $request->request->get('filterStatus');
			$data = $this->manutentionService->getDataForDatatable($request->request, $filterStatus);

			return new JsonResponse($data);
		} else {
			throw new NotFoundHttpException('404');
		}
    }

    /**
     * @Route("/liste/{filter}", name="manutention_index", options={"expose"=true}, methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param string|null $filter
     * @return Response
     */
    public function index(EntityManagerInterface $entityManager,
                          $filter = null): Response
    {
        if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_MANU)) {
            return $this->redirectToRoute('access_denied');
        }

        $statutRepository = $entityManager->getRepository(Statut::class);

        return $this->render('manutention/index.html.twig', [
            'utilisateurs' => $this->utilisateurRepository->findAll(),
            'statuts' => $statutRepository->findByCategorieName(Manutention::CATEGORIE),
			'filterStatus' => $filter
		]);
    }

    /**
     * @Route("/voir", name="manutention_show", options={"expose"=true}, methods="GET|POST")
     */
    public function show(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_MANU)) {
				return $this->redirectToRoute('access_denied');
			}

            $manutention = $this->manutentionRepository->find($data);
            $json = $this->renderView('manutention/modalShowManutentionContent.html.twig', [
                'manut' => $manutention,
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }


    /**
     * @Route("/creer", name="manutention_new", options={"expose"=true}, methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function new(EntityManagerInterface $entityManager,
                        Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);

            $status = $statutRepository->findOneByCategorieNameAndStatutCode(Manutention::CATEGORIE, Manutention::STATUT_A_TRAITER);
            $manutention = new Manutention();
            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));

            $manutention
                ->setDate($date)
                ->setLibelle(substr($data['Libelle'], 0, 64))
                ->setSource($data['source'])
                ->setDestination($data['destination'])
                ->setStatut($status)
                ->setDemandeur($this->utilisateurRepository->find($data['demandeur']))
				->setDateAttendue($data['date-attendue'] ? new \DateTime($data['date-attendue']) : null)
				->setCommentaire($data['commentaire']);

            $em = $this->getDoctrine()->getManager();
            $em->persist($manutention);

            $em->flush();

            return new JsonResponse($data);
        }
        throw new XmlHttpException('404 not found');
    }

    /**
     * @Route("/api-modifier", name="manutention_edit_api", options={"expose"=true}, methods="GET|POST")
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
            $manutentionRepository = $entityManager->getRepository(Manutention::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $manutention = $manutentionRepository->find($data['id']);
            $json = $this->renderView('manutention/modalEditManutentionContent.html.twig', [
                'manut' => $manutention,
                'utilisateurs' => $utilisateurRepository->findAll(),
                'emplacements' => $emplacementRepository->findAll(),
                'statusTreated' => ($manutention->getStatut()->getNom() === Manutention::STATUT_A_TRAITER) ? 1 : 0,
                'statuts' => $statutRepository->findByCategorieName(Manutention::CATEGORIE),
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="manutention_edit", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function edit(EntityManagerInterface $entityManager,
                         Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $manutentionRepository = $entityManager->getRepository(Manutention::class);

            $manutention = $manutentionRepository->find($data['id']);

            $statutLabel = (intval($data['statut']) === 1) ? Manutention::STATUT_A_TRAITER : Manutention::STATUT_TRAITE;
            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(Manutention::CATEGORIE, $statutLabel);
            $manutention->setStatut($statut);

            $manutention
                ->setLibelle(substr($data['Libelle'], 0, 64))
                ->setSource($data['source'])
                ->setDestination($data['destination'])
                ->setDemandeur($this->utilisateurRepository->find($data['demandeur']))
				->setDateAttendue($data['date-attendue'] ? new \DateTime($data['date-attendue']) : null)
				->setCommentaire($data['commentaire']);
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            if ($statutLabel == Manutention::STATUT_TRAITE) {
                $this->mailerService->sendMail(
                    'FOLLOW GT // Manutention effectuée',
                    $this->renderView('mails/mailManutentionDone.html.twig', [
                    	'manut' => $manutention,
						'title' => 'Votre demande de manutention a bien été effectuée.',
					]),
                    $manutention->getDemandeur()->getEmail()
                );
            }

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="manutention_delete", options={"expose"=true},methods={"GET","POST"})
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			if (!$this->userService->hasRightFunction(Menu::DEM, Action::DELETE)) {
				return $this->redirectToRoute('access_denied');
			}

            $manutention = $this->manutentionRepository->find($data['manutention']);

            if ($manutention->getStatut()->getNom() == Manutention::STATUT_A_TRAITER) {
				$entityManager = $this->getDoctrine()->getManager();
				$entityManager->remove($manutention);
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
     * @Route("/infos", name="get_manutentions_for_csv", options={"expose"=true}, methods={"GET","POST"})
     */
    public function getOrdreLivraisonIntels(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $dateMin = $data['dateMin'] . ' 00:00:00';
            $dateMax = $data['dateMax'] . ' 23:59:59';

            $dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
            $dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);

            $manutentions = $this->manutentionRepository->findByDates($dateTimeMin, $dateTimeMax);

            $headers = [
                'date création',
                'demandeur',
                'chargement',
                'déchargement',
                'date attendue',
                'statut',
            ];

            $data = [];
            $data[] = $headers;

            foreach ($manutentions as $manutention) {
                $this->buildInfos($manutention, $data);
            }
            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }


    private function buildInfos(Manutention $manutention, &$data)
    {
        $data[] =
            [
                $manutention->getDate()->format('d/m/Y H:i'),
                $manutention->getDemandeur()->getUsername(),
                $manutention->getSource(),
                $manutention->getDestination(),
                $manutention->getDateAttendue()->format('d/m/Y H:i'),
                $manutention->getStatut() ? $manutention->getStatut()->getNom() : '',
            ];
    }
}
