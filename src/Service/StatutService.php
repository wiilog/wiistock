<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\CategorieStatut;
use App\Entity\FiltreSup;
use App\Entity\Statut;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Component\Security\Core\Security;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;

class StatutService
{

    private $specificService;
    private $entityManager;
    private $security;

    public function __construct(SpecificService $specificService,
                                EntityManagerInterface $entityManager,
                                Security $security) {
        $this->specificService = $specificService;
        $this->entityManager = $entityManager;
        $this->security = $security;
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
