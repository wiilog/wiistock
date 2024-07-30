<?php

namespace App;

use App\EventListener\NullLogoutListener;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    public function process(ContainerBuilder $container): void {
        $container->register('Nbgrp\OneloginSamlBundle\EventListener\Security\SamlLogoutListener', NullLogoutListener::class);
    }
}
