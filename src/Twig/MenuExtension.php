<?php

namespace App\Twig;

use App\Entity\Menu;
use App\Service\Cache\CacheNamespaceEnum;
use App\Service\Cache\CacheService;
use App\Service\RoleService;
use App\Service\UserService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;

class MenuExtension extends AbstractExtension
{

    private array $menuConfig;

    public function __construct(#[Autowire('%menu_config%')] array $menuConfig,
                                private  CacheService      $cache,
                                private  RoleService       $roleService,
                                private  UserService       $userService) {
        $this->menuConfig = $menuConfig;
    }

    public function getFunctions(): array {
        return [
            new TwigFunction('getMenuConfig', [$this, 'getMenuConfigFunction']),
            new TwigFunction('displaySettings', [$this, 'displaySettings']),
        ];
    }

	public function getMenuConfigFunction() {
        $user = $this->userService->getUser();
        $role = $user->getRole();
        $menuPrefix = RoleService::MENU_CACHE_PREFIX;
        return $this->cache->get(CacheNamespaceEnum::PERMISSIONS, "{$menuPrefix}.{$role->getId()}", function() use ($user) {
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

    public function displaySettings(): bool{
        $permissions = $this->roleService->getPermissions($this->userService->getUser(), true);
        return Stream::from(array_keys($permissions))->some(fn(string $setting) =>
            str_contains($setting, StringHelper::stripAccents(Menu::PARAM)));
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
