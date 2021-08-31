<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Menu;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/groupes-visibilite")
 */
class VisibilityGroupController extends AbstractController
{
    /**
     * @Route("/liste", name="visibility_group_index", methods="GET")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_VISIBILITY_GROUPS})
     */
    public function index(): Response {
        return $this->render('visibility_group/index.html.twig');
    }
}
