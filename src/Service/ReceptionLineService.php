<?php


namespace App\Service;


use App\Entity\Reception;
use App\Entity\ReceptionLine;
use App\Entity\Tracking\Pack;
use Doctrine\ORM\EntityManagerInterface;


class ReceptionLineService {

    public function persistReceptionLine(EntityManagerInterface $entityManager,
                                         Reception              $reception,
                                         ?Pack                  $pack): ReceptionLine {

        $line = new ReceptionLine();
        $line
            ->setReception($reception)
            ->setPack($pack);
        $entityManager->persist($line);
        return $line;
    }

}
