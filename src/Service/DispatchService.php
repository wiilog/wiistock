<?php


namespace App\Service;

use App\Entity\Dispatch;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\FiltreSup;
use App\Entity\MouvementTraca;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Error\LoaderError as Twig_Error_Loader;
use Twig\Error\RuntimeError as Twig_Error_Runtime;
use Twig\Error\SyntaxError as Twig_Error_Syntax;
use Twig\Environment as Twig_Environment;

class DispatchService
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

    private $entityManager;
    private $freeFieldService;
    private $translator;
    private $mailerService;
    private $mouvementTracaService;

    public function __construct(TokenStorageInterface $tokenStorage,
                                RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                FreeFieldService $champLibreService,
                                TranslatorInterface $translator,
                                MouvementTracaService $mouvementTracaService,
                                MailerService $mailerService) {
        $this->templating = $templating;
        $this->mouvementTracaService = $mouvementTracaService;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->freeFieldService = $champLibreService;
        $this->translator = $translator;
        $this->mailerService = $mailerService;
    }

    /**
     * @param null $params
     * @return array
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function getDataForDatatable($params = null) {

        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $dispatchRepository = $this->entityManager->getRepository(Dispatch::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_DISPATCH, $this->user);
        $queryResult = $dispatchRepository->findByParamAndFilters($params, $filters);

        $dispatchesArray = $queryResult['data'];

        $rows = [];
        foreach ($dispatchesArray as $dispatch) {
            $rows[] = $this->dataRowDispatch($dispatch);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

	/**
	 * @param Dispatch $dispatch
	 * @return array
	 * @throws Twig_Error_Loader
	 * @throws Twig_Error_Runtime
	 * @throws Twig_Error_Syntax
	 */
    public function dataRowDispatch(Dispatch $dispatch)
    {
        $url = $this->router->generate('dispatch_show', ['id' => $dispatch->getId()]);

        return [
            'id' => $dispatch->getId() ?? 'Non défini',
            'number' => $dispatch->getNumber() ?? '',
            'creationDate' => $dispatch->getCreationDate() ? $dispatch->getCreationDate()->format('d/m/Y H:i:s') : '',
            'validationDate' => $dispatch->getValidationDate() ? $dispatch->getValidationDate()->format('d/m/Y H:i:s') : '',
            'requester' => $dispatch->getRequester() ? $dispatch->getRequester()->getUserName() : '',
            'receiver' => $dispatch->getReceiver() ? $dispatch->getReceiver()->getUserName() : '',
            'locationFrom' => $dispatch->getLocationFrom() ? $dispatch->getLocationFrom()->getLabel() : '',
            'locationTo' => $dispatch->getLocationTo() ? $dispatch->getLocationTo()->getLabel() : '',
            'nbPacks' => $dispatch->getDispatchPacks()->count(),
            'type' => $dispatch->getType() ? $dispatch->getType()->getLabel() : '',
            'status' => $dispatch->getStatut() ? $dispatch->getStatut()->getNom() : '',
            'urgent' => $dispatch->isUrgent() ? 'oui' : 'non',
            'actions' => $this->templating->render('dispatch/datatableDispatchRow.html.twig', [
                'dispatch' => $dispatch,
                'url' => $url
            ]),
        ];
    }

    public function createHeaderDetailsConfig(Dispatch $dispatch): array
    {
        $status = $dispatch->getStatut();
        $type = $dispatch->getType();
        $requester = $dispatch->getRequester();
        $receiver = $dispatch->getReceiver();
        $locationFrom = $dispatch->getLocationFrom();
        $locationTo = $dispatch->getLocationTo();
        $creationDate = $dispatch->getCreationDate();
        $validationDate = $dispatch->getValidationDate() ? $dispatch->getValidationDate() : '';
        $startDate = $dispatch->getStartDate();
        $endDate = $dispatch->getEndDate();
        $startDateStr = $startDate ? $startDate->format('d/m/Y') : '-';
        $endDateStr = $endDate ? $endDate->format('d/m/Y') : '-';
        $comment = $dispatch->getCommentaire();

        $freeFieldArray = $this->freeFieldService->getFilledFreeFieldArray(
            $this->entityManager,
            $dispatch,
            CategorieCL::DEMANDE_DISPATCH,
            CategoryType::DEMANDE_DISPATCH
        );

        return array_merge(
            [
                ['label' => 'Statut', 'value' => $status ? $status->getNom() : ''],
                ['label' => 'Type', 'value' => $type ? $type->getLabel() : ''],
                ['label' => 'Demandeur', 'value' => $requester ? $requester->getUsername() : ''],
                ['label' => 'Destinataire', 'value' => $receiver ? $requester->getUsername() : ''],
                ['label' => $this->translator->trans('acheminement.emplacement prise'), 'value' => $locationFrom ? $locationFrom->getLabel() : ''],
                ['label' => $this->translator->trans('acheminement.emplacement dépose'), 'value' => $locationTo ? $locationTo->getLabel() : ''],
                ['label' => 'Date de création', 'value' => $creationDate ? $creationDate->format('d/m/Y H:i:s') : ''],
                ['label' => 'Date de validation', 'value' => $validationDate ? $validationDate->format('d/m/Y H:i:s') : ''],
                ['label' => 'Dates d\'échéance', 'value' => ($startDate || $endDate) ? ('Du ' . $startDateStr . ' au ' . $endDateStr) : '']
            ],
            $freeFieldArray,
            [
                [
                    'label' => 'Pièces jointes',
                    'value' => $dispatch->getAttachements()->toArray(),
                    'isAttachments' => true,
                    'isNeededNotEmpty' => true
                ],
                [
                    'label' => 'Commentaire',
                    'value' => $comment ?: '',
                    'isRaw' => true,
                    'isScrollable' => true,
                    'isNeededNotEmpty' => true
                ]
            ]
        );
    }

    public function createDispatchNumber(EntityManagerInterface $entityManager,
                                         DateTime $date): string {

        $dispatchRepository = $entityManager->getRepository(Dispatch::class);

        $dateStr = $date->format('Ymd');

        $lastDispatchNumber = $dispatchRepository->getLastDispatchNumberByPrefix(Dispatch::PREFIX_NUMBER . $dateStr);

        if ($lastDispatchNumber) {
            $lastCounter = (int) substr($lastDispatchNumber, -4, 4);
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

        return (Dispatch::PREFIX_NUMBER . $dateStr . $currentCounterStr);
    }

    public function createDateFromStr(?string $dateStr): ?DateTime {
        $date = null;
        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            $date = (!empty($dateStr) && empty($date))
                ? DateTime::createFromFormat($format, $dateStr, new DateTimeZone("Europe/Paris"))
                : $date;
        }
        return $date ?: null;
    }

    public function sendMailsAccordingToStatus(Dispatch $dispatch, bool $isUpdate) {
        $status = $dispatch->getStatut();
        $recipientAbleToReceivedMail = $status ? $status->getSendNotifToRecipient() : false;
        $requesterAbleToReceivedMail = $status ? $status->getSendNotifToDeclarant() : false;

        if ($recipientAbleToReceivedMail || $requesterAbleToReceivedMail) {
            $type = $dispatch->getType() ? $dispatch->getType()->getLabel() : '';
            $receiverEmails = $dispatch->getReceiver() ? $dispatch->getReceiver()->getMainAndSecondaryEmails() : [];
            $requesterEmails = $dispatch->getRequester() ? $dispatch->getRequester()->getMainAndSecondaryEmails() : [];

            $translatedCategory = $this->translator->trans('acheminement.demande d\'acheminement');
            $title = $status->getTreated()
                ? ($this->translator->trans('acheminement.acheminement') . ' traité(e) : '.$dispatch->getNumber() . ', le ' . $dispatch->getValidationDate()->format('d/m/Y à H:i:s'))
                : (!$isUpdate
                    ? ('Une ' . $translatedCategory . ' de type ' . $type . ' vous concerne :')
                    : ('Changement de statut d\'une ' . $translatedCategory . ' de type ' . $type . ' vous concernant :'));
            $subject = $status->getTreated() ? ('FOLLOW GT // Notification de traitement d\'une ' . $this->translator->trans('acheminement.demande d\'acheminement') . '.')
                : (!$isUpdate
                ? ('FOLLOW GT // Création d\'une ' . $translatedCategory)
                : 'FOLLOW GT // Changement de statut d\'une ' . $translatedCategory . '.');

            $emails = [];

            if ($recipientAbleToReceivedMail && !empty($receiverEmails)) {
                array_push($emails, ...$receiverEmails);
            }

            if ($requesterAbleToReceivedMail && !empty($requesterEmails)) {
                array_push($emails, ...$requesterEmails);
            }

            $isTreatedStatus = $dispatch->getStatut() && $dispatch->getStatut()->getTreated();

            if (!empty($emails)) {
                $this->mailerService->sendMail(
                    $subject,
                    $this->templating->render('mails/contents/mailDispatch.html.twig', [
                        'dispatch' => $dispatch,
                        'title' => $title,
                        'urlSuffix' => $translatedCategory,
                        'hideNumber' => $isTreatedStatus,
                        'hideValidationDate' => $isTreatedStatus
                    ]),
                    $emails
                );
            }
        }
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Dispatch $dispatch
     * @param Statut $treatedStatus
     * @param Utilisateur $loggedUser
     * @param bool $fromNomade
     * @throws Exception
     */
    public function validateDispatchRequest(EntityManagerInterface $entityManager,
                                            Dispatch $dispatch,
                                            Statut $treatedStatus,
                                            Utilisateur $loggedUser,
                                            bool $fromNomade = false): void {
        $dispatchPacks = $dispatch->getDispatchPacks();
        $takingLocation = $dispatch->getLocationFrom();
        $dropLocation = $dispatch->getLocationTo();
        $date = new DateTime('now', new \DateTimeZone('Europe/Paris'));
        $dispatch
            ->setStatut($treatedStatus)
            ->setValidationDate($date);

        foreach ($dispatchPacks as $dispatchPack) {
            $pack = $dispatchPack->getPack();

            $trackingTaking = $this->mouvementTracaService->createTrackingMovement(
                $pack,
                $takingLocation,
                $loggedUser,
                $date,
                $fromNomade,
                true,
                MouvementTraca::TYPE_PRISE,
                ['quantity' => $dispatchPack->getQuantity(), 'from' => $dispatch]
            );

            $trackingDrop = $this->mouvementTracaService->createTrackingMovement(
                $pack,
                $dropLocation,
                $loggedUser,
                $date,
                $fromNomade,
                true,
                MouvementTraca::TYPE_DEPOSE,
                ['quantity' => $dispatchPack->getQuantity(), 'from' => $dispatch]
            );

            $entityManager->persist($trackingTaking);
            $entityManager->persist($trackingDrop);
        }
        $entityManager->flush();

        $this->sendMailsAccordingToStatus($dispatch, true);
    }
}
