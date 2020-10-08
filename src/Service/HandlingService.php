<?php


namespace App\Service;


use App\Entity\Action;
use App\Entity\Demande;
use App\Entity\FiltreSup;
use App\Entity\Handling;
use App\Entity\Menu;
use App\Entity\Statut;
use App\Entity\Utilisateur;
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

    /**
     * @var Utilisateur
     */
    private $user;

    private $userService;
    private $entityManager;
    private $mailerService;
    private $translator;

    public function __construct(TokenStorageInterface $tokenStorage,
                                UserService $userService,
                                RouterInterface $router,
                                MailerService $mailerService,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                TranslatorInterface $translator)
    {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->userService = $userService;
        $this->mailerService = $mailerService;
        $this->router = $router;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->translator = $translator;
    }

    public function getDataForDatatable($params = null, $statusFilter = null)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $handlingRepository = $this->entityManager->getRepository(Handling::class);

        if ($statusFilter) {
            $filters = [
                [
                    'field' => 'statut',
                    'value' => $statusFilter
                ]
            ];
        } else {
            $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_HAND, $this->user);
        }

        $queryResult = $handlingRepository->findByParamAndFilters($params, $filters);

        $handlingArray = $queryResult['data'];

        $rows = [];
        foreach ($handlingArray as $handling) {
            $rows[] = $this->dataRowHandling($handling);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    /**
     * @param Handling $handling
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function dataRowHandling(Handling $handling)
    {
        return [
            'id' => $handling->getId() ? $handling->getId() : 'Non défini',
            'number' => $handling->getNumber() ? $handling->getNumber() : '',
            'creationDate' => $handling->getCreationDate() ? $handling->getCreationDate()->format('d/m/Y H:i:s') : null,
            'type' => $handling->getType() ? $handling->getType()->getLabel() : '',
            'requester' => $handling->getRequester() ? $handling->getRequester()->getUserName() : null,
            'subject' => $handling->getSubject() ? $handling->getSubject() : '',
            'desiredDate' => $handling->getDesiredDate() ? $handling->getDesiredDate()->format('d/m/Y H:i:s') : null,
            'validationDate' => $handling->getValidationDate() ? $handling->getValidationDate()->format('d/m/Y H:i:s') : null,
            'status' => $handling->getStatus()->getNom() ? $handling->getStatus()->getNom() : null,
            'emergency' => $handling->getEmergency() ?? '',
            'treatedBy' => $handling->getTreatedByHandling() ? $handling->getTreatedByHandling()->getUsername() : '',
            'Actions' => $this->templating->render('handling/datatableHandlingRow.html.twig', [
                'handling' => $handling
            ]),
        ];
    }

    /**
     * @param Handling $handling
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function sendEmailsAccordingToStatus(Handling $handling): void {
        $requester = $handling->getRequester();
        $emails = $requester ? $requester->getMainAndSecondaryEmails() : [];
        if (!empty($emails)) {
            $status = $handling->getStatus();
            if ($status && $status->getSendNotifToDeclarant()) {
                $statusTreated = $status->isTreated();
                $subject = $statusTreated
                    ? $this->translator->trans('services.Demande de service effectuée')
                    : $this->translator->trans('services.Changement de statut d\'une demande de service');
                $title = $statusTreated
                    ? $this->translator->trans('services.Votre demande de service a bien été effectuée') . '.'
                    : $this->translator->trans('services.Une demande de service vous concernant a changé de statut') . '.';
                $this->mailerService->sendMail(
                    'FOLLOW GT // ' . $subject,
                    $this->templating->render('mails/contents/mailHandlingTreated.html.twig', [
                        'handling' => $handling,
                        'title' => $title
                    ]),
                    $emails
                );
            }
        }
    }

    public function createHandlingNumber(EntityManagerInterface $entityManager,
                                         DateTime $date): string {

        $handlingRepository = $entityManager->getRepository(Handling::class);

        $dateStr = $date->format('Ymd');

        $lastHandlingNumber = $handlingRepository->getLastHandlingNumberByPrefix(Handling::PREFIX_NUMBER . $dateStr);

        if ($lastHandlingNumber) {
            $lastCounter = (int) substr($lastHandlingNumber, -4, 4);
            $currentCounter = ($lastCounter + 1);
        }
        else {
            $currentCounter = 1;
        }

        $currentCounterStr = (
        $currentCounter < 10 ? ('000' . $currentCounter) :
            ($currentCounter < 100 ? ('00' . $currentCounter) :
                ($currentCounter < 1000 ? ('0' . $currentCounter) :
                    $currentCounter))
        );

        return (Handling::PREFIX_NUMBER . $dateStr . $currentCounterStr);
    }

    /**
     * @param Handling $handling
     * @param DateService $dateService
     * @param array $averageRequestTimesByType
     * @return array
     * @throws \Exception
     */
    public function parseRequestForCard(Handling $handling, DateService $dateService, array $averageRequestTimesByType) {
        $hasRightHandling = $this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_HAND);

        $requestStatus = $handling->getStatus() ? $handling->getStatus()->getNom() : '';
        $state = $handling->getStatus() ? $handling->getStatus()->getState() : null;

        if ($hasRightHandling) {
            $href = $this->router->generate('handling_index');
        }

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
            'requestBodyTitle' => $handling->getSubject(),
            'requestLocation' => $handling->getDestination() ?: 'Non défini',
            'requestNumber' => $handling->getNumber(),
            'requestDate' => $requestDateStr,
            'requestUser' => $handling->getRequester() ? $handling->getRequester()->getUsername() : 'Non défini',
            'cardColor' => 'white',
            'bodyColor' => 'lightGrey',
            'topRightIcon' => 'fa-box',
            'progress' =>  $statusesToProgress[$state] ?? 0,
            'progressBarColor' => '#2ec2ab',
            'progressBarBGColor' => 'lightGrey',
        ];
    }

}
