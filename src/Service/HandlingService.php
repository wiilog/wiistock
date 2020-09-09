<?php


namespace App\Service;


use App\Entity\FiltreSup;
use App\Entity\Handling;
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

    private $entityManager;
    private $mailerService;
    private $translator;

    public function __construct(TokenStorageInterface $tokenStorage,
                                RouterInterface $router,
                                MailerService $mailerService,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                TranslatorInterface $translator)
    {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
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
                    'field' => 'status',
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
            'emergency' => $handling->getEmergency() ? 'oui' : 'non',
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
    public function sendTreatedEmail(Handling $handling): void {
        $this->mailerService->sendMail(
            'FOLLOW GT // '.$this->translator->trans('services.Demande de service effectuée'),
            $this->templating->render('mails/contents/mailHandlingDone.html.twig', [
                'handling' => $handling,
                'title' => $this->translator->trans('services.Votre demande de service a bien été effectuée').'.',
            ]),
            $handling->getRequester()->getMainAndSecondaryEmails()
        );
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
}
