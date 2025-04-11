<?php

namespace App\Controller;


use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Emergency\Emergency;
use App\Entity\Menu;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/urgence')]
class EmergencyController extends AbstractController
{

    #[Route('/', name: 'emergency_index')]
    #[HasPermission([Menu::QUALI, Action::DISPLAY_URGE])]
    public function index(EntityManagerInterface $entityManager)
    {
        return $this->render('emergency/index.html.twig', []);
    }
}
