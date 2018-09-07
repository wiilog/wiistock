<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class AccueilController extends Controller
{
    /**
     * @Route("/accueil", name="accueil")
     */
    public function index()
    {
    /*
        $roles = $this->getUser()->getRoles();
        $new_roles = array("ROLE_PARC_ADMIN", "ROLE_USER");
        $this->getUser()->setRoles($new_roles);
        $this->getDoctrine()->getManager()->flush();*/

        if (in_array("ROLE_PARC", $roles) || in_array("ROLE_PARC_ADMIN", $roles)) {
            return $this->redirectToRoute('parc_list');
        }

        $today = date("d/m/Y");

        return $this->render('accueil/index.html.twig', [
            'date' => $today,
            'controller_name' => 'AccueilController',
        ]);
    }
}
