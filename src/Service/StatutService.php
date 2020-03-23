<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\CategorieStatut;
use App\Entity\Statut;
use Doctrine\ORM\EntityManagerInterface;

class StatutService
{

    private $specificService;
    private $entityManager;

    public function __construct(SpecificService $specificService,
                                EntityManagerInterface $entityManager) {
        $this->specificService = $specificService;
        $this->entityManager = $entityManager;
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
}
