<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 05/03/2019
 * Time: 14:31
 */

namespace App\Controller;

// use App\Entity\Article;

use App\Repository\UtilisateurRepository;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
// use FOS\RestBundle\Controller\Annotations\View;
// use FOS\RestBundle\Controller\Annotations\Get;
// use FOS\RestBundle\Controller\Annotations\Post;
// use FOS\RestBundle\View\View as RestView;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;

use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

// use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

/**
 * Class ApiController
 * @package App\Controller
 */
class ApiController extends FOSRestController implements ClassResourceInterface
{
    /**
     * @Rest\Post("/api/test", name= "test-api")
     */
    public function getUsers(
        UtilisateurRepository $utilisateurRepository,
        SerializerInterface $serializer,
        Request $request,
        AuthenticationUtils $authenticationUtils
    ) {
        $data = json_decode($request->getContent(), true);
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) //Si la requête est de type Xml
            {
                $login = $data['login'];
                $password = $data['password'];
                dump($login, $password);
                $user = $utilisateurRepository->findBy(['username' => $login]);
                $error = $authenticationUtils->getLastAuthenticationError();
                // last username entered by the user
                $lastUsername = $authenticationUtils->getLastUsername();
                dump($user, $lastUsername);
                // $json = $serializer->serialize($user, 'json');

                return new JsonResponse('helloWorl254');
            }
        throw new NotFoundHttpException("404");
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
