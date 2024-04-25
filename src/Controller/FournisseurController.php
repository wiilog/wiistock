<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fournisseur;
use App\Entity\Menu;
use App\Entity\Reception;
use App\Exceptions\FormException;
use App\Service\CSVExportService;
use App\Service\FormatService;
use App\Service\FournisseurDataService;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[Route('/fournisseur', name: 'supplier_')]
class FournisseurController extends AbstractController {

    #[Route("/api", name: "api", options: ["expose" => true], methods: [self::POST], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::DISPLAY_FOUR], mode: HasPermission::IN_JSON)]
    public function api(Request $request,
                        FournisseurDataService $fournisseurDataService): Response {
        $data = $fournisseurDataService->getFournisseurDataByParams($request->request);

        return $this->json($data);
    }


    #[Route("/", name: "index", methods: [self::GET])]
    #[HasPermission([Menu::REFERENTIEL, Action::DISPLAY_FOUR])]
    public function index(): Response {
        return $this->render('fournisseur/index.html.twig', [
            'supplier' => new Fournisseur(),
        ]);
    }

    #[Route("/creer", name: "new", options: ["expose" => true], methods: [self::POST], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::CREATE], mode: HasPermission::IN_JSON)]
    public function new(Request                 $request,
                        FournisseurDataService  $fournisseurDataService,
                        EntityManagerInterface  $entityManager): Response {
        $supplier = $fournisseurDataService->editSupplier((new Fournisseur()), $request->request, $entityManager);
        $entityManager->persist($supplier);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'id' => $supplier->getId(),
            'text' => $supplier->getCodeReference(),
            'msg' => "Le fournisseur a été crée avec succès.",
        ]);
}

    #[Route("/api-modifier/{supplier}", name: "api_edit", options: ["expose" => true], methods: [self::GET], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function apiEdit(Fournisseur $supplier): JsonResponse {
        return new JsonResponse([
            "success" => true,
            "html" => $this->renderView('fournisseur/modal/form.html.twig', [
                'supplier' => $supplier,
            ]),
        ]);
    }

    #[Route("/modifier", name: "edit",  options: ["expose" => true], methods: [self::POST], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function edit(Request $request,
                         FournisseurDataService $fournisseurDataService,
                         EntityManagerInterface $entityManager): Response {
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $data = $request->request;
        $supplier = $fournisseurRepository->find($data->getInt('id'));
        if (!$supplier) {
            Throw new BadRequestHttpException("Fournisseur introuvable");
        }

        $fournisseurDataService->editSupplier($supplier, $data, $entityManager);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => "Le fournisseur a bien été modifié",
        ]);
    }

    #[Route("/supprimer/{supplier}", name: "delete",  options: ["expose" => true], methods: [self::DELETE], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function delete(Fournisseur              $supplier,
                           FournisseurDataService   $fournisseurDataService,
                           EntityManagerInterface   $entityManager): Response {

        $usedFournisseur = $fournisseurDataService->isSupplierUsed($supplier, $entityManager);
        if (!empty($usedFournisseur)) {
            throw new FormException("Ce fournisseur ne peut pas être supprimé car il est utilisé dans les réceptions suivantes : " . implode(", ", $usedFournisseur));
        }
        $entityManager->remove($supplier);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => "Le fournisseur a bien été supprimé"
        ]);
    }

    #[Route("/get-label-fournisseur", name: "find_by_name", options: ["expose" => true], methods: [self::GET])]
    public function getLabelsSupplier(Request $request, EntityManagerInterface $entityManager): Response {
        $search = $request->query->get('term');
        $supplierRepository = $entityManager->getRepository(Fournisseur::class);

        $supplier = $supplierRepository->getIdAndLabelseBySearch($search);
        return $this->json([
            'results' => $supplier
        ]);
    }

    #[Route("/export", name: "csv", options: ["expose" => true], methods: [self::GET])]
    #[HasPermission([Menu::REFERENTIEL, Action::EXPORT])]
    public function export(EntityManagerInterface   $manager,
                           CSVExportService         $csvService,
                           FormatService            $formatService): Response {

        $now = (new DateTime())->format("d-m-Y-H-i-s");

        $headers = [
            FixedFieldEnum::name->value,
            'Code',
            'Possible douane',
            FixedFieldEnum::urgent->value,
            FixedFieldEnum::address->value,
            FixedFieldEnum::receiver->value,
            FixedFieldEnum::phoneNumber->value,
            FixedFieldEnum::email->value,
        ];

        return $csvService->streamResponse(function ($output) use ($formatService, $manager, $csvService) {
            $suppliers = $manager->getRepository(Fournisseur::class)->getForExport();

            foreach ($suppliers as $supplier) {
                $csvService->putLine($output, $supplier->serialize($formatService));
            }
        }, "fournisseurs_$now.csv", $headers);
    }
}
