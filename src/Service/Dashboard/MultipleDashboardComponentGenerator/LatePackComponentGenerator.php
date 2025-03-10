<?php

namespace App\Service\Dashboard\MultipleDashboardComponentGenerator;

use App\Entity\Dashboard;
use App\Entity\LatePack;
use App\Service\EnCoursService;
use Doctrine\ORM\EntityManagerInterface;

class LatePackComponentGenerator extends MultipleDashboardComponentGenerator {

    public function __construct(
        private EnCoursService     $enCoursService,
    ) {
    }

    /**
     * @param array<Dashboard\Component> $components
     */
    public function persistAll(EntityManagerInterface $entityManager, array $components): void {
        $latePackRepository = $entityManager->getRepository(LatePack::class);
        $lastLates = $this->enCoursService->getLastEnCoursForLate($entityManager);
        $latePackRepository->clearTable();
        foreach ($lastLates as $lastLate) {
            $latePack = new LatePack();
            $latePack
                ->setDelay($lastLate['delayTimeStamp'])
                ->setDate($lastLate['date'])
                ->setEmp($lastLate['emp'])
                ->setLU($lastLate['LU']);
            $entityManager->persist($latePack);
        }
    }
}
