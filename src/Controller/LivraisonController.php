<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\Demande;
use App\Entity\Livraison;
use App\Entity\Menu;
use App\Entity\Preparation;
use App\Repository\ArticleRepository;
use App\Repository\LivraisonRepository;
use App\Repository\PreparationRepository;
use App\Service\MailerService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


use App\Repository\DemandeRepository;
use App\Repository\StatutRepository;
use App\Repository\EmplacementRepository;
use App\Repository\LigneArticleRepository;
use App\Repository\ReferenceArticleRepository;


use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/livraison")
 */
class LivraisonController extends AbstractController
{
    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var PreparationRepository
     */
    private $preparationRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

    /**
     * @var LivraisonRepository
     */
    private $livraisonRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var LigneArticleRepository
     */
    private $ligneArticleRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var MailerService
     */
    private $mailerService;


    public function __construct(ReferenceArticleRepository $referenceArticleRepository, PreparationRepository $preparationRepository, LigneArticleRepository $ligneArticleRepository, EmplacementRepository $emplacementRepository, DemandeRepository $demandeRepository, LivraisonRepository $livraisonRepository, StatutRepository $statutRepository, UserService $userService, MailerService $mailerService, ArticleRepository $articleRepository)
    {
        $this->emplacementRepository = $emplacementRepository;
        $this->demandeRepository = $demandeRepository;
        $this->livraisonRepository = $livraisonRepository;
        $this->statutRepository = $statutRepository;
        $this->preparationRepository = $preparationRepository;
        $this->ligneArticleRepository = $ligneArticleRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->userService = $userService;
        $this->mailerService = $mailerService;
    }

    /**
     *  @Route("/creer/{id}", name="livraison_new", methods={"GET","POST"} )
     */
    public function new($id): Response
    {
        if (!$this->userService->hasRightFunction(Menu::LIVRAISON, Action::CREATE_EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        $preparation = $this->preparationRepository->find($id);

        $demande1 = $preparation->getDemandes();
        $demande = $demande1[0];
        $statut = $this->statutRepository->findOneByCategorieAndStatut(Livraison::CATEGORIE, Livraison::STATUT_A_TRAITER);
        $livraison = new Livraison();
        $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        $livraison
            ->setDate($date)
            ->setNumero('L-' . $date->format('YmdHis'))
            ->setStatut($statut);
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($livraison);
        $preparation
            ->addLivraison($livraison)
            ->setUtilisateur($this->getUser())
            ->setStatut($this->statutRepository->findOneByCategorieAndStatut(Preparation::CATEGORIE, Preparation::STATUT_PREPARE));

        $demande
            ->setStatut($this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_PREPARE))
            ->setLivraison($livraison);
        $entityManager->flush();
        $livraison = $preparation->getLivraisons()->toArray();

        return $this->redirectToRoute('livraison_show', [
            'id' => $livraison[0]->getId(),
        ]);
    }

    /**
     * @Route("/", name="livraison_index", methods={"GET", "POST"})
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::LIVRAISON, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('livraison/index.html.twig');
    }

    /**
     * @Route("/finir/{id}", name="livraison_finish", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function finish(Livraison $livraison): Response
    {
        if (!$this->userService->hasRightFunction(Menu::LIVRAISON, Action::CREATE_EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        if ($livraison->getStatut()->getnom() ===  Livraison::STATUT_A_TRAITER) {
            $livraison
                ->setStatut($this->statutRepository->findOneByCategorieAndStatut(Livraison::CATEGORIE, Livraison::STATUT_LIVRE))
                ->setUtilisateur($this->getUser())
                ->setDateFin(new \DateTime('now', new \DateTimeZone('Europe/Paris')));

            $demande = $this->demandeRepository->getByLivraison($livraison->getId());
            /** @var Demande $demande */
            $statutLivre = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_LIVRE);
            $demande->setStatut($statutLivre);

            $this->mailerService->sendMail(
                'FOLLOW GT // Livraison effectuée',
                $this->renderView('mails/mailLivraisonDone.html.twig', ['livraison' => $demande]),
                $demande->getUtilisateur()->getEmail()
            );

            $ligneArticles = $this->ligneArticleRepository->getByDemande($demande);

            foreach ($ligneArticles as $ligneArticle) {
                $refArticle = $ligneArticle->getReference();
                $refArticle->setQuantiteStock($refArticle->getQuantiteStock() - $ligneArticle->getQuantite());
            }

            $preparation = $livraison->getPreparation();
            $articles = $preparation->getArticle();
            foreach ($articles as $article) {
                $article->setStatut($this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_INACTIF));
            }
        }
        $this->getDoctrine()->getManager()->flush();
        return $this->redirectToRoute('livraison_show', [
            'id' => $livraison->getId()
        ]);
    }

    /**
     * @Route("/api", name="livraison_api", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) { //Si la requête est de type Xml
            if (!$this->userService->hasRightFunction(Menu::LIVRAISON, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $livraisons = $this->livraisonRepository->findAll();
            $rows = [];
            foreach ($livraisons as $livraison) {
                $url['show'] = $this->generateUrl('livraison_show', ['id' => $livraison->getId()]);
                $rows[] = [
                    'id' => ($livraison->getId() ? $livraison->getId() : ''),
                    'Numéro' => ($livraison->getNumero() ? $livraison->getNumero() : ''),
                    'Date' => ($livraison->getDate() ? $livraison->getDate()->format('d-m-Y') : ''),
                    'Statut' => ($livraison->getStatut() ? $livraison->getStatut()->getNom() : ''),
                    'Opérateur' => ($livraison->getUtilisateur() ? $livraison->getUtilisateur()->getUsername() : ''),
                    'Actions' => $this->renderView('livraison/datatableLivraisonRow.html.twig', ['url' => $url])
                ];
            }

            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-article/{id}", name="livraison_article_api", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function apiArticle(Request $request, Livraison $livraison): Response
    {
        if ($request->isXmlHttpRequest()) {
                if (!$this->userService->hasRightFunction(Menu::LIVRAISON, Action::LIST)) {
                    return $this->redirectToRoute('access_denied');
                }

                $demande = $this->demandeRepository->getByLivraison($livraison->getId());
                if ($demande) {

                    $rows = [];

                    $ligneArticle = $this->ligneArticleRepository->getByDemande($demande->getId());
                    foreach ($ligneArticle as $article) {
                        $rows[] = [
                            "Référence CEA" => ($article->getReference() ? $article->getReference()->getReference() : ' '),
                            "Libellé" => ($article->getReference() ? $article->getReference()->getLibelle() : ' '),
                            "Quantité" => ($article->getQuantite() ? $article->getQuantite() : ' '),
                        ];
                    }

                    $articles = $this->articleRepository->getByDemande($demande);
                    foreach ($articles as $article) {
                        /** @var Article $article */
                        $rows[] = [
                            "Référence CEA" => $article->getArticleFournisseur()->getReferenceArticle() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : '',
                            "Libellé" => $article->getLabel() ? $article->getLabel() : '',
                            "Quantité" => $article->getQuantite(),
                        ];
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
     */
    public function show(Livraison $livraison): Response
    {
        if (!$this->userService->hasRightFunction(Menu::LIVRAISON, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('livraison/show.html.twig', [
            'demande' => $this->demandeRepository->findOneByLivraison($livraison),
            'livraison' => $livraison,
            'preparation' => $this->preparationRepository->find($livraison->getPreparation()->getId()),
            'finished' => ($livraison->getStatut()->getNom() === Livraison::STATUT_LIVRE)
        ]);
    }

    /**
     * @Route("/supprimer/{id}", name="livraison_delete", options={"expose"=true},methods={"GET","POST"})
     */

    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            if (!$this->userService->hasRightFunction(Menu::PREPA, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            $livraison = $this->livraisonRepository->find($data['livraison']);

            $statutP = $this->statutRepository->findOneByCategorieAndStatut(Preparation::CATEGORIE, Preparation::STATUT_A_TRAITER);

            $preparation = $livraison->getpreparation();
            $preparation->setStatut($statutP);

            $demandes = $livraison->getDemande();
            foreach ($demandes as $demande) {
                $demande->setLivraison(null);
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($livraison);
            $entityManager->flush();
            $data = [
                'redirect' => $this->generateUrl('preparation_show', [
                    'id' => $livraison->getPreparation()->getId()
                ]),
            ];

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }
}
