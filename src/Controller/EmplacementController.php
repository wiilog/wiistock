<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Emplacement;
use App\Entity\Menu;
use App\Repository\CollecteRepository;
use App\Repository\DemandeRepository;
use App\Repository\EmplacementRepository;
use App\Repository\LivraisonRepository;
use App\Repository\MouvementRepository;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Repository\ArticleRepository;

/**
 * @Route("/emplacement")
 */
class EmplacementController extends AbstractController
{

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

    /**
     * @var LivraisonRepository
     */
    private $livraisonRepository;

    /**
     * @var CollecteRepository
     */
    private $collecteRepository;

    /**
     * @var MouvementRepository
     */
    private $mouvementRepository;

    /**
     * @var UserService
     */
    private $userService;


    public function __construct(ArticleRepository $articleRepository, EmplacementRepository $emplacementRepository, UserService $userService, DemandeRepository $demandeRepository, LivraisonRepository $livraisonRepository, CollecteRepository $collecteRepository, MouvementRepository $mouvementRepository)
    {
        $this->emplacementRepository = $emplacementRepository;
        $this->articleRepository = $articleRepository;
        $this->userService = $userService;
        $this->demandeRepository = $demandeRepository;
        $this->livraisonRepository = $livraisonRepository;
        $this->collecteRepository = $collecteRepository;
        $this->mouvementRepository = $mouvementRepository;
    }

    /**
     * @Route("/api", name="emplacement_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $emplacements = $this->emplacementRepository->findAll();
            $rows = [];
            foreach ($emplacements as $emplacement) {
                $emplacementId = $emplacement->getId();
                $url['edit'] = $this->generateUrl('emplacement_edit', ['id' => $emplacementId]);
                $rows[] = [
                    'id' => $emplacement->getId(),
                    'Nom' => $emplacement->getLabel(),
                    'Description' => $emplacement->getDescription(),
                    'Actions' => $this->renderView('emplacement/datatableEmplacementRow.html.twig', [
                        'url' => $url,
                        'emplacementId' => $emplacementId
                    ]),
                ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="emplacement_index", methods="GET")
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('emplacement/index.html.twig', ['emplacement' => $this->emplacementRepository->findAll()]);
    }

    /**
     * @Route("/creer", name="emplacement_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getEntityManager();
            $emplacement = new Emplacement();
            $emplacement->setLabel($data["Label"]);
            $emplacement->setDescription($data["Description"]);
            $em->persist($emplacement);
            $em->flush();
            return new JsonResponse($data);
        }

        throw new NotFoundHttpException("404");
    }

//    /**
//     * @Route("/voir", name="emplacement_show", options={"expose"=true},  methods="GET|POST")
//     */
//    public function show(Request $request): Response
//    {
//        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
//            $emplacement = $this->emplacementRepository->find($data);
//
//            $json = $this->renderView('emplacement/modalShowEmplacementContent.html.twig', [
//                'emplacement' => $emplacement,
//            ]);
//            return new JsonResponse($json);
//        }
//        throw new NotFoundHttpException("404");
//    }

    /**
     * @Route("/api-modifier", name="emplacement_api_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function apiEdit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $emplacement = $this->emplacementRepository->find($data);
            $json = $this->renderView('emplacement/modalEditEmplacementContent.html.twig', [
                'emplacement' => $emplacement,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/edit", name="emplacement_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $emplacement = $this->emplacementRepository->find($data['id']);
            $emplacement
                ->setLabel($data["Label"])
                ->setDescription($data["Description"]);
            $em = $this->getDoctrine()->getManager();
            $em->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/verification", name="emplacement_check_delete", options={"expose"=true})
     */
    public function checkEmplacementCanBeDeleted(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $emplacementId = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            if ($this->countUsedEmplacements($emplacementId) == 0) {
                $delete = true;
                $html = $this->renderView('emplacement/modalDeleteEmplacementRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('emplacement/modalDeleteEmplacementWrong.html.twig', ['delete' => false]);
            }

            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new NotFoundHttpException('404');
    }

    private function countUsedEmplacements($emplacementId)
    {
        $usedEmplacement = $this->demandeRepository->countByEmplacement($emplacementId);
        $usedEmplacement += $this->livraisonRepository->countByEmplacement($emplacementId);
        $usedEmplacement += $this->collecteRepository->countByEmplacement($emplacementId);
        $usedEmplacement += $this->mouvementRepository->countByEmplacement($emplacementId);

        return $usedEmplacement;
    }

    /**
     * @Route("/supprimer", name="emplacement_delete", options={"expose"=true})
     */
    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            if ($emplacementId = (int)$data['emplacement']) {

                $emplacement = $this->emplacementRepository->find($emplacementId);

                // on vérifie que l'emplacement n'est plus utilisé
                $usedEmplacement = $this->countUsedEmplacements($emplacementId);

                if ($usedEmplacement > 0) {
                    return new JsonResponse(false);
                }

                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->remove($emplacement);
                $entityManager->flush();
                return new JsonResponse();
            }
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/autocomplete", name="get_emplacement", options={"expose"=true})
     */
    public function getRefArticles(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
                return new JsonResponse(['results' => []]);
            }

            $search = $request->query->get('term');
            
            $emplacement = $this->emplacementRepository->getIdAndLibelleBySearch($search);
            return new JsonResponse(['results' => $emplacement]);
        }
        throw new NotFoundHttpException("404");
    }

}
