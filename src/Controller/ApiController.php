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
    private $utilisateurRepository;
    private $passwordEncoder;
    private $successData;


    public function __construct(UtilisateurRepository $utilisateurRepository, UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->utilisateurRepository = $utilisateurRepository;
        $this->passwordEncoder = $passwordEncoder;
        $this->successData = ['success' => false, 'data' => ''];
    }

    /**
//     * @Get("/api/test", name= "test-api")
//     * @Rest\Post("/api/test", name= "test-api")
     * @Rest\Post("/api/test")
     */
    public function connection(Request $request)
    {
        $login = $request->request->get('login');
        $password = $request->request->get('password');

        if($this->checkLoginPassword($login, $password)) {
            //TODO renvoyer en plus la clé
            $this->successData['success'] = true;
            $this->successData['data'] = $this->getData();
        }

        return new JsonResponse($this->successData);

    }

    private function checkLoginPassword($login, $password)
    {
        $user = $this->utilisateurRepository->findOneBy(['username' => $login]);

        if ($user) {
            return $this->passwordEncoder->isPasswordValid($user, $password);
        } else {
            return false;
        }

    }

    private function getData()
    {
     //TODO
        return true;
    }

}