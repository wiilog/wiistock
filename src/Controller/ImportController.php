<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Import;
use App\Entity\Menu;
use App\Entity\Statut;
use App\Service\AttachmentService;
use App\Service\ImportDataService;
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
	 * @param ImportDataService $importDataService
	 * @param UserService $userService
	 * @return Response
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
	public function api(Request $request, ImportDataService $importDataService, UserService $userService): Response
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
	 * @return Response
	 * @throws NonUniqueResultException
	 */
	public function new(Request $request, UserService $userService, AttachmentService $attachmentService): Response
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

		$attachmentService->addAttachements($request->files, $import);

		return new JsonResponse([
			'success' => true,
			'html' => $this->renderView('import/modalNewImportSecond.html.twig', ['import' => $import])
		]);
	}

}
