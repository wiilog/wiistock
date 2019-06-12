<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Chauffeur;
use App\Entity\Transporteur;
use App\Entity\Menu;
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

    public function __construct(ChauffeurRepository $chauffeurRepository, TransporteurRepository $transporteurRepository)
    {
        $this->chauffeurRepository = $chauffeurRepository;
        $this->transporteurRepository = $transporteurRepository;
    }

    /**
     * @Route("/api", name="chauffeur_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
//            if (!$this->userService->hasRightFunction(Menu::MANUT, Action::LIST)) {
//                return $this->redirectToRoute('access_denied');
//            }

            $chauffeurs = $this->chauffeurRepository->findAll();

            $rows = [];
            foreach ($chauffeurs as $chauffeur) {

                $rows[] = [
//                    'id' => ($chauffeur->getId() ? $chauffeur->getId() : 'Non défini'),
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

        return $this->render('chauffeur/index.html.twig', [
            'chauffeurs' => $this->chauffeurRepository->findAll(),
            'transporteurs' => $this->transporteurRepository->findAll(),
        ]);
    }

    /**
     * @Route("/creer", name="chauffeur_new", options={"expose"=true}, methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::CHAUFFEUR, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $chauffeur = new Chauffeur();

            $chauffeur
                ->setNom($data['nom'])
                ->setPrenom($data['prenom'])
                ->setDocumentID($data['documentID'])
                ->setTransporteur($this->transporteurRepository->find($data['transporteur']));

            $em = $this->getDoctrine()->getEntityManager();
            $em->persist($chauffeur);

            $em->flush();

            return new JsonResponse($data);
        }
        throw new XmlHttpException('404 not found');
    }

    /**
     * @Route("/api-modifier", name="chauffeur_edit_api", options={"expose"=true}, methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
//            if (!$this->userService->hasRightFunction(Menu::MANUT, Action::LIST)) {
//                return $this->redirectToRoute('access_denied');
//            }

            $transporteurs = $this->transporteurRepository->findAll();
            $chauffeur = $this->chauffeurRepository->find($data['id']);
            $json = $this->renderView('chauffeur/modalEditChauffeurContent.html.twig', [
                'chauffeur' => $chauffeur,
                'transporteurs' => $transporteurs,
                'transporteur' => ($chauffeur->getTransporteur()),

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
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::CHAUFFEUR, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $chauffeur = $this->chauffeurRepository->find($data['id']);

            $chauffeur
                ->setNom($data['nom'])
                ->setPrenom($data['prenom'])
                ->setDocumentID($data['documentID'])
                ->setTransporteur($this->transporteurRepository->find($data['transporteur']));
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="chauffeur_delete", options={"expose"=true}, methods={"GET","POST"})
     */
    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $chauffeur = $this->chauffeurRepository->find($data['chauffeur']);


            if (
                !$this->userService->hasRightFunction(Menu::CHAUFFEUR, Action::LIST)

            ) {
                return $this->redirectToRoute('access_denied');
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($chauffeur);
            $entityManager->flush();
            return new JsonResponse();
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/autocomplete", name="get_Transporteur", options={"expose"=true})
     */
    public function getTransporteur(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
//            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
//                return new JsonResponse(['results' => []]);
//            }

            $search = $request->query->get('term');

            $transporteur = $this->transporteurRepository->getIdAndLibelleBySearch($search);
            return new JsonResponse(['results' => $transporteur]);
        }
        throw new NotFoundHttpException("404");
    }





}
