<?php


namespace App\Service;

use App\Entity\Action;
use App\Entity\ReferenceArticle;
use App\Entity\Role;
use App\Entity\Utilisateur;
use Symfony\Component\HttpFoundation\Request;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;
use Doctrine\ORM\EntityManagerInterface;

class RoleService
{

    public const PERMISSIONS_CACHE_PREFIX = 'permissions';
    public const MENU_CACHE_PREFIX = 'menu';

    /** @Required  */
    public EntityManagerInterface $entityManager;

    /** @Required  */
    public CacheService $cacheService;

    public function getPermissions(Utilisateur $user, $bool = false): array {
        $role = $user->getRole();
        $permissionsPrefix = self::PERMISSIONS_CACHE_PREFIX;
        $actions = Stream::from($role->getActions())
            ->keymap(function(Action $action) use ($bool) {
                $key = $this->getPermissionKey($action->getMenu()->getLabel(), $action->getLabel(), $bool ? $action->getSubMenu()?->getLabel() : null);
                return [$key, true];
            })
            ->toArray();
        return $this->cacheService->get(CacheService::PERMISSIONS, "{$permissionsPrefix}.{$role->getId()}", function() use ($actions, $role, $bool) {
            return $actions;
        });
    }

    public function getPermissionKey(string $menuLabel, string $actionLabel, $subMenuLabel = null): string {
        return StringHelper::slugify($menuLabel . '_' . $actionLabel.($subMenuLabel ? '_'.$subMenuLabel : ""));
    }

    public function onRoleUpdate(int $roleId): void {
        $menuPrefix = self::MENU_CACHE_PREFIX;
        $permissionsPrefix = self::PERMISSIONS_CACHE_PREFIX;
        $this->cacheService->delete(CacheService::PERMISSIONS, "{$menuPrefix}.{$roleId}");
        $this->cacheService->delete(CacheService::PERMISSIONS, "{$permissionsPrefix}.{$roleId}");
    }

    public function updateRole(EntityManagerInterface $entityManager,
                               Role $role,
                               Request $request): array {
        $actionRepository = $entityManager->getRepository(Action::class);
        $roleRepository = $entityManager->getRepository(Role::class);

        $label = $request->request->get('label', '');

        if (empty($label)) {
            return [
                "success" => false,
                "message" => "Le libellé est requis."
            ];
        }
        else if ($label !== $role->getLabel()) {
            $labelCount = $roleRepository->countByLabel($label);
            if ($labelCount > 0) {
                return [
                    "success" => false,
                    "message" => "Le rôle <strong>${label}</strong> existe déjà, veuillez choisir un autre libellé"
                ];
            }
        }

        $quantityType = $request->request->get('quantityType');
        if (!in_array($quantityType, [ReferenceArticle::QUANTITY_TYPE_REFERENCE, ReferenceArticle::QUANTITY_TYPE_ARTICLE])) {
            return [
                "success" => false,
                "message" => "La gestion de quantité sélectionnée est invalide"
            ];
        }

        $landingPage = $request->request->get('landingPage');
        if (!in_array($landingPage, [Role::LANDING_PAGE_DASHBOARD, Role::LANDING_PAGE_TRANSPORT_PLANNING, Role::LANDING_PAGE_TRANSPORT_REQUEST])) {
            return [
                "success" => false,
                "message" => "La page d'accueil sélectionnée est invalide"
            ];
        }

        $actionIds = Stream::explode(',', $request->request->get('actions', ''))
            ->filter()
            ->toArray();
        $actions = !empty($actionIds)
            ? $actionRepository->findBy(['id' => $actionIds])
            : [];

        $role
            ->setLabel($label)
            ->setIsMailSendAccountCreation($request->request->getBoolean('isMailSendAccountCreation'))
            ->setQuantityType($quantityType)
            ->setLandingPage($landingPage)
            ->setActions($actions);

        return [
            "success" => true,
            "message" => "Le role <strong>$label</strong> a bien été sauvegardé"
        ];
    }
}
