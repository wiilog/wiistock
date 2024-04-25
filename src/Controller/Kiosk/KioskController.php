<?php

namespace App\Controller\Kiosk;

use App\Annotation\HasValidToken;
use App\Controller\AbstractController;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\FreeField;
use App\Entity\Kiosk;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Service\Kiosk\KioskService;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Article;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


#[Route("/borne")]
class KioskController extends AbstractController
{

    #[Route("/delete/{kiosk}", name: "kiosk_delete", options: ["expose" => true], methods: self::DELETE, condition: 'request.isXmlHttpRequest()')]
    public function deleteKiosk(EntityManagerInterface $manager,
                                Kiosk                $kiosk): JsonResponse
    {

        $manager->remove($kiosk);
        $manager->flush();

        return $this->json([
            'success' => true,
            'msg' => 'La borne a bien été supprimée.'
        ]);
    }

    #[Route('/edit-api', name: 'kiosk_edit_api', options: ['expose' => true], methods: self::GET, condition: 'request.isXmlHttpRequest()')]
    public function editApi(EntityManagerInterface $manager,
                            Request                $request): JsonResponse
    {
        $kioskId= $request->query->get('id');
        $kioskRepository = $manager->getRepository(Kiosk::class);
        $kiosk = $kioskRepository->find($kioskId);

        if(!$kiosk){
            throw new FormException("La borne n'existe pas.");
        }

        $content = $this->renderView('kiosk/modals/form.html.twig', [
            'kiosk' => $kiosk,
        ]);
        return $this->json([
            'success' => true,
            'html' => $content,
        ]);
    }

    #[Route("/edit", name: "kiosk_edit", options: ["expose" => true], methods: self::POST, condition: 'request.isXmlHttpRequest()')]
    public function editKiosk(Request                   $request,
                              EntityManagerInterface    $entityManager): JsonResponse {
        $inputBag = $request->request;

        // repositories
        $kioskRepository = $entityManager->getRepository(Kiosk::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $requesterRepository = $entityManager->getRepository(Utilisateur::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);

        // find kiosk
        $kiosk = $kioskRepository->find($inputBag->get(FixedFieldEnum::id->name));

        if(!$kiosk){
            throw new FormException("La borne n'existe pas.");
        }

        // find entities
        $pickingType = $typeRepository->find($inputBag->get(FixedFieldEnum::type->name));
        $requester = $requesterRepository->find($inputBag->get(FixedFieldEnum::requester->name));
        $pickingLocation = $locationRepository->find($inputBag->get(FixedFieldEnum::pickingLocation->name));

        $kiosk
            ->setSubject($inputBag->get(FixedFieldEnum::object->name))
            ->setQuantityToPick($inputBag->get(FixedFieldEnum::quantityToPick->name))
            ->setDestination($inputBag->get(FixedFieldEnum::destination->name))
            ->setName($inputBag->get(FixedFieldEnum::name->name) ?? null)
            ->setPickingType($pickingType)
            ->setRequester($requester)
            ->setPickingLocation($pickingLocation);

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => 'La borne a bien été modifiée.'
        ]);
    }

    #[Route("/create", name: "kiosk_create", options: ["expose" => true], methods: self::POST, condition: 'request.isXmlHttpRequest()')]
    public function createKiosk(EntityManagerInterface  $manager,
                                Request                 $request): JsonResponse{
        $inputBag = $request->request;

        // repositories
        $typeRepository = $manager->getRepository(Type::class);
        $locationRepository = $manager->getRepository(Emplacement::class);
        $userRepository = $manager->getRepository(Utilisateur::class);

        // find entities
        $pickingType = $typeRepository->find($inputBag->get(FixedFieldEnum::type->name));
        $requester = $userRepository->find($inputBag->get(FixedFieldEnum::requester->name));
        $pickingLocation = $locationRepository->find($inputBag->get(FixedFieldEnum::pickingLocation->name));

        // create kiosk without token and expiration date (will be set later when user click on "lien externe")
        $kiosk = (new Kiosk())
            ->setSubject($inputBag->get(FixedFieldEnum::object->name))
            ->setQuantityToPick($inputBag->get(FixedFieldEnum::quantityToPick->name))
            ->setDestination($inputBag->get(FixedFieldEnum::destination->name))
            ->setName($inputBag->get(FixedFieldEnum::name->name) ?? null)
            ->setPickingType($pickingType)
            ->setRequester($requester)
            ->setPickingLocation($pickingLocation);

        $manager->persist($kiosk);
        $manager->flush();

        return $this->json([
            'success' => true,
            'msg' => 'La borne a bien été créée.'
        ]);
    }

    #[Route('/api', name: 'kiosk_api', options: ['expose' => true], methods: self::POST, condition: 'request.isXmlHttpRequest()')]
    public function api(Request $request, KioskService $kioskService): JsonResponse {
        $data = $kioskService->getDataForDatatable($request->request);

        return $this->json($data);
    }

    #[Route("/generate-kiosk-token", name: "kiosk_token_generate", options: ["expose" => true], methods: self::GET, condition: 'request.isXmlHttpRequest()')]
    public function generateToken(EntityManagerInterface    $manager, Request $request): JsonResponse
    {
        $kiosk = $manager->getRepository(Kiosk::class)->find($request->query->get('kiosk'));
        $newToken = bin2hex(random_bytes(30));
        $date = (new DateTime())->add(new DateInterval('P3D'));

        if(!$kiosk){
            throw new FormException("La borne n'existe pas.");
        }

        // set token and expiration date
        $kiosk
            ->setToken($newToken)
            ->setExpireAt($date);

        $manager->flush();

        return $this->json([
            'token' => $newToken
        ]);
    }

    #[Route("/", name: "kiosk_index", options: ["expose" => true])]
    #[HasValidToken]
    public function index(EntityManagerInterface $manager): Response
    {
        $articleRepository = $manager->getRepository(Article::class);
        $latestsPrint = $articleRepository->getLatestsKioskPrint();

        return $this->render('kiosk/home.html.twig', [
            'latestsPrint' => $latestsPrint
        ]);
    }

    #[Route("/formulaire", name: "kiosk_form", options: ["expose" => true])]
    #[HasValidToken]
    public function form(Request $request, EntityManagerInterface $entityManager): Response
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $scannedReference = $request->query->get('scannedReference');
        $notExistRefresh = $request->query->getBoolean('notExistRefresh');

        if (str_starts_with($scannedReference, 'ART')) {
            $article = $articleRepository->findOneBy(['barCode' => $scannedReference]);
            $reference = $article->getArticleFournisseur()->getReferenceArticle();
        } else if ($scannedReference){
            $reference = $referenceArticleRepository->findOneBy(['barCode' => $scannedReference])
                ?? $referenceArticleRepository->findOneBy(['reference' => $scannedReference])
                ?? new ReferenceArticle();
            $associatedArticle = $reference->getAssociatedArticles();
            if (count($associatedArticle) == 1) {
                $article = $associatedArticle[0];
            } else {
                $article = null;
            }
        } else {
            $reference = new ReferenceArticle();
        }

        $freeField = $settingRepository->getOneParamByLabel(Setting::FREE_FIELD_REFERENCE_CREATE) ? $freeFieldRepository->find($settingRepository->getOneParamByLabel(Setting::FREE_FIELD_REFERENCE_CREATE)) : '';
        return $this->render('kiosk/form.html.twig', [
            'reference' => $reference,
            'scannedReference' => $scannedReference,
            'freeField' => $reference?->getType() && $freeField instanceof FreeField ? ($reference?->getType()?->getId() === $freeField?->getType()?->getId() ? $freeField : null) : $freeField,
            'inStock' => $reference?->getQuantiteStock() > 0,
            'article' => $article ?? null,
            'notExistRefresh' => $notExistRefresh,
        ]);
    }

    #[Route("/check-is-valid", name: "check_article_is_valid", options: ["expose" => true], methods: [self::POST])]
    #[HasValidToken]
    public function getArticleExistAndNotActive(Request $request, EntityManagerInterface $entityManager): Response
    {
        $articleRepository = $entityManager->getRepository(Article::class);
        $referenceRepository = $entityManager->getRepository(ReferenceArticle::class);

        $article = $articleRepository->findOneBy(['barCode' => $request->query->get('barcode')]);
        $reference = $referenceRepository->findOneBy(['reference' => $request->query->get('referenceLabel')]);

        if($request->query->get('barcode') && !$article){
            return new JsonResponse([
                'success' => false,
                'fromArticlePage' => true,
            ]);
        }

        if($article && $reference && $article->getReferenceArticle() != $reference){
            return new JsonResponse([
                'success' => false,
                'fromArticlePage' => true,
            ]);
        }

        $articleIsNotActive = $article && $article->getStatut()->getCode() === Article::STATUT_INACTIF;
        return new JsonResponse([
            'success' => $articleIsNotActive,
            'fromArticlePage' => $request->query->get('barCode') !== null,
        ]);
    }

    #[Route("/unlink-kiosk-token/{kiosk}", name: "kiosk_unlink_token", options: ["expose" => true], methods: self::POST, condition: "request.isXmlHttpRequest()")]
    public function unlinkKioskToken(EntityManagerInterface $manager,
                                     Kiosk                  $kiosk): JsonResponse {

        $kiosk->setToken(null);
        $manager->flush();

        return $this->json([
            'success' => true,
            'msg' => 'La borne '.$kiosk->getName().' a bien été déconnectée.'
        ]);
    }

    #[Route("/kiosk-print", name: "kiosk_print", options: ["expose" => true])]
    public function printLabel(EntityManagerInterface $entityManager, Request $request, KioskService $kioskService){
        $articleRepository = $entityManager->getRepository(Article::class);
        $data = json_decode($request->query->get('barcodesToPrint'), true) ?? [];

        $articleId = $request->query->get('article');
        $reprint = $request->query->get('reprint');
        if ($articleId) {
            $article = $articleRepository->find($articleId);
        } elseif ($reprint) {
            $article = $articleRepository->getLatestsKioskPrint()[0];
        }

        return $kioskService->testPrintWiispool($data, $article ?? null);
    }
}
