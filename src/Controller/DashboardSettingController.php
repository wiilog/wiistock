<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parametrage-global")
 */
class DashboardSettingController extends AbstractController {

    /**
     * @Route("/dashboard", name="dashboard_settings")
     */
    public function settings() {
        return $this->render("dashboard/settings.html.twig", [

        ]);
    }

}
