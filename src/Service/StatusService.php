<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\CategorieStatut;
use App\Entity\FiltreSup;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Security;
use Twig\Environment as Twig_Environment;

class StatusService
{

    private $specificService;
    private $entityManager;
    private $security;
    private $templating;
    private $router;

    /**
     * @var Utilisateur
     */
    private $user;

    public function __construct(SpecificService $specificService,
                                EntityManagerInterface $entityManager,
                                Security $security,
                                TokenStorageInterface $tokenStorage,
                                Twig_Environment $templating,
                                RouterInterface $router) {
        $this->specificService = $specificService;
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->templating = $templating;
        $this->router = $router;
    }

    public function findAllStatusArrivage() {
        $statutRepository = $this->entityManager->getRepository(Statut::class);
        if ($this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED)) {
            $status =  $statutRepository->findByCategoryNameAndStatusCodes(CategorieStatut::ARRIVAGE, [Arrivage::STATUS_CONFORME, Arrivage::STATUS_RESERVE]);
        } else {
            $status = $statutRepository->findByCategorieName(CategorieStatut::ARRIVAGE);
        }
        return $status;
    }

    /**
     * @param null $params
     * @return array
     */
    public function getDataForDatatable($params = null)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $statusRepository = $this->entityManager->getRepository(Statut::class);

        $statusFilter = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_STATUS, $this->security->getUser());
        $queryResult = $statusRepository->findByParamsAndFilters($params, $statusFilter);

        $statusArray = $queryResult['data'];

        $rows = [];
        foreach ($statusArray as $status) {
            $rows[] = $this->dataRowStatus($status);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    /**
     * @param Statut $status
     * @return array
     * @throws SyntaxError
     */
    public function dataRowStatus($status)
    {

        $url['edit'] = $this->router->generate('status_api_edit', ['id' => $status->getId()]);
        return [
            'id' => $status->getId() ?? '',
            'category' => $status->getCategorie() ? $status->getCategorie()->getNom() : '',
            'label' => $status->getNom() ? $status->getNom() : '',
            'comment' => $status->getComment() ? $status->getComment() : '',
            'treatedStatus' => $status->getTreated() ? 'oui' : 'non',
            'defaultStatus' => $status->isDefaultForCategory() ? 'oui' : 'non',
            'notifToDeclarant' => $status->getSendNotifToDeclarant() ? 'oui' : 'non',
            'order' => $status->getDisplayOrder() ?? '',
            'actions' => $this->templating->render('status/datatableStatusRow.html.twig', [
                'url' => $url,
                'statusId' => $status->getId(),
            ]),
        ];
    }

    public function canStatusBeDefault(EntityManagerInterface $entityManager,
                                       string $categoryStatusLabel,
                                       ?Type $type = null,
                                       Statut $status = null): bool {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeId = $type ? $type->getId() : null;
        $definedDefaultStatus = array_values(array_filter(
            $statutRepository->findByCategorieNames([$categoryStatusLabel]),
            function (Statut $savedStatus) use ($typeId) {
                $savedTypeId = $savedStatus->getType() ? $savedStatus->getType()->getId() : null;
                return (
                    $savedTypeId === $typeId
                    && $savedStatus->isDefaultForCategory()
                );
            }
        ));
        $definedDefaultStatusCounter = count($definedDefaultStatus);

        return (
            $definedDefaultStatusCounter === 0
            || (
                !empty($status)
                && $definedDefaultStatusCounter === 1
                && $status->getId() === $definedDefaultStatus[0]->getId()
            )
        );
    }
}
