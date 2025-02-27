<?php

namespace App\Service\Dashboard\MultipleDashboardComponentGenerator;

use App\Entity\Dashboard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;


#[Autoconfigure(public: true)]
abstract class MultipleDashboardComponentGenerator {

    /**
     * @param array<Dashboard\Component> $components
     */
    public abstract function persistAll(EntityManagerInterface $entityManager, array $components): void;
}
