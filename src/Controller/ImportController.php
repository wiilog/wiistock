<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\ChampLibre;
use App\Entity\Fournisseur;
use App\Entity\Import;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Service\AttachmentService;
use App\Service\ImportService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\ORMException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @Route("/import")
 */
class ImportController extends AbstractController
{
    /**
     * @Route("/", name="import_index")
     * @param UserService $userService
     * @return RedirectResponse|Response
     */
	public function index(UserService $userService)
	{
		if (!$userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_IMPORT)) {
			return $this->redirectToRoute('access_denied');
		}

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
     * @param Request $request
     * @param ImportService $importDataService
     * @param UserService $userService
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws NonUniqueResultException
     */
	public function api(Request $request, ImportService $importDataService, UserService $userService): Response
	{
		if (!$userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_IMPORT)) {
			return $this->redirectToRoute('access_denied');
		}
		$data = $importDataService->getDataForDatatable($request->request);

		return new JsonResponse($data);
	}

	/**
	 * @Route("/creer", name="import_new", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
	 * @param Request $request
	 * @param UserService $userService
	 * @param AttachmentService $attachmentService
	 * @param ImportService $importService
	 * @return Response
	 * @throws NonUniqueResultException
	 */
	public function new(Request $request,
						UserService $userService,
						AttachmentService $attachmentService,
						ImportService $importService): Response
	{
		if (!$userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_IMPORT)) {
			return $this->redirectToRoute('access_denied');
		}

		$post = $request->request;
		$em = $this->getDoctrine()->getManager();
		$statusRepository = $em->getRepository(Statut::class);

		$import = new Import();
		$import
			->setLabel($post->get('label'))
			->setEntity($post->get('entity'))
			->setStatus($statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_DRAFT))
			->setUser($this->getUser());

		$em->persist($import);
		$em->flush();

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
                $attachements = $attachmentService->addAttachements($request->files, $import);
                $data = $importService->readFile($attachements[0]);

                $entity = $import->getEntity();
                $entityCodeToClass = [
                    Import::ENTITY_ART => Article::class,
                    Import::ENTITY_REF => ReferenceArticle::class,
                    Import::ENTITY_FOU => Fournisseur::class,
                    Import::ENTITY_ART_FOU => ArticleFournisseur::class
                ];
                $attributes = $em->getClassMetadata($entityCodeToClass[$entity]);

                $fieldsToHide = ['id', 'barCode', 'conform', 'quantiteAPrelever', 'quantitePrelevee',
                    'dateEmergencyTriggered', 'expiryDate', 'isUrgent', 'quantiteDisponible',
                    'quantiteReservee'];
                $fieldNames = array_diff($attributes->getFieldNames(), $fieldsToHide);
                switch ($entity) {
                    case Import::ENTITY_ART:
                        $categoryCL = CategorieCL::ARTICLE;
                        $fieldsToAdd = ['référence article fournisseur', 'référence article de référence', 'référence fournisseur', 'emplacement'];
                        $fieldNames = array_merge($fieldNames, $fieldsToAdd);
                        break;
                    case Import::ENTITY_REF:
                        $categoryCL = CategorieCL::REFERENCE_ARTICLE;
                        $fieldsToAdd = ['type', 'emplacement', 'catégorie d\'inventaire', 'statut'];
                        $fieldNames = array_merge($fieldNames, $fieldsToAdd);
                        break;
                    case Import::ENTITY_ART_FOU:
                        $fieldsToAdd = ['référence article de référence', 'référence fournisseur'];
                        $fieldNames = array_merge($fieldNames, $fieldsToAdd);
                        break;
                }

                $fields = [];
                foreach ($fieldNames as $fieldName) {
                    $fields[$fieldName] = Import::FIELDS_ENTITY[$fieldName] ?? $fieldName;
                }

                if (isset($categoryCL)) {
                    $champsLibres = $em->getRepository(ChampLibre::class)->getLabelAndIdByCategory($categoryCL);

                    foreach ($champsLibres as $champLibre) {
                        $fields[$champLibre['id']] = $champLibre['value'];
                    }
                }

                natcasesort($fields);

                if ($post->get('importId')){
                    $copiedImport = $this->getDoctrine()->getRepository(Import::class)->find($post->get('importId'));
                    $columnsToFields = $copiedImport->getColumnToField();
                }

                $response = [
                    'success' => true,
                    'importId' => $import->getId(),
                    'html' => $this->renderView('import/modalNewImportSecond.html.twig', [
                        'data' => $data ?? [],
                        'fields' => $fields ?? [],
                        'fieldsNeeded' => Import::FIELDS_NEEDED[$entity],
                        'columnsToFields' => $columnsToFields ?? null
                    ])
                ];
            }
        }

		return new JsonResponse($response);
	}

	/**
	 * @Route("/lier", name="import_links", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
	 */
	public function defineLinks(Request $request): Response
	{
	    $success = true;
		$importRepository = $this->getDoctrine()->getRepository(Import::class);
		$data = json_decode($request->getContent(), true);

		$importId = $data['importId'];
		unset($data['importId']);

		$import = $importRepository->find($importId);

        if ($success) {
            $import->setColumnToField($data);
            $this->getDoctrine()->getManager()->flush();
        }

		return new JsonResponse([
			'success' => $success,
			'html' => $success ? $this->renderView('import/modalNewImportConfirm.html.twig') : ''
		]);
	}

	/**
	 * @Route("/confirmer", name="import_confirm", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
	 * @param Request $request
	 * @return JsonResponse
	 * @throws NonUniqueResultException
	 */
	public function confirm(Request $request)
	{
		$doctrine = $this->getDoctrine();
		$importRepository = $doctrine->getRepository(Import::class);
		$statusRepository = $doctrine->getRepository(Statut::class);

		$data = json_decode($request->getContent(), true);

		$import = $importRepository->find($data['importId']);

		if ($import) {
			$import
                ->setStatus($statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_IN_PROGRESS))
			    ->setStartDate(new DateTime('now'));
			$doctrine->getManager()->flush();
			$resp = true;
		} else {
			$resp = false;
		}

		return new JsonResponse(['success' => $resp, 'importId' => $data['importId']]);
	}

    /**
     * @Route("/modale-une", name="get_first_modal_content", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @return JsonResponse
     */
	public function getFirstModalContent(Request $request)
	{
	    $importId = $request->get('importId');
	    $import = $importId ? $this->getDoctrine()->getRepository(Import::class)->find($importId) : null;

		return new JsonResponse($this->renderView('import/modalNewImportFirst.html.twig', ['import' => $import]));
	}

    /**
     * @Route("/lancer-import", name="import_launch", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param ImportService $importService
     * @return JsonResponse
     * @throws NonUniqueResultException
     * @throws NoResultException
     * @throws ORMException
     */
	public function launchImport(Request $request, ImportService $importService)
	{
		$importId = $request->request->get('importId');
		$em = $this->getDoctrine()->getManager();
        $import = $em->getRepository(Import::class)->find($importId);

        if ($import) {
            $importService->loadData($import);
        }

		return new JsonResponse();
	}

    /**
     * @Route("/annuler-import", name="import_cancel", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @return JsonResponse
     * @throws NonUniqueResultException
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
     * @param Request $request
     * @return JsonResponse
     */
	public function deleteImport(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $importId = (int)$request->request->get('importId');
        $import = $em->getRepository(Import::class)->find($importId);

        if ($import) {
            $em->remove($import);
            $em->flush();
        }

        return new JsonResponse();
    }

}
