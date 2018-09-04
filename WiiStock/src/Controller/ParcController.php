<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\ParcsRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * @Route("/parc")
 */

class ParcController extends AbstractController
{
    
	/**
 	 * @Route("/list")
 	 */    
    public function index(ParcsRepository $parcsRepository, Request $request)
    {
        $encoders = array(new JsonEncoder());
        $normalizers = array(new ObjectNormalizer());

        $serializer = new Serializer($normalizers, $encoders);

        // Ajax
        if ($request->isXmlHttpRequest()) {
            $parcs = $parcsRepository->findAll();

            // TODO : gestion des filtres
            // 

            $jsonContent = $serializer->serialize($parcs, 'json');
            return new JsonResponse($jsonContent);
        }


        return $this->render('parc/index.html.twig', [
            'controller_name' => 'ParcController',
        ]);
    }

    /**
     * @Route("/create")
     */
    public function create() 
    {
    	return $this->render('parc/create.html.twig', [
    		'controller_name' => 'ParcController',
    	]);
    }

    /**
     * @Route("/modify")
     */
    public function modify()
    {
    	return $this->render('parc/modify.html.twig', [
    		'controller_name' => 'ParcController',
    	]);
    }
}
