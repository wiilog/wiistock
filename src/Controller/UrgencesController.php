<?php

namespace App\Controller;


use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\FieldsParam;
use App\Entity\Menu;
use App\Entity\Urgence;
use App\Service\CSVExportService;
use App\Service\FieldsParamService;
use App\Service\FormatService;
use App\Service\SpecificService;
use App\Service\TranslationService;
use App\Service\UrgenceService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use DateTime;

#[Route('/urgences')]
class UrgencesController extends AbstractController
{
    #[Route('/', name: 'emergency_index')]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_URGE])]
    public function index(EntityManagerInterface $entityManager)
    {
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);

        return $this->render('urgence/index.html.twig', [
            'fieldsParam' => $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_EMERGENCY),
            'newEmergency' => new Urgence(),
            'types' => $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_EMERGENCY, FieldsParam::FIELD_CODE_EMERGENCY_TYPE)
        ]);
    }

    #[Route('/api', name: 'emergency_api', options: ['expose' => true], methods: ['GET'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_URGE], mode: HasPermission::IN_JSON)]
    public function api(Request $request, UrgenceService $emergencyService): Response
    {
        $data = $emergencyService->getDataForDatatable($request->request);
        return new JsonResponse($data);
    }

    #[Route('/creer', name: 'emergency_new', options: ['expose' => true], methods: ['POST'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::CREATE], mode: HasPermission::IN_JSON)]
    public function new(Request $request,
                        EntityManagerInterface $entityManager,
                        SpecificService $specificService,
                        UrgenceService $urgenceService,
                        FieldsParamService $fieldsParamService,
                        FormatService $formatService): Response {
        $data = $fieldsParamService->checkForErrors($entityManager, $request->request , FieldsParam::ENTITY_CODE_EMERGENCY, true)->all();

        $urgenceRepository = $entityManager->getRepository(Urgence::class);

        $urgence = new Urgence();

        $urgenceService->updateUrgence($urgence, $data, $formatService);

        $response = [];

        $isSEDCurrentClient = $specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED)
            || $specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_NS);

        $sameUrgentCounter = $urgenceRepository->countUrgenceMatching(
            $urgence->getDateStart(),
            $urgence->getDateEnd(),
            $urgence->getProvider(),
            $urgence->getCommande(),
            $isSEDCurrentClient ? $urgence->getPostNb() : null
        );

        if ($sameUrgentCounter > 0) {
            $response['success'] = false;
            $response['msg'] = $this->getErrorMessageForDuplicate($isSEDCurrentClient);
        }
        else {
            $entityManager->persist($urgence);
            $entityManager->flush();
            $response['success'] = true;
            $response['msg'] = "L'urgence a été créée avec succès.";
        }
        return new JsonResponse($response);
    }

    #[Route('/supprimer', name: 'emergency_delete', options: ['expose' => true], methods: ['GET', 'POST'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $urgenceRepository = $entityManager->getRepository(Urgence::class);
            $urgence = $urgenceRepository->find($data['urgence']);
            $canDeleteUrgence = !$urgence->getLastArrival();
            if ($canDeleteUrgence) {
                $entityManager->remove($urgence);
                $entityManager->flush();
            }

            return new JsonResponse();
        }

        throw new BadRequestHttpException();
    }

    #[Route('/api-modifier', name: 'emergency_edit_api', options: ['expose' => true], methods: ['POST'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);

        if ($data = json_decode($request->getContent(), true)) {
            $urgenceRepository = $entityManager->getRepository(Urgence::class);
            $urgence = $urgenceRepository->find($data['id']);
            $json = $this->renderView('urgence/formEmergency.html.twig', [
                'emergency' => $urgence,
                'fieldsParam' => $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_EMERGENCY),
                'types' => $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_EMERGENCY, FieldsParam::FIELD_CODE_EMERGENCY_TYPE)
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }
    #[route('/modifier', name: 'emergency_edit', options: ['expose' => true], methods: ['POST'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function edit(Request $request,
                         SpecificService $specificService,
                         EntityManagerInterface $entityManager,
                         UrgenceService $urgenceService,
                         FieldsParamService $fieldsParamService,
                         FormatService $formatService): Response
    {
        $data = $fieldsParamService->checkForErrors($entityManager, $request->request , FieldsParam::ENTITY_CODE_EMERGENCY, false)->all();
        $urgenceRepository = $entityManager->getRepository(Urgence::class);
        $urgence = $urgenceRepository->find($data['id']);
        $response = [];

        if ($urgence) {
            $isSEDCurrentClient = $specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED)
                || $specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_NS);

            $urgenceService->updateUrgence($urgence, $data, $formatService);
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
                $response['msg'] = $this->getErrorMessageForDuplicate($isSEDCurrentClient);;
            }
            else {
                $entityManager->flush();
                $response['success'] = true;
                $response['msg'] = "L'urgence a été modifiée avec succès.";
            }
        }
        else {
            $response['success'] = false;
            $response['msg'] = "Une erreur est survenue lors de la modification de l'urgence.";
        }

        return new JsonResponse($response);
    }

    #[Route('/verification', name: 'emergency_check_delete', options: ['expose' => true], methods: ['GET', 'POST'], condition: 'request.isXmlHttpRequest()')]
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

    #[Route("/csv", name: "get_emergencies_csv", options: ["expose" => true], methods: ["GET"])]
    #[HasPermission([Menu::TRACA, Action::EXPORT], mode: HasPermission::IN_JSON)]
    public function getEmergenciesCSV(EntityManagerInterface $entityManager,
                                      Request                $request,
                                      UrgenceService         $emergencyService,
                                      TranslationService     $translationService,
                                      CSVExportService       $CSVExportService): Response {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (\Throwable $throwable) {

        }
        if (isset($dateTimeMin) && isset($dateTimeMax)) {

            $urgenceRepositoty = $entityManager->getRepository(Urgence::class);
            $urgenceIterator = $urgenceRepositoty->iterateByDates($dateTimeMin, $dateTimeMax);

            $csvheader = [
                $translationService->translate('Traçabilité', 'Urgences', 'Date de début', false),
                $translationService->translate('Traçabilité', 'Urgences', 'Date de fin', false),
                $translationService->translate('Traçabilité', 'Urgences', 'N° de commande', false),
                $translationService->translate('Traçabilité', 'Urgences', 'N° poste', false),
                $translationService->translate('Traçabilité', 'Urgences', 'Acheteur', false),
                $translationService->translate('Traçabilité', 'Urgences', 'Fournisseur', false),
                $translationService->translate('Traçabilité', 'Urgences', 'Transporteur', false),
                $translationService->translate('Traçabilité', 'Urgences', 'N° tracking transporteur', false),
                $translationService->translate('Traçabilité', 'Urgences', 'Date arrivage', false),
                $translationService->translate('Traçabilité', 'Urgences', 'Numéro d\'arrivage', false),
                $translationService->translate('Général', null, 'Zone liste', 'Date de création', false),
            ];
            $today = new DateTime();
            $user = $this->getUser();
            $today = $today->format("d-m-Y-H-i-s");
            return $CSVExportService->streamResponse(
                function ($output) use ($urgenceIterator, $CSVExportService, $user, $emergencyService) {
                    /** @var Urgence $urgence */
                    foreach ($urgenceIterator as $urgence) {
                        $CSVExportService->putLine($output, $emergencyService->serializeEmergency($urgence, $user));
                    }
                },
                "Export-Urgence-" . $today . ".csv",
                $csvheader
            );
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
