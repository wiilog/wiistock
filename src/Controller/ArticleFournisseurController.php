<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\ArticleFournisseur;
use App\Entity\Fournisseur;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Helper\AdvancedSearchHelper;
use App\Service\ArticleFournisseurService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;


#[Route("/article-fournisseur")]
class ArticleFournisseurController extends AbstractController {

    #[Route("/", name: "article_fournisseur_index")]
    #[HasPermission([Menu::STOCK, Action::DISPLAY_ARTI_FOUR])]
    public function index():Response {
        return $this->render('article_fournisseur/index.html.twig');
    }

    #[Route("/api", name: "article_fournisseur_api", options: ["expose" => true], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::STOCK, Action::DISPLAY_ARTI_FOUR], mode: HasPermission::IN_JSON)]
    public function api(Request $request, EntityManagerInterface $entityManager): JsonResponse {
        $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);

        /** @var Utilisateur $user */
        $user = $this->getUser();

        $articlesFournisseurs = $articleFournisseurRepository->findByParams($request->request, $user);
        $searchParts = $articlesFournisseurs["searchParts"];
        $rows = [];
        foreach ($articlesFournisseurs['data'] as $articleFournisseur) {
            $rows[] = $this->dataRowArticleFournisseur($articleFournisseur, $searchParts);
        }

        $data['data'] = $rows;
        $data['recordsTotal'] = $articlesFournisseurs['total'];
        $data['recordsFiltered'] = $articlesFournisseurs['count'];

        return new JsonResponse($data);
    }

    #[Route("/creer", name: "article_fournisseur_new", options: ["expose" => true], methods: [self::POST, self::GET], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::STOCK, Action::CREATE], mode: HasPermission::IN_JSON)]
    public function new(Request                     $request,
                        EntityManagerInterface      $entityManager,
                        ArticleFournisseurService   $articleFournisseurService): JsonResponse {
        $dataResponse = [];
        if ($data = json_decode($request->getContent(), true)) {
            try {
                $articleFournisseur = $articleFournisseurService->createArticleFournisseur($data);
                $entityManager->persist($articleFournisseur);
                $entityManager->flush();

                $dataResponse['success'] = true;
                $dataResponse['msg'] = 'L\'article fournisseur ' . $data['label'] . ' a bien été créé.';
            } catch (\Exception $exception) {
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

    #[Route("/api-modifier", name: "article_fournisseur_api_edit", options: ["expose" => true], methods: [self::POST, self::GET], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::STOCK, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function displayEdit(Request $request,
                                EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);

            $articleFournisseur = $articleFournisseurRepository->find(intval($data['id']));
            $json = $this->renderView('article_fournisseur/modalEditArticleFournisseurContent.html.twig', [
                'articleFournisseur' => $articleFournisseur,
            ]);
            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    #[Route("/modifier", name: "article_fournisseur_edit", options: ["expose" => true], methods: [self::POST, self::GET], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::STOCK, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function edit(Request                $request,
                         EntityManagerInterface $entityManager): JsonResponse {
        if ($data = json_decode($request->getContent(), true)) {
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $articleFournisseur = $articleFournisseurRepository->find(intval($data['id']));
            $fournisseur = $fournisseurRepository->find(intval($data['fournisseur']));
            $referenceArticle = $referenceArticleRepository->find(intval($data['article-reference']));

            $articleFournisseur
                ->setFournisseur($fournisseur)
                ->setReferenceArticle($referenceArticle)
                ->setLabel($data['label'] ? trim($data['label']) : null);

            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'L\'article fournisseur ' . $articleFournisseur->getLabel() . ' a bien été modifié.'
            ]);
        }
        throw new BadRequestHttpException();
    }

    #[Route("/supprimer", name: "article_fournisseur_delete", options: ["expose" => true], methods: [self::POST, self::GET], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::STOCK, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function delete(Request                  $request,
                           EntityManagerInterface   $entityManager): JsonResponse {
        if ($data = json_decode($request->getContent(), true)) {
            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
            $articleFournisseur = $articleFournisseurRepository->find(intval($data['article-fournisseur']));

            if (!$articleFournisseur->getArticles()->isEmpty()) {
                return $this->json([
                    "success" => false,
                    "msg" => "Cet article fournisseur est lié à un ou plusieurs articles et ne peut pas être supprimé"
                ]);
            }

            $entityManager->remove($articleFournisseur);
            $entityManager->flush();
            return new JsonResponse([
                'success' => true,
                'msg' => 'L\'article fournisseur ' . $articleFournisseur->getLabel() . ' a bien été supprimé.'
            ]);
        }
        throw new BadRequestHttpException();
    }

    #[Route("/supprimer_verif", name: "article_fournisseur_can_delete", options: ["expose" => true], methods: [self::POST, self::GET], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::STOCK, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function deleteVerif(Request                 $request,
                                EntityManagerInterface  $entityManager): JsonResponse {
        if ($data = json_decode($request->getContent(), true)) {
            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
            $articleFournisseur = $articleFournisseurRepository->find(intval($data['articleFournisseur']));

            if (count($articleFournisseur->getArticles()) > 0) {
                return new JsonResponse(false);
            }
            return new JsonResponse(true);
        }
        throw new BadRequestHttpException();
    }

    public function dataRowArticleFournisseur(ArticleFournisseur $articleFournisseur, array $searchParts): array {
        $articleFournisseurId = $articleFournisseur->getId();

        $url['edit'] = $this->generateUrl('article_fournisseur_edit', ['id' => $articleFournisseurId]);
        $url['delete'] = $this->generateUrl('article_fournisseur_delete', ['id' => $articleFournisseurId]);

        $row = [
            'label' => $articleFournisseur->getLabel(),
            'Code Fournisseur' => $articleFournisseur->getFournisseur()->getCodeReference(),
            'Référence' => $articleFournisseur->getReference(),
            'Article de référence' => $articleFournisseur->getReferenceArticle() ? $articleFournisseur->getReferenceArticle()->getReference() : '',
            'Actions' => $this->renderView('article_fournisseur/datatableRowActions.html.twig', [
                'url' => $url,
                'id' => $articleFournisseurId
            ]),
        ];

        return AdvancedSearchHelper::highlight($row, $searchParts);
    }

    #[Route("/autocomplete", name: "get_article_fournisseur_autocomplete", options: ["expose" => true], methods: [self::POST, self::GET], condition: "request.isXmlHttpRequest()")]
    public function getArticleFournisseur(Request                   $request,
                                          EntityManagerInterface    $entityManager): JsonResponse {
        $search = $request->query->get('term');
        $referenceArticle = $request->query->get('referenceArticle');

        $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
        $articleFournisseurs = $articleFournisseurRepository->getIdAndLibelleBySearch($search, $referenceArticle);

        return new JsonResponse(['results' => $articleFournisseurs]);
    }

}
