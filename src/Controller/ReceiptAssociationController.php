<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\ReceiptAssociation;
use App\Exceptions\FormException;
use App\Service\CSVExportService;
use App\Service\ReceiptAssociationService;
use App\Service\TranslationService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
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
    public function api(Request                $request,
                        EntityManagerInterface $entityManager): Response
    {
        $data = $this->receiptAssociationService->getDataForDatatable($entityManager, $request->request);

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
                               EntityManagerInterface $entityManager,
                               TranslationService     $translation): Response
    {
        $post = $request->request;
        $user = $this->userService->getUser();

        $receiptAssociationRepository = $entityManager->getRepository(ReceiptAssociation::class);

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

        $receiptAssociations = $this->receiptAssociationService->persistReceiptAssociation($entityManager, $receptionNumbers, $logisticUnitCodes, $user);

        foreach($receiptAssociations as $receiptAssociation) {
            if ($receiptAssociation->getLogisticUnits()->isEmpty()) {
                $existingReceiptAssociations = $receiptAssociationRepository->findBy(['receptionNumber' => $receiptAssociation->getReceptionNumber()]);
                $existingAssociationWithoutLogisticUnit = !Stream::from($existingReceiptAssociations)
                    ->filter(fn(ReceiptAssociation $existingReceiptAssociation) => $existingReceiptAssociation->getLogisticUnits()->isEmpty())
                    ->isEmpty();

                if ($existingAssociationWithoutLogisticUnit) {
                    throw new FormException($translation->translate('Traçabilité', 'Association BR', "Une association sans unité logistique avec ce numéro de réception existe déjà"));
                }
            }
        }

        $entityManager->flush();
        return $this->json([
            "success" => true,
            "msg" => "{$translation->translate('Traçabilité', 'Association BR', "L'association BR a bien été créée")}."
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
            $user = $this->getUser();

            $headers = [
                $translationService->translate('Traçabilité', 'Général', 'Date',false),
                $translationService->translate('Traçabilité', 'Général', 'Unité logistique',false),
                $translationService->translate('Traçabilité', 'Association BR', 'Réception',false),
                $translationService->translate('Traçabilité', 'Général', 'Utilisateur',false),
                $translationService->translate('Traçabilité', 'Général', 'Date dernier mouvement',false),
                $translationService->translate('Traçabilité', 'Général', 'Dernier emplacement',false),
            ];

            return $csvService->streamResponse(function ($output) use ($manager, $csvService, $dateTimeMin, $dateTimeMax,$receiptAssociationService, $user) {
                $receiptAssociations = $manager->getRepository(ReceiptAssociation::class)->getByDates($dateTimeMin, $dateTimeMax, $user->getDateFormat());
                foreach ($receiptAssociations as $receiptAssociation) {
                    $receiptAssociationService->receiptAssociationPutLine($output, $receiptAssociation);
                }
            }, "association-br_$today.csv", $headers);
        } else {
            throw new BadRequestHttpException();
        }
    }
}
