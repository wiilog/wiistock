<?php

namespace App\Controller;

use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use App\Entity\Menu;
use App\Entity\Action;

use App\Entity\Pack;
use App\Entity\Project;
use App\Entity\ProjectHistoryRecord;
use App\Entity\Utilisateur;
use App\Service\CSVExportService;
use App\Service\ProjectService;
use App\Service\TranslationService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Annotation\HasPermission;

/**
 * @Route("/project")
 */
class ProjectController extends AbstractController
{

    const MAX_CHAR_CODE_PROJET = 20;

    #[Route('/liste', name: 'project_index', methods: 'GET')]
    #[HasPermission([Menu::REFERENTIEL, Action::DISPLAY_PROJECTS])]
    public function index(): Response {
        return $this->render('project/index.html.twig', [
            'newProject' => new Project(),
        ]);
    }

    #[Route('/api', name: 'project_api', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::REFERENTIEL, Action::DISPLAY_PROJECTS], mode: HasPermission::IN_JSON)]
    public function api(Request $request, ProjectService $projectService): Response {
        $data = $projectService->getDataForDatatable($request->request);

        return $this->json($data);
    }

    #[Route('/new', name: 'project_new', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::REFERENTIEL, Action::CREATE], mode: HasPermission::IN_JSON)]
    public function new(Request                     $request,
                        EntityManagerInterface      $manager,
                        TranslationService          $translation): Response
    {
        $data = $request->request->all();

        $codeExisting = $data['code'];
        $existing = $manager->getRepository(Project::class)->findOneBy(['code' => $codeExisting]);

        if ($existing) {
            return $this->json([
                'success' => false,
                'msg' => 'Un ' . mb_strtolower($translation->translate('Référentiel', 'Projet', 'Projet', false)) . ' avec ce code existe déjà'
            ]);
        } else if (mb_strlen($data['code']) > $this::MAX_CHAR_CODE_PROJET) {
            return $this->json([
                'success' => false,
                'msg' => 'La longueur maximale du code est de ' . $this::MAX_CHAR_CODE_PROJET .  ' caractères',
            ]);
        } else {
            $projectManager = $manager->getRepository(Utilisateur::class)->findOneBy(['id' => $data['projectManager']]);
            $project = (new Project())
                ->setCode($codeExisting)
                ->setProjectManager($projectManager)
                ->setDescription($data['description'] ?? null)
                ->setActive($data['isActive']);

            $manager->persist($project);
            $manager->flush();

            return $this->json([
                'success' => true,
                'msg' => 'Le ' . mb_strtolower($translation->translate('Référentiel', 'Projet', 'Projet', false)) . ' a bien été créé'
            ]);
        }
    }

    #[Route('/edit-api', name: 'project_edit_api', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::REFERENTIEL, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function editApi(EntityManagerInterface $manager,
                            Request                $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $project = $manager->find(Project::class, $data['id']);

        $content = $this->renderView('project/modal/form.html.twig', [
            'project' => $project,
        ]);
        return $this->json($content);
    }

    #[Route('/edit', name: 'project_edit', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::REFERENTIEL, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function edit(Request                    $request,
                         EntityManagerInterface     $manager,
                         TranslationService         $translation): Response
    {
        $data = $request->request->all();
        $project = $manager->find(Project::class, $data['id']);

        $codeExisting = $data['code'];
        $existing = $manager->getRepository(Project::class)->findOneBy(['code' => $codeExisting]);

        if ($existing && $existing !== $project) {
            return $this->json([
                'success' => false,
                'msg' => 'Un ' . mb_strtolower($translation->translate('Référentiel', 'Projet', 'Projet', false)) . ' avec ce code existe déjà'
            ]);
        } else if (mb_strlen($data['code']) > $this::MAX_CHAR_CODE_PROJET) {
            return $this->json([
                'success' => false,
                'msg' => 'La longueur maximale du code est de ' . $this::MAX_CHAR_CODE_PROJET .  ' caractères',
            ]);
        } else {
            $projectManager = $manager->getRepository(Utilisateur::class)->findOneBy(['id' => $data['projectManager']]);
            $project
                ->setCode($codeExisting)
                ->setProjectManager($projectManager)
                ->setDescription($data['description'] ?? null)
                ->setActive($data['isActive']);

            $manager->persist($project);
            $manager->flush();

            return $this->json([
                'success' => true,
                'msg' => 'Le ' . mb_strtolower($translation->translate('Référentiel', 'Projet', 'Projet', false)) . ' a bien été modifiée'
            ]);
        }
    }

    #[Route('/delete-check', name: 'project_check_delete', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::REFERENTIEL, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function checkProjectCanBeDeleted(Request                $request,
                                             EntityManagerInterface $manager,
                                             TranslationService     $translation): Response
    {
        $id = json_decode($request->getContent(), true);
        $projectRepository = $manager->getRepository(Project::class);
        $packRepository = $manager->getRepository(Pack::class);
        $projectHistoryRecordRepository = $manager->getRepository(ProjectHistoryRecord::class);
        $deliveryRequestReferenceLineRepository = $manager->getRepository(DeliveryRequestReferenceLine::class);
        $deliveryRequestArticleLineRepository = $manager->getRepository(DeliveryRequestArticleLine::class);

        $project = $projectRepository->find($id);
        $logisticUnitCount = $packRepository->count(['project' => $project]);
        $projectHistoryRecordCount = $projectHistoryRecordRepository->count(['project' => $project]);
        $requestLineCount = (
            $deliveryRequestReferenceLineRepository->count(['project' => $project])
            + $deliveryRequestArticleLineRepository->count(['project' => $project])
        );

        return $this->json([
            'delete' => empty($logisticUnitCount) && empty($projectHistoryRecordCount) && empty($requestLineCount),
            'html' => match(true) {
                $logisticUnitCount > 0         => '<span>Ce ' . mb_strtolower($translation->translate('Référentiel', 'Projet', 'Projet', false)) . ' est lié à une ou plusieurs unités logistiques ou articles, vous ne pouvez pas le supprimer</span>',
                $projectHistoryRecordCount > 0 => '<span>Ce ' . mb_strtolower($translation->translate('Référentiel', 'Projet', 'Projet', false)) . ' est lié à un ou plusieurs historiques de ' . mb_strtolower($translation->translate('Référentiel', 'Projet', 'Projet', false)) . ', vous ne pouvez pas le supprimer</span>',
                $requestLineCount > 0          => '<span>Ce ' . mb_strtolower($translation->translate('Référentiel', 'Projet', 'Projet', false)) . ' est lié à une ou plusieurs lignes de ' . mb_strtolower($translation->translate("Demande", "Livraison", "Demande de livraison", false)) . ', vous ne pouvez pas le supprimer</span>',
                default                        => '<span>Voulez-vous réellement supprimer ce ' . mb_strtolower($translation->translate('Référentiel', 'Projet', 'Projet', false)) . ' ?</span>'
            }
        ]);
    }

    #[Route('/delete', name: 'project_delete', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::REFERENTIEL, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function delete(Request                $request,
                           EntityManagerInterface $manager,
                           TranslationService     $translation): Response
    {
        $project = $manager->find(Project::class, $request->request->get("id"));

        $manager->remove($project);
        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Le " . mb_strtolower($translation->translate('Référentiel', 'Projet', 'Projet', false)) . " a bien été supprimé"
        ]);
    }


    #[Route("/csv", name: "get_projects_csv", options: ["expose" => true], methods: "GET")]
    #[HasPermission([Menu::REFERENTIEL, Action::EXPORT])]
    public function getProjectsCSV(CSVExportService $CSVExportService, EntityManagerInterface $entityManager): Response {
        $csvHeader = [
            "Code",
            "Description",
            "Chef de projet",
            "Actif",
        ];

        $today = (new DateTime())->format("d-m-Y-H-i-s");
        return $CSVExportService->streamResponse(function ($output) use ($entityManager, $CSVExportService) {
            $projects = $entityManager->getRepository(Project::class)->iterateAll();

            foreach ($projects as $project) {
                $CSVExportService->putLine($output, $project->serialize());
            }
        }, "export-projets-$today.csv", $csvHeader);
    }
}
