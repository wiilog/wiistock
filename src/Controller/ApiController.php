<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 05/03/2019
 * Time: 14:31
 */
namespace App\Controller;

use App\Entity\Mouvement;

use App\Entity\MouvementTraca;
use App\Entity\ReferenceArticle;
use App\Repository\ReferenceArticleRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\ArticleRepository;
use App\Repository\EmplacementRepository;

use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
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
     * @var array
     */
    private $successData;


    public function __construct(ReferenceArticleRepository $referenceArticleRepository, UtilisateurRepository $utilisateurRepository, UserPasswordEncoderInterface $passwordEncoder, ArticleRepository $articleRepository, EmplacementRepository $emplacementRepository)
    {
        $this->emplacementRepository = $emplacementRepository;
        $this->articleRepository = $articleRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->passwordEncoder = $passwordEncoder;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->successData = ['success' => false, 'data' => []];
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
     * @Rest\Post("/api/addMouvementTraca", name="api-add-mouvement-traca")
     * @Rest\Get("/api/addMouvementTraca")
     * @Rest\View()
     */
    public function addMouvementTraca(Request $request) {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)){
            $response = new Response();
            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');
            $em = $this->getDoctrine()->getManager();
            foreach ($data['mouvements'] as $mvt) {
                $toInsert = new MouvementTraca();
                $toInsert->setRefArticle($mvt['ref_article']);
                $toInsert->setRefEmplacement($mvt['ref_emplacement']);
                $toInsert->setDate($mvt['date']);
                $toInsert->setType($mvt['type']);
                $em->persist($toInsert);
            }
            $em->flush();
            dump('yes');
            $this->successData['success'] = true;
            $this->successData['data'] = [];
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
            'articles' => array_merge($articles, $articlesRef) //TODO CG provisoire pour éviter charger trop de données sur mobile en phase test
        ];
        return $data;
    }

    public function apiKeyGenerator()
    {
        $key = md5(microtime() . rand());
        return $key;
    }
}
