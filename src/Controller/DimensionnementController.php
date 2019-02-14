<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use App\Entity\Entrepots;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Serializer;

class DimensionnementController extends Controller
{
    /**
     * @Route("/stock/admin/dimensionnement", name="dimensionnement")
     */
    public function index()
    {
        return $this->render('dimensionnement/index.html.twig', [
            'controller_name' => 'DimensionnementController',
            'entrepots' => $this->getEntrepots(),
        ]);
    }

    /**
     * @Route("/stock/admin/entrepots", name="getAjaxEntrepots", methods="GET")
     */
    public function getAjaxEntrepots(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            return new Response($this->getEntrepots());
        }
        throw new NotFoundHttpException('404 not found');
    }

    private function getEntrepots()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new ObjectNormalizer($classMetadataFactory);
        $serializer = new Serializer([$normalizer], [new JsonEncode()]);

        $entrepots = $this->getDoctrine()->getManager()->getRepository(Entrepots::class)->findAll();
        $data = $serializer->serialize($entrepots, 'json', array('groups' => array('entrepots')));

        return $data;
    }
}
