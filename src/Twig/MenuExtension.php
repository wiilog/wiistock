<?php

namespace App\Twig;

use App\Service\CacheService;
use App\Service\RoleService;
use App\Service\SpecificService;
use App\Service\UserService;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;

class MenuExtension extends AbstractExtension
{

    /** @Required  */
    public UserService $userService;

    /** @Required  */
    public RoleService $roleService;

    /** @Required  */
    public SpecificService $specificService;

    /** @Required  */
    public CacheService $cache;

    private array $menuConfig;

    public function __construct(array $menuConfig)
    {
        $this->menuConfig = $menuConfig;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('getMenuConfig', [$this, 'getMenuConfigFunction'])
        ];
    }

	public function getMenuConfigFunction() {
        $user = $this->userService->getUser();
        $role = $user->getRole();
        $menuPrefix = RoleService::MENU_CACHE_PREFIX;
        return $this->cache->get(CacheService::PERMISSIONS, "{$menuPrefix}.{$role->getId()}", function() use ($user) {
            $menuWithRight = [];
            $permissions = $this->roleService->getPermissions($this->userService->getUser(), true);

            foreach($this->menuConfig as $menu) {
                if($this->hasRight($permissions, $menu)) {
                    if (!isset($menu["sub"])) {
                        $menuWithRight[] = $menu;
                    } else {
                        $subWithRight = array_filter($menu["sub"], function($item) use ($permissions) {
                            return $this->hasRight($permissions, $item);
                        });

                        if(!empty($subWithRight)) {
                            $menuWithRight[] = array_merge(
                                $menu,
                                ["sub" => $subWithRight]
                            );
                        }
                    }
                }
            }

            return $menuWithRight;
        });
    }

    private function hasRight(array $permissions, array $item) {
        $rightIsset = isset($item["rights"]);
        $key = $rightIsset
            ? $this->roleService->getPermissionKey(constant($item["rights"]["menu"]), constant($item["rights"]["action"]))
            : null;
        return !$rightIsset || !empty($permissions[$key]);
    }

}
