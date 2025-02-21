<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Dashboard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;


#[Autoconfigure(public: true)]
interface DashboardComponentGenerator {
    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void;
}
