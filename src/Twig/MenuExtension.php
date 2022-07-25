<?php

namespace App\Twig;

use App\Service\CacheService;
use App\Service\RoleService;
use App\Service\SpecificService;
use App\Service\UserService;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;
use WiiCommon\Helper\Stream;

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
            new TwigFunction('getMenuConfig', [$this, 'getMenuConfigFunction']),
            new TwigFunction('displaySettings', [$this, 'displaySettings']),
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

    public function displaySettings(){
        $permissions = $this->roleService->getPermissions($this->userService->getUser(), true);
        $displaySettings = false;
        foreach ($permissions as $key => $value){
            if(str_contains($key, 'parametrage') && !$displaySettings){
                $displaySettings = true;
            }
        }
        return $displaySettings;
    }

    private function hasRight(array $permissions, array $item) {
        if (isset($item["rights"])) {
            if (is_array($item["rights"]) && array_values($item["rights"]) === $item["rights"]) {
                $permission = $this->getRightsFromActions($permissions, $item["rights"])
                    ->reduce(fn(bool $carry, bool $right) => $carry && $right, true);
            }
            else {
                $key = $this->roleService->getPermissionKey(constant($item["rights"]["menu"]), constant($item["rights"]["action"]));
                $permission = $permissions[$key] ?? false;
            }
        }
        if (isset($item["rightsOR"])) {
            $permission = $this->getRightsFromActions($permissions, $item["rightsOR"])
                ->some(fn(bool$right) => $right);
        }
        return $permission ?? true;
    }

    private function getRightsFromActions(array $permissions, array $actions): Stream {
        return Stream::from($actions)
            ->map(fn(array $action) => $this->roleService->getPermissionKey(constant($action["menu"]), constant($action["action"])))
            ->map(fn(string $key) => $permissions[$key] ?? false);
    }

}
