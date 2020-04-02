<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\ArticleFournisseur;
use App\Entity\Fournisseur;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
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
     * @var UserService
     */
    private $userService;


    public function __construct(UserService $userService)
    {
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
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function api(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_ARTI_FOUR)) {
                return $this->redirectToRoute('access_denied');
            }

            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);

            $articlesFournisseurs = $articleFournisseurRepository->findByParams($request->request);
            $rows = [];
            foreach ($articlesFournisseurs as $articleFournisseur) {
                $rows[] = $this->dataRowArticleFournisseur($articleFournisseur);
            }

            $data['data'] = $rows;
            $data['recordsTotal'] = (int)$articleFournisseurRepository->countAll();
            $data['recordsFiltered'] = (int)$articleFournisseurRepository->countAll();

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="article_fournisseur_new", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $fournisseur = $fournisseurRepository->find(intval($data['fournisseur']));
            $referenceArticle = $referenceArticleRepository->find(intval($data['article-reference']));

            $articleFournisseur = new ArticleFournisseur();
            $articleFournisseur
                ->setFournisseur($fournisseur)
                ->setReference($data['reference'])
                ->setReferenceArticle($referenceArticle);

            $entityManager->persist($articleFournisseur);
            $entityManager->flush();
            return new JsonResponse($data);
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="article_fournisseur_api_edit", options={"expose"=true},  methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function displayEdit(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);

            $articleFournisseur = $articleFournisseurRepository->find(intval($data['id']));
            $json = $this->renderView('article_fournisseur/modalEditArticleFournisseurContent.html.twig', [
                'articleFournisseur' => $articleFournisseur,
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="article_fournisseur_edit",  options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $articleFournisseur = $articleFournisseurRepository->find(intval($data['id']));
            $fournisseur = $fournisseurRepository->find(intval($data['fournisseur']));
            $referenceArticle = $referenceArticleRepository->find(intval($data['article-reference']));

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
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
            $articleFournisseur = $articleFournisseurRepository->find(intval($data['article-fournisseur']));

            $entityManager->remove($articleFournisseur);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer_verif", name="article_fournisseur_can_delete",  options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function deleteVerif(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
            $articleFournisseur = $articleFournisseurRepository->find(intval($data['articleFournisseur']));

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
            'Code Fournisseur' => $articleFournisseur->getFournisseur()->getNom(),
            'Référence' => $articleFournisseur->getReference(),
            'Article de référence' => $articleFournisseur->getReferenceArticle()->getLibelle(),
            'Actions' => $this->renderView('article_fournisseur/datatableRowActions.html.twig', [
                'url' => $url,
                'id' => $articleFournisseurId
            ]),
        ];

        return $row;
    }
}
