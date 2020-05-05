<?php

namespace App\Twig;

use App\Service\SpecificService;
use App\Service\UserService;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;

class MenuExtension extends AbstractExtension
{

    private $userService;
    private $specificService;
    private $menuConfig;


    public function __construct(SpecificService $specificService,
                                UserService $userService,
                                array $menuConfig)
    {
        $this->userService = $userService;
        $this->specificService = $specificService;
        $this->menuConfig = $menuConfig;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('getMenuConfig', [$this, 'getMenuConfigFunction'])
        ];
    }

	public function getMenuConfigFunction()
    {
        return array_reduce($this->menuConfig, function($menuConfigWithRight, array $item) {
            if ($this->itemHasRights($item)) {
                if (!isset($item['sub'])) {
                    $menuConfigWithRight[] = $item;
                }
                else {
                    $subWithRight = $this->getMenuSubConfigFunction($item['sub']);
                    if (!empty($subWithRight)) {
                        $menuConfigWithRight[] = array_merge(
                            $item,
                            ['sub' => $subWithRight]
                        );
                    }
                }
            }
            return $menuConfigWithRight;
        }, []);
    }

	private function getMenuSubConfigFunction(array $sub) {
        return array_reduce($sub, function(array $menuConfigWithRight, array $item) {
            if ($this->itemHasRights($item)) {
                $menuConfigWithRight[] = $item;
            }
            return $menuConfigWithRight;
        }, []);
    }

    private function itemHasRights(array $item): bool {
        return (
            !isset($item['rights'])
            || $this->userService->hasRightFunction(constant($item['rights']['menu']), constant($item['rights']['action']))
        );
    }

}
