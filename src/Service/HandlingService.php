<?php


namespace App\Service;


use App\Entity\FieldsParam;
use App\Entity\FiltreSup;
use App\Entity\Handling;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use WiiCommon\Helper\Stream;
use DateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class HandlingService
{
    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var RouterInterface
     */
    private $router;

    private $userService;
    private $entityManager;
    private $mailerService;
    private $translator;
    private $dateService;
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage,
                                UserService $userService,
                                RouterInterface $router,
                                MailerService $mailerService,
                                EntityManagerInterface $entityManager,
                                DateService $dateService,
                                Twig_Environment $templating,
                                TranslatorInterface $translator)
    {
        $this->templating = $templating;
        $this->entityManager  = $entityManager;
        $this->userService = $userService;
        $this->mailerService = $mailerService;
        $this->router = $router;
        $this->tokenStorage = $tokenStorage;
        $this->translator = $translator;
        $this->dateService = $dateService;
    }

    public function getDataForDatatable($params = null, $statusFilter = null)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $handlingRepository = $this->entityManager->getRepository(Handling::class);
        $settingRepository = $this->entityManager->getRepository(Setting::class);

        $includeDesiredTime = !$settingRepository->getOneParamByLabel(Setting::REMOVE_HOURS_DATETIME);

        if ($statusFilter) {
            $filters = [
                [
                    'field' => 'statut',
                    'value' => $statusFilter
                ]
            ];
        } else {
            $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_HAND, $this->tokenStorage->getToken()->getUser());
        }

        $queryResult = $handlingRepository->findByParamAndFilters($params, $filters);

        $handlingArray = $queryResult['data'];

        $rows = [];
        foreach ($handlingArray as $handling) {
            $rows[] = $this->dataRowHandling($handling, $includeDesiredTime);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    /**
     * @param Handling $handling
     * @param bool $includeDesiredTime
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function dataRowHandling(Handling $handling, bool $includeDesiredTime = true)
    {
//        $treatmentDelay = $handling->getTreatmentDelay();
//        $treatmentDelayInterval = $treatmentDelay ? $this->dateService->secondsToDateInterval($treatmentDelay) : null;
//        $treatmentDelayStr = $treatmentDelayInterval ? $this->dateService->intervalToStr($treatmentDelayInterval) : '';
        return [
            'id' => $handling->getId() ?: 'Non défini',
            'number' => $handling->getNumber() ?: '',
            'creationDate' => FormatHelper::datetime($handling->getCreationDate()),
            'type' => $handling->getType() ? $handling->getType()->getLabel() : '',
            'requester' => FormatHelper::handlingRequester($handling),
            'subject' => $handling->getSubject() ?: '',
            "receivers" => FormatHelper::users($handling->getReceivers()->toArray()),
            'desiredDate' => $includeDesiredTime
                ? FormatHelper::datetime($handling->getDesiredDate())
                : FormatHelper::date($handling->getDesiredDate()),
            'validationDate' => FormatHelper::datetime($handling->getValidationDate()),
            'status' => $handling->getStatus()->getNom() ? $handling->getStatus()->getNom() : null,
            'emergency' => $handling->getEmergency() ?? '',
            'treatedBy' => $handling->getTreatedByHandling() ? $handling->getTreatedByHandling()->getUsername() : '',
            //'treatmentDelay' => $treatmentDelayStr,
            'carriedOutOperationCount' => is_int($handling->getCarriedOutOperationCount()) ? $handling->getCarriedOutOperationCount() : '',
            'Actions' => $this->templating->render('handling/datatableHandlingRow.html.twig', [
                'handling' => $handling
            ]),
        ];
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Handling $handling
     * @param bool $isNewHandlingAndNotTreated
     * @param bool $viewHoursOnExpectedDate
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function sendEmailsAccordingToStatus(EntityManagerInterface $entityManager,
                                                Handling $handling,
                                                $viewHoursOnExpectedDate = false,
                                                $isNewHandlingAndNotTreated = false): void {
        $status = $handling->getStatus();
        $requester = $status->getSendNotifToDeclarant() ? $handling->getRequester() : null;
        $receivers = $status->getSendNotifToRecipient() ? $handling->getReceivers() : [];

        $emailReceivers = Stream::from($receivers, [$requester])
            ->unique()
            ->toArray();

        if (!empty($emailReceivers)) {
            $statusTreated = $status->isTreated();
            if ($isNewHandlingAndNotTreated) {
                $subject = $this->translator->trans('services.Création d\'une demande de service');
                $title = $this->translator->trans('services.Votre demande de service a été créée') . '.';
            } else {
                $subject = $statusTreated
                    ? $this->translator->trans('services.Demande de service effectuée')
                    : $this->translator->trans('services.Changement de statut d\'une demande de service');
                $title = $statusTreated
                    ? $this->translator->trans('services.Votre demande de service a bien été effectuée') . '.'
                    : $this->translator->trans('services.Une demande de service vous concernant a changé de statut') . '.';
            }

            $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
            $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_HANDLING);

            $this->mailerService->sendMail(
                'FOLLOW GT // ' . $subject,
                $this->templating->render('mails/contents/mailHandlingTreated.html.twig', [
                    'handling' => $handling,
                    'title' => $title,
                    'fieldsParam' => $fieldsParam,
                    'viewHoursOnExpectedDate' => $viewHoursOnExpectedDate
                ]),
                $emailReceivers
            );
        }
    }

    /**
     * @param Handling $handling
     * @param DateService $dateService
     * @param array $averageRequestTimesByType
     * @return array
     * @throws \Exception
     */
    public function parseRequestForCard(Handling $handling, DateService $dateService, array $averageRequestTimesByType) {
        $requestStatus = $handling->getStatus() ? $handling->getStatus()->getNom() : '';
        $requestBodyTitle = !empty($handling->getSubject())
            ? $handling->getSubject() . (!empty($handling->getType())
                ? ' - ' . $handling->getType()->getLabel()
                : '')
            : '';
        $state = $handling->getStatus() ? $handling->getStatus()->getState() : null;

        $href = $this->router->generate('handling_index') . '?open-modal=edit&modal-edit-id=' . $handling->getId();

        $typeId = $handling->getType() ? $handling->getType()->getId() : null;
        $averageTime = $averageRequestTimesByType[$typeId] ?? null;

        $deliveryDateEstimated = 'Non estimée';
        $estimatedFinishTimeLabel = 'Date de traitement non estimée';

        if (isset($averageTime)) {
            $today = new DateTime();
            $expectedDate = (clone $handling->getCreationDate())
                ->add($dateService->secondsToDateInterval($averageTime->getAverage()));
            if ($expectedDate >= $today) {
                $estimatedFinishTimeLabel = 'Date et heure de traitement prévue';
                $deliveryDateEstimated = $expectedDate->format('d/m/Y H:i');
                if ($expectedDate->format('d/m/Y') === $today->format('d/m/Y')) {
                    $estimatedFinishTimeLabel = 'Heure de traitement estimée';
                    $deliveryDateEstimated = $expectedDate->format('H:i');
                } else {

                }
            }
        }

        $requestDate = $handling->getCreationDate();
        $requestDateStr = $requestDate
            ? (
                $requestDate->format('d ')
                . DateService::ENG_TO_FR_MONTHS[$requestDate->format('M')]
                . $requestDate->format(' (H\hi)')
            )
            : 'Non défini';

        $statusesToProgress = [
            Statut::DRAFT => 0,
            Statut::NOT_TREATED => 50,
            Statut::TREATED => 100
        ];

        return [
            'href' => $href ?? null,
            'errorMessage' => 'Vous n\'avez pas les droits d\'accéder à la page d\'état actuel de la demande de livraison',
            'estimatedFinishTime' => $deliveryDateEstimated,
            'estimatedFinishTimeLabel' => $estimatedFinishTimeLabel,
            'requestStatus' => $requestStatus,
            'requestBodyTitle' => $requestBodyTitle,
            'requestLocation' => $handling->getDestination() ?: 'Non défini',
            'requestNumber' => $handling->getNumber(),
            'requestDate' => $requestDateStr,
            'requestUser' => $handling->getRequester() ? $handling->getRequester()->getUsername() : 'Non défini',
            'cardColor' => 'darkWhite',
            'bodyColor' => 'lightGrey',
            'topRightIcon' => $handling->getEmergency() ? '' : 'livreur.svg',
            'emergencyText' => $handling->getEmergency() ?? '',
            'progress' =>  $statusesToProgress[$state] ?? 0,
            'progressBarColor' => '#2ec2ab',
            'progressBarBGColor' => 'lightGrey',
        ];
    }

}
