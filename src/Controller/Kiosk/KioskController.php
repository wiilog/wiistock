<?php

namespace App\Controller\Kiosk;

use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/borne")
 */
class KioskController extends AbstractController
{
    #[Route("/", name: "kiosk_index")]
    public function index(): Response {
        return $this->render('kiosk/home.html.twig');
    }

    #[Route("/formulaire", name: "kiosk_form")]
    public function form(): Response {
        return $this->render('kiosk/form.html.twig');
    }
}
