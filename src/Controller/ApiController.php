<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 05/03/2019
 * Time: 14:31
 */

namespace App\Controller;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Colis;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\MouvementTraca;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;

use App\Repository\ColisRepository;
use App\Repository\LigneArticleRepository;
use App\Repository\LivraisonRepository;
use App\Repository\MailerServerRepository;
use App\Repository\MouvementStockRepository;
use App\Repository\MouvementTracaRepository;
use App\Repository\PieceJointeRepository;
use App\Repository\PreparationRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\ArticleRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseurRepository;

use App\Service\ArticleDataService;
use App\Service\MailerService;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\ORMException;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\View\View as RestView;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder;
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoder;
use Symfony\Component\Serializer\SerializerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Routing\Annotation\Route;
use DateTime;

/**
 * Class ApiController
 * @package App\Controller
 */
class ApiController extends FOSRestController implements ClassResourceInterface
{

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var UserPasswordEncoderInterface
     */
    private $passwordEncoder;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var MouvementTracaRepository
     */
    private $mouvementTracaRepository;

    /**
     * @var ColisRepository
     */
    private $colisRepository;

    /**
     * @var array
     */
    private $successDataMsg;

    /**
     * @var MailerService
     */
    private $mailerService;

    /**
     * @var MailerServerRepository
     */
    private $mailerServerRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var PieceJointeRepository
     */
    private $pieceJointeRepository;

    /**
     * @var PreparationRepository
     */
    private $preparationRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var ArticleDataService
     */
    private $articleDataService;

	/**
	 * @var LivraisonRepository
	 */
    private $livraisonRepository;

	/**
	 * @var MouvementStockRepository
	 */
    private $mouvementRepository;

	/**
	 * @var LigneArticleRepository
	 */
    private $ligneArticleRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;
    /**
     * ApiController constructor.
     * @param LoggerInterface $logger
     * @param MailerServerRepository $mailerServerRepository
     * @param MailerService $mailerService
     * @param ColisRepository $colisRepository
     * @param MouvementTracaRepository $mouvementTracaRepository
     * @param ReferenceArticleRepository $referenceArticleRepository
     * @param UtilisateurRepository $utilisateurRepository
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @param ArticleRepository $articleRepository
     * @param EmplacementRepository $emplacementRepository
     * @param PieceJointeRepository $pieceJointeRepository
     * @param PreparationRepository $preparationRepository
     * @param StatutRepository $statutRepository
     * @param ArticleDataService $articleDataService
	 * @param LivraisonRepository $livraisonRepository
	 * @param MouvementStockRepository $mouvementRepository
	 * @param LigneArticleRepository $ligneArticleRepository
     */
    public function __construct(FournisseurRepository $fournisseurRepository, LigneArticleRepository $ligneArticleRepository, MouvementStockRepository $mouvementRepository, LivraisonRepository $livraisonRepository, ArticleDataService $articleDataService, StatutRepository $statutRepository, PreparationRepository $preparationRepository, PieceJointeRepository $pieceJointeRepository, LoggerInterface $logger, MailerServerRepository $mailerServerRepository, MailerService $mailerService, ColisRepository $colisRepository, MouvementTracaRepository $mouvementTracaRepository, ReferenceArticleRepository $referenceArticleRepository, UtilisateurRepository $utilisateurRepository, UserPasswordEncoderInterface $passwordEncoder, ArticleRepository $articleRepository, EmplacementRepository $emplacementRepository)
    {
        $this->pieceJointeRepository = $pieceJointeRepository;
        $this->mailerServerRepository = $mailerServerRepository;
        $this->mailerService = $mailerService;
        $this->colisRepository = $colisRepository;
        $this->mouvementTracaRepository = $mouvementTracaRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->articleRepository = $articleRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->passwordEncoder = $passwordEncoder;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->successDataMsg = ['success' => false, 'data' => [], 'msg' => ''];
        $this->logger = $logger;
        $this->preparationRepository = $preparationRepository;
        $this->statutRepository = $statutRepository;
        $this->articleDataService = $articleDataService;
        $this->livraisonRepository = $livraisonRepository;
        $this->mouvementRepository = $mouvementRepository;
        $this->ligneArticleRepository = $ligneArticleRepository;
        $this->fournisseurRepository = $fournisseurRepository;
    }

    /**
     * @Rest\Post("/api/connect", name= "api-connect")
     * @Rest\Get("/api/connect")
     * @Rest\View()
     */
    public function connection(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $response = new Response();

            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');

            $user = $this->utilisateurRepository->findOneBy(['username' => $data['login']]);

            if ($user !== null) {
                if ($this->passwordEncoder->isPasswordValid($user, $data['password'])) {
                    $apiKey = $this->apiKeyGenerator();

                    $user->setApiKey($apiKey);
                    $em = $this->getDoctrine()->getManager();
                    $em->flush();

                    $this->successDataMsg['success'] = true;
                    $this->successDataMsg['data'] = $this->getDataArray($user);
                    $this->successDataMsg['data']['apiKey'] = $apiKey;
                }
            }

            $response->setContent(json_encode($this->successDataMsg));
            return $response;
        }
    }

    /**
     * @Rest\Post("/api/ping", name= "api-ping")
     * @Rest\Get("/api/ping")
     * @Rest\View()
     */
    public function ping(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            $response = new Response();

            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');
            $this->successDataMsg['success'] = true;

            $response->setContent(json_encode($this->successDataMsg));
            return $response;
        }
    }

    /**
     * @Rest\Post("/api/addMouvementTraca", name="api-add-mouvement-traca")
     * @Rest\Get("/api/addMouvementTraca")
     * @Rest\View()
     */
    public function addMouvementTraca(Request $request)
    {
		if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			$response = new Response();
			$response->headers->set('Content-Type', 'application/json');
			$response->headers->set('Access-Control-Allow-Origin', '*');
			$response->headers->set('Access-Control-Allow-Methods', 'POST, GET');

			if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

				$em = $this->getDoctrine()->getManager();
				$numberOfRowsInserted = 0;
				foreach ($data['mouvements'] as $mvt) {
					if (!$this->mouvementTracaRepository->getOneByDate($mvt['date'])) {
						$refEmplacement = $mvt['ref_emplacement'];
						$refArticle = $mvt['ref_article'];
						$type = $mvt['type'];

						$toInsert = new MouvementTraca();
						$toInsert
							->setRefArticle($refArticle)
							->setRefEmplacement($refEmplacement)
							->setOperateur($this->utilisateurRepository->findOneByApiKey($data['apiKey'])->getUsername())
							->setDate($mvt['date'])
							->setType($type);
						$em->persist($toInsert);
						$numberOfRowsInserted++;

						$emplacement = $this->emplacementRepository->findOneByLabel($refEmplacement);
						/** @var Emplacement $emplacement */

						if ($emplacement) {

							$isDepose = $type === MouvementTraca::TYPE_DEPOSE;
							$colis = $this->colisRepository->getOneByCode($mvt['ref_article']);
							/**@var Colis $colis */

							if ($isDepose && $colis && $emplacement->getIsDeliveryPoint()) {
							    $fournisseur = $this->fournisseurRepository->findOneByColis($colis);
								$arrivage = $colis->getArrivage();
								$destinataire = $arrivage->getDestinataire();
								if ($this->mailerServerRepository->findOneMailerServer()) {
									$dateArray = explode('_', $toInsert->getDate());
									$date = new DateTime($dateArray[0]);
									$this->mailerService->sendMail(
										'FOLLOW GT // Dépose effectuée',
										$this->renderView(
											'mails/mailDeposeTraca.html.twig',
											[
												'title' => 'Votre colis a été livré.',
												'colis' => $colis->getCode(),
												'emplacement' => $emplacement,
												'fournisseur' => $fournisseur ? $fournisseur->getNom() : '',
												'date' => $date,
												'operateur' => $toInsert->getOperateur(),
												'pjs' => $arrivage->getPiecesJointes()
											]
										),
										$destinataire->getEmail()
									);
								} else {
									$this->logger->critical('Parametrage mail non defini.');
								}
							}
						} else {
							$emplacement = new Emplacement();
							$emplacement->setLabel($refEmplacement);
							$em->persist($emplacement);
							$em->flush();
						}
					}
				}
				$em->flush();

				$s = $numberOfRowsInserted > 0 ? 's' : '';
				$this->successDataMsg['success'] = true;
				$this->successDataMsg['data']['status'] = ($numberOfRowsInserted === 0) ?
					'Aucun mouvement à synchroniser.' : $numberOfRowsInserted . ' mouvement' . $s . ' synchronisé' . $s;

			} else {
				$this->successDataMsg['success'] = false;
				$this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
			}

			$response->setContent(json_encode($this->successDataMsg));
			return $response;
		}
	}

    /**
     * @Rest\Post("/api/setmouvement", name= "api-set-mouvement")
     * @Rest\View()
     */
    public function setMouvement(Request $request)
    {
		if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {
				$mouvementsR = $data['mouvement'];
				foreach ($mouvementsR as $mouvementR) {
					$mouvement = new MouvementStock;
					$mouvement
						->setType($mouvementR['type'])
						->setDate(DateTime::createFromFormat('j-M-Y', $mouvementR['date']))
						->setEmplacementFrom($this->emplacemnt->$mouvementR[''])
						->setUser($mouvementR['']);
				}
				$this->successDataMsg['success'] = true;
			}  else {
				$this->successDataMsg['success'] = false;
				$this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
			}

        	return new JsonResponse($this->successDataMsg);
        }
    }

    /**
     * @Rest\Post("/api/beginPrepa", name= "api-begin-prepa")
     * @Rest\View()
     */
    public function beginPrepa(Request $request)
    {
		if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

				$em = $this->getDoctrine()->getManager();

				$preparation = $this->preparationRepository->find($data['id']);

				if ($preparation->getStatut()->getNom() == Preparation::STATUT_A_TRAITER || $preparation->getUtilisateur() === $nomadUser) {

					$demandes = $preparation->getDemandes();
					$demande = $demandes[0];

					// modification des articles de la demande
					$articles = $demande->getArticles();
					foreach ($articles as $article) {
						$article->setStatut($this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_EN_TRANSIT));
						// scission des articles dont la quantité prélevée n'est pas totale
						if ($article->getQuantite() !== $article->getQuantiteAPrelever()) {
							$newArticle = [
								'articleFournisseur' => $article->getArticleFournisseur()->getId(),
								'libelle' => $article->getLabel(),
								'conform' => !$article->getConform(),
								'commentaire' => $article->getcommentaire(),
								'quantite' => $article->getQuantite() - $article->getQuantiteAPrelever(),
								'emplacement' => $article->getEmplacement() ? $article->getEmplacement()->getId() : '',
								'statut' => Article::STATUT_ACTIF,
								'refArticle' => isset($data['refArticle']) ? $data['refArticle'] : $article->getArticleFournisseur()->getReferenceArticle()->getId()
							];

							foreach ($article->getValeurChampsLibres() as $valeurChampLibre) {
								$newArticle[$valeurChampLibre->getChampLibre()->getId()] = $valeurChampLibre->getValeur();
							}
							$this->articleDataService->newArticle($newArticle);

							$article->setQuantite($article->getQuantiteAPrelever(), 0);
						}

						// création des mouvements de préparation pour les articles
						$mouvement = new MouvementStock();
						$mouvement
							->setUser($nomadUser)
							->setArticle($article)
							->setQuantity($article->getQuantiteAPrelever())
							->setEmplacementFrom($article->getEmplacement())
							->setType(MouvementStock::TYPE_TRANSFERT)
							->setPreparationOrder($preparation)
							->setExpectedDate($preparation->getDate());
						$em->persist($mouvement);
						$em->flush();
					}

					// création des mouvements de préparation pour les articles de référence
					foreach($demande->getLigneArticle() as $ligneArticle) {
						$articleRef = $ligneArticle->getReference();

						$mouvement = new MouvementStock();
						$mouvement
							->setUser($nomadUser)
							->setRefArticle($articleRef)
							->setQuantity($ligneArticle->getQuantite())
							->setEmplacementFrom($articleRef->getEmplacement())
							->setType(MouvementStock::TYPE_TRANSFERT)
							->setPreparationOrder($preparation)
							->setExpectedDate($preparation->getDate());
						$em->persist($mouvement);
						$em->flush();
					}

					// modif du statut de la préparation
					$statutEDP = $this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::PREPARATION, Preparation::STATUT_EN_COURS_DE_PREPARATION);
					$preparation
						->setStatut($statutEDP)
						->setUtilisateur($nomadUser);
					$em->flush();

					$this->successDataMsg['success'] = true;
				} else {
					$this->successDataMsg['success'] = false;
					$this->successDataMsg['msg'] = "Cette préparation a déjà été prise en charge par un opérateur.";
				}
			} else {
				$this->successDataMsg['success'] = false;
				$this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
			}
			return new JsonResponse($this->successDataMsg);
		}
    }

    /**
     * @Rest\Post("/api/finishPrepa", name= "api-finish-prepa")
     * @Rest\View()
     */
    public function finishPrepa(Request $request)
    {
		if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

				$entityManager = $this->getDoctrine()->getManager();

				$preparations = $data['preparations'];

				// on termine les préparations
				// même comportement que LivraisonController.new()
				foreach ($preparations as $preparationArray) {
					$preparation = $this->preparationRepository->find($preparationArray['id']);

					if ($preparation) {
						$demandes = $preparation->getDemandes();
						$demande = $demandes[0];

						$statut = $this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::LIVRAISON, Livraison::STATUT_A_TRAITER);
						$livraison = new Livraison();

						$date = DateTime::createFromFormat(DateTime::ATOM, $preparationArray['date_end']);
						$livraison
							->setDate($date)
							->setNumero('L-' . $date->format('YmdHis'))
							->setStatut($statut);
						$entityManager->persist($livraison);

						$preparation
							->addLivraison($livraison)
							->setUtilisateur($nomadUser)
							->setStatut($this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::PREPARATION, Preparation::STATUT_PREPARE));

						$demande
							->setStatut($this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::DEMANDE, Demande::STATUT_PREPARE))
							->setLivraison($livraison);

						// on termine les mouvements de préparation
						$mouvements = $this->mouvementRepository->findByPreparation($preparation);
						$emplacementPrepa = $this->emplacementRepository->findOneByLabel($preparationArray['emplacement']);
						foreach ($mouvements as $mouvement) {
							if ($emplacementPrepa) {
								$mouvement
									->setDate($date)
									->setEmplacementTo($emplacementPrepa);
							} else {
								$this->successDataMsg['success'] = false;
								$this->successDataMsg['msg'] = "L'emplacement que vous avez sélectionné n'existe plus.";
								return new JsonResponse($this->successDataMsg);
							}
						}

						$entityManager->flush();
					}
				}

				$mouvementsNomade = $data['mouvements'];

				// on crée les mouvements de livraison
				foreach ($mouvementsNomade as $mouvementNomade) {
					$livraison = $this->livraisonRepository->findOneByPreparationId($mouvementNomade['id_prepa']);
					$emplacement = $this->emplacementRepository->findOneByLabel($mouvementNomade['location']);

					$mouvement = new MouvementStock();
					$mouvement
						->setUser($nomadUser)
						->setQuantity($mouvementNomade['quantity'])
						->setEmplacementFrom($emplacement)
						->setType(MouvementStock::TYPE_SORTIE)
						->setLivraisonOrder($livraison)
						->setExpectedDate($livraison->getDate());
					$entityManager->persist($mouvement);

					if ($mouvementNomade['is_ref']) {
						$refArticle = $this->referenceArticleRepository->findOneByReference($mouvementNomade['reference']);
						if ($refArticle) {
							$mouvement->setRefArticle($refArticle);
						}
					} else {
						$article = $this->articleRepository->findOneByReference($mouvementNomade['reference']);
						if ($article) {
							$article->setStatut($this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::ARTICLE, Article::STATUT_EN_TRANSIT));
							$mouvement->setArticle($article);
						}
					}

					$entityManager->flush();
				}

				$this->successDataMsg['success'] = true;

			} else {
				$this->successDataMsg['success'] = false;
				$this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
			}

			return new JsonResponse($this->successDataMsg);
		}
    }

	/**
	 * @Rest\Post("/api/beginLivraison", name= "api-begin-livraison")
	 * @Rest\View()
	 */
	public function beginLivraison(Request $request)
	{
		if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

				$em = $this->getDoctrine()->getManager();

				$livraison = $this->livraisonRepository->find($data['id']);

				if (
					$livraison->getStatut()->getNom() == Livraison::STATUT_A_TRAITER &&
					(empty($livraison->getUtilisateur()) || $livraison->getUtilisateur() === $nomadUser)
				) {
					// modif de la livraison
					$livraison->setUtilisateur($nomadUser);

					$em->flush();

					$this->successDataMsg['success'] = true;
				} else {
					$this->successDataMsg['success'] = false;
					$this->successDataMsg['msg'] = "Cette livraison a déjà été prise en charge par un opérateur.";
				}
			} else {
				$this->successDataMsg['success'] = false;
				$this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
			}
			return new JsonResponse($this->successDataMsg);
		}
	}

	/**
	 * @Rest\Post("/api/finishLivraison", name= "api-finish-livraison")
	 * @Rest\View()
	 */
	public function finishLivraison(Request $request)
	{
		if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

				$entityManager = $this->getDoctrine()->getManager();

				$livraisons = $data['livraisons'];

				// on termine les livraisons
				// même comportement que LivraisonController.finish()
				foreach ($livraisons as $livraisonArray) {
					$livraison = $this->livraisonRepository->find($livraisonArray['id']);

					if ($livraison) {
						$date = DateTime::createFromFormat(DateTime::ATOM, $livraisonArray['date_end']);

						$livraison
							->setStatut($this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::LIVRAISON, Livraison::STATUT_LIVRE))
							->setUtilisateur($nomadUser)
							->setDateFin($date);

						$demandes = $livraison->getDemande();
						$demande = $demandes[0];

						$statutLivre = $this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::DEMANDE, Demande::STATUT_LIVRE);
						$demande->setStatut($statutLivre);

						$this->mailerService->sendMail(
							'FOLLOW GT // Livraison effectuée',
							$this->renderView('mails/mailLivraisonDone.html.twig', [
								'livraison' => $demande,
								'title' => 'Votre demande a bien été livrée.',
							]),
							$demande->getUtilisateur()->getEmail()
						);

						// quantités gérées à la référence
						$ligneArticles = $demande->getLigneArticle();

						foreach ($ligneArticles as $ligneArticle) {
							$refArticle = $ligneArticle->getReference();
							$refArticle->setQuantiteStock($refArticle->getQuantiteStock() - $ligneArticle->getQuantite());
						}

						// quantités gérées à l'article
						$articles = $demande->getArticles();

						foreach ($articles as $article) {
							$article
								->setStatut($this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::ARTICLE, Article::STATUT_INACTIF))
								->setEmplacement($demande->getDestination());
						}

						// on termine les mouvements de livraison
						$mouvements = $this->mouvementRepository->findByLivraison($livraison);
						foreach ($mouvements as $mouvement) {
							$emplacement = $this->emplacementRepository->findOneByLabel($livraisonArray['emplacement']);
							if ($emplacement) {
								$mouvement
									->setDate($date)
									->setEmplacementTo($emplacement);
							} else {
								$this->successDataMsg['success'] = false;
								$this->successDataMsg['msg'] = "L'emplacement que vous avez sélectionné n'existe plus.";
								return new JsonResponse($this->successDataMsg);
							}
						}

						$entityManager->flush();
					}
				}

				$this->successDataMsg['success'] = true;

			} else {
				$this->successDataMsg['success'] = false;
				$this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
			}

			return new JsonResponse($this->successDataMsg);
		}
	}

    private function getDataArray($user)
    {
        $articles = $this->articleRepository->getIdRefLabelAndQuantity();
        $articlesRef = $this->referenceArticleRepository->getIdRefLabelAndQuantityByTypeQuantite(ReferenceArticle::TYPE_QUANTITE_REFERENCE);

        $articlesPrepa = $this->articleRepository->getByPreparationStatutLabelAndUser(Preparation::STATUT_A_TRAITER, Preparation::STATUT_EN_COURS_DE_PREPARATION, $user);
        $refArticlesPrepa = $this->referenceArticleRepository->getByPreparationStatutLabelAndUser(Preparation::STATUT_A_TRAITER, Preparation::STATUT_EN_COURS_DE_PREPARATION, $user);

        $articlesLivraison = $this->articleRepository->getByLivraisonStatutLabelAndWithoutOtherUser(Livraison::STATUT_A_TRAITER, $user);
        $refArticlesLivraison = $this->referenceArticleRepository->getByLivraisonStatutLabelAndWithoutOtherUser(Livraison::STATUT_A_TRAITER, $user);

        $data = [
            'emplacements' => $this->emplacementRepository->getIdAndNom(),
            'articles' => array_merge($articles, $articlesRef),
            'preparations' => $this->preparationRepository->getByStatusLabelAndUser(Preparation::STATUT_A_TRAITER, Preparation::STATUT_EN_COURS_DE_PREPARATION, $user),
            'articlesPrepa' => array_merge($articlesPrepa, $refArticlesPrepa),
			'livraisons' => $this->livraisonRepository->getByStatusLabelAndWithoutOtherUser(Livraison::STATUT_A_TRAITER, $user),
			'articlesLivraison' => array_merge($articlesLivraison, $refArticlesLivraison)
        ];
        return $data;
    }

    /**
     * @Rest\Post("/api/getData", name= "api-get-data")
     */
    public function getData(Request $request)
    {
		if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {
				$this->successDataMsg['success'] = true;
				$this->successDataMsg['data'] = $this->getDataArray($nomadUser);

			} else {
				$this->successDataMsg['success'] = false;
				$this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
			}

			return new JsonResponse($this->successDataMsg);
		}
	}

    private function apiKeyGenerator()
    {
        $key = md5(microtime() . rand());
        return $key;
    }

}
