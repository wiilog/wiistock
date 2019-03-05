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
//use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\View\View as RestView;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class ApiController
 * @package App\Controller
 */
class ApiController extends FOSRestController implements ClassResourceInterface
{
    /**
     * @Get("/api/test", name= "test-api")
     * @View()
     */
    public function getUsers(UtilisateurRepository $utilisateurRepository, SerializerInterface $serializer)
    {
        $user = $utilisateurRepository->findAll();
        $json = $serializer->serialize($user, 'json');

        return new JsonResponse($json);
    }
}