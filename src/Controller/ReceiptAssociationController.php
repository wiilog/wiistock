<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Pack;
use App\Entity\ReceiptAssociation;
use App\Service\CSVExportService;
use App\Service\ReceiptAssociationService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

/**
 * @Route("/association_br")
 */
class ReceiptAssociationController extends AbstractController
{

    private $userService;

    private $receiptAssociationService;

    public function __construct(UserService $userService,
                                ReceiptAssociationService $receiptAssociationService)
    {
        $this->userService = $userService;
        $this->receiptAssociationService = $receiptAssociationService;
    }

    /**
     * @Route("/", name="receipt_association_index", methods={"GET"})
     * @HasPermission({Menu::TRACA, Action::DISPLAY_ASSO})
     */
    public function index(): Response
    {
        return $this->render('receipt_association/modal/index.html.twig');
    }

    /**
     * @Route("/api", name="receipt_association_api", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::TRACA, Action::DISPLAY_ASSO})
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            $data = $this->receiptAssociationService->getDataForDatatable($request->request);

            return $this->json($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="receipt_association_delete", options={"expose"=true}, methods={"GET","POST"})
     * @HasPermission({Menu::TRACA, Action::DELETE})
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $receiptAssociation = $entityManager->getRepository(ReceiptAssociation::class)->find($data['id']);

            $entityManager->remove($receiptAssociation);
            $entityManager->flush();

            return $this->json([
                "success" => true,
                "msg" => "L'association BR a bien été supprimée"
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/creer", name="receipt_association_new", options={"expose"=true}, methods={"GET","POST"})
     * @HasPermission({Menu::TRACA, Action::CREATE})
     */
    public function new(Request $request,
                        EntityManagerInterface $manager,
                        ReceiptAssociationService $receiptAssociationService): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $packs = isset($data['packs']) ? $data['packs'] : null;
            $reception = isset($data['receptionNumber']) ? $data['receptionNumber'] : null;

            if(!$packs) {
                return $this->json([
                    "success" => false,
                    "msg" => "Merci de renseigner au moins un colis pour faire l'association"
                ]);
            } else if(!$reception) {
                return $this->json([
                    "success" => false,
                    "msg" => "Merci de renseigner un numéro de réception pour faire l'association"
                ]);
            } else {
                $user = $this->userService->getUser();

                $packs = $manager->getRepository(Pack::class)->findBy(['id' => $packs]);

                $receiptAssociationService->persistReceiptAssociation($manager, $packs, $reception, $user);

                $manager->flush();
                return $this->json([
                    "success" => true,
                    "msg" => "L'association BR a bien été créée"
                ]);
            }
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/export", name="get_receipt_associations_csv", options={"expose"=true}, methods="GET")
     * @HasPermission({Menu::TRACA, Action::EXPORT})
     */
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

            $today = (new DateTime())->format("d-m-Y-H-i-s");

            $headers = [
                'date',
                'colis',
                'dernier emplacement',
                'date dernier mouvement',
                'réception',
                'utilisateur',
            ];

            return $csvService->streamResponse(function ($output) use ($manager, $csvService, $dateTimeMin, $dateTimeMax) {
                $receiptAssociations = $manager->getRepository(ReceiptAssociation::class)->iterateBetween($dateTimeMin, $dateTimeMax);

                foreach ($receiptAssociations as $receiptAssociation) {
                    $csvService->putLine($output, $receiptAssociation->serialize());
                }
            }, "association-br_${today}.csv", $headers);
        }
        else {
            throw new BadRequestHttpException();
        }
    }
}
