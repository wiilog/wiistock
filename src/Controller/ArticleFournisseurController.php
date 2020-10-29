<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\ArticleFournisseur;
use App\Entity\Fournisseur;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Service\ArticleFournisseurService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
    public function api(Request $request,
                        EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_ARTI_FOUR)) {
                return $this->redirectToRoute('access_denied');
            }

            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);

            $articlesFournisseurs = $articleFournisseurRepository->findByParams($request->request);
            $rows = [];
            foreach ($articlesFournisseurs['data'] as $articleFournisseur) {
                $rows[] = $this->dataRowArticleFournisseur($articleFournisseur);
            }

            $data['data'] = $rows;
            $data['recordsTotal'] = $articlesFournisseurs['total'];
            $data['recordsFiltered'] = $articlesFournisseurs['count'];

            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/creer", name="article_fournisseur_new", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param ArticleFournisseurService $articleFournisseurService
     * @return Response
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager,
                        ArticleFournisseurService $articleFournisseurService): Response
    {
        $dataResponse = [];
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            try {
                $articleFournisseur = $articleFournisseurService->createArticleFournisseur($data);
                $entityManager->persist($articleFournisseur);
                $entityManager->flush();

                $dataResponse['success'] = true;
                $dataResponse['msg'] = 'L\'article fournisseur ' .$data['label']. ' a bien été créé.';
            }
            catch (\Exception $exception) {
                if ($exception->getMessage() === ArticleFournisseurService::ERROR_REFERENCE_ALREADY_EXISTS) {
                    $dataResponse['message'] = 'La référence existe déjà.';
                } else {
                    $dataResponse['message'] = 'Une erreur est survenue.';
                }
                $dataResponse['success'] = false;
            }
        }
        return new JsonResponse($dataResponse);
    }

    /**
     * @Route("/api-modifier", name="article_fournisseur_api_edit", options={"expose"=true},  methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function displayEdit(Request $request,
                                EntityManagerInterface $entityManager): Response
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
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="article_fournisseur_edit",  options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function edit(Request $request,
                         EntityManagerInterface $entityManager): Response
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
                ->setReferenceArticle($referenceArticle)
                ->setLabel($data['label'] ?: null);

            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'L\'article fournisseur '.$articleFournisseur->getLabel(). ' a bien été modifié.'
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="article_fournisseur_delete",  options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
            $articleFournisseur = $articleFournisseurRepository->find(intval($data['article-fournisseur']));

            if($articleFournisseur->getArticles()->isEmpty()) {
                return $this->json([
                    "success" => false,
                    "msg" => "Cet article fournisseur est lié à un ou plusieurs articles et ne peut pas être supprimé"
                ]);
            }

            $articleFournisseur->getLabel();
            $entityManager->remove($articleFournisseur);
            $entityManager->flush();
            return new JsonResponse([
                'success' => true,
                'msg' => 'L\'article fournisseur ' .$articleFournisseur->getLabel(). ' a bien été supprimé.'
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer_verif", name="article_fournisseur_can_delete",  options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function deleteVerif(Request $request,
                                EntityManagerInterface $entityManager): Response
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
        throw new BadRequestHttpException();
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
            'label' => $articleFournisseur ->getLabel(),
            'Code Fournisseur' => $articleFournisseur->getFournisseur()->getCodeReference(),
            'Référence' => $articleFournisseur->getReference(),
            'Article de référence' => $articleFournisseur->getReferenceArticle()->getReference(),
            'Actions' => $this->renderView('article_fournisseur/datatableRowActions.html.twig', [
                'url' => $url,
                'id' => $articleFournisseurId
            ]),
        ];
        return $row;
    }

    /**
     * @Route("/autocomplete", name="get_article_fournisseur_autocomplete", options={"expose"=true})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    public function getArticleFournisseur(Request $request,
                                          EntityManagerInterface $entityManager)
    {
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('term');
            $referenceArticle = $request->query->get('referenceArticle');

            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
            $articleFournisseurs = $articleFournisseurRepository->getIdAndLibelleBySearch($search, $referenceArticle);

            return new JsonResponse(['results' => $articleFournisseurs]);
        }
        throw new BadRequestHttpException();
    }
}
