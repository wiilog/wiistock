<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\Chauffeur;
use App\Entity\Menu;
use App\Entity\Transporteur;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/chauffeur")
 */
class ChauffeurController extends AbstractController
{

    /**
     * @var UserService
     */
    private $userService;

    public function __construct(UserService $userService) {
        $this->userService = $userService;
    }

    /**
     * @Route("/api", name="chauffeur_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::DISPLAY_CHAU}, mode=HasPermission::IN_JSON)
     */
    public function api(EntityManagerInterface $entityManager): Response
    {
        $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);

        $chauffeurs = $chauffeurRepository->findAllSorted();

        $rows = [];
        foreach ($chauffeurs as $chauffeur) {

            $rows[] = [
                'Nom' => $chauffeur->getNom() ?? null,
                'Prénom' => $chauffeur->getPrenom() ?? null,
                'DocumentID' => $chauffeur->getDocumentID() ?? null,
                'Transporteur' => $chauffeur->getTransporteur() ? $chauffeur->getTransporteur()->getLabel() : null,
                'Actions' => $this->renderView('chauffeur/datatableChauffeurRow.html.twig', [
                    'chauffeur' => $chauffeur
                ]),
            ];
        }
        $data['data'] = $rows;

        return new JsonResponse($data);
    }

    /**
     * @Route("/", name="chauffeur_index", methods={"GET"})
     * @HasPermission({Menu::REFERENTIEL, Action::DISPLAY_CHAU})
     */
    public function index(EntityManagerInterface $entityManager): Response
    {
		$chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
		$transporteurRepository = $entityManager->getRepository(Transporteur::class);

        return $this->render('chauffeur/index.html.twig', [
            'chauffeurs' => $chauffeurRepository->findAllSorted(),
            'transporteurs' => $transporteurRepository->findAllSorted(),
        ]);
    }

    /**
     * @Route("/creer", name="chauffeur_new", options={"expose"=true}, methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $chauffeur = new Chauffeur();

            $transporteurRepository = $entityManager->getRepository(Transporteur::class);

            $chauffeur
                ->setNom($data['nom'])
                ->setPrenom($data['prenom'] ?? null)
                ->setDocumentID($data['documentID'] ?? null)
                ->setTransporteur(!empty($data['transporteur']) ? $transporteurRepository->find($data['transporteur']) : null);

            $entityManager->persist($chauffeur);
            $entityManager->flush();

            $data['id'] = $chauffeur->getId();
            $data['text'] = $data['nom'];

            return new JsonResponse([
                'success' => true,
                'msg' => 'Le chauffeur ' . $data['nom'] . ' ' . $data['prenom'] . ' a bien été créé.',
                'id' => $chauffeur->getId(),
                'text' => $data['nom']
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="chauffeur_edit_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editApi(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
            $transporteurRepository = $entityManager->getRepository(Transporteur::class);

            $chauffeur = $chauffeurRepository->find($data['id']);
            $transporteurs = $transporteurRepository->findAll();

            $json = $this->renderView('chauffeur/modalEditChauffeurContent.html.twig', [
                'chauffeur' => $chauffeur,
                'transporteurs' => $transporteurs,
                'transporteur' => $chauffeur->getTransporteur(),
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="chauffeur_edit", options={"expose"=true}, methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
            $transporteurRepository = $entityManager->getRepository(Transporteur::class);

            $chauffeur = $chauffeurRepository->find($data['id']);

            $chauffeur
                ->setNom($data['nom'])
                ->setPrenom($data['prenom'])
                ->setDocumentID($data['documentID']);

            if ($data['transporteur']) {
            	$chauffeur->setTransporteur($transporteurRepository->find($data['transporteur']));
			}
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'Le chauffeur a bien été modifié.'
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/verification", name="chauffeur_check_delete", options={"expose"=true}, methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function checkChauffeurCanBeDeleted(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($chauffeurId = json_decode($request->getContent(), true)) {
            $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);

			$chauffeur = $chauffeurRepository->find($chauffeurId);

			// on vérifie que le chauffeur n'est plus utilisé
			$chauffeurIsUsed = $this->isChauffeurInUse($entityManager, $chauffeur);

			if (!$chauffeurIsUsed) {
				$delete = true;
				$html = $this->renderView('chauffeur/modalDeleteChauffeurRight.html.twig');
			} else {
				$delete = false;
				$html = $this->renderView('chauffeur/modalDeleteChauffeurWrong.html.twig');
			}

			return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }

        throw new BadRequestHttpException();
    }

    public function isChauffeurInUse(EntityManagerInterface $manager, $chauffeur)
	{
		return $manager->getRepository(Arrivage::class)->countByChauffeur($chauffeur) > 0;
	}

    /**
     * @Route("/supprimer", name="chauffeur_delete",  options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
	public function delete(Request $request, EntityManagerInterface $entityManager): Response
	{
		if ($data = json_decode($request->getContent(), true)) {
			if ($chauffeurId = (int)$data['chauffeur']) {
                $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
				$chauffeur = $chauffeurRepository->find($chauffeurId);

				// on vérifie que le chauffeur n'est plus utilisé
				$isUsedChauffeur = $this->isChauffeurInUse($entityManager, $chauffeur);

				if ($isUsedChauffeur) {
					return new JsonResponse([
					    'success' => false,
                        'msg' => 'Le chauffeur ' .$chauffeur->getNom(). ' ' .$chauffeur->getPrenom(). ' est utilisé, vous ne pouvez pas le supprimer.'
                    ]);
				}

				$entityManager->remove($chauffeur);
				$entityManager->flush();

			}
            return new JsonResponse([
                'success' => true,
                'msg' => 'Le chauffeur ' .$chauffeur->getNom(). ' ' .$chauffeur->getPrenom(). ' a bien été supprimé.'
            ]);
		}
		throw new BadRequestHttpException();
	}

    /**
     * @Route("/autocomplete", name="get_transporteurs", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function getTransporteurs(Request $request,
                                     EntityManagerInterface $entityManager)
    {
        $search = $request->query->get('term');

        $transporteurRepository = $entityManager->getRepository(Transporteur::class);
        $transporteur = $transporteurRepository->getIdAndLibelleBySearch($search);

        return new JsonResponse(['results' => $transporteur]);
    }

    /**
     * @Route("/autocomplete-chauffeur", name="get_chauffeur", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function getChauffeur(Request $request, EntityManagerInterface $entityManager)
    {
        $search = $request->query->get('term');
        $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
        $chauffeur = $chauffeurRepository->getIdAndLibelleBySearch($search);
        return new JsonResponse(['results' => $chauffeur]);
    }
}
