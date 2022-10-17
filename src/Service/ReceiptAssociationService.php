<?php


namespace App\Service;

use App\Entity\FiltreSup;

use App\Entity\ReceiptAssociation;
use App\Helper\FormatHelper;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;

class ReceiptAssociationService
{
    /** @Required */
    public Twig_Environment $templating;

    /** @Required */
    public RouterInterface $router;

    /** @Required */
    public UserService $userService;

    /** @Required */
    public Security $security;

    /** @Required */
    public EntityManagerInterface $entityManager;


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
            'creationDate' => FormatHelper::datetime($receiptAssocation->getCreationDate(), "", false, $this->security->getUser()),
            'packCode' => $receiptAssocation->getPackCode() ?? '',
            'receptionNumber' => $receiptAssocation->getReceptionNumber() ?? '',
            'user' => FormatHelper::user($receiptAssocation->getUser()),
            'Actions' => $this->templating->render('receipt_association/datatableRowActions.html.twig', [
                'receipt_association' => $receiptAssocation,
            ])
        ];
    }
}
