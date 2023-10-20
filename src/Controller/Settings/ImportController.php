<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Import;
use App\Entity\ImportScheduleRule;
use App\Entity\Menu;
use App\Entity\Statut;
use App\Entity\Type;
use App\Service\AttachmentService;
use App\Service\FTPService;
use App\Service\ImportService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route("/import")]
class ImportController extends AbstractController
{

    #[Route("/api", name: "import_api", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function api(Request $request, ImportService $importDataService, FTPService $FTPService): Response
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
                        FTPService             $FTPService): Response
    {
        $post = $request->request;

        $statusRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $loggedUser = $this->getUser();

        $import = (new Import())
            ->setLabel($post->get('label'))
            ->setEntity($post->get('entity'))
            ->setStatus($statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_DRAFT))
            ->setEraseData($post->get('deleteDifData') ?? false)
            ->setUser($loggedUser);

        $isScheduled = $post->get("type") === "scheduled-import-checkbox";
        if ($isScheduled) {
            $rule = new ImportScheduleRule();
            $importService->updateScheduleRules($rule, $request->request);
            $import->setScheduleRule($rule);

            $FTPConfig = [
                "host" => $post->get("host"),
                "port" => $post->get("port"),
                "user" => $post->get("user"),
                "pass" => $post->get("pass"),
            ];

            $connect = $FTPService->try($FTPConfig);
            if(!is_bool($connect)) {
                return $this->json([
                    "success" => false,
                    "msg" => $connect->getMessage(),
                ]);
            }

            $nextExecutionDate = $importService->calculateNextExecutionDate($import);
            $import
                ->setScheduleRule($rule)
                ->setNextExecutionDate($nextExecutionDate)
                ->setType($typeRepository->findOneByCategoryLabelAndLabel(CategoryType::IMPORT, Type::LABEL_SCHEDULED_IMPORT))
                ->setFTPConfig($FTPConfig);
        } else {
            $import->setType($typeRepository->findOneByCategoryLabelAndLabel(CategoryType::IMPORT, Type::LABEL_UNIQUE_IMPORT));
        }

        $entityManager->persist($import);
        $entityManager->flush();

        $nbFiles = count($request->files);
        if ($nbFiles !== 1) {
            return $this->json([
                'success' => false,
                'msg' => 'Veuillez charger un ' . ($nbFiles > 1 ? 'seul ' : '') . 'fichier.'
            ]);
        }

        $file = $request->files->all()['file0'];
        if ($file->getClientOriginalExtension() !== 'csv') {
            return $this->json([
                'success' => false,
                'msg' => 'Veuillez charger un fichier au format .csv.'
            ]);
        }

        $attachments = $attachmentService->createAttachments([$file]);
        $csvAttachment = $attachments[0];
        $entityManager->persist($csvAttachment);
        $import->setCsvFile($csvAttachment);

        $entityManager->flush();
        $fileImportConfig = $importService->getFileImportConfig($attachments[0]);
        $fileValidationResponse = $importService->validateImportAttachment($fileImportConfig, !$isScheduled);

        if ($fileValidationResponse['success']) {
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

    #[Route("/edit-api", name: "import_edit_api", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    public function editApi(Request                $request,
                            EntityManagerInterface $entityManager,
                            UserService            $userService): Response
    {
        $data = $request->query->all();
        if (!$userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
            return $this->redirectToRoute('access_denied');
        }
        $importRepository = $entityManager->getRepository(Import::class);
        $importScheduleRuleRepository = $entityManager->getRepository(ImportScheduleRule::class);

        $import = $importRepository->findOneBy(['id' => $data['id']]);
        $importScheduleRule = $importScheduleRuleRepository->findOneBy(['import' => $data['id']]);

        return $this->json([
            'html' => $this->renderView('settings/donnees/import/content.html.twig', [
                'import' => $import,
                'importScheduleRule' => $importScheduleRule,
                'edit' => true,
                'attachment' => $import->getCsvFile()
            ]),
        ]);
    }

    #[Route("/edit", name: "import_edit", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PARAM, Action::EDIT])]
    public function edit(Request                $request,
                         ImportService          $importService,
                         AttachmentService      $attachmentService,
                         EntityManagerInterface $entityManager): Response
    {
        $data = $request->request;
        $importRepository = $entityManager->getRepository(Import::class);

        $import = $importRepository->find($data->get('sourceImport'));

        $importScheduleRule = $import->getScheduleRule();
        $importService->updateScheduleRules($importScheduleRule, $request->request);

        $nextExecutionDate = $importService->calculateNextExecutionDate($import);

        $import
            ->setNextExecutionDate($nextExecutionDate)
            ->setLabel($data->get('label'))
            ->setEntity($data->get('entity'));

        $file = $request->files->get('file0');
        if ($file) {
            $oldCSVFile = $import->getCsvFile();

            if ($file->getClientOriginalExtension() !== 'csv') {
                return $this->json([
                    'success' => false,
                    'msg' => 'Veuillez charger un fichier au format .csv.'
                ]);
            }

            $attachments = $attachmentService->createAttachments([$file]);
            $csvAttachment = $attachments[0];

            $fileHeaders = $importService->getFileImportConfig($attachments[0]);
            $fileValidationResponse = $importService->validateImportAttachment($fileHeaders, false);

            if ($fileValidationResponse['success']) {
                $import->setCsvFile(null);
                if ($oldCSVFile) {
                    $attachmentService->removeAndDeleteAttachment($oldCSVFile);
                }
                $import->setCsvFile($csvAttachment);
                $entityManager->persist($csvAttachment);
                $entityManager->flush();
            } else {
                return $this->json($fileValidationResponse);
            }
        }

        $secondModalConfig = $importService->getImportSecondModalConfig($entityManager, $data, $import);
        $entityManager->flush();

        $importService->saveScheduledImportsCache($entityManager);
        return $this->json([
            'success' => true,
            'importId' => $import->getId(),
            'html' => $this->renderView('settings/donnees/import/new/second.html.twig', $secondModalConfig)
        ]);
    }

    #[Route("/link", name: "import_links", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function defineLinks(Request $request, EntityManagerInterface $entityManager, ImportService $importService): Response
    {
        $importRepository = $entityManager->getRepository(Import::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $data = $request->request->all();

        $importId = $data['importId'];
        unset($data['importId']);
        unset($data['id']);

        $import = $importRepository->find($importId);
        $import->setColumnToField($data);
        $entityManager->flush();

        $responseData = [
            'success' => true
        ];

        $importTypeLabel = $this->formatService->type($import->getType());
        if ($importTypeLabel === Type::LABEL_SCHEDULED_IMPORT) {
            $import
                ->setStatus($statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_SCHEDULED));
            $entityManager->flush();

            $importService->saveScheduledImportsCache($entityManager);
            $responseData['msg'] = "L'import planifié a bien été modifié.";
        } else {
            $responseData['html'] = $this->renderView('settings/donnees/import/new/confirm.html.twig');
        }

        return $this->json($responseData);
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
        $import = $entityManager->getRepository(Import::class)->find($importId);
        $statusRepository = $entityManager->getRepository(Statut::class);

        if ($import && $import->getType()->getLabel() === Type::LABEL_UNIQUE_IMPORT) {
            $importModeTodo = ($force ? ImportService::IMPORT_MODE_FORCE_PLAN : ImportService::IMPORT_MODE_PLAN);
            $importModeDone = $importService->treatImport($entityManager, $import, $importModeTodo);

            $success = true;
            $message = (
            ($importModeDone === ImportService::IMPORT_MODE_RUN) ? 'Votre import a bien été lancé. Vous pouvez poursuivre votre navigation.' :
                (($importModeDone === ImportService::IMPORT_MODE_FORCE_PLAN) ? 'Votre import a bien été lancé. Il sera effectué dans moins de 30min.' :
                    (($importModeDone === ImportService::IMPORT_MODE_PLAN) ? 'Votre import a bien été lancé. Il sera effectué cette nuit.' :
                        /* $importModeDone === ImportService::IMPORT_MODE_NONE */
                        'Votre import a déjà été lancé. Il sera effectué dans moins de 30min.'))
            );
        } else if ($import) {
            $import
                ->setStatus($statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_SCHEDULED));
            $entityManager->flush();
            $success = true;
            $message = "L'import planifié a été créé avec succès";
        } else {
            $success = false;
            $message = 'Une erreur est survenue lors de l\'import. Veuillez le renouveler.';
        }

        return $this->json([
            'success' => $success,
            'message' => $message
        ]);
    }

    #[Route("/cancel", name: "import_cancel", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function cancelImport(Request                $request,
                                 EntityManagerInterface $manager,
                                 ImportService          $importService): JsonResponse
    {
        $statusRepository = $manager->getRepository(Statut::class);

        $import = $manager->find(Import::class, $request->request->getInt('importId'));

        $importType = $import->getType();
        $importStatus = $import->getStatus();
        if ($importType && $importStatus) {
            if ($importType->getLabel() == Type::LABEL_UNIQUE_IMPORT
                && $importStatus->getNom() == Import::STATUS_UPCOMING) {
                $import->setStatus($statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_CANCELLED));
                $manager->flush();
            } else if ($importType->getLabel() == Type::LABEL_SCHEDULED_IMPORT
                && $importStatus->getNom() == Import::STATUS_SCHEDULED) {
                $manager->remove($import);
                $manager->flush();

                $importService->saveScheduledImportsCache($manager);
            }
        }

        return $this->json([]);
    }

    #[Route("/delete", name: "import_delete", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function deleteImport(Request $request, EntityManagerInterface $manager): Response
    {
        $import = $manager->find(Import::class, $request->request->getInt('importId'));

        if ($import && $import->getStatus()?->getCode() === Import::STATUS_DRAFT) {
            $manager->remove($import);
            $manager->flush();
        }

        return $this->json([]);
    }

    #[Route("/{import}/force", name: "import_force", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function forceImport(EntityManagerInterface $entityManager,
                                Import                 $import,
                                ImportService          $importService): JsonResponse
    {
        if (!$import->isForced()
            && $import->getType()->getLabel() === Type::LABEL_SCHEDULED_IMPORT
            && $import->getStatus()->getNom() === Import::STATUS_SCHEDULED) {

            $import->setForced(true);

            $nextExecutionDate = $importService->calculateNextExecutionDate($import);
            $import->setNextExecutionDate($nextExecutionDate);

            $entityManager->flush();
            $importService->saveScheduledImportsCache($entityManager);
        }
        return $this->json([
            'success' => true,
            'msg' => "L'import va être exécuté dans les prochaines minutes."
        ]);
    }
}

