<?php

namespace App\Controller\Kiosk;

use App\Controller\AbstractController;
use App\Entity\Article;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/borne")
 */
class KioskController extends AbstractController
{
    #[Route("/", name: "kiosk_index")]
    public function index(EntityManagerInterface $manager): Response {
        $refArticleRepository = $manager->getRepository(ReferenceArticle::class);
        $latestsPrint = $refArticleRepository->getLatestsKioskPrint();

        return $this->render('kiosk/home.html.twig' , [
            'latestsPrint' => $latestsPrint
        ]);
    }

    #[Route("/formulaire", name: "kiosk_form")]
    public function form(): Response {
        return $this->render('kiosk/form.html.twig');
    }
}
