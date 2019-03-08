<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 05/03/2019
 * Time: 14:31
 */

namespace App\Controller;

use App\Repository\UtilisateurRepository;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\View\View as RestView;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder;
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoder;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
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
//     * @Get("/api/test", name= "test-api")
//     * @Rest\Post("/api/test", name= "test-api")
     * @Rest\Get("/api/test")
     * @Rest\View()
     */
    public function connection()
    {
        if($this->checkLoginPassword()) {
            //TODO renvoyer en plus la clé (si true)
            return $this->getData();
        } else {
            return false;
        }

    }

    private function checkLoginPassword(UtilisateurRepository $utilisateurRepository, Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        $login = $request->request->get('login');
        $password = $request->request->get('password');

        $user = $utilisateurRepository->findOneBy(['username' => $login]);

        $match = $passwordEncoder->isPasswordValid($user, $password);

        return new JsonResponse($match);
    }

    private function getData()
    {
     //TODO
    }

}