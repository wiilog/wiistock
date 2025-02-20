<?php

namespace App\Service\Dashboard;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Dashboard;

interface DashboardComponentService {
    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component,
                            array                  $options = []): void;
}
