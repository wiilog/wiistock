<?php

namespace App\Controller;


use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Urgence;
use App\Service\SpecificService;
use App\Service\UrgenceService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use DateTime;

/**
 * @Route("/urgences")
 */
class UrgencesController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var UrgenceService
     */
    private $urgenceService;

    public function __construct(UserService $userService,
                                UrgenceService $urgenceService)
    {
        $this->userService = $userService;
        $this->urgenceService = $urgenceService;
    }

    /**
     * @Route("/", name="urgence_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_URGE)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('urgence/index.html.twig', [

        ]);
    }

    /**
     * @Route("/api", name="urgence_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_URGE)) {
                return $this->redirectToRoute('access_denied');
            }
            $data = $this->urgenceService->getDataForDatatable($request->request);
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="urgence_new", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param SpecificService $specificService
     * @param UrgenceService $urgenceService
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager,
                        SpecificService $specificService,
                        UrgenceService $urgenceService): Response
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::CREATE)) {
            return $this->redirectToRoute('access_denied');
        }

        $data = json_decode($request->getContent(), true);

        $urgenceRepository = $entityManager->getRepository(Urgence::class);

        $urgence = new Urgence();

        $urgenceService->updateUrgence($urgence, $data);

        $response = [];

        $isSEDCurrentClient = $specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED);

        $sameUrgentCounter = $urgenceRepository->countUrgenceMatching(
            $urgence->getDateStart(),
            $urgence->getDateEnd(),
            $urgence->getProvider(),
            $urgence->getCommande(),
            $isSEDCurrentClient ? $urgence->getPostNb() : null
        );

        if ($sameUrgentCounter > 0) {
            $response['success'] = false;
            $response['message'] = $this->getErrorMessageForDuplicate($isSEDCurrentClient);
        }
        else {
            $entityManager->persist($urgence);
            $entityManager->flush();
            $response['success'] = true;
            $response['message'] = "L'urgence a été créée avec succès.";
        }
        return new JsonResponse($response);
    }

    /**
     * @Route("/supprimer", name="urgence_delete", options={"expose"=true},methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $urgenceRepository = $entityManager->getRepository(Urgence::class);
            $urgence = $urgenceRepository->find($data['urgence']);
            $canDeleteUrgence = !$urgence->getLastArrival();
            if ($canDeleteUrgence) {
                $entityManager->remove($urgence);
                $entityManager->flush();
            }

            return new JsonResponse();
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="urgence_edit_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $urgenceRepository = $entityManager->getRepository(Urgence::class);
            $urgence = $urgenceRepository->find($data['id']);
            $json = $this->renderView('urgence/modalEditUrgenceContent.html.twig', [
                'urgence' => $urgence,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="urgence_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param SpecificService $specificService
     * @param EntityManagerInterface $entityManager
     * @param UrgenceService $urgenceService
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function edit(Request $request,
                         SpecificService $specificService,
                         EntityManagerInterface $entityManager,
                         UrgenceService $urgenceService): Response
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        $data = json_decode($request->getContent(), true);

        $urgenceRepository = $entityManager->getRepository(Urgence::class);
        $urgence = $urgenceRepository->find($data['id']);
        $response = [];

        if ($urgence) {
            $isSEDCurrentClient = $specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED);

            $urgenceService->updateUrgence($urgence, $data);
            $sameUrgentCounter = $urgenceRepository->countUrgenceMatching(
                $urgence->getDateStart(),
                $urgence->getDateEnd(),
                $urgence->getProvider(),
                $urgence->getCommande(),
                $isSEDCurrentClient ? $urgence->getPostNb() : null,
                [$urgence->getId()]
            );

            if ($sameUrgentCounter > 0) {
                $response['success'] = false;
                $response['message'] = $this->getErrorMessageForDuplicate($isSEDCurrentClient);;
            }
            else {
                $entityManager->flush();
                $response['success'] = true;
                $response['message'] = "L'urgence a été modifiée avec succès.";
            }
        }
        else {
            $response['success'] = false;
            $response['message'] = "Une erreur est survenue lors de la modification de l'urgence.";
        }

        return new JsonResponse($response);
    }

    /**
     * @Route("/verification", name="urgence_check_delete", options={"expose"=true}, methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
	public function checkUrgenceCanBeDeleted(Request $request, EntityManagerInterface $entityManager): Response
	{
		$urgenceId = json_decode($request->getContent(), true);
		$urgenceRepository = $entityManager->getRepository(Urgence::class);

		$urgence = $urgenceRepository->find($urgenceId);

		// on vérifie que l'urgence n'a pas été déclenchée
		$urgenceUsed = !empty($urgence->getLastArrival());

		if (!$urgenceUsed) {
			$delete = true;
			$html = $this->renderView('urgence/modalDeleteUrgenceRight.html.twig');
		} else {
			$delete = false;
			$html = $this->renderView('urgence/modalDeleteUrgenceWrong.html.twig');
		}

		return new JsonResponse(['delete' => $delete, 'html' => $html]);
	}

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return Urgence[]|null
     */
    public function findByDates($dateMin, $dateMax)
    {
        $dateMax = $dateMax->format('Y-m-d H:i:s');
        $dateMin = $dateMin->format('Y-m-d H:i:s');

        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT u
            FROM App\Entity\Urgence u
            WHERE d.date BETWEEN :dateMin AND :dateMax'
        )->setParameters([
            'dateMin' => $dateMin,
            'dateMax' => $dateMax
        ]);
        return $query->execute();
    }

    /**
     * @Route("/urgences-infos", name="get_urgence_for_csv", options={"expose"=true}, methods={"GET","POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function getUrgencesIntels(EntityManagerInterface $entityManager,
                                      Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $dateMin = $data['dateMin'] . ' 00:00:00';
            $dateMax = $data['dateMax'] . ' 23:59:59';

            $dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
            $dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);


            $urgenceRepositoty = $entityManager->getRepository(Urgence::class);

            $urgences = $urgenceRepositoty->findByDates($dateTimeMin, $dateTimeMax);
            // en-têtes champs fixes
            $headers = [
                'Debut delais livraison',
                'Fin delais livraison',
                'Numero de commande',
                'Numero de poste',
                'Contact PFF',
                'Fournisseur',
                'Transporteur',
                'Numero tracking transporteur',
                'Date Arrivage',
                "Numero d'arrivage",
                'Date de creation',
            ];

            $data = [$headers];

            /** @var Urgence $urgence */
            foreach ($urgences as $urgence) {
                $dateStart = $urgence->getDateStart();
                $dateEnd = $urgence->getDateEnd();
                $dateArrival = $urgence->getLastArrival() ? $urgence->getLastArrival()->getDate() : null;
                $dateCreation = $urgence->getCreatedAt() ? $urgence->getCreatedAt() : null;
                $urgenceData = [];
                $urgenceData[] = date_format($dateStart, 'd/m/Y H:i:s');
                $urgenceData[] = date_format($dateEnd, 'd/m/Y H:i:s');
                $urgenceData[] = $urgence->getCommande() ? $urgence->getCommande() : '';
                $urgenceData[] = $urgence->getPostNb() ? $urgence->getPostNb() : '';
                $urgenceData[] = $urgence->getBuyer() ? $urgence->getBuyer()->getUsername() : '';
                $urgenceData[] = $urgence->getProvider() ? $urgence->getProvider()->getNom() : '';
                $urgenceData[] = $urgence->getCarrier() ? $urgence->getCarrier()->getLabel() : '';
                $urgenceData[] = $urgence->getTrackingNb() ? $urgence->getTrackingNb() : '';
                $urgenceData[] = $dateArrival ? date_format($dateArrival, 'd/m/Y H:i:s') : '';
                $urgenceData[] = $dateArrival ? $urgence->getLastArrival()->getNumeroArrivage() : '';
                $urgenceData[] = $dateCreation ? date_format($dateCreation, 'd/m/Y H:i:s') : '';

                $data[] = $urgenceData;
            }

            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    private function getErrorMessageForDuplicate(bool $isSEDCurrentClient): string
    {
        $suffixErrorMessage = $isSEDCurrentClient ? ', le même numéro de commande et le même numéro de poste existe déjà' : ' et le même numéro de commande existe déjà';
        return "Une urgence sur la même période, avec le même fournisseur$suffixErrorMessage.";
    }
}
