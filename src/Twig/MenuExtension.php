<?php

namespace App\Twig;

use App\Helper\CacheHelper;
use App\Service\RoleService;
use App\Service\SpecificService;
use App\Service\UserService;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;

class MenuExtension extends AbstractExtension
{

    private $userService;
    private $roleService;
    private $specificService;
    private $cache;
    private $menuConfig;


    public function __construct(SpecificService $specificService,
                                UserService $userService,
                                RoleService $roleService,
                                array $menuConfig)
    {
        $this->userService = $userService;
        $this->roleService = $roleService;
        $this->specificService = $specificService;
        $this->cache = CacheHelper::create(RoleService::PERMISSIONS_CACHE_POOL);
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
        return $this->cache->get("{$menuPrefix}.{$role->getId()}", function() use ($user) {
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
        return !isset($item["rights"]) || !empty($permissions[constant($item["rights"]["menu"]) . constant($item["rights"]["action"])]);
    }

}
