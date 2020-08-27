<?php


namespace App\Service;

use App\Entity\Acheminements;
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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Error\LoaderError as Twig_Error_Loader;
use Twig\Error\RuntimeError as Twig_Error_Runtime;
use Twig\Error\SyntaxError as Twig_Error_Syntax;
use Twig\Environment as Twig_Environment;

class AcheminementsService
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
        $acheminementsRepository = $this->entityManager->getRepository(Acheminements::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ACHEMINEMENTS, $this->user);
        $queryResult = $acheminementsRepository->findByParamAndFilters($params, $filters);

        $acheminementsArray = $queryResult['data'];

        $rows = [];
        foreach ($acheminementsArray as $acheminement) {
            $rows[] = $this->dataRowAcheminement($acheminement);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

	/**
	 * @param Acheminements $acheminement
	 * @return array
	 * @throws Twig_Error_Loader
	 * @throws Twig_Error_Runtime
	 * @throws Twig_Error_Syntax
	 */
    public function dataRowAcheminement($acheminement)
    {
        $nbColis = count($acheminement->getPacks());
        $url = $this->router->generate('acheminement-show', ['id' => $acheminement->getId()]);

        return [
            'id' => $acheminement->getId() ?? 'Non défini',
            'Numero' => $acheminement->getNumber() ?? '',
            'Date' => $acheminement->getDate() ? $acheminement->getDate()->format('d/m/Y H:i:s') : 'Non défini',
            'Demandeur' => $acheminement->getRequester() ? $acheminement->getRequester()->getUserName() : '',
            'Destinataire' => $acheminement->getReceiver() ? $acheminement->getReceiver()->getUserName() : '',
            'Emplacement prise' => $acheminement->getLocationFrom() ? $acheminement->getLocationFrom()->getLabel() : '',
            'Emplacement de dépose' => $acheminement->getLocationTo() ? $acheminement->getLocationTo()->getLabel() : '',
            'Nb Colis' => $nbColis ?? 0,
            'Type' => $acheminement->getType() ? $acheminement->getType()->getLabel() : '',
            'Statut' => $acheminement->getStatut() ? $acheminement->getStatut()->getNom() : '',
            'Urgence' => $acheminement->isUrgent() ? 'oui' : 'non',
            'Actions' => $this->templating->render('acheminements/datatableAcheminementsRow.html.twig', [
                'acheminement' => $acheminement,
                'url' => $url,
            ]),
        ];
    }

    public function createHeaderDetailsConfig(Acheminements $acheminement): array
    {
        $status = $acheminement->getStatut();
        $type = $acheminement->getType();
        $requester = $acheminement->getRequester();
        $locationFrom = $acheminement->getLocationFrom();
        $locationTo = $acheminement->getLocationTo();
        $creationDate = $acheminement->getDate();
        $comment = $acheminement->getCommentaire();

        $freeFieldArray = $this->freeFieldService->getFilledFreeFieldArray(
            $this->entityManager,
            $acheminement,
            CategorieCL::DEMANDE_ACHEMINEMENT,
            CategoryType::DEMANDE_ACHEMINEMENT
        );

        return array_merge(
            [
                ['label' => 'Statut', 'value' => $status ? $status->getNom() : ''],
                ['label' => 'Type', 'value' => $type ? $type->getLabel() : ''],
                ['label' => 'Demandeur', 'value' => $requester ? $requester->getUsername() : ''],
                ['label' => 'Emplacement de prise', 'value' => $locationFrom ? $locationFrom->getLabel() : ''],
                ['label' => 'Emplacement de dépose', 'value' => $locationTo ? $locationTo->getLabel() : ''],
                ['label' => 'Date de création', 'value' => $creationDate ? $creationDate->format('d/m/Y H:i:s') : ''],
            ],
            $freeFieldArray,
            [
                [
                    'label' => 'Commentaire',
                    'value' => $comment ?: '',
                    'isRaw' => true,
                    'colClass' => 'col-sm-6 col-12',
                    'isScrollable' => true,
                    'isNeededNotEmpty' => true
                ]
            ]
        );
    }

    public function createDispatchNumber(EntityManagerInterface $entityManager,
                                         DateTime $date): string {

        $acheminementRepository = $entityManager->getRepository(Acheminements::class);

        $dateStr = $date->format('Ymd');

        $lastDispatchNumber = $acheminementRepository->getLastDispatchNumberByPrefix(Acheminements::PREFIX_NUMBER . $dateStr);

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

        return (Acheminements::PREFIX_NUMBER . $dateStr . $currentCounterStr);
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

    public function sendMailsAccordingToStatus(Acheminements $acheminement, bool $isUpdate) {
        $status = $acheminement->getStatut();
        $recipientAbleToReceivedMail = $status ? $status->getSendNotifToRecipient() : false;
        $requesterAbleToReceivedMail = $status ? $status->getSendNotifToDeclarant() : false;

        if ($recipientAbleToReceivedMail || $requesterAbleToReceivedMail) {
            $type = $acheminement->getType() ? $acheminement->getType()->getLabel() : '';
            $receiverEmails = $acheminement->getReceiver() ? $acheminement->getReceiver()->getMainAndSecondaryEmails() : [];
            $requesterEmails = $acheminement->getRequester() ? $acheminement->getRequester()->getMainAndSecondaryEmails() : [];

            $translatedCategory = $this->translator->trans('acheminement.demande d\'acheminement');
            $title = !$isUpdate
                ? ('Une' . $translatedCategory . ' de type ' . $type . ' vous concerne :')
                : ('Changement de statut d\'une ' . $translatedCategory . ' de type ' . $type . ' vous concernant :');
            $subject = !$isUpdate
                ? ('FOLLOW GT // Création d\'une ' . $translatedCategory)
                : 'FOLLOW GT // Changement de statut d\'une ' . $translatedCategory . '.';

            $emails = [];

            if ($recipientAbleToReceivedMail && !empty($receiverEmails)) {
                array_push($emails, ...$receiverEmails);
            }

            if ($requesterAbleToReceivedMail && !empty($requesterEmails)) {
                array_push($emails, ...$requesterEmails);
            }

            if (!empty($emails)) {
                $this->mailerService->sendMail(
                    $subject,
                    $this->templating->render('mails/contents/mailAcheminement.html.twig', [
                        'acheminement' => $acheminement,
                        'title' => $title,
                        'urlSuffix' => $translatedCategory
                    ]),
                    $emails
                );
            }
        }
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Acheminements $acheminement
     * @param Statut $treatedStatus
     * @param Utilisateur $loggedUser
     * @param bool $fromNomade
     * @throws Exception
     */
    public function validateDispatchRequest(EntityManagerInterface $entityManager,
                                            Acheminements $acheminement,
                                            Statut $treatedStatus,
                                            Utilisateur $loggedUser,
                                            bool $fromNomade = false): void {
        $acheminement->setStatut($treatedStatus);
        $packsDispatch = $acheminement->getPackAcheminements();
        $takingLocation = $acheminement->getLocationFrom();
        $dropLocation = $acheminement->getLocationTo();
        $date = new DateTime('now', new \DateTimeZone('Europe/Paris'));

        foreach ($packsDispatch as $packDispatch) {
            $pack = $packDispatch->getPack();

            $trackingTaking = $this->mouvementTracaService->createTrackingMovement(
                $pack,
                $takingLocation,
                $loggedUser,
                $date,
                $fromNomade,
                true,
                MouvementTraca::TYPE_PRISE
            );

            $trackingDrop = $this->mouvementTracaService->createTrackingMovement(
                $pack,
                $dropLocation,
                $loggedUser,
                $date,
                $fromNomade,
                true,
                MouvementTraca::TYPE_DEPOSE
            );

            $entityManager->persist($trackingTaking);
            $entityManager->persist($trackingDrop);
        }
        $entityManager->flush();

        $this->sendMailsAccordingToStatus($acheminement, true);
    }
}
