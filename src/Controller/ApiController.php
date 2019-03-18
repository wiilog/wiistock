<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 05/03/2019
 * Time: 14:31
 */
namespace App\Controller;

use App\Entity\Mouvement;

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
     * @var array
     */
    private $successData;


    public function __construct(UtilisateurRepository $utilisateurRepository, UserPasswordEncoderInterface $passwordEncoder, ArticleRepository $articleRepository, EmplacementRepository $emplacementRepository)
    {
        $this->emplacementRepository = $emplacementRepository;
        $this->articleRepository = $articleRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->passwordEncoder = $passwordEncoder;
        $this->successData = ['success' => false, 'apiKey' => '', 'data' => ''];
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

            $this->successData['data'] = $data;

            if ($this->checkLoginPassword($data)) {
//                $apiKey = $this->apiKeyGenerator();
                $apiKey ='366d041c57996ffcc2324ef3f939717d';

                $user = $this->utilisateurRepository->findOneBy(['username' => $data['login']]);
                if ($user) {
                    $user->setApiKey($apiKey); //TODO
                    $em = $this->getDoctrine()->getManager();
                    $em->flush();

                    $this->successData['success'] = true;
                    $this->successData['data'] = [
                        'data' => $this->getData(),
                        'apiKey' => $apiKey
                    ];
                }
            }

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

    /**
     * @param array $data
     * @return bool
     */
    private function checkLoginPassword($data)
    {
        $login = $data['login'];
        $password = $data['password'];
        $user = $this->utilisateurRepository->findOneBy(['username' => $login]);

        if ($user !== null) {
            $match = $this->passwordEncoder->isPasswordValid($user, $password);
        } else {
            $match = false;
        }
        return $match;
    }

    private function getData()
    {
        $data = [
            'emplacements' => $this->emplacementRepository->getIdAndNom(),
            'articles' => $this->articleRepository->getArticleByRefId()
        ];
        return $data;
    }

    public function apiKeyGenerator()
    {
        $key = md5(microtime() . rand());
        return $key;
    }
}
