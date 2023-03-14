<?php

namespace App\Controller\Settings;

use App\Entity\CategorieStatut;
use App\Entity\FieldsParam;
use App\Entity\Import;
use App\Entity\Statut;
use App\Entity\StorageRule;
use App\Entity\Utilisateur;
use App\Service\AttachmentService;
use App\Service\ImportService;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/import")
 */
class DataImportController extends AbstractController
{
    /**
     * @Route("/api", name="import_api", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function api(Request $request, ImportService $importDataService): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $data = $importDataService->getDataForDatatable($user, $request->request);

        return new JsonResponse($data);
    }

    /**
     * @Route("/creer", name="import_new", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function new(Request $request,
                        AttachmentService $attachmentService,
                        EntityManagerInterface $entityManager,
                        ImportService $importService): Response
    {
        $post = $request->request;

        $statusRepository = $entityManager->getRepository(Statut::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $importRepository = $entityManager->getRepository(Import::class);

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        if ($post->get('deleteDifData') && $post->get('entity') === Import::ENTITY_REF_LOCATION ) {
            $entityManager->getRepository(StorageRule::class)->clearTable();
        }

        $import = new Import();
        $import
            ->setLabel($post->get('label'))
            ->setEntity($post->get('entity'))
            ->setStatus($statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_DRAFT))
            ->setEraseData($post->get('deleteDifData') ?? false)
            ->setUser($loggedUser);

        $entityManager->persist($import);
        $entityManager->flush();

        // vérif qu'un et un seul fichier a été chargé
        $nbFiles = count($request->files);
        if ($nbFiles !== 1) {
            $response = [
                'success' => false,
                'msg' => 'Veuillez charger un ' . ($nbFiles > 1 ? 'seul ' : '') . 'fichier.'
            ];
        } else {
            // vérif format du fichier csv
            $file = $request->files->all()['file0'];
            if ($file->getClientOriginalExtension() !== 'csv') {
                $response = [
                    'success' => false,
                    'msg' => 'Veuillez charger un fichier au format .csv.'
                ];

            } else {
                $attachments = $attachmentService->createAttachements([$file]);
                $csvAttachment = $attachments[0];
                $entityManager->persist($csvAttachment);
                $import->setCsvFile($csvAttachment);

                $entityManager->flush();
                $data = $importService->getImportConfig($attachments[0]);
                if (!$data) {
                    $response = [
                        'success' => false,
                        'msg' => 'Format du fichier incorrect. Il doit au moins contenir une ligne d\'en-tête et une ligne à importer.'
                    ];
                }
                else if (!$data["isUtf8"]) {
                    $response = [
                        'success' => false,
                        'msg' => 'Veuillez charger un fichier encodé en UTF-8'
                    ];
                }
                else {
                    $entity = $import->getEntity();
                    $fieldsToAssociate = $importService->getFieldsToAssociate($entityManager, $entity);
                    natcasesort($fieldsToAssociate);

                    $preselection = [];
                    if(isset($data['headers'])) {
                        $headers = $data['headers'];

                        $fieldsToCheck = array_merge($fieldsToAssociate);
                        $sourceImportId = $post->get('sourceImport');
                        if (isset($sourceImportId)) {
                            $sourceImport = $importRepository->find($sourceImportId);
                            if (isset($sourceImport)) {
                                $sourceColumnToField = $sourceImport->getColumnToField();
                            }
                        }

                        $preselection = $importService->createPreselection($headers, $fieldsToCheck, $sourceColumnToField ?? null);
                    }

                    if ($post->get('importId')) {
                        $importRepository = $entityManager->getRepository(Import::class);
                        $copiedImport = $importRepository->find($post->get('importId'));
                        $columnsToFields = $copiedImport->getColumnToField();
                    }

                    $fieldsNeeded = Import::FIELDS_NEEDED[$entity];

                    if ($entity === Import::ENTITY_RECEPTION) {
                        foreach ($fieldsToAssociate as $field) {
                            $fieldParamCode = isset(Import::IMPORT_FIELDS_TO_FIELDS_PARAM[$field]) ? Import::IMPORT_FIELDS_TO_FIELDS_PARAM[$field] : null;
                            if ($fieldParamCode) {
                                $fieldParam = $fieldsParamRepository->findOneBy([
                                    'fieldCode' => $fieldParamCode,
                                    'entityCode' => FieldsParam::ENTITY_CODE_RECEPTION,
                                ]);
                                if ($fieldParam && $fieldParam->isRequiredCreate()) {
                                    $fieldsNeeded[] = $field;
                                }
                            }
                        }
                    }

                    $response = [
                        'success' => true,
                        'importId' => $import->getId(),
                        'html' => $this->renderView('settings/donnees/import/modalNewImportSecond.html.twig', [
                            'data' => $data ?? [],
                            'fields' => $fieldsToAssociate ?? [],
                            'preselection' => $preselection ?? [],
                            'fieldsNeeded' => $fieldsNeeded,
                            'fieldPK' => Import::FIELD_PK[$entity],
                            'columnsToFields' => $columnsToFields ?? null,
                            'fromExistingImport' => !empty($sourceColumnToField)
                        ])
                    ];
                }
            }
        }

		return new JsonResponse($response);
	}

    /**
     * @Route("/lier", name="import_links", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
	public function defineLinks(Request $request, EntityManagerInterface $manager): Response
	{
		$importRepository = $manager->getRepository(Import::class);
		$data = json_decode($request->getContent(), true);

		$importId = $data['importId'];
		unset($data['importId']);

		$import = $importRepository->find($importId);
        if(!$import) {
            return $this->json([
                "success" => false,
                "msg" => "Une erreur est survenue lors de la création de l'import",
            ]);
        }

        $import->setColumnToField($data);
        $manager->flush();

		return new JsonResponse([
			'success' => true,
			'html' => $this->renderView('settings/donnees/import/modalNewImportConfirm.html.twig')
		]);
	}

    /**
     * @Route("/modale-une", name="get_first_modal_content", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
	public function getFirstModalContent(Request $request,
                                         EntityManagerInterface $entityManager): JsonResponse
	{
	    $importId = $request->get('importId');
	    $import = $importId ? $entityManager->getRepository(Import::class)->find($importId) : null;

		return new JsonResponse($this->renderView('settings/donnees/import/modalNewImportFirst.html.twig', [
            'import' => $import
        ]));
	}

    /**
     * @Route("/lancer-import", name="import_launch", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
	public function launchImport(Request $request,
                                 EntityManagerInterface $entityManager,
                                 ImportService $importService)
	{
		$importId = $request->request->get('importId');
		$force = $request->request->getBoolean('force');
        $import = $entityManager->getRepository(Import::class)->find($importId);

        if ($import) {
            $importModeTodo = ($force ? ImportService::IMPORT_MODE_FORCE_PLAN : ImportService::IMPORT_MODE_PLAN);
            $importModeDone = $importService->treatImport($import, $importModeTodo);

            $success = true;
            $message = (
                ($importModeDone === ImportService::IMPORT_MODE_RUN) ? 'Votre import a bien été lancé. Vous pouvez poursuivre votre navigation.' :
                (($importModeDone === ImportService::IMPORT_MODE_FORCE_PLAN) ? 'Votre import a bien été lancé. Il sera effectué dans moins de 30min.' :
                (($importModeDone === ImportService::IMPORT_MODE_PLAN) ? 'Votre import a bien été lancé. Il sera effectué cette nuit.' :
                /* $importModeDone === ImportService::IMPORT_MODE_NONE */ 'Votre import a déjà été lancé. Il sera effectué dans moins de 30min.'))
            );
        }
        else {
            $success = false;
            $message = 'Une erreur est survenue lors de l\'import. Veuillez le renouveler.';
        }

		return new JsonResponse([
		    'success' => $success,
            'message' => $message
        ]);
	}

    /**
     * @Route("/annuler-import", name="import_cancel", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
	public function cancelImport(Request $request, EntityManagerInterface $manager)
    {
        $statusRepository = $manager->getRepository(Statut::class);

        $importId = (int)$request->request->get('importId');

        $import = $manager->getRepository(Import::class)->find($importId);
        if ($import->getStatus() == Import::STATUS_PLANNED) {
            $import->setStatus($statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_CANCELLED));
            $manager->flush();
        }

        return new JsonResponse();
    }
    /**
     * @Route("/supprimer-import", name="import_delete", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
	public function deleteImport(Request $request, EntityManagerInterface $manager)
    {
        $importId = (int)$request->request->get('importId');
        $import = $manager->getRepository(Import::class)->find($importId);

        if ($import && $import->getStatus()?->getCode() === Import::STATUS_DRAFT) {
            $manager->remove($import);
            $manager->flush();
        }

        return new JsonResponse();
    }
}

