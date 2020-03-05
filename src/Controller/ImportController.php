<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieStatut;
use App\Entity\Fournisseur;
use App\Entity\Import;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Service\AttachmentService;
use App\Service\ImportService;
use App\Service\UserService;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
	 */
	public function index(UserService $userService)
	{
		if (!$userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_IMPORT)) {
			return $this->redirectToRoute('access_denied');
		}

		$statusRepository = $this->getDoctrine()->getRepository(Statut::class);
		$statuts = $statusRepository->findByCategorieName(CategorieStatut::IMPORT);

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
//TODO CG test format fichier
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

		$fieldsToHide = ['id', 'barCode', 'conform', 'commentaire', 'quantiteAPrelever', 'quantitePrelevee',
			'dateLastInventory', 'dateEmergencyTriggered', 'expiryDate', 'isUrgent', 'quantiteDisponible',
			'quantiteReservee' ];
		$fieldNames = array_diff($attributes->getFieldNames(), $fieldsToHide);

		$fields = [];
		foreach ($fieldNames as $fieldName) {
			$fields[$fieldName] = Import::FIELDS_ENTITY[$fieldName] ?? $fieldName;
		}
		asort($fields);

		return new JsonResponse([
			'success' => true,
			'importId' => $import->getId(),
			'html' => $this->renderView('import/modalNewImportSecondContent.html.twig', [
				'data' => $data,
				'fields' => $fields
			])
		]);
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

		return new JsonResponse(['importId' => $importId]);
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
			$import->setStatus($statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_IN_PROGRESS));
			$doctrine->getManager()->flush();
			$resp = true;
		} else {
			$resp = false;
		}

		return new JsonResponse($resp);
	}
}
