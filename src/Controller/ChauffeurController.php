<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Chauffeur;
use App\Entity\Menu;
use App\Repository\ArrivageRepository;
use App\Service\UserService;
use App\Repository\ChauffeurRepository;
use App\Repository\TransporteurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/chauffeur")
 */
class ChauffeurController extends AbstractController
{
    /**
     * @var ChauffeurRepository
     */
    private $chauffeurRepository;

    /**
     * @var TransporteurRepository
     */
    private $transporteurRepository;

    /**
     * @var UserService
     */
    private $userService;

	/**
	 * @var ArrivageRepository
	 */
    private $arrivageRepository;


    public function __construct(ArrivageRepository $arrivageRepository, ChauffeurRepository $chauffeurRepository, TransporteurRepository $transporteurRepository, UserService $userService)
    {
        $this->chauffeurRepository = $chauffeurRepository;
        $this->transporteurRepository = $transporteurRepository;
        $this->userService = $userService;
        $this->arrivageRepository = $arrivageRepository;
    }

    /**
     * @Route("/api", name="chauffeur_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DISPLAY_CHAU)) {
                return $this->redirectToRoute('access_denied');
            }

            $chauffeurs = $this->chauffeurRepository->findAllSorted();

            $rows = [];
            foreach ($chauffeurs as $chauffeur) {

                $rows[] = [
                    'Nom' => ($chauffeur->getNom() ? $chauffeur->getNom() : null),
                    'Prénom' => ($chauffeur->getPrenom() ? $chauffeur->getPrenom(): null),
                    'DocumentID' => ($chauffeur->getDocumentID() ? $chauffeur->getDocumentID() : null),
                    'Transporteur' => ($chauffeur->getTransporteur() ? $chauffeur->getTransporteur()->getLabel() : null),
                    'Actions' => $this->renderView('chauffeur/datatableChauffeurRow.html.twig', [
                        'chauffeur' => $chauffeur
                    ]),
                ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/", name="chauffeur_index", methods={"GET"})
     */
    public function index(): Response
    {
		if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DISPLAY_CHAU)) {
			return $this->redirectToRoute('access_denied');
		}

        return $this->render('chauffeur/index.html.twig', [
            'chauffeurs' => $this->chauffeurRepository->findAllSorted(),
            'transporteurs' => $this->transporteurRepository->findAllSorted(),
        ]);
    }

    /**
     * @Route("/creer", name="chauffeur_new", options={"expose"=true}, methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }
            $chauffeur = new Chauffeur();

            $chauffeur
                ->setNom($data['nom'])
                ->setPrenom($data['prenom'])
                ->setDocumentID($data['documentID'])
                ->setTransporteur($this->transporteurRepository->find($data['transporteur']));

            $em = $this->getDoctrine()->getManager();
            $em->persist($chauffeur);

            $em->flush();

            $data['id'] = $chauffeur->getId();
            $data['text'] = $data['nom'];

            return new JsonResponse($data);
        }
        throw new XmlHttpException('404 not found');
    }

    /**
     * @Route("/api-modifier", name="chauffeur_edit_api", options={"expose"=true}, methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DISPLAY_CHAU)) {
                return $this->redirectToRoute('access_denied');
            }

            $chauffeur = $this->chauffeurRepository->find($data['id']);
            $transporteurs = $this->transporteurRepository->findAll();
            $json = $this->renderView('chauffeur/modalEditChauffeurContent.html.twig', [
                'chauffeur' => $chauffeur,
                'transporteurs' => $transporteurs,
                'transporteur' => $chauffeur->getTransporteur(),

            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="chauffeur_edit", options={"expose"=true}, methods={"GET","POST"})
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $chauffeur = $this->chauffeurRepository->find($data['id']);

            $chauffeur
                ->setNom($data['nom'])
                ->setPrenom($data['prenom'])
                ->setDocumentID($data['documentID']);

            if ($data['transporteur']) {
            	$chauffeur->setTransporteur($this->transporteurRepository->find($data['transporteur']));
			}
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/verification", name="chauffeur_check_delete", options={"expose"=true}, methods={"GET","POST"})
     */
    public function checkChauffeurCanBeDeleted(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $chauffeurId = json_decode($request->getContent(), true)) {
			if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DELETE)) {
				return $this->redirectToRoute('access_denied');
			}

			$chauffeur = $this->chauffeurRepository->find($chauffeurId);

			// on vérifie que le chauffeur n'est plus utilisé
			$chauffeurIsUsed = $this->isChauffeurUsed($chauffeur);

			if (!$chauffeurIsUsed) {
				$delete = true;
				$html = $this->renderView('chauffeur/modalDeleteChauffeurRight.html.twig');
			} else {
				$delete = false;
				$html = $this->renderView('chauffeur/modalDeleteChauffeurWrong.html.twig');
			}

			return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }

        throw new NotFoundHttpException("404");
    }

    public function isChauffeurUsed($chauffeur)
	{
		return $this->arrivageRepository->countByChauffeur($chauffeur) > 0;
	}

	/**
	 * @Route("/supprimer", name="chauffeur_delete",  options={"expose"=true}, methods={"GET", "POST"})
	 */
	public function delete(Request $request): Response
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DELETE)) {
				return $this->redirectToRoute('access_denied');
			}

			if ($chauffeurId = (int)$data['chauffeur']) {

				$chauffeur = $this->chauffeurRepository->find($chauffeurId);

				// on vérifie que le fournisseur n'est plus utilisé
				$isUsedChauffeur = $this->isChauffeurUsed($chauffeur);

				if ($isUsedChauffeur) {
					return new JsonResponse(false);
				}

				$entityManager = $this->getDoctrine()->getManager();
				$entityManager->remove($chauffeur);
				$entityManager->flush();
			}
			return new JsonResponse();
		}
		throw new NotFoundHttpException("404");
	}

    /**
     * @Route("/autocomplete", name="get_transporteurs", options={"expose"=true})
     */
    public function getTransporteurs(Request $request)
    {
        if ($request->isXmlHttpRequest()) {

            $search = $request->query->get('term');

            $transporteur = $this->transporteurRepository->getIdAndLibelleBySearch($search);
            return new JsonResponse(['results' => $transporteur]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/autocomplete-chauffeur", name="get_chauffeur", options={"expose"=true})
     */
    public function getChauffeur(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('term');
            $chauffeur = $this->chauffeurRepository->getIdAndLibelleBySearch($search);
            return new JsonResponse(['results' => $chauffeur]);
        }
        throw new NotFoundHttpException("404");
    }
}
