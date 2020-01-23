<?php


namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Colis;
use App\Entity\Nature;
use App\Repository\ColisRepository;
use Doctrine\ORM\EntityManagerInterface;

Class ColisService
{

    private $entityManager;
    private $colisRepository;

    public function __construct(ColisRepository $colisRepository,
                                EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->colisRepository = $colisRepository;
    }

    public function persistColis(Arrivage $arrivage, Nature $nature): Colis {
        $arrivageNum = $arrivage->getNumeroArrivage();
        $highestCode = $this->colisRepository->getHighestCodeByPrefix($arrivageNum);
        if ($highestCode) {
            $highestCodeArray = explode('-', $highestCode);
            $highestCounter = $highestCodeArray ? $highestCodeArray[1] : 0;
        } else {
            $highestCounter = 0;
        }

        $newCounter = sprintf('%05u', $highestCounter + 1);

        $colis = new Colis();
        $code = ($nature->getPrefix() ?? '') . $arrivageNum . '-' . $newCounter;
        $colis
            ->setCode($code)
            ->setNature($nature)
            ->setArrivage($arrivage);
        $this->entityManager->persist($colis);

        return $colis;
    }
}
