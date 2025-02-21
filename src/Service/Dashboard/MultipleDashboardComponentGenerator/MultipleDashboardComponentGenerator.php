<?php

namespace App\Service\Dashboard\MultipleDashboardComponentGenerator;

use App\Entity\Dashboard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;


#[Autoconfigure(public: true)]
abstract class MultipleDashboardComponentGenerator {

    /** @var Dashboard\Component[]  */
    public array $collection = [];

    public function clear(): void {
        $this->collection = [];
    }

    public function push(Dashboard\Component $component): void {
        $this->collection[] = $component;
    }

    public abstract function persistAll(EntityManagerInterface $entityManager): void;
}
