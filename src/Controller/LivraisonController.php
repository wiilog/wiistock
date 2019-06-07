<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategoryType;
use App\Entity\Demande;
use App\Entity\Livraison;
use App\Entity\Menu;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
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
use Symfony\Component\Validator\Constraints\DateTime;
use App\Repository\UtilisateurRepository;
use App\Repository\ChampsLibreRepository;
use Proxies\__CG__\App\Entity\CategorieCL;
use App\Repository\ValeurChampsLibreRepository;
use App\Repository\CategorieCLRepository;
use App\Repository\TypeRepository;

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
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    /**
     * @var ChampsLibreRepository
     */
    private $champsLibreRepository;

    /**
     * @var ValeurChampsLibreRepository
     */
    private $valeurChampsLibreRepository;

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
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

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


    public function __construct(CategorieCLRepository $categorieCLRepository, TypeRepository $typeRepository, ValeurChampsLibreRepository $valeurChampsLibreRepository, ChampsLibreRepository $champsLibreRepository, UtilisateurRepository $utilisateurRepository, ReferenceArticleRepository $referenceArticleRepository, PreparationRepository $preparationRepository, LigneArticleRepository $ligneArticleRepository, EmplacementRepository $emplacementRepository, DemandeRepository $demandeRepository, LivraisonRepository $livraisonRepository, StatutRepository $statutRepository, UserService $userService, MailerService $mailerService, ArticleRepository $articleRepository)
    {
        $this->typeRepository = $typeRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->valeurChampsLibreRepository = $valeurChampsLibreRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->utilisateurRepository = $utilisateurRepository;
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
     * @Route("/creer/{id}", name="livraison_new", methods={"GET","POST"} )
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

        if ($livraison->getStatut()->getnom() === Livraison::STATUT_A_TRAITER) {
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
            $data = [];
            if ($demande) {
                $rows = [];
                $articles = $this->articleRepository->getByDemande($demande);
                foreach ($articles as $article) {
                    /** @var Article $article */
                    $rows[] = [
                        "Référence CEA" => $article->getArticleFournisseur()->getReferenceArticle() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : '',
                        "Libellé" => $article->getLabel() ? $article->getLabel() : '',
                        "Emplacement" => $article->getEmplacement() ? $article->getEmplacement()->getLabel() : '',
                        "Quantité" => $article->getQuantite(),
                        "Actions" => $this->renderView('livraison/datatableLivraisonListeRow.html.twig', [
                            'id' => $article->getId(),
                        ])

                    ];
                }
                $lignes = $demande->getLigneArticle();

                foreach ($lignes as $ligne) {
                    $rows[] = [
                        "Référence CEA" => $ligne->getReference()->getReference(),
                        "Libellé" => $ligne->getReference()->getLibelle(),
                        "Emplacement" => $ligne->getReference()->getEmplacement() ? $ligne->getReference()->getEmplacement()->getLabel() : '',
                        "Quantité" => $ligne->getQuantite(),
                        "Actions" => $this->renderView('livraison/datatableLivraisonListeRow.html.twig', [
                            'refArticleId' => $ligne->getReference()->getId(),
                        ])
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
            if (!$this->userService->hasRightFunction(Menu::LIVRAISON, Action::CREATE_EDIT)) {
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

    /**
     * @Route("/livraison-infos", name="get_livraisons_for_csv", options={"expose"=true}, methods={"GET","POST"})
     */
    public function getLivraisonIntels(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $dateMin = $data['dateMin'] . ' 00:00:00';
            $dateMax = $data['dateMax'] . ' 23:59:59';
            $livraisons = $this->demandeRepository->findByDates($dateMin, $dateMax);
            $data = [];
            $headers = ['demandeur', 'statut', 'destination', 'commentaire', 'dateDemande', 'dateValidation', 'reference', 'referenceArticle', 'libelleArticle', 'quantite'];
            $cls = $this->champsLibreRepository->findAll();
            foreach ($cls as $champLibre) {
                $headers[] = $champLibre->getLabel();
            }
            $data[] = $headers;
            $listTypes = $this->typeRepository->getIdAndLabelByCategoryLabel(CategoryType::ARTICLES_ET_REF_CEA);
            foreach ($livraisons as $livraison) {
                foreach ($livraison->getLigneArticle() as $ligneArticle) {
                    $livraisonData = [];
                    $livraisonData[] = $livraison->getUtilisateur()->getUsername();
                    $livraisonData[] = $livraison->getStatut()->getNom();
                    $livraisonData[] = $livraison->getDestination()->getLabel();
                    $livraisonData[] = strip_tags($livraison->getCommentaire());
                    $livraisonData[] = $livraison->getDate()->format('Y/m/d-H:i:s');
                    $livraisonData[] = $livraison->getPreparation() ? $livraison->getPreparation()->getDate()->format('Y/m/d-H:i:s') : '';
                    $livraisonData[] = $livraison->getNumero();
                    $livraisonData[] = $ligneArticle->getReference() ? $ligneArticle->getReference()->getReference() : '';
                    $livraisonData[] = $ligneArticle->getReference() ? $ligneArticle->getReference()->getLibelle() : '';
                    $livraisonData[] = $ligneArticle->getQuantite();
                    $categorieCL = $this->categorieCLRepository->findOneByLabel(($ligneArticle->getReference()->getTypeQuantite() === 'reference') ? 'reference CEA' : 'article');
                    $champsLibres = [];
                    foreach ($listTypes as $type) {
                        $listChampsLibres = $this->champsLibreRepository->findByLabelTypeAndCategorieCL($type['label'], $categorieCL);
                        foreach ($listChampsLibres as $champLibre) {
                            $valeurChampRefArticle = $this->valeurChampsLibreRepository->findOneByRefArticleANDChampsLibre($ligneArticle->getReference()->getId(), $champLibre);
                            if ($valeurChampRefArticle) {
                                $champsLibres[$champLibre->getLabel()] = $valeurChampRefArticle->getValeur();
                            }
                        }
                    }
                    foreach ($cls as $type) {
                        if (array_key_exists($type->getLabel(), $champsLibres)) {
                            $livraisonData[] = $champsLibres[$type->getLabel()];
                        } else {
                            $livraisonData[] = '';
                        }
                    }
                    $data[] = $livraisonData;
                }
                foreach ($this->articleRepository->getByDemande($livraison) as $article) {
                    $livraisonData = [];
                    $livraisonData[] = $livraison->getUtilisateur()->getUsername();
                    $livraisonData[] = $livraison->getStatut()->getNom();
                    $livraisonData[] = $livraison->getDestination()->getLabel();
                    $livraisonData[] = strip_tags($livraison->getCommentaire());
                    $livraisonData[] = $livraison->getDate()->format('Y/m/d-H:i:s');
                    $livraisonData[] = $livraison->getPreparation() ? $livraison->getPreparation()->getDate()->format('Y/m/d-H:i:s') : '';
                    $livraisonData[] = $livraison->getNumero();
                    $livraisonData[] = $article->getArticleFournisseur()->getReferenceArticle()->getReference();
                    $livraisonData[] = $article->getLabel();
                    $livraisonData[] = $article->getQuantite();
                    $categorieCL = $this->categorieCLRepository->findOneByLabel('article');
                    $champsLibres = [];
                    foreach ($listTypes as $type) {
                        $listChampsLibres = $this->champsLibreRepository->findByLabelTypeAndCategorieCL($type['label'], $categorieCL);
                        foreach ($listChampsLibres as $champLibre) {
                            $valeurChampRefArticle = $this->valeurChampsLibreRepository->findOneByArticleANDChampsLibre($article, $champLibre);
                            if ($valeurChampRefArticle) {
                                $champsLibres[$champLibre->getLabel()] = $valeurChampRefArticle->getValeur();
                            }
                        }
                    }
                    foreach ($cls as $type) {
                        if (array_key_exists($type->getLabel(), $champsLibres)) {
                            $livraisonData[] = $champsLibres[$type->getLabel()];
                        } else {
                            $livraisonData[] = '';
                        }
                    }
                    $data[] = $livraisonData;
                }
            }
            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }
}
