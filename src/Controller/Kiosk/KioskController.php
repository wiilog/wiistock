<?php

namespace App\Controller\Kiosk;

use App\Annotation\HasValidToken;
use App\Controller\AbstractController;
use App\Entity\ArticleFournisseur;
use App\Entity\FreeField;
use App\Entity\KioskToken;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Service\Kiosk\KioskService;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Article;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/borne")
 */
class KioskController extends AbstractController
{
    #[Route("/generate-kiosk-token", name: "generate_kiosk_token", options: ["expose" => true], methods: "GET")]
    public function generateToken(EntityManagerInterface $manager): Response {
        $token = bin2hex(random_bytes(30));
        $date = (new DateTime())->add(new DateInterval('P3D'));

        $existingTokens = $manager->getRepository(KioskToken::class)->findAll();
        foreach ($existingTokens as $existingToken) {
            $manager->remove($existingToken);
        }
        $manager->flush();

        $kioskToken = (new KioskToken())
            ->setUser($this->getUser())
            ->setToken($token)
            ->setExpireAt($date);

        $manager->persist($kioskToken);
        $manager->flush();

        return $this->json([
            'token' => $token
        ]);
    }

    #[Route("/", name: "kiosk_index", options: ["expose" => true])]
    #[HasValidToken]
    public function index(EntityManagerInterface $manager): Response {
        $articleRepository = $manager->getRepository(Article::class);
        $latestsPrint = $articleRepository->getLatestsKioskPrint();

        return $this->render('kiosk/home.html.twig' , [
            'latestsPrint' => $latestsPrint
        ]);
    }

    #[Route("/formulaire", name: "kiosk_form", options: ["expose" => true])]
    #[HasValidToken]
    public function form(Request $request, EntityManagerInterface $entityManager): Response {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $scannedReference = $request->query->get('scannedReference');

        if(str_starts_with($scannedReference, 'ART')){
            $article = $articleRepository->findOneBy(['barCode' => $scannedReference]);
            $reference = $article->getArticleFournisseur()->getReferenceArticle();
        } else {
            $reference = $referenceArticleRepository->findOneBy(['barCode' => $scannedReference]) ?? new ReferenceArticle();
        }
        $freeField = $settingRepository->getOneParamByLabel(Setting::FREE_FIELD_REFERENCE_CREATE) ? $freeFieldRepository->find($settingRepository->getOneParamByLabel(Setting::FREE_FIELD_REFERENCE_CREATE)) : '';

        return $this->render('kiosk/form.html.twig', [
            'reference' => $reference,
            'scannedReference' => $scannedReference,
            'freeField' => $reference?->getType() ? ($reference?->getType()?->getId() === $freeField?->getType()?->getId() ? $freeField : null) : $freeField,
            'inStock' => $reference?->getQuantiteStock() > 0,
            'article' => $article ?? null
        ]);
    }

    #[Route("/check-is-valid", name: "check_article_is_valid", options: ["expose" => true], methods: 'GET|POST')]
    #[HasValidToken]
    public function getArticleExistAndNotActive(Request $request, EntityManagerInterface $entityManager): Response {
        $articleRepository = $entityManager->getRepository(Article::class);
        $article = $articleRepository->findOneBy(['barCode' => $request->query->get('articleLabel')]);
        return new JsonResponse([
            'success' => $article && $article->getStatut()->getCode() === Article::STATUT_INACTIF,
            'fromArticlePage' => $request->query->get('articleLabel') !== null
        ]);
    }

    #[Route("/imprimer", name: "print_article", options: ["expose" => true], methods: ["GET"])]
    public function print(Request                $request,
                                  EntityManagerInterface $entityManager,
                                  KioskService $kioskService): JsonResponse
    {
        $articleRepository = $entityManager->getRepository(Article::class);

        $articleId = $request->query->get('article');
        $reprint = $request->query->get('reprint');
        $testPrint = $request->query->get('testPrint');
        if ($articleId) {
            $article = $articleRepository->find($request->query->get('article'));
        } elseif ($reprint) {
            $article = $articleRepository->getLatestsKioskPrint()[0];
        } elseif ($testPrint) {
            $refArticle = new ReferenceArticle();
            $refArticle->setReference('Test')
                ->setLibelle('Test')
                ->setQuantiteStock(1);

            $article = new Article();
            $article->setLabel('Test')
                ->setBarCode('Test')
                ->setArticleFournisseur((new ArticleFournisseur())->setReferenceArticle($refArticle));

            $options['serialNumber'] = $request->query->get('serialNumber');
            $options['labelWidth'] = $request->query->get('labelWidth');
            $options['labelHeight'] = $request->query->get('labelHeight');
            $options['printerDPI'] = $request->query->get('printerDPI');
        } else {
            return $this->json(['success' => false]);
        }
        $options['text'] = $kioskService->getTextForLabel($article, $entityManager);
        $options['barcode'] = $article->getBarCode();

        $kioskService->printLabel($options, $entityManager);
        return $this->json(['success' => true]);
    }

    #[Route("/kiosk-unlink", name: "kiosk_unlink", options: ["expose" => true], methods: "POST")]
    public function unlink(EntityManagerInterface $manager): Response {
        $tokens = $manager->getRepository(KioskToken::class)->findAll();
        foreach ($tokens as $token) {
            $manager->remove($token);
        }
        $manager->flush();

        return $this->json([
            'success' => true,
            'msg' => 'La borne a bien été déconnectée.'
        ]);
    }
}
