<?php

namespace App\Controller\Kiosk;

use App\Controller\AbstractController;
use App\Entity\FreeField;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\Type;
use App\Service\Kiosk\KioskService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    #[Route("/", name: "kiosk_index", options: ["expose" => true])]
    public function index(EntityManagerInterface $manager): Response {
        $articleRepository = $manager->getRepository(Article::class);
        $latestsPrint = $articleRepository->getLatestsKioskPrint();

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

        $freeField = $settingRepository->getOneParamByLabel(Setting::FREE_FIELD_REFERENCE_CREATE) ? $freeFieldRepository->find($settingRepository->getOneParamByLabel(Setting::FREE_FIELD_REFERENCE_CREATE)) : '';
        $reference = $referenceArticleRepository->findOneBy(['reference' => $scannedReference]) ?? new ReferenceArticle();

        return $this->render('kiosk/form.html.twig', [
            'reference' => $reference,
            'scannedReference' => $scannedReference,
            'freeField' => $freeField,
            'inStock' => $reference?->getQuantiteStock() > 0,
        ]);
    }

    #[Route("/check-is-valid", name: "check_article_is_valid", options: ["expose" => true], methods: 'GET|POST')]
    public function getArticleExistAndNotActive(Request $request, EntityManagerInterface $entityManager): Response {
        $articleRepository = $entityManager->getRepository(Article::class);
        $article = $articleRepository->findOneBy(['barCode' => $request->request->get('articleLabel')]);
        return new JsonResponse([
            'success' => $article && $article->getStatut()->getCode() === Article::STATUT_INACTIF,
            'fromArticlePage' => $request->request->get('articleLabel') !== null
        ]);
    }

    #[Route("/imprimer", name: "print_article", options: ["expose" => true], methods: ["GET"])]
    public function print(Request                $request,
                                  EntityManagerInterface $entityManager,
                                  KioskService $kioskService): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $articleRepository = $entityManager->getRepository(Article::class);

        $articleId = $request->query->get('article');
        $reprint = $request->query->get('reprint');
        if ($articleId) {
            $article = $articleRepository->find($request->query->get('article'));
        } elseif ($reprint) {
            $article = $articleRepository->getLatestsKioskPrint()[0];
        } else {
            return $this->json(['success' => false]);
        }
        $options['text'] = $kioskService->getTextForLabel($article, $entityManager);
        $options['barcode'] = $article->getBarCode();

        $kioskService->printLabel($options, $entityManager);
        return $this->json(['success' => true]);
    }
}
