<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 05/03/2019
 * Time: 14:31
 */

namespace App\Controller;

use App\Entity\Colis;
use App\Entity\Emplacement;
use App\Entity\Mouvement;

use App\Entity\MouvementTraca;
use App\Entity\ReferenceArticle;
use App\Repository\ColisRepository;
use App\Repository\MailerServerRepository;
use App\Repository\MouvementTracaRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\ArticleRepository;
use App\Repository\EmplacementRepository;

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
    private $successData;

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
     */
    public function __construct(LoggerInterface $logger, MailerServerRepository $mailerServerRepository, MailerService $mailerService, ColisRepository $colisRepository, MouvementTracaRepository $mouvementTracaRepository, ReferenceArticleRepository $referenceArticleRepository, UtilisateurRepository $utilisateurRepository, UserPasswordEncoderInterface $passwordEncoder, ArticleRepository $articleRepository, EmplacementRepository $emplacementRepository)
    {
        $this->mailerServerRepository = $mailerServerRepository;
        $this->mailerService = $mailerService;
        $this->colisRepository = $colisRepository;
        $this->mouvementTracaRepository = $mouvementTracaRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->articleRepository = $articleRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->passwordEncoder = $passwordEncoder;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->successData = ['success' => false, 'data' => []];
        $this->logger = $logger;
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

                    $this->successData['success'] = true;
                    $this->successData['data'] = $this->getData();
                    $this->successData['data']['apiKey'] = $apiKey;
                }
            }

            $response->setContent(json_encode($this->successData));
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
            $this->successData['success'] = true;

            $response->setContent(json_encode($this->successData));
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
            dump($data['mouvements']);
            foreach ($data['mouvements'] as $mvt) {
                dump($mvt);
                if (!$this->mouvementTracaRepository->getOneByDate($mvt['date'])) {
                    $refEmplacement = $mvt['ref_emplacement'];
                    $refArticle = $mvt['ref_article'];
                    $type = $mvt['type'];

                    $toInsert = new MouvementTraca();
                    $toInsert
                        ->setRefArticle($refArticle)
                        ->setRefEmplacement($refEmplacement)
                        ->setOperateur($mvt['operateur'])
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
                            dump('wanted to send');
                            if ($this->mailerServerRepository->findOneMailerServer()) {
                                dump($mvt);
                                $dateArray = explode('_', $mvt->getDate());
                                dump('after');
                                $date = new DateTime($dateArray[0]);
                                $this->mailerService->sendMail(
                                    'FOLLOW GT // Dépose effectuée',
                                    $this->renderView(
                                        'mails/mailDeposeTraca.html.twig',
                                        [
                                            'colis' => $colis->getCode(),
                                            'emplacement' => $refEmplacement,
                                            'arrivage' => $arrivage->getNumeroArrivage(),
                                            'date' => $date,
                                            'operateur' => $mvt['operateur']
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
            $this->successData['success'] = true;
            $this->successData['data']['status'] = ($numberOfRowsInserted === 0) ?
                'Aucun mouvement à synchroniser.' : $numberOfRowsInserted . ' mouvement' . $s . ' synchronisé' . $s;
            $response->setContent(json_encode($this->successData));
            return $response;
        }
    }

    /**
     * @Rest\Post("/api/setmouvement", name= "api-set-mouvement")
     * @Rest\View()
     */
    public function setMouvement(Request $request)
    {
        //TODO JV récupérer fichier json construit sur ce modèle :
        // ['mouvements':
        //  ['id_article': int, 'date_prise': date, 'id_emplacement_prise': int, 'date_depose': date, 'id_emplacement_depose': int],
        //  [...],
        //];
        // ajouter l'id en autoincrement
        // ajouter l'user retrouvé grâce au token api
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

            return new JsonResponse($this->successData);
        } else {
            return new JsonResponse($this->successData);
        }
    }

    private function getData()
    {
        $articles = $this->articleRepository->getIdRefLabelAndQuantity();
        $articlesRef = $this->referenceArticleRepository->getIdRefLabelAndQuantityByTypeQuantite(ReferenceArticle::TYPE_QUANTITE_REFERENCE);

        $data = [
            'emplacements' => $this->emplacementRepository->getIdAndNom(),
            'articles' => array_merge($articles, $articlesRef)
        ];
        return $data;
    }

    public function apiKeyGenerator()
    {
        $key = md5(microtime() . rand());
        return $key;
    }
}
