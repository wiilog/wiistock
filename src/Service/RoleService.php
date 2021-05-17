<?php


namespace App\Service;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Parametre;
use App\Entity\ParametreRole;
use App\Entity\Role;
use App\Entity\Utilisateur;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Psr\Cache\InvalidArgumentException;

class RoleService
{

    public const PERMISSIONS_CACHE_PREFIX = 'permissions';
    public const MENU_CACHE_PREFIX = 'menu';

    /** @Required  */
    public EntityManagerInterface $entityManager;

    /** @Required  */
    public CacheService $cacheService;

    /**
     * @param Role $role
     * @param array $data
     * @throws NonUniqueResultException
     */
    public function parseParameters(Role $role, array $data) {
        $actionRepository = $this->entityManager->getRepository(Action::class);

        // on traite les actions
        $dashboardsVisible = [];
        foreach ($data as $menuAction => $isChecked) {
            $menuActionArray = explode('/', $menuAction);
            if (count($menuActionArray) > 1) {
                $menuLabel = $menuActionArray[0];
                $actionLabel = $menuActionArray[1];

                $action = $actionRepository->findOneByMenuLabelAndActionLabel($menuLabel, $actionLabel);
                if ($action && $isChecked) {
                    $role->addAction($action);
                } else {
                    $role->removeAction($action);
                }
            } else {
                if ($isChecked && is_string($menuAction)) {
                    $dashboardsVisible[] = $menuAction;
                }
            }
        }
        $role->setDashboardsVisible($dashboardsVisible);
    }

    public function createFormTemplateParameters(Role $role = null): array {
        $parametreRepository = $this->entityManager->getRepository(Parametre::class);
        $menuRepository = $this->entityManager->getRepository(Menu::class);
        $parametreRoleRepository = $this->entityManager->getRepository(ParametreRole::class);

        $menus = $menuRepository->findAll();

        $params = array_map(
            function (Parametre $param) use ($role, $parametreRoleRepository) {
                $paramArray = [
                    'id' => $param->getId(),
                    'label' => $param->getLabel(),
                    'typage' => $param->getTypage(),
                    'elements' => $param->getElements(),
                    'default' => $param->getDefaultValue()
                ];

                if (isset($role)) {
                    $paramArray['value'] = $parametreRoleRepository->getValueByRoleAndParam($role, $param);
                }

                return $paramArray;
            },
            $parametreRepository->findAll()
        );

        return [
            'menus' => $menus,
            'params' => $params,
        ];
    }

    public function getPermissions(Utilisateur $user, $bool = false): array {
        $role = $user->getRole();
        $permissionsPrefix = self::PERMISSIONS_CACHE_PREFIX;
        return $this->cacheService->get(CacheService::PERMISSIONS, "{$permissionsPrefix}.{$role->getId()}", function() use ($role, $bool) {
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

    /**
     * @param int $roleId
     * @throws InvalidArgumentException
     */
    public function onRoleUpdate(int $roleId): void {
        $menuPrefix = self::MENU_CACHE_PREFIX;
        $permissionsPrefix = self::PERMISSIONS_CACHE_PREFIX;
        $this->cacheService->delete(CacheService::PERMISSIONS, "{$menuPrefix}.{$roleId}");
        $this->cacheService->delete(CacheService::PERMISSIONS, "{$permissionsPrefix}.{$roleId}");
    }
}
