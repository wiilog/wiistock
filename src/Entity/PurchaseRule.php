<?php

namespace App\Entity;

use App\Entity\Emplacement;
use App\Entity\Utilisateur;
use App\Repository\Inventory\InventoryMissionRuleRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryMissionRuleRepository::class)]
class PurchaseRule {

}
