<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 05/03/2019
 * Time: 14:31
 */

namespace App\Controller;

use App\Entity\Article;
use App\Repository\UtilisateurRepository;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\View\View as RestView;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

/**
 * Class ApiController
 * @package App\Controller
 */
class ApiController extends FOSRestController implements ClassResourceInterface
{
    /**
//     * @Get("/api/test", name= "test-api")
//     * @Rest\Post("/api/test", name= "test-api")
     * @Rest\Get("/api/test")
     * @Rest\View()
     */
    public function getUsers(UtilisateurRepository $utilisateurRepository, SerializerInterface $serializer, Request $request)
    {
        $login = $request->request->get('login');
        $password = $request->request->get('password');

        $user = $utilisateurRepository->findBy(['username' => $login]);
        $json = $serializer->serialize($user, 'json');

        return new JsonResponse($json);
    }

//    /**
//     * @Rest\Post("/article")
//     * @Rest\View
//     * @ParamConverter("article", converter="fos_rest.request_body")
//     */
//    public function createArticle(Article $article)
//    {
//        dump($article);die;
//    }
}