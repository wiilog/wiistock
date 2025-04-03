<?php

namespace App\DataFixtures;

use App\Entity\Action;
use App\Entity\ReferenceArticle;
use App\Entity\Role;
use App\Service\CacheService;
use App\Service\RoleService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Contracts\Service\Attribute\Required;

class RolesFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface {

    #[Required]
    public CacheService $cacheService;

    /**
     * @param ObjectManager $manager
     * @throws NonUniqueResultException
     */
    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();

        $rolesLabels = [
            Role::NO_ACCESS_USER,
            Role::SUPER_ADMIN
        ];
        $actionRepository = $manager->getRepository(Action::class);
        $roleRepository = $manager->getRepository(Role::class);
        foreach ($rolesLabels as $roleLabel) {
            $role = $roleRepository->findByLabel($roleLabel);

            if (empty($role)) {
                $role = new Role();
                $role
                    ->setLabel($roleLabel)
                    ->setQuantityType(ReferenceArticle::QUANTITY_TYPE_REFERENCE);

                $manager->persist($role);
                $output->writeln("Création du rôle " . $roleLabel);

                if ($roleLabel == Role::SUPER_ADMIN) {
                    $actions = $actionRepository->findAll();
                    foreach ($actions as $action) {
                        $action->addRole($role);
                    }
                }
            }
        }
        $manager->flush();

        $menuPrefix = RoleService::MENU_CACHE_PREFIX;
        $permissionsPrefix = RoleService::PERMISSIONS_CACHE_PREFIX;

        $roles = $roleRepository->findAll();
        foreach($roles as $role) {
            $this->cacheService->delete(CacheService::COLLECTION_PERMISSIONS, "{$menuPrefix}.{$role->getId()}");
            $this->cacheService->delete(CacheService::COLLECTION_PERMISSIONS, "{$permissionsPrefix}.{$role->getId()}");
        }
    }

    public static function getGroups(): array {
        return ['fixtures'];
    }

    public function getDependencies() {
        return [ActionsFixtures::class];
    }

}
