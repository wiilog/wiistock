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
use App\Entity\Mouvement;

use App\Entity\MouvementTraca;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Repository\ColisRepository;
use App\Repository\MailerServerRepository;
use App\Repository\MouvementTracaRepository;
use App\Repository\PieceJointeRepository;
use App\Repository\PreparationRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\ArticleRepository;
use App\Repository\EmplacementRepository;

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
     */
    public function __construct(ArticleDataService $articleDataService, StatutRepository $statutRepository, PreparationRepository $preparationRepository, PieceJointeRepository $pieceJointeRepository, LoggerInterface $logger, MailerServerRepository $mailerServerRepository, MailerService $mailerService, ColisRepository $colisRepository, MouvementTracaRepository $mouvementTracaRepository, ReferenceArticleRepository $referenceArticleRepository, UtilisateurRepository $utilisateurRepository, UserPasswordEncoderInterface $passwordEncoder, ArticleRepository $articleRepository, EmplacementRepository $emplacementRepository)
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
                    $this->successDataMsg['data'] = $this->getDataArray();
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
                        ->setOperateur($this->utilisateurRepository->findOneByApiKey($data['apikey'])->getUsername())
                        ->setDate($mvt['date'])
                        ->setType($type);
                    $em->persist($toInsert);
                    $numberOfRowsInserted++;

                    $emplacement = $this->emplacementRepository->getOneByLabel($refEmplacement);
                    /** @var Emplacement $emplacement */

                    if ($emplacement) {

                        $isDepose = $type === MouvementTraca::DEPOSE;
                        $colis = $this->colisRepository->getOneByCode($mvt['ref_article']);
                        /**@var Colis $colis */

                        if ($isDepose && $colis && $emplacement->getIsDeliveryPoint()) {
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
                                            'colis' => $colis->getCode(),
                                            'emplacement' => $emplacement,
                                            'arrivage' => $arrivage->getNumeroArrivage(),
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
        $data = json_decode($request->getContent(), true);

        if (!$request->isXmlHttpRequest() && ($this->utilisateurRepository->countApiKey($data['apiKey'])) === '1') {
            $mouvementsR = $data['mouvement'];
            foreach ($mouvementsR as $mouvementR) {
                $mouvement = new Mouvement;
                $mouvement
                    ->setType($mouvementR['type'])
                    ->setDate(DateTime::createFromFormat('j-M-Y', $mouvementR['date']))
                    ->setEmplacement($this->emplacemnt->$mouvementR[''])
                    ->setUser($mouvementR['']);
            }
        }

        return new JsonResponse($this->successDataMsg);
    }

	/**
	 * @Rest\Post("/api/beginPrepa", name= "api-begin-prepa")
	 * @Rest\View()
	 */
	public function beginPrepa(Request $request)
	{
		$data = json_decode($request->getContent(), true);

		if (!$request->isXmlHttpRequest() && ($this->utilisateurRepository->countApiKey($data['apiKey'])) === '1') {
			$em = $this->getDoctrine()->getManager();

			$preparation = $this->preparationRepository->find($data['id']);

			if ($preparation->getStatut()->getNom() == Preparation::STATUT_A_TRAITER) {

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
				}

				// modif du statut de la préparation
				$statutEDP = $this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::PREPARATION, Preparation::STATUT_EN_COURS_DE_PREPARATION);
				$preparation->setStatut($statutEDP);
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

	/**
	 * @Rest\Post("/api/finishPrepa", name= "api-finish-prepa")
	 * @Rest\View()
	 */
    public function finishPrepa(Request $request)
	{
		$data = json_decode($request->getContent(), true);
		if (!$request->isXmlHttpRequest() && ($this->utilisateurRepository->countApiKey($data['apiKey'])) === '1') {
			$nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey']);
			$entityManager = $this->getDoctrine()->getManager();
            dump($nomadUser);
			$preparations = $data['preparations'];
			$mouvements = $data['mouvements'];

			foreach ($mouvements as $mouvement) {
				if ($mouvement['is_ref']) {
					$refArticle = $this->referenceArticleRepository->findOneByReference($mouvement['reference']);
					if ($refArticle) {

					}
				} else {
					$article = $this->articleRepository->findOneByReference($mouvement['reference']);
					if ($article) {
						$article->setStatut($this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::ARTICLE, Article::STATUT_EN_TRANSIT));
					}
				}
			}

			// on termine les préparations
			// même comportement que LivraisonController.new()
			foreach($preparations as $preparationArray) {
				$preparation = $this->preparationRepository->find($preparationArray['id']);
				if ($preparation) {

					$demandes = $preparation->getDemandes();
					$demande = $demandes[0];

					$statut = $this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::LIVRAISON, Livraison::STATUT_A_TRAITER);
					$livraison = new Livraison();
					dump($preparationArray['date_end']);
					$date = DateTime::createFromFormat('Ymd-H:i:s', $preparationArray['date_end'], new \DateTimeZone('Europe/Paris'));
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
					$entityManager->flush();
					$this->successDataMsg['success'] = true;
				}
			}
		} else {
			$this->successDataMsg['success'] = false;
			$this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
		}

		return new JsonResponse($this->successDataMsg);
	}

    private function getDataArray()
    {
        $articles = $this->articleRepository->getIdRefLabelAndQuantity();
        $articlesRef = $this->referenceArticleRepository->getIdRefLabelAndQuantityByTypeQuantite(ReferenceArticle::TYPE_QUANTITE_REFERENCE);

        $articlesPrepa = $this->articleRepository->getByPreparationStatutLabel(Preparation::STATUT_A_TRAITER);
        $refArticlesPrepa = $this->referenceArticleRepository->getByPreparationStatutLabel(Preparation::STATUT_A_TRAITER);

        $data = [
            'emplacements' => $this->emplacementRepository->getIdAndNom(),
            'articles' => array_merge($articles, $articlesRef),
			'preparations' => $this->preparationRepository->getByStatusLabel(Preparation::STATUT_A_TRAITER),
			'articlesPrepa' => array_merge($articlesPrepa, $refArticlesPrepa)
        ];
        return $data;
    }

	/**
	 * @Rest\Post("/api/getData", name= "api-get-data")
	 */
    public function getData()
	{
	    $response = [];
	    $response['success'] = true;
	    $response['data'] = $this->getDataArray();
		return new JsonResponse($response);
	}

    public function apiKeyGenerator()
    {
        $key = md5(microtime() . rand());
        return $key;
    }
}
