<?php

namespace App\Controller\Kiosk;

use App\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/borne")
 */
class KioskController extends AbstractController
{
    #[Route("/", name: "kiosk_index", options: ["expose" => true], methods: ["GET","POST"])]
    public function showIndex( EntityManagerInterface $entityManager): Response {

        return $this->render('kiosk/pages/home.html.twig', [
        ]);
    }
}
