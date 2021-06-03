<?php

namespace App\Controller;

use App\Entity\Box;
use App\Entity\BoxType;
use App\Entity\Client;
use App\Entity\DepositTicket;
use App\Entity\Emplacement;
use App\Entity\Group;
use App\Entity\Location;
use App\Entity\Quality;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SelectController extends AbstractController {

    /**
     * @Route("/select/emplacement", name="ajax_select_locations", options={"expose": true})
     */
    public function boxes(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Emplacement::class)->getForSelect($request->query->get("term"));

        return $this->json([
            "results" => $results,
        ]);
    }

}
