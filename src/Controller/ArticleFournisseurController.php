<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\ArticleFournisseur;
use App\Entity\Menu;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\FournisseurRepository;
use App\Repository\ReferenceArticleRepository;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/article-fournisseur")
 */
class ArticleFournisseurController extends AbstractController
{
    /**
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var UserService
     */
    private $userService;


    public function __construct(ArticleFournisseurRepository $articleFournisseurRepository, FournisseurRepository $fournisseurRepository, ReferenceArticleRepository $referenceArticleRepository, UserService $userService)
    {
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->userService = $userService;
    }

    /**
     * @Route("/", name="article_fournisseur_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_ARTI_FOUR)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('article_fournisseur/index.html.twig');
    }

    /**
     * @Route("/api", name="article_fournisseur_api", options={"expose"=true})
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_ARTI_FOUR)) {
                return $this->redirectToRoute('access_denied');
            }

            $articlesFournisseurs = $this->articleFournisseurRepository->findByParams($request->request);
            $rows = [];
            foreach ($articlesFournisseurs as $articleFournisseur) {
                $rows[] = $this->dataRowArticleFournisseur($articleFournisseur);
            }


            $data['data'] = $rows;
            $data['recordsTotal'] = (int)$this->articleFournisseurRepository->countAll();
            $data['recordsFiltered'] = (int)$this->articleFournisseurRepository->countAll();

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="article_fournisseur_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $fournisseur = $this->fournisseurRepository->find(intval($data['fournisseur']));
            $referenceArticle = $this->referenceArticleRepository->find(intval($data['article-reference']));

            $articleFournisseur = new ArticleFournisseur();
            $articleFournisseur
                ->setFournisseur($fournisseur)
                ->setReference($data['reference'])
                ->setReferenceArticle($referenceArticle);

            $em = $this->getDoctrine()->getManager();
            $em->persist($articleFournisseur);
            $em->flush();
            return new JsonResponse($data);
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="article_fournisseur_api_edit", options={"expose"=true},  methods="GET|POST")
     */
    public function displayEdit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $articleFournisseur = $this->articleFournisseurRepository->find(intval($data['id']));
            $json = $this->renderView('article_fournisseur/modalEditArticleFournisseurContent.html.twig', [
                'articleFournisseur' => $articleFournisseur,
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="article_fournisseur_edit",  options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $articleFournisseur = $this->articleFournisseurRepository->find(intval($data['id']));
            $fournisseur = $this->fournisseurRepository->find(intval($data['fournisseur']));
            $referenceArticle = $this->referenceArticleRepository->find(intval($data['article-reference']));

            $articleFournisseur
                ->setFournisseur($fournisseur)
                ->setReference($data['reference'])
                ->setReferenceArticle($referenceArticle);

            $em = $this->getDoctrine()->getManager();
            $em->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer", name="article_fournisseur_delete",  options={"expose"=true}, methods={"GET", "POST"})
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $articleFournisseur = $this->articleFournisseurRepository->find(intval($data['article-fournisseur']));

            $em = $this->getDoctrine()->getManager();
            $em->remove($articleFournisseur);
            $em->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer_verif", name="article_fournisseur_can_delete",  options={"expose"=true}, methods={"GET", "POST"})
     */
    public function deleteVerif(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $articleFournisseur = $this->articleFournisseurRepository->find(intval($data['articleFournisseur']));
            if (count($articleFournisseur->getArticles()) > 0) {
                return new JsonResponse(false);
            }
            return new JsonResponse(true);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @param ArticleFournisseur $articleFournisseur
     * @return array
     */
    public function dataRowArticleFournisseur(ArticleFournisseur $articleFournisseur): array
    {
        $articleFournisseurId = $articleFournisseur->getId();

        $url['edit'] = $this->generateUrl('article_fournisseur_edit', ['id' => $articleFournisseurId]);
        $url['delete'] = $this->generateUrl('article_fournisseur_delete', ['id' => $articleFournisseurId]);

        $row = [
            'Fournisseur' => $articleFournisseur->getFournisseur()->getNom(),
            'Référence' => $articleFournisseur->getReference(),
            'Article de référence' => $articleFournisseur->getReferenceArticle()->getLibelle(),
            'Actions' => $this->renderView('article_fournisseur/datatableRowActions.html.twig', [
                'url' => $url,
                'id' => $articleFournisseurId
            ]),
        ];

        return $row;
    }

//    /**
//     * @Route("/autocomplete-fournisseur-by-ref/{referenceArticle}", name="get_article_fournisseur_autocomplete", options={"expose"=true})
//     * @param Request $request
//     * @param String $referenceArticle
//     * @return JsonResponse
//     * @throws NonUniqueResultException
//     */
//    public function getArticleFournisseurByRef(Request $request, String $referenceArticle)
//    {
//        if ($request->isXmlHttpRequest()) {
//            $search = $request->query->get('term');
//            $reference = $this->referenceArticleRepository->findOneByReference($referenceArticle);
//            $articleFournisseur = $this->articleFournisseurRepository->getIdAndLibelleBySearchAndRef($search, $reference);
//
//            return new JsonResponse(['results' => $articleFournisseur]);
//        }
//        throw new NotFoundHttpException("404");
//    }
}
