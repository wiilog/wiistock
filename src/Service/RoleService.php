<?php


namespace App\Service;

use App\Entity\Action;
use App\Entity\ReferenceArticle;
use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Service\Cache\CacheNamespaceEnum;
use App\Service\Cache\CacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;

class RoleService
{

    public const PERMISSIONS_CACHE_PREFIX = 'permissions';
    public const MENU_CACHE_PREFIX = 'menu';

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public CacheService $cacheService;

    public function getPermissions(Utilisateur $user, $bool = false): array {
        $role = $user->getRole();
        $permissionsPrefix = self::PERMISSIONS_CACHE_PREFIX;
        return $this->cacheService->get(CacheNamespaceEnum::PERMISSIONS, "{$permissionsPrefix}.{$role->getId()}", function() use ($role, $bool) {
            return Stream::from($role->getActions())
                ->keymap(function(Action $action) use ($bool) {
                    $key = $this->getPermissionKey($action->getMenu()->getLabel(), $action->getLabel());
                    return [$key, true];
                })
                ->toArray();
        });
    }

    public function getPermissionKey(string $menuLabel, string $actionLabel): string {
        return StringHelper::slugify($menuLabel . '_' . $actionLabel);
    }

    public function onRoleUpdate(int $roleId): void {
        $menuPrefix = self::MENU_CACHE_PREFIX;
        $permissionsPrefix = self::PERMISSIONS_CACHE_PREFIX;
        $this->cacheService->delete(CacheNamespaceEnum::PERMISSIONS, "{$menuPrefix}.{$roleId}");
        $this->cacheService->delete(CacheNamespaceEnum::PERMISSIONS, "{$permissionsPrefix}.{$roleId}");
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
                    "message" => "Le rôle <strong>{$label}</strong> existe déjà, veuillez choisir un autre libellé"
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
            ->setQuantityType($quantityType)
            ->setLandingPage($landingPage)
            ->setActions($actions);

        return [
            "success" => true,
            "message" => "Le role <strong>$label</strong> a bien été sauvegardé"
        ];
    }
}
