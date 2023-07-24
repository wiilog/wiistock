<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Pack;
use App\Entity\ReceiptAssociation;
use App\Entity\Setting;
use App\Service\CSVExportService;
use App\Service\ReceiptAssociationService;
use App\Service\TranslationService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;
use WiiCommon\Helper\Stream;

#[Route("/association_br")]
class ReceiptAssociationController extends AbstractController
{

    #[Required]
    public UserService $userService;

    #[Required]
    public ReceiptAssociationService $receiptAssociationService;

    #[Route("/", name: "receipt_association_index", methods: ["GET"])]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_ASSO])]
    public function index(): Response
    {
        return $this->render('receipt_association/index.html.twig');
    }

    #[Route("/api", name: "receipt_association_api", options: ["expose" => true], methods: ["GET", "POST"])]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_ASSO])]
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            $data = $this->receiptAssociationService->getDataForDatatable($request->request);

            return $this->json($data);
        }
        throw new BadRequestHttpException();
    }

    #[Route("/supprimer", name: "receipt_association_delete", options: ["expose" => true], methods: ["GET", "POST"])]
    #[HasPermission([Menu::TRACA, Action::DELETE])]
    public function delete(Request $request,
                           EntityManagerInterface $entityManager,
                           TranslationService $translation): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $receiptAssociation = $entityManager->getRepository(ReceiptAssociation::class)->find($data['id']);

            $entityManager->remove($receiptAssociation);
            $entityManager->flush();

            return $this->json([
                "success" => true,
                "msg" => $translation->translate('Traçabilité', 'Association BR', "L'association BR a bien été supprimée")
            ]);
        }

        throw new BadRequestHttpException();
    }

    #[Route("/creer", name: "receipt_association_new", options: ["expose" => true], methods: ["GET", "POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::CREATE])]
    public function new(Request $request,
                        EntityManagerInterface $manager,
                        TranslationService $translation): Response
    {
        $settingRepository = $manager->getRepository(Setting::class);

        $data = json_decode($request->getContent(), true);
        $packs = $data['packCode'] ?? null;
        $receptions = $data['receptionNumber'] ?? null;

        $packsStr = str_replace(['[', ']', '"'], '', $packs);
        $receptionsStr = str_replace(['[', ']', '"'], '', $receptions);

        $packs = Stream::explode(",", $packsStr)->toArray();
        $receptions = Stream::explode(",", $receptionsStr)->toArray();

        $existingPacks = $manager->getRepository(Pack::class)->findBy(['code' => $packs]);
        $packsForMovements = $existingPacks;
        $existingPacks = Stream::from($existingPacks)->map(fn(Pack $pack) => $pack->getCode())->toArray();
        $invalidPacks = Stream::diff($existingPacks, $packs)->toArray();
        if(!empty($invalidPacks)) {
            $invalidPacksStr = implode(", ", $invalidPacks);
            return $this->json([
                'success' => false,
                'msg' =>  $translation->translate('Traçabilité', 'Association BR', "Les unités logistiques suivantes n'existent pas :") . $invalidPacksStr
            ]);
        }

        if(empty($receptions)) {
            return $this->json([
                'success' => false,
                'msg' => $translation->translate('Traçabilité', 'Association BR', "Un numéro de réception minimum est requis pour procéder à l'association")
            ]);
        }

        if (empty($packs)) {
            $receiptAssociations = $manager->getRepository(ReceiptAssociation::class)->findBy(['receptionNumber' => $receptions]);
            $existingAssociationWithoutPack = !Stream::from($receiptAssociations)
                ->filter(fn(ReceiptAssociation $receiptAssociation) => !$receiptAssociation->getPackCode())
                ->isEmpty();

            if ($existingAssociationWithoutPack) {
                return $this->json([
                    "success" => false,
                    "msg" => $translation->translate('Traçabilité', 'Association BR', "Une association sans unité logistique avec ce numéro de réception existe déjà")
                ]);
            }
        }

        $user = $this->userService->getUser();
        $now = new DateTime('now');

        foreach ($receptions as $reception) {
            $receiptAssociation = (new ReceiptAssociation())
                ->setReceptionNumber($reception)
                ->setUser($user)
                ->setCreationDate($now)
                ->setPackCode(str_replace(",", ", ", $packsStr));

            $manager->persist($receiptAssociation);
        }

        if ($settingRepository->getOneParamByLabel(Setting::BR_ASSOCIATION_DEFAULT_MVT_LOCATION_UL)
            && $settingRepository->getOneParamByLabel(Setting::BR_ASSOCIATION_DEFAULT_MVT_LOCATION_RECEPTION_NUM)) {
            $this->receiptAssociationService->createMovements($receptions, $packsForMovements);
        }

        $manager->flush();
        return $this->json([
            "success" => true,
            "msg" => $translation->translate('Traçabilité', 'Association BR', "L'association BR a bien été créée")
        ]);
    }

    #[Route("/export", name: "get_receipt_associations_csv", options: ["expose" => true], methods: ["GET"])]
    #[HasPermission([Menu::TRACA, Action::EXPORT])]
    public function export(EntityManagerInterface $manager,
                           Request $request,
                           CSVExportService $csvService): Response
    {

        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (Throwable $throwable) {
            return $this->json([
                "success" => false,
                "msg" => "Les dates renseignées sont invalides"
            ]);
        }

        if (!empty($dateTimeMin) && !empty($dateTimeMax)) {
            $today = (new DateTime('now'))->format("d-m-Y-H-i-s");

            $headers = [
                'date',
                'unité logistique',
                'réception(s)',
                'utilisateur',
            ];

            return $csvService->streamResponse(function ($output) use ($manager, $csvService, $dateTimeMin, $dateTimeMax) {
                $receiptAssociations = $manager->getRepository(ReceiptAssociation::class)->iterateBetween($dateTimeMin, $dateTimeMax);

                foreach ($receiptAssociations as $receiptAssociation) {
                    $csvService->putLine($output, $receiptAssociation->serialize($this->getUser()));
                }
            }, "association-br_$today.csv", $headers);
        }
        else {
            throw new BadRequestHttpException();
        }
    }
}
