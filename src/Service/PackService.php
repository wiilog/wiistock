<?php


namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Pack;
use App\Entity\Emplacement;
use App\Entity\MouvementTraca;
use App\Entity\Nature;
use App\Entity\ParametrageGlobal;
use App\Entity\Utilisateur;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Exception;


Class PackService
{

    private $entityManager;
    private $mouvementTracaService;
    private $specificService;

    public function __construct(MouvementTracaService $mouvementTracaService,
                                SpecificService $specificService,
                                EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
        $this->specificService = $specificService;
        $this->mouvementTracaService = $mouvementTracaService;
    }

    /**
     * @param array $options Either ['arrival' => Arrivage, 'nature' => Nature] or ['code' => string]
     * @return Pack
     */
    public function createPack(array $options = []): Pack {
        if (!empty($options['code'])) {
            $pack = $this->createPackWithCode($options['code']);
        }
        else {
            /** @var Arrivage $arrival */
            $arrival = $options['arrival'];

            /** @var Nature $nature */
            $nature = $options['nature'];

            $arrivalNum = $arrival->getNumeroArrivage();
            $newCounter = $arrival->getPacks()->count() + 1;

            if ($newCounter < 10) {
                $newCounter = "00" . $newCounter;
            } elseif ($newCounter < 100) {
                $newCounter = "0" . $newCounter;
            }

            $code = (($nature->getPrefix() ?? '') . $arrivalNum . $newCounter ?? '');
            $pack = $this
                ->createPackWithCode($code)
                ->setNature($nature);

            $arrival->addPack($pack);
        }
        return $pack;
    }

    /**
     * @param string code
     * @return Pack
     */
    public function createPackWithCode(string $code): Pack {
        $pack = new Pack();
        $pack->setCode($code);
        return $pack;
    }

    public function getHighestCodeByPrefix(Arrivage $arrivage): int {
        /** @var Pack $lastColis */
        $lastColis = $arrivage->getPacks()->last();
        $lastCode = $lastColis ? $lastColis->getCode() : null;
        $lastCodeSplitted = isset($lastCode) ? explode('-', $lastCode) : null;
        return (int) ((isset($lastCodeSplitted) && count($lastCodeSplitted) > 1)
            ? $lastCodeSplitted[1]
            : 0);
    }

    /**
     * @param Arrivage $arrivage
     * @param array $colisByNatures
     * @param Utilisateur $user
     * @param EntityManagerInterface $entityManager
     * @return Pack[]
     * @throws Exception
     */
    public function persistMultiPacks(Arrivage $arrivage,
                                      array $colisByNatures,
                                      $user,
                                      EntityManagerInterface $entityManager): array {
        $parametrageGlobalRepository = $this->entityManager->getRepository(ParametrageGlobal::class);
        $emplacementRepository = $this->entityManager->getRepository(Emplacement::class);
        $natureRepository = $this->entityManager->getRepository(Nature::class);
        $defaultEmpForMvt = ($this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED) && $arrivage->getDestinataire())
            ? $emplacementRepository->findOneByLabel(SpecificService::ARRIVAGE_SPECIFIQUE_SED_MVT_DEPOSE)
            : null;
        if (!isset($defaultEmpForMvt)) {
            $defaultEmpForMvtParam = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::MVT_DEPOSE_DESTINATION);
            $defaultEmpForMvt = !empty($defaultEmpForMvtParam)
                ? $emplacementRepository->find($defaultEmpForMvtParam)
                : null;
        }
        $now = new DateTime('now', new DateTimeZone('Europe/Paris'));
        $createdPacks = [];
        foreach ($colisByNatures as $natureId => $number) {
            $nature = $natureRepository->find($natureId);
            for ($i = 0; $i < $number; $i++) {
                $pack = $this->createPack(['arrival' => $arrivage, 'nature' => $nature]);
                if ($defaultEmpForMvt) {
                    $mouvementDepose = $this->mouvementTracaService->createTrackingMovement(
                        $pack,
                        $defaultEmpForMvt,
                        $user,
                        $now,
                        false,
                        true,
                        MouvementTraca::TYPE_DEPOSE,
                        ['from' => $arrivage]
                    );
                    $this->mouvementTracaService->persistSubEntities($this->entityManager, $mouvementDepose);
                    $this->entityManager->persist($mouvementDepose);
                }
                $entityManager->persist($pack);
                $createdPacks[] = $pack;
            }
        }
        return $createdPacks;
    }
}
