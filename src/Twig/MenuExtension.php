<?php

namespace App\Twig;

use App\Service\SpecificService;
use App\Service\UserService;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;

class MenuExtension extends AbstractExtension
{

    private $userService;
    private $specificService;
    private $cache;
    private $menuConfig;


    public function __construct(SpecificService $specificService,
                                UserService $userService,
                                array $menuConfig)
    {
        $this->userService = $userService;
        $this->specificService = $specificService;
        $this->cache = new FilesystemAdapter();
        $this->menuConfig = $menuConfig;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('getMenuConfig', [$this, 'getMenuConfigFunction'])
        ];
    }

	public function getMenuConfigFunction() {
        $role = $this->userService->getUserRole();

        return $this->cache->get("menu.{$role->getLabel()}", function(ItemInterface $item) {
            $menuWithRight = [];
            $permissions = $this->userService->getPermissions();

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
        return !isset($item["rights"]) || isset($permissions[constant($item["rights"]["menu"]) . constant($item["rights"]["action"])]);
    }

}
