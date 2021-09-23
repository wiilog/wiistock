<?php


namespace App\Service;

use App\Entity\FiltreSup;

use App\Entity\Pack;
use App\Entity\ReceiptAssociation;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use DateTime;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;

class ReceiptAssociationService
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
     * @var UserService
     */
    private $userService;

    private $security;

    private $entityManager;

    public function __construct(UserService $userService,
                                RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                Security $security)
    {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->userService = $userService;
        $this->security = $security;
    }

    public function getDataForDatatable($params = null)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $receiptAssociationRepository = $this->entityManager->getRepository(ReceiptAssociation::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_RECEIPT_ASSOCIATION, $this->security->getUser());
        $queryResult = $receiptAssociationRepository->findByParamsAndFilters($params, $filters);

        $receiptAssocations = $queryResult['data'];

        $rows = [];
        foreach ($receiptAssocations as $receiptAssocation) {
            $rows[] = $this->dataRowReceiptAssociation($receiptAssocation);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    public function dataRowReceiptAssociation(ReceiptAssociation $receiptAssocation)
    {
        return [
            'id' => $receiptAssocation->getId(),
            'creationDate' => FormatHelper::datetime($receiptAssocation->getCreationDate()),
            'pack' => $receiptAssocation->getPack() ? $receiptAssocation->getPack()->getCode() : '',
            'lastLocation' => $receiptAssocation->getPack()
                ? ($receiptAssocation->getPack()->getLastTracking()
                    ? FormatHelper::location($receiptAssocation->getPack()->getLastTracking()->getEmplacement())
                    : '')
                : '',
            'lastMovementDate' => $receiptAssocation->getPack()
                ? ($receiptAssocation->getPack()->getLastTracking()
                    ? FormatHelper::datetime($receiptAssocation->getPack()->getLastTracking()->getDatetime())
                    : '')
                : '',
            'receptionNumber' => $receiptAssocation->getReceptionNumber() ?? '',
            'user' => FormatHelper::user($receiptAssocation->getUser()),
            'Actions' => $this->templating->render('receipt_association/datatableRowActions.html.twig', [
                'receipt_association' => $receiptAssocation,
            ])
        ];
    }

    public function persistReceiptAssociation(EntityManagerInterface $manager,
                                              ?Pack $pack,
                                              string $reception,
                                              Utilisateur $user) {
        $now = new DateTime('now');

        $receiptAssociation = (new ReceiptAssociation())
            ->setReceptionNumber($reception)
            ->setUser($user)
            ->setCreationDate($now)
            ->setPack($pack);

        $manager->persist($receiptAssociation);
    }
}
