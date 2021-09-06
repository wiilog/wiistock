<?php

namespace App\Service;

use App\Entity\VisibilityGroup;
use Symfony\Component\HttpFoundation\InputBag;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;
use Doctrine\ORM\EntityManagerInterface;

class VisibilityGroupService {

    /** @Required */
    public Twig_Environment $templating;

    public function getDataForDatatable(EntityManagerInterface $entityManager, InputBag $params = null) {
        $visibilityGroupRepository = $entityManager->getRepository(VisibilityGroup::class);

        $queryResult = $visibilityGroupRepository->findByParamsAndFilters($params);

        return [
            'data' => Stream::from($queryResult['data'])
                ->map(fn (VisibilityGroup $visibilityGroup) => $this->dataRowGroup($visibilityGroup))
                ->toArray(),
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    public function dataRowGroup(VisibilityGroup $visibilityGroup): array {
        return [
            'actions' => $this->templating->render('visibility_group/actions.html.twig', [
                'visibilityGroupId' => $visibilityGroup->getId()
            ]),
            'label' => $visibilityGroup->getLabel(),
            'description' => $visibilityGroup->getDescription(),
            'status' => $visibilityGroup->isActive() ? 'Actif' : 'Inactif',
        ];
    }

}
