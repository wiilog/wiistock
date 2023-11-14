<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Pack;
use App\Entity\ReceiptAssociation;
use App\Entity\Setting;
use App\Exceptions\FormException;
use App\Service\CSVExportService;
use App\Service\ReceiptAssociationService;
use App\Service\TranslationService;
use App\Service\UserService;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;
use WiiCommon\Helper\Stream;

#[Route("/association-br")]
class ReceiptAssociationController extends AbstractController
{

    #[Required]
    public UserService $userService;

    #[Required]
    public ReceiptAssociationService $receiptAssociationService;

    #[Required]
    public TranslationService $translation;

    #[Route("/", name: "receipt_association_index", methods: "GET")]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_ASSO])]
    public function index(): Response
    {
        return $this->render('receipt_association/index.html.twig');
    }

    #[Route("/api", name: "receipt_association_api", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_ASSO])]
    public function api(Request $request): Response
    {
        $data = $this->receiptAssociationService->getDataForDatatable($request->request);

        return $this->json($data);
    }

    #[Route("/delete", name: "receipt_association_delete", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::DELETE])]
    public function delete(Request                $request,
                           EntityManagerInterface $entityManager,
                           TranslationService     $translation): Response
    {
        $data = json_decode($request->getContent(), true);
        $receiptAssociation = $entityManager->getRepository(ReceiptAssociation::class)->find($data['id']);

        $entityManager->remove($receiptAssociation);
        $entityManager->flush();

        return $this->json([
            "success" => true,
            "msg" => $translation->translate('Traçabilité', 'Association BR', "L'association BR a bien été supprimée")
        ]);
    }

    #[Route("/form-submit", name: "receipt_association_form_submit", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::CREATE])]
    public function formSubmit(Request                $request,
                               EntityManagerInterface $manager,
                               TranslationService     $translation): Response
    {
        $post = $request->request;
        $user = $this->userService->getUser();
        $now = new DateTime('now');

        $settingRepository = $manager->getRepository(Setting::class);
        $logisticUnitRepository = $manager->getRepository(Pack::class);

        $logisticUnitCodes = $post->getBoolean("existingLogisticUnits")
            ? Stream::from(json_decode($post->get("logisticUnits"), true))
                ->flatten()
                ->filter(static fn(string $code) => $code)
                ->unique()
                ->toArray()
            : [];

        $receptionNumbers = Stream::from(json_decode($post->get("receptionNumbers"), true))
            ->flatten()
            ->toArray();

        $logisticUnits = $logisticUnitRepository->findBy(["code" => $logisticUnitCodes]);

        if (count($logisticUnits) !== count($logisticUnitCodes)) {
            $invalidLogisticUnits = Stream::diff(
                $logisticUnitCodes,
                Stream::from($logisticUnits)
                    ->map(static fn(Pack $logisticUnit) => $logisticUnit->getCode())
                    ->toArray()
            )->toArray();

            if (count($invalidLogisticUnits) > 1) {
                $joinedInvalidLogisticUnits = implode(", ", $invalidLogisticUnits);
                throw new FormException("Les unités logistiques <strong>$joinedInvalidLogisticUnits</strong> n'existent pas.");
            } else {
                throw new FormException("L'unité logistique <strong>$invalidLogisticUnits[0]</strong> n'existe pas.");
            }
        }

        if (empty($logisticUnits)) {
            $receiptAssociations = $manager->getRepository(ReceiptAssociation::class)->findBy(['receptionNumber' => $receptionNumbers]);
            $existingAssociationWithoutLogisticUnit = !Stream::from($receiptAssociations)
                ->filter(fn(ReceiptAssociation $receiptAssociation) => $receiptAssociation->getLogisticUnits()->isEmpty())
                ->isEmpty();

            if ($existingAssociationWithoutLogisticUnit) {
                throw new FormException($translation->translate('Traçabilité', 'Association BR', "Une association sans unité logistique avec ce numéro de réception existe déjà"));
            }
        }

        foreach ($receptionNumbers as $receptionNumber) {
            $receiptAssociation = (new ReceiptAssociation())
                ->setReceptionNumber($receptionNumber)
                ->setUser($user)
                ->setCreationDate($now);

            if (!empty($logisticUnits)) {
                $receiptAssociation->setLogisticUnits(new ArrayCollection($logisticUnits));
            }

            $manager->persist($receiptAssociation);
        }

        if ($settingRepository->getOneParamByLabel(Setting::BR_ASSOCIATION_DEFAULT_MVT_LOCATION_UL)
            && $settingRepository->getOneParamByLabel(Setting::BR_ASSOCIATION_DEFAULT_MVT_LOCATION_RECEPTION_NUM)) {
            $this->receiptAssociationService->createMovements($receptionNumbers, $logisticUnits);
        }

        $manager->flush();
        return $this->json([
            "success" => true,
            "msg" => "{$translation->translate('Traçabilité', 'Association BR', "L'association BR a bien été créée")}."
        ]);
    }

    #[Route("/form-template", name: "receipt_association_form_template", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    public function formTemplate(): Response
    {
        return $this->json([
            "html" => $this->renderView("receipt_association/modal/form.html.twig"),
        ]);
    }

    #[Route("/export", name: "get_receipt_associations_csv", options: ["expose" => true], methods: "GET")]
    #[HasPermission([Menu::TRACA, Action::EXPORT])]
    public function export(EntityManagerInterface    $manager,
                           Request                   $request,
                           CSVExportService          $csvService,
                           ReceiptAssociationService $receiptAssociationService,
                           TranslationService        $translationService): Response
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
                $translationService->translate('Traçabilité', 'Général', 'Date',false),
                $translationService->translate('Traçabilité', 'Général', 'Unité logistique',false),
                $translationService->translate('Traçabilité', 'Association BR', 'Réception',false),
                $translationService->translate('Traçabilité', 'Général', 'Utilisateur',false),
                $translationService->translate('Traçabilité', 'Général', 'Date dernier mouvement',false),
                $translationService->translate('Traçabilité', 'Général', 'Dernier emplacement',false),
            ];

            return $csvService->streamResponse(function ($output) use ($manager, $csvService, $dateTimeMin, $dateTimeMax,$receiptAssociationService) {
                $receiptAssociations = $manager->getRepository(ReceiptAssociation::class)->getByDates($dateTimeMin, $dateTimeMax);
                foreach ($receiptAssociations as $receiptAssociation) {
                    $receiptAssociationService->receiptAssociationPutLine($output, $receiptAssociation);
                }
            }, "association-br_$today.csv", $headers);
        } else {
            throw new BadRequestHttpException();
        }
    }
}
