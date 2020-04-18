<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Demande;
use App\Entity\Livraison;
use App\Entity\Menu;
use App\Entity\Preparation;
use App\Entity\Statut;
use App\Entity\Type;
use App\Service\LivraisonService;
use App\Service\LivraisonsManagerService;
use App\Service\PreparationsManagerService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Error\LoaderError as Twig_Error_Loader;
use Twig\Error\RuntimeError as Twig_Error_Runtime;
use Twig\Error\SyntaxError as Twig_Error_Syntax;


/**
 * @Route("/livraison")
 */
class LivraisonController extends AbstractController
{
    /**
     * @Route("/liste/{demandId}", name="livraison_index", methods={"GET", "POST"})
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @param string|null $demandId
     * @return Response
     */
    public function index(UserService $userService,
                          EntityManagerInterface $entityManager,
                          string $demandId = null): Response {
        if (!$userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR)) {
            return $this->redirectToRoute('access_denied');
        }

        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $demandeRepository = $entityManager->getRepository(Demande::class);

        $filterDemand = $demandId
            ? $demandeRepository->find($demandId)
            : null;

        return $this->render('livraison/index.html.twig', [
            'filterDemandId' => isset($filterDemand) ? $demandId : null,
            'filterDemandValue' => isset($filterDemand) ? $filterDemand->getNumero() : null,
            'filtersDisabled' => isset($filterDemand),
            'displayDemandFilter' => true,
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::ORDRE_LIVRAISON),
            'types' => $typeRepository->findByCategoryLabel(CategoryType::DEMANDE_LIVRAISON),
        ]);
    }

    /**
     * @Route("/finir/{id}", name="livraison_finish", options={"expose"=true}, methods={"GET", "POST"})
     * @param Livraison $livraison
     * @param LivraisonsManagerService $livraisonsManager
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     * @throws Exception
     */
    public function finish(Livraison $livraison,
                           LivraisonsManagerService $livraisonsManager,
                           UserService $userService,
                           EntityManagerInterface $entityManager): Response
    {
        if (!$userService->hasRightFunction(Menu::ORDRE, Action::EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        if ($livraison->getStatut()->getnom() === Livraison::STATUT_A_TRAITER) {
            $dateEnd = new DateTime('now', new \DateTimeZone('Europe/Paris'));
            $livraisonsManager->finishLivraison(
                $this->getUser(),
                $livraison,
                $dateEnd,
                $livraison->getDemande()->getDestination()
            );
            $entityManager->flush();
        }
        return $this->redirectToRoute('livraison_show', [
            'id' => $livraison->getId()
        ]);
    }

    /**
     * @Route("/api", name="livraison_api", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param LivraisonService $livraisonService
     * @param UserService $userService
     * @return Response
     * @throws NonUniqueResultException
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function api(Request $request,
                        LivraisonService $livraisonService,
                        UserService $userService): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR)) {
                return $this->redirectToRoute('access_denied');
            }

            $filterDemandId = $request->request->get('filterDemand');
            $data = $livraisonService->getDataForDatatable($request->request, $filterDemandId);
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-article/{id}", name="livraison_article_api", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param UserService $userService
     * @param Livraison $livraison
     * @return Response
     */
    public function apiArticle(Request $request,
                               UserService $userService,
                               Livraison $livraison): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR)) {
                return $this->redirectToRoute('access_denied');
            }

            $preparation = $livraison->getPreparation();
            $data = [];
            if ($preparation) {
                $rows = [];
                foreach ($preparation->getArticles() as $article) {
                    if ($article->getQuantite() !== 0 && $article->getQuantitePrelevee() !== 0) {
                        $rows[] = [
                            "Référence" => $article->getArticleFournisseur()->getReferenceArticle() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : '',
                            "Libellé" => $article->getLabel() ? $article->getLabel() : '',
                            "Emplacement" => $article->getEmplacement() ? $article->getEmplacement()->getLabel() : '',
                            "Quantité" => $article->getQuantitePrelevee(),
                            "Actions" => $this->renderView('livraison/datatableLivraisonListeRow.html.twig', [
                                'id' => $article->getId(),
                            ])
                        ];
                    }
                }

                foreach ($preparation->getLigneArticlePreparations() as $ligne) {
                    if ($ligne->getQuantitePrelevee() > 0) {
                        $rows[] = [
                            "Référence" => $ligne->getReference()->getReference(),
                            "Libellé" => $ligne->getReference()->getLibelle(),
                            "Emplacement" => $ligne->getReference()->getEmplacement() ? $ligne->getReference()->getEmplacement()->getLabel() : '',
                            "Quantité" => $ligne->getQuantitePrelevee(),
                            "Actions" => $this->renderView('livraison/datatableLivraisonListeRow.html.twig', [
                                'refArticleId' => $ligne->getReference()->getId(),
                            ])
                        ];
                    }
                }

                $data['data'] = $rows;
            } else {
                $data = false; //TODO gérer retour message erreur
            }
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/voir/{id}", name="livraison_show", methods={"GET","POST"})
     * @param Livraison $livraison
     * @param EntityManagerInterface $entityManager
     * @param UserService $userService
     * @return Response
     */
    public function show(Livraison $livraison,
                         EntityManagerInterface $entityManager,
                         UserService $userService): Response
    {
        if (!$userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR)) {
            return $this->redirectToRoute('access_denied');
        }

        $preparationRepository = $entityManager->getRepository(Preparation::class);

        return $this->render('livraison/show.html.twig', [
            'demande' => $livraison->getDemande(),
            'livraison' => $livraison,
            'preparation' => $preparationRepository->find($livraison->getPreparation()->getId()),
            'finished' => ($livraison->getStatut()->getNom() === Livraison::STATUT_LIVRE || $livraison->getStatut()->getNom() === Livraison::STATUT_INCOMPLETE)
        ]);
    }

    /**
     * @Route("/supprimer/{id}", name="livraison_delete", options={"expose"=true},methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param PreparationsManagerService $preparationsManager
     * @param UserService $userService
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */

    public function delete(Request $request,
                           EntityManagerInterface $entityManager,
                           PreparationsManagerService $preparationsManager,
                           UserService $userService): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$userService->hasRightFunction(Menu::ORDRE, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $livraisonRepository = $entityManager->getRepository(Livraison::class);

            $livraison = $livraisonRepository->find($data['livraison']);

            $statutP = $statutRepository->findOneByCategorieNameAndStatutCode(Preparation::CATEGORIE, Preparation::STATUT_A_TRAITER);

            $preparation = $livraison->getpreparation();
            $preparation->setStatut($statutP);
            foreach ($preparation->getArticles() as $article) {
                $article->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_ACTIF));
            }
            $preparation->setLivraison(null);
            $entityManager->remove($livraison);
            $entityManager->flush();

            $preparationsManager->updateRefArticlesQuantities($preparation, false);

            $data = [
                'redirect' => $this->generateUrl('preparation_show', [
                    'id' => $preparation->getId()
                ]),
            ];

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

	/**
	 * @Route("/infos", name="get_ordres_livraison_for_csv", options={"expose"=true}, methods={"GET","POST"})
	 * @param Request $request
	 * @param EntityManagerInterface $entityManager
	 * @return Response
	 */
    public function getOrdreLivraisonIntels(Request $request,
                                            EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $dateMin = $data['dateMin'] . ' 00:00:00';
            $dateMax = $data['dateMax'] . ' 23:59:59';

            $dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
            $dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);

            $livraisonRepository = $entityManager->getRepository(Livraison::class);

            $livraisons = $livraisonRepository->findByDates($dateTimeMin, $dateTimeMax);

            $headers = [
                'numéro',
                'statut',
                'date création',
                'opérateur',
                'type',
                'référence',
                'libellé',
                'emplacement',
                'quantité à livrer',
                'quantité en stock',
                'code-barre'
            ];

            $data = [];
            $data[] = $headers;

            foreach ($livraisons as $livraison) {
                $this->buildInfos($livraison, $data);
            }
            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }


    private function buildInfos(Livraison $livraison, &$data)
    {
        $demande = $livraison->getDemande();
        if (isset($demande)) {
            $dataLivraison =
                [
                    $livraison->getNumero() ?? '',
                    $livraison->getStatut() ? $livraison->getStatut()->getNom() : '',
                    $livraison->getDate() ? $livraison->getDate()->format('d/m/Y h:i') : '',
                    $livraison->getUtilisateur() ? $livraison->getUtilisateur()->getUsername() : '',
                    $demande ? $demande->getType() ? $demande->getType()->getLabel() : '' : '',
                ];

            foreach ($demande->getLigneArticle() as $ligneArticle) {
                $referenceArticle = $ligneArticle->getReference();

                $data[] = array_merge($dataLivraison, [
                    $referenceArticle->getReference() ?? '',
                    $referenceArticle->getLibelle() ?? '',
                    $referenceArticle->getEmplacement() ? $referenceArticle->getEmplacement()->getLabel() : '',
                    $ligneArticle->getQuantite() ?? 0,
                    $referenceArticle->getBarCode(),
                ]);
            }

            foreach ($demande->getArticles() as $article) {
                $articleFournisseur = $article->getArticleFournisseur();
                $referenceArticle = $articleFournisseur ? $articleFournisseur->getReferenceArticle() : null;
                $reference = $referenceArticle ? $referenceArticle->getReference() : '';

                $data[] = array_merge($dataLivraison, [
                    $reference,
                    $article->getLabel() ?? '',
                    $article->getEmplacement() ? $article->getEmplacement()->getLabel() : '',
                    $article->getQuantite() ?? 0,
                    $article->getBarCode(),
                ]);
            }
        }
    }
}
