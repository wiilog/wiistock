<?php

namespace App\Controller\Kiosk;

use App\Controller\AbstractController;
use App\Entity\FreeField;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\Type;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Article;
use App\Entity\Utilisateur;
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

    #[Route("/formulaire", name: "kiosk_form", options: ["expose" => true])]
    public function form(Request $request, EntityManagerInterface $entityManager): Response {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $scannedReference = $request->query->get('scannedReference');

        $freeField = $freeFieldRepository->find($settingRepository->getOneParamByLabel(Setting::FREE_FIELD_REFERENCE_CREATE));
        $reference = $referenceArticleRepository->findOneBy(['reference' => $scannedReference]) ?? new ReferenceArticle();

        return $this->render('kiosk/form.html.twig', [
            'reference' => $reference,
            'scannedReference' => $scannedReference,
            'freeField' => $freeField
        ]);
    }
}
