<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 05/03/2019
 * Time: 14:31
 */
namespace App\Controller;

use App\Repository\UtilisateurRepository;
use App\Repository\ArticleRepository;
use App\Repository\EmplacementRepository;

use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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


    public function __construct(UtilisateurRepository $utilisateurRepository, UserPasswordEncoderInterface $passwordEncoder, ArticleRepository $articleRepository, EmplacementRepository $emplacementRepository)
    {
        $this->emplacementRepository = $emplacementRepository;
        $this->articleRepository = $articleRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->passwordEncoder = $passwordEncoder;
    }

    /**
     * @Rest\Post("/api/connection", name= "test-api")
     * @Rest\View()
     */
    public function connection(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($this->checkLoginPassword($data)) {
                $apiKey = $this->apiKeyGenerator();

                $user = $this->utilisateurRepository->findOneBy(['username' => $data['login']]);
                $user->setApiKey($apiKey);
                $em = $this->getDoctrine()->getManager();
                $em->flush();


                $json = [
                    'apiKey' =>  $apiKey,
                    'data' => $this->getData()
                ];
                dump($json);

                return new JsonResponse($json);
            } else {
                return false;
            }
        }
    }

    /**
     * @Rest\Post("/api/mouvement", name= "test-api")
     * @Rest\View()
     */
    public function mouvement(Request $request)
    {
        if (
            !$request->isXmlHttpRequest()
            && $data = json_decode($request->getContent(), true)
            && ($this->utilisateurRepository->countApiKey($data['apiKey'])) === '1'
        ) {

            return new JsonResponse($json);
        }
    }


    private function checkLoginPassword($data)
    {
        $login = $data['login'];
        $password = $data['password'];
        $user = $this->utilisateurRepository->findOneBy(['username' => $login]);
        $match = $this->passwordEncoder->isPasswordValid($user, $password);
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
