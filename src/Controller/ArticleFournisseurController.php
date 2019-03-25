<?php

namespace App\Controller;

use App\Entity\ArticleFournisseur;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\FournisseurRepository;
use App\Repository\ReferenceArticleRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
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


    public function __construct(ArticleFournisseurRepository $articleFournisseurRepository, FournisseurRepository $fournisseurRepository, ReferenceArticleRepository $referenceArticleRepository)
    {
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
    }

    /**
     * @Route("/", name="article_fournisseur_index")
     */
    public function index()
    {
        return $this->render('article-fournisseur/index.html.twig', [
            'fournisseurs' => $this->fournisseurRepository->findAll(),
            'referencesArticles' => $this->referenceArticleRepository->findAll()
        ]);
    }

    /**
     * @Route("/api", name="article_fournisseur_api", options={"expose"=true}, methods="POST")
     */
    public function api(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $articleFournisseurs = $this->articleFournisseurRepository->findAll();
            $rows = [];
            foreach ($articleFournisseurs as $articleFournisseur) {
                $articleFournisseurId = $articleFournisseur->getId();
                $url['edit'] = $this->generateUrl('article_fournisseur_edit', ['id' => $articleFournisseurId]);
                $url['delete'] = $this->generateUrl('article_fournisseur_delete', ['id' => $articleFournisseurId]);
                $rows[] = [
                    'Fournisseur' => $articleFournisseur->getFournisseur()->getNom(),
                    'Référence' => $articleFournisseur->getReference(),
                    'Article de référence' => $articleFournisseur->getReferenceArticle()->getLibelle(),
                    'Actions' => $this->renderView('article-fournisseur/datatableRowActions.html.twig', [
                        'url' => $url,
                        'id'=>$articleFournisseurId
                    ]),
                ];
            }

            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="article_fournisseur_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
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
     * @Route("/afficher-modifier", name="article_fournisseur_display_edit", options={"expose"=true},  methods="GET|POST")
     */
    public function displayEdit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $articleFournisseur = $this->articleFournisseurRepository->find(intval($data));

            $json = $this->renderView('article-fournisseur/modalEditArticleFournisseurContent.html.twig', [
                'articleFournisseur' => $articleFournisseur,
                'fournisseurs' => $this->fournisseurRepository->findAll(),
                'referencesArticles' => $this->referenceArticleRepository->findAll()
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
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
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
    public function delete(Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $articleFournisseur= $this->articleFournisseurRepository->find(intval($data['article-fournisseur']));

            $em = $this->getDoctrine()->getManager();
            $em->remove($articleFournisseur);
            $em->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

}
