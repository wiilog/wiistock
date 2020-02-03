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

        $highestCounter = $this->getHighestCodeByPrefix($arrivage) + 1;
        $newCounter = sprintf('%05u', $highestCounter);

        $colis = new Colis();
        $code = ($nature->getPrefix() ?? '') . $arrivageNum . '-' . $newCounter;
        $colis
            ->setCode($code)
            ->setNature($nature);

        $arrivage->addColis($colis);

        $this->entityManager->persist($colis);
        return $colis;
    }

    public function getHighestCodeByPrefix(Arrivage $arrivage): int {
        /** @var Colis $lastColis */
        $lastColis = $arrivage->getColis()->last();
        $lastCode = $lastColis ? $lastColis->getCode() : null;
        $lastCodeSplitted = isset($lastCode) ? explode('-', $lastCode) : null;
        return (int) ((isset($lastCodeSplitted) && count($lastCodeSplitted) > 1)
            ? $lastCodeSplitted[1]
            : 0);
    }
}
