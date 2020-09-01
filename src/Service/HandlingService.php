<?php


namespace App\Service;


use App\Entity\FiltreSup;
use App\Entity\Handling;
use App\Entity\Utilisateur;
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

    public function __construct(TokenStorageInterface $tokenStorage,
                                RouterInterface $router,
                                MailerService $mailerService,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating)
    {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->mailerService = $mailerService;
        $this->router = $router;
        $this->user = $tokenStorage->getToken()->getUser();
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
            $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_MANUT, $this->user);
        }

        $queryResult = $handlingRepository->findByParamAndFilters($params, $filters);

        $handlingArray = $queryResult['data'];

        $rows = [];
        foreach ($handlingArray as $handling) {
            $rows[] = $this->dataRowManut($handling);
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
    public function dataRowManut(Handling $handling)
    {
        return [
            'id' => ($handling->getId() ? $handling->getId() : 'Non défini'),
            'Date demande' => ($handling->getDate() ? $handling->getDate()->format('d/m/Y') : null),
            'Demandeur' => ($handling->getDemandeur() ? $handling->getDemandeur()->getUserName() : null),
            'Libellé' => ($handling->getlibelle() ? $handling->getLibelle() : null),
            'Date souhaitée' => ($handling->getDateAttendue() ? $handling->getDateAttendue()->format('d/m/Y H:i') : null),
            'Date de réalisation' => ($handling->getDateEnd() ? $handling->getDateEnd()->format('d/m/Y H:i') : null),
            'Statut' => ($handling->getStatut()->getNom() ? $handling->getStatut()->getNom() : null),
            'Actions' => $this->templating->render('manutention/datatableManutentionRow.html.twig', [
                'manut' => $handling
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
            'FOLLOW GT // Manutention effectuée',
            $this->templating->render('mails/contents/mailManutentionDone.html.twig', [
                'manut' => $handling,
                'title' => 'Votre demande de manutention a bien été effectuée.',
            ]),
            $handling->getDemandeur()->getMainAndSecondaryEmails()
        );
    }
}
