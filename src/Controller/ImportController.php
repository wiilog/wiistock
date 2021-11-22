<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\FieldsParam;
use App\Entity\FreeField;
use App\Entity\Fournisseur;
use App\Entity\Import;
use App\Entity\Menu;
use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use WiiCommon\Helper\Stream;
use App\Service\AttachmentService;
use App\Service\ImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/import")
 */
class ImportController extends AbstractController
{
    /**
     * @Route("/", name="import_index")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_IMPORT})
     */
    public function index()
    {
        $statusRepository = $this->getDoctrine()->getRepository(Statut::class);
        $statuts = $statusRepository->findByCategoryNameAndStatusCodes(
            CategorieStatut::IMPORT,
            [Import::STATUS_PLANNED, Import::STATUS_IN_PROGRESS, Import::STATUS_CANCELLED, Import::STATUS_FINISHED]
        );

        return $this->render('import/index.html.twig', [
            'statuts' => $statuts
        ]);
    }

    /**
     * @Route("/api", name="import_api", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_IMPORT}, mode=HasPermission::IN_JSON)
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
     * @HasPermission({Menu::PARAM, Action::DISPLAY_IMPORT}, mode=HasPermission::IN_JSON)
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

        $import = new Import();
        $import
            ->setLabel($post->get('label'))
            ->setEntity($post->get('entity'))
            ->setStatus($statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_DRAFT))
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
                    $entityCodeToClass = [
                        Import::ENTITY_ART => Article::class,
                        Import::ENTITY_REF => ReferenceArticle::class,
                        Import::ENTITY_FOU => Fournisseur::class,
                        Import::ENTITY_ART_FOU => ArticleFournisseur::class,
                        Import::ENTITY_RECEPTION => Reception::class,
                        Import::ENTITY_USER => Utilisateur::class,
                        Import::ENTITY_DELIVERY => Demande::class
                    ];
                    $attributes = $entityManager->getClassMetadata($entityCodeToClass[$entity]);

                    $fieldsToHide = [
                        'id',
                        'barCode',
                        'reference',
                        'conform',
                        'dateEmergencyTriggered',
                        'isUrgent',
                        'quantiteDisponible',
                        'freeFields',
                        'urgentArticles',
                        'quantiteReservee',
                        'dateAttendue',
                        'dateFinReception',
                        'dateCommande',
                        'number',
                        'date',
                        'emergencyTriggered',
                        'cleanedComment',
                        'apiKey',
                        'columnsVisibleForArrivage',
                        'columnsVisibleForArticle',
                        'columnsVisibleForDispatch',
                        'columnsVisibleForLitige',
                        'columnsVisibleForReception',
                        'columnsVisibleForTrackingMovement',
                        'columnVisible',
                        'lastLogin',
                        'pageLengthForArrivage',
                        'password',
                        'savedDispatchDeliveryNoteData',
                        'savedDispatchWaybillData',
                        'secondaryEmails',
                        'token',
                        'searches',
                        'pageIndexes',
                        'columnsOrder',
                        'rechercheForArticle',
                        'recherche',
                        'roles',
                        'createdAt',
                        'validatedAt',
                    ];

                    $fieldNames = $attributes->getFieldNames();
                    switch ($entity) {
                        case Import::ENTITY_ART:
                            $categoryCL = CategorieCL::ARTICLE;
                            $fieldsToAdd = ['référence article fournisseur', 'référence article de référence', 'référence fournisseur', 'emplacement', 'barCode'];
                            $fieldNames = array_merge($fieldNames, $fieldsToAdd);
                            break;
                        case Import::ENTITY_REF:
                            $categoryCL = CategorieCL::REFERENCE_ARTICLE;
                            $fieldsToHide = Stream::from($fieldsToHide)->filter(fn(string $field) => $field !== 'reference')->toArray();
                            $fieldsToAdd = ['type', 'emplacement', 'catégorie d\'inventaire', 'statut', 'reference', 'managers', 'buyer', 'visibilityGroups'];
                            $fieldNames = array_merge($fieldNames, $fieldsToAdd);
                            break;
                        case Import::ENTITY_ART_FOU:
                            $fieldsToAdd = ['référence article de référence', 'référence fournisseur', 'reference'];
                            $fieldNames = array_merge($fieldNames, $fieldsToAdd);
                            break;
                        case Import::ENTITY_RECEPTION:
                            $fieldsToAdd = ['anomalie', 'fournisseur', 'transporteur', 'référence', 'location','storageLocation', 'quantité à recevoir', 'orderDate', 'expectedDate'];
                            $fieldNames = array_merge($fieldNames, $fieldsToAdd);
                            break;
                        case Import::ENTITY_USER:
                            $fieldsToAdd = ['role', 'secondaryEmail', 'lastEmail', 'phone', 'mobileLoginKey', 'address', 'deliveryTypes', 'dispatchTypes', 'handlingTypes', 'dropzone', 'visibilityGroup'];
                            $fieldNames = array_merge($fieldNames, $fieldsToAdd);
                            break;
                        case Import::ENTITY_DELIVERY:
                            $categoryCL = CategorieCL::DEMANDE_LIVRAISON;
                            $fieldsToHide = array_merge($fieldsToHide, ['numero', 'filled']);
                            $fieldsToAdd = ['articleReference', 'quantityDelivery', 'articleCode', 'status', 'type', 'requester', 'destination'];
                            $fieldNames = array_merge($fieldNames, $fieldsToAdd);
                            break;
                    }
                    $fieldNames = array_diff($fieldNames, $fieldsToHide);
                    $fields = [];
                    foreach ($fieldNames as $fieldName) {
                        $fields[$fieldName] = Import::FIELDS_ENTITY[$fieldName] ?? $fieldName;
                    }

                    if (isset($categoryCL)) {
                        $champsLibres = $entityManager->getRepository(FreeField::class)->getLabelAndIdByCategory($categoryCL);

                        foreach ($champsLibres as $champLibre) {
                            $fields[$champLibre['id']] = $champLibre['value'];
                        }
                    }

                    natcasesort($fields);

                    $preselection = [];
                    if(isset($data['headers'])) {
                        $headers = $data['headers'];

                        $fieldsToCheck = array_merge($fields);
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
                        $copiedImport = $this->getDoctrine()->getRepository(Import::class)->find($post->get('importId'));
                        $columnsToFields = $copiedImport->getColumnToField();
                    }

                    $fieldsNeeded = Import::FIELDS_NEEDED[$entity];

                    if ($entity === Import::ENTITY_RECEPTION) {
                        foreach ($fields as $field) {
                            $fieldParamCode = isset(Import::IMPORT_FIELDS_TO_FIELDS_PARAM[$field]) ? Import::IMPORT_FIELDS_TO_FIELDS_PARAM[$field] : null;
                            if ($fieldParamCode) {
                                $fieldParam = $fieldsParamRepository->findOneBy([
                                    'fieldCode' => $fieldParamCode,
                                    'entityCode' => FieldsParam::ENTITY_CODE_RECEPTION,
                                ]);
                                if ($fieldParam && $fieldParam->getRequiredCreate()) {
                                    $fieldsNeeded[] = $field;
                                }
                            }
                        }
                    }

                    $response = [
                        'success' => true,
                        'importId' => $import->getId(),
                        'html' => $this->renderView('import/modalNewImportSecond.html.twig', [
                            'data' => $data ?? [],
                            'fields' => $fields ?? [],
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
	public function defineLinks(Request $request): Response
	{
		$importRepository = $this->getDoctrine()->getRepository(Import::class);
		$data = json_decode($request->getContent(), true);

		$importId = $data['importId'];
		unset($data['importId']);

		$import = $importRepository->find($importId);
        $import->setColumnToField($data);
        $this->getDoctrine()->getManager()->flush();

		return new JsonResponse([
			'success' => true,
			'html' => $this->renderView('import/modalNewImportConfirm.html.twig')
		]);
	}

    /**
     * @Route("/modale-une", name="get_first_modal_content", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
	public function getFirstModalContent(Request $request)
	{
	    $importId = $request->get('importId');
	    $import = $importId ? $this->getDoctrine()->getRepository(Import::class)->find($importId) : null;

		return new JsonResponse($this->renderView('import/modalNewImportFirst.html.twig', ['import' => $import]));
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
            $importModeDone = $importService->treatImport($import, $this->getUser(), $importModeTodo);

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
	public function cancelImport(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $statusRepository = $em->getRepository(Statut::class);

        $importId = (int)$request->request->get('importId');

        $import = $em->getRepository(Import::class)->find($importId);
        if ($import->getStatus() == Import::STATUS_PLANNED) {
            $import->setStatus($statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_CANCELLED));
            $em->flush();
        }

        return new JsonResponse();
    }
    /**
     * @Route("/supprimer-import", name="import_delete", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
	public function deleteImport(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $importId = (int)$request->request->get('importId');
        $import = $em->getRepository(Import::class)->find($importId);

        if ($import && $import->getStatus()->getNom() === Import::STATUS_DRAFT) {
            $em->remove($import);
            $em->flush();
        }

        return new JsonResponse();
    }
}

