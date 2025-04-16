<?php

namespace App\Controller\Settings;

use App\Controller\AbstractController;
use App\Entity\CategorieStatut;
use App\Entity\ScheduledTask\Import;
use App\Entity\Statut;
use App\Entity\Type\CategoryType;
use App\Entity\Type\Type;
use App\Exceptions\FormException;
use App\Exceptions\FTPException;
use App\Service\AttachmentService;
use App\Service\CSVExportService;
use App\Service\FTPService;
use App\Service\ImportService;
use App\Service\ScheduledTaskService;
use App\Service\ScheduleRuleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;
use WiiCommon\Helper\StringHelper;

#[Route("/import")]
class ImportController extends AbstractController
{

    #[Route("/api", name: "import_api", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function api(Request $request, ImportService $importDataService): Response
    {
        $user = $this->getUser();
        $data = $importDataService->getDataForDatatable($user, $request->request);

        return $this->json($data);
    }

    #[Route("/new", name: "import_new", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function new(Request                $request,
                        AttachmentService      $attachmentService,
                        EntityManagerInterface $entityManager,
                        ImportService          $importService,
                        FTPService             $FTPService,
                        ScheduledTaskService   $scheduledTaskService,
                        ScheduleRuleService    $scheduleRuleService): Response {
        $post = $request->request;

        $statusRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $loggedUser = $this->getUser();
        $draftStatus = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_DRAFT);

        $import = (new Import())
            ->setLabel($post->get('label'))
            ->setEntity($post->get('entity'))
            ->setStatus($draftStatus)
            ->setEraseData($post->get('deleteDifData') ?? false)
            ->setUser($loggedUser);

        $isScheduled = $post->get("type") === "scheduled-import-checkbox";
        if ($isScheduled) {
            $importType = $typeRepository->findOneByCategoryLabelAndLabel(CategoryType::IMPORT, Type::LABEL_SCHEDULED_IMPORT);

            // Check if the user can plan a new import
            if(!$scheduledTaskService->canSchedule($entityManager, Import::class)){
                throw new FormException("Vous avez déjà planifié " . ScheduledTaskService::MAX_ONGOING_SCHEDULED_TASKS . " planifications. Pensez à supprimer celles qui sont terminées en fréquence \"une fois\".");
            }

            $rule = $scheduleRuleService->updateRule(null, $post);

            $import
                ->setFilePath($request->get('path-import-file'))
                ->setScheduleRule($rule);

            $FTPHost = $post->get("host");
            $FTPPort = $post->get("port");
            $FTPUser = $post->get("user");
            $FTPPass = $post->get("pass");

            if ($FTPHost && $FTPPort && $FTPUser && $FTPPass) {
                $FTPConfig = [
                    "host" => $FTPHost,
                    "port" => $FTPPort,
                    "user" => $FTPUser,
                    "pass" => $FTPPass,
                ];

                try {
                    $FTPService->try($FTPConfig);
                    $import
                        ->setFTPConfig($FTPConfig);
                }
                catch(FTPException $exception) {
                    throw new FormException($exception->getMessage());
                }
                catch(Throwable) {
                    throw new FormException("Une erreur s'est produite lors de vérification de la connexion avec le serveur FTP");
                }
            }
        }
        else {
            $importType = $typeRepository->findOneByCategoryLabelAndLabel(CategoryType::IMPORT, Type::LABEL_UNIQUE_IMPORT);
        }

        $import->setType($importType);
        $entityManager->persist($import);

        $nbFiles = $request->files->count();
        if ($nbFiles !== 1) {
            throw new FormException('Veuillez charger un ' . ($nbFiles > 1 ? 'seul ' : '') . 'fichier.');
        }

        $file = $request->files->get("file0");
        if ($file->getClientOriginalExtension() !== 'csv') {
            throw new FormException('Veuillez charger un fichier au format .csv.');
        }

        $csvAttachment = $attachmentService->persistAttachment($entityManager, $file);
        $import->setCsvFile($csvAttachment);

        $fileValidationResponse = $importService->validateImportAttachment($csvAttachment, !$isScheduled);

        if ($fileValidationResponse['success']) {
            // We save import in database
            $entityManager->flush();
            $secondModalConfig = $importService->getImportSecondModalConfig($entityManager, $post, $import);

            return $this->json([
                'success' => true,
                'importId' => $import->getId(),
                'html' => $this->renderView('settings/donnees/import/new/second.html.twig', $secondModalConfig),
            ]);
        } else {
            return $this->json($fileValidationResponse);
        }
    }

    #[Route("/link", name: "import_links", options: ["expose" => true], methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    public function defineLinks(Request                $request,
                                ScheduledTaskService   $scheduledTaskService,
                                EntityManagerInterface $entityManager): JsonResponse {
        $importRepository = $entityManager->getRepository(Import::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $data = $request->request->all();

        $importId = $data['importId'];
        unset($data['importId']);
        unset($data['id']);

        $import = $importRepository->find($importId);
        $import->setColumnToField($data);
        $entityManager->flush();

        if ($import->getType()?->getLabel() === Type::LABEL_SCHEDULED_IMPORT) {
            $scheduleStatus = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_SCHEDULED);
            $import
                ->setStatus($scheduleStatus);
            $entityManager->flush();

            $scheduledTaskService->deleteCache(Import::class);

            return $this->json([
                "success" => true,
                "msg" => "L'import planifié a bien été créé."
            ]);
        } else {
            return $this->json([
                "success" => true,
                "html" => $this->renderView('settings/donnees/import/new/confirm.html.twig')
            ]);
        }
    }

    #[Route("/get-first-modal-content/{import}", name: "get_first_modal_content", options: ["expose" => true], defaults: ["import" => null], methods: "GET", condition: "request.isXmlHttpRequest()")]
    public function getFirstModalContent(?Import $import): Response
    {
        $type = $import?->getType();
        $typeLabel = $type?->getLabel();

        return $this->json([
            'html' => $this->renderView('settings/donnees/import/content.html.twig', [
                'import' => $import ?? new Import(),
                'isScheduledImport' => $typeLabel === Type::LABEL_SCHEDULED_IMPORT,
                'isUniqueImport' => $typeLabel === Type::LABEL_UNIQUE_IMPORT
            ])
        ]);
    }

    #[Route("/launch", name: "import_launch", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function launchImport(Request                $request,
                                 EntityManagerInterface $entityManager,
                                 ImportService          $importService): Response
    {
        $importId = $request->query->get('importId');
        $force = $request->query->getBoolean('force');

        $statusRepository = $entityManager->getRepository(Statut::class);
        $importRepository = $entityManager->getRepository(Import::class);

        $import = $importRepository->find($importId);

        if ($import) {
            switch ($import->getType()?->getLabel()) {
                case Type::LABEL_UNIQUE_IMPORT:
                    $importModeTodo = $force ? ImportService::IMPORT_MODE_FORCE_PLAN : ImportService::IMPORT_MODE_PLAN;
                    $importModeDone = $importService->treatImport($entityManager, $import, $importModeTodo);
                    break;
                case Type::LABEL_SCHEDULED_IMPORT:
                    $import
                        ->setStatus($statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_CHEDULED));
                    $entityManager->flush();
                    $importModeDone = $force ? ImportService::IMPORT_MODE_FORCE_PLAN : null;
                    break;
                default:
                    throw new FormException("Une erreur est survenue lors de l'import. Veuillez le renouveler.");
            }
        } else {
            throw new FormException("Une erreur est survenue lors de l'import. Veuillez le renouveler.");
        }

        return $this->json([
            "success" => true,
            "message" => match ($importModeDone) {
                ImportService::IMPORT_MODE_RUN => "Votre import a bien été lancé. Vous pouvez poursuivre votre navigation.",
                ImportService::IMPORT_MODE_FORCE_PLAN => "Votre import a bien été lancé. Il sera effectué dans moins de 30min.",
                ImportService::IMPORT_MODE_PLAN => "Votre import a bien été lancé. Il sera effectué cette nuit.",
                ImportService::IMPORT_MODE_NONE =>  "Votre import a déjà été lancé. Il sera effectué dans moins de 30min.",
                default => "L'import planifié a été créé avec succès."
            }
        ]);
    }

    #[Route("/{import}/cancel", name: "import_cancel", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function cancelImport(Import                 $import,
                                 EntityManagerInterface $entityManager,
                                 ScheduledTaskService   $scheduledTaskService): JsonResponse {
        $statusRepository = $entityManager->getRepository(Statut::class);

        if (!$import->isCancellable()) {
            throw new FormException("L'import ne peut pas être annulé.");
        }

        $cancelledStatus = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_CANCELLED);
        $import
            ->setStatus($cancelledStatus);
        $entityManager->flush();

        if ($import->getType()?->getLabel() == Type::LABEL_SCHEDULED_IMPORT) {
            $scheduledTaskService->deleteCache(Import::class);
        }

        return $this->json([
            "success" => true,
            "msg" => "L'import a bien été annulé.",
        ]);
    }

    #[Route("/{import}/delete", name: "import_delete", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function deleteImport(Import $import, EntityManagerInterface $manager): Response {
        if ($import->isDeletable()) {
            $manager->remove($import);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "L'import a bien été supprimé."
            ]);
        } else {
            throw new FormException("L'import ne peut pas être supprimé.");
        }
    }

    #[Route("/template/{entity}", name: "import_template", options: ["expose" => true])]
    public function importTemplate(string                 $entity,
                                   EntityManagerInterface $entityManager,
                                   CSVExportService       $CSVExportService,
                                   ImportService          $importService): Response {
        $entityLabel = Import::ENTITY_LABEL[$entity] ?? null;
        if (!$entityLabel) {
            throw new NotFoundHttpException("Not found template");
        }

        $headers = $importService->getFieldsToAssociate($entityManager, $entity);
        $cleanedEntityName = StringHelper::slugify(str_replace(" ","-", $entityLabel));

        return $CSVExportService->streamResponse(function () {}, "modele-$cleanedEntityName.csv", $headers);
    }
}

