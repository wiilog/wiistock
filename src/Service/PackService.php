<?php


namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\FiltreSup;
use App\Entity\Pack;
use App\Entity\TrackingMovement;
use App\Entity\Nature;
use App\Entity\Transport\TransportDeliveryOrderPack;
use App\Exceptions\FormException;
use App\Helper\FormatHelper;
use App\Repository\NatureRepository;
use App\Repository\PackRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Security\Core\Security;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class PackService {

    /** @Required */
    public EntityManagerInterface $entityManager;

    /** @Required */
    public Security $security;

    /** @Required */
    public Twig_Environment $templating;

    /** @Required */
    public TrackingMovementService $trackingMovementService;

    /** @Required */
    public ArrivageService $arrivageDataService;

    /** @Required */
    public MailerService $mailerService;

    public function getDataForDatatable($params = null) {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $packRepository = $this->entityManager->getRepository(Pack::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PACK, $this->security->getUser());
        $queryResult = $packRepository->findByParamsAndFilters($params, $filters, PackRepository::PACKS_MODE);

        $packs = $queryResult["data"];

        $rows = [];
        foreach ($packs as $pack) {
            $rows[] = $this->dataRowPack($pack);
        }

        return [
            "data" => $rows,
            "recordsFiltered" => $queryResult['count'],
            "recordsTotal" => $queryResult['total'],
        ];
    }

    public function getGroupHistoryForDatatable($pack, $params) {
        $trackingMovementRepository = $this->entityManager->getRepository(TrackingMovement::class);

        $queryResult = $trackingMovementRepository->findTrackingMovementsForGroupHistory($pack, $params);

        $trackingMovements = $queryResult["data"];

        $rows = [];
        foreach ($trackingMovements as $trackingMovement) {
            $rows[] = $this->dataRowGroupHistory($trackingMovement);
        }

        return [
            "data" => $rows,
            "recordsFiltered" => $queryResult['filtered'],
            "recordsTotal" => $queryResult['total'],
        ];
    }

    public function dataRowPack(Pack $pack)
    {
        $firstMovement = $pack->getTrackingMovements('ASC')->first();
        $fromColumnData = $this->trackingMovementService->getFromColumnData($firstMovement ?: null);
        $user = $this->security->getUser();
        $prefix = $user && $user->getDateFormat() ? $user->getDateFormat() : 'd/m/Y';
        $lastMessage = $pack->getLastMessage();
        $hasPairing = !$pack->getPairings()->isEmpty() || $lastMessage;
        $sensorCode = ($lastMessage && $lastMessage->getSensor() && $lastMessage->getSensor()->getAvailableSensorWrapper())
            ? $lastMessage->getSensor()->getAvailableSensorWrapper()->getName()
            : null;

        /** @var TrackingMovement $lastPackMovement */
        $lastPackMovement = $pack->getLastTracking();
        return [
            'actions' => $this->templating->render('pack/datatablePackRow.html.twig', [
                'pack' => $pack,
                'hasPairing' => $hasPairing
            ]),
            'pairing' => $this->templating->render('pairing-icon.html.twig', [
                'sensorCode' => $sensorCode,
                'hasPairing' => $hasPairing
            ]),
            'packNum' => $pack->getCode(),
            'packNature' => $pack->getNature() ? $pack->getNature()->getLabel() : '',
            'quantity' => $pack->getQuantity() ?: 1,
            'packLastDate' => $lastPackMovement
                ? ($lastPackMovement->getDatetime()
                    ? $lastPackMovement->getDatetime()->format($prefix . ' \à H:i:s')
                    : '')
                : '',
            'packOrigin' => $this->templating->render('mouvement_traca/datatableMvtTracaRowFrom.html.twig', $fromColumnData),
            'packLocation' => $lastPackMovement
                ? ($lastPackMovement->getEmplacement()
                    ? $lastPackMovement->getEmplacement()->getLabel()
                    : '')
                : ''
        ];
    }

    public function dataRowGroupHistory(TrackingMovement $trackingMovement) {
        return [
            'group' => $trackingMovement->getPackParent() ? (FormatHelper::pack($trackingMovement->getPackParent()) . '-' . $trackingMovement->getGroupIteration()) : '',
            'date' => FormatHelper::datetime($trackingMovement->getDatetime(), "", false, $this->security->getUser()),
            'type' => FormatHelper::status($trackingMovement->getType())
        ];
    }

    public function checkPackDataBeforeEdition(array $data): array
    {
        $quantity = $data['quantity'] ?? null;
        $weight = !empty($data['weight']) ? str_replace(",", ".", $data['weight']) : null;
        $volume = !empty($data['volume']) ? str_replace(",", ".", $data['volume']) : null;

        if ($quantity <= 0) {
            return [
                'success' => false,
                'msg' => 'La quantité doit être supérieure à 0.'
            ];
        }

        if (!empty($weight) && (!is_numeric($weight) || ((float)$weight) <= 0)) {
            return [
                'success' => false,
                'msg' => 'Le poids doit être un nombre valide supérieur à 0.'
            ];
        }

        if (!empty($volume) && (!is_numeric($volume) || ((float)$volume) <= 0)) {
            return [
                'success' => false,
                'msg' => 'Le volume doit être un nombre valide supérieur à 0.'
            ];
        }

        return [
            'success' => true,
            'msg' => 'OK',
        ];
    }

    public function editPack(array $data, NatureRepository $natureRepository, Pack $pack)
    {
        $natureId = $data['nature'] ?? null;
        $quantity = $data['quantity'] ?? null;
        $comment = $data['comment'] ?? null;
        $weight = !empty($data['weight']) ? str_replace(",", ".", $data['weight']) : null;
        $volume = !empty($data['volume']) ? str_replace(",", ".", $data['volume']) : null;

        $nature = $natureRepository->find($natureId);
        if (!empty($nature)) {
            $pack->setNature($nature);
        }

        $pack
            ->setQuantity($quantity)
            ->setWeight($weight)
            ->setVolume($volume)
            ->setComment($comment);
    }

    public function createPack(array $options = []): Pack
    {
        if (!empty($options['code'])) {
            $pack = $this->createPackWithCode($options['code']);
        } else {
            if(isset($options['arrival'])) {
                /** @var Arrivage $arrival */
                $arrival = $options['arrival'];

                /** @var Nature $nature */
                $nature = $options['nature'];

                $arrivalNum = $arrival->getNumeroArrivage();
                $counter = $arrival->getPacks()->count() + 1;
                $counterStr = sprintf("%03u", $counter);

                $code = (($nature->getPrefix() ?? '') . $arrivalNum . $counterStr ?? '');
                $pack = $this
                    ->createPackWithCode($code)
                    ->setNature($nature);

                $arrival->addPack($pack);
            }
            else if (isset($options['orderLine'])) {
                /** @var Nature $nature */
                $nature = $options['nature'];

                /** @var TransportDeliveryOrderPack $orderLine */
                $orderLine = $options['orderLine'];
                $order = $orderLine->getOrder();
                $request = $order->getRequest();

                $requestNumber = $request->getNumber();
                $naturePrefix = $nature->getPrefix() ?? '';
                $counter = $order->getPacks()->count();
                $counterStr = sprintf("%03u", $counter);

                $code = $naturePrefix . $requestNumber . $counterStr;
                $pack = $this
                    ->createPackWithCode($code)
                    ->setNature($nature);

                $orderLine->setPack($pack);
            }
            else {
                throw new RuntimeException('Unhandled pack configuration');
            }
        }
        return $pack;
    }

    public function createPackWithCode(string $code): Pack
    {
        $pack = new Pack();
        $pack->setCode(str_replace("    ", " ", $code));
        return $pack;
    }

    public function persistMultiPacks(EntityManagerInterface $entityManager,
                                      Arrivage $arrivage,
                                      array $colisByNatures,
                                      $user,
                                      bool $persistTrackingMovements = true): array
    {
        $natureRepository = $entityManager->getRepository(Nature::class);

        $location = $persistTrackingMovements
            ? $this->arrivageDataService->getLocationForTracking($entityManager, $arrivage)
            : null;

        $totalPacks = Stream::from($colisByNatures)->sum();
        if($totalPacks > 500) {
            throw new FormException("Vous ne pouvez pas ajouter plus de 500 colis");
        }

        $now = new DateTime('now');
        $createdPacks = [];
        foreach ($colisByNatures as $natureId => $number) {
            $nature = $natureRepository->find($natureId);
            for ($i = 0; $i < $number; $i++) {
                $pack = $this->createPack(['arrival' => $arrivage, 'nature' => $nature]);
                if ($persistTrackingMovements && isset($location)) {
                    $this->trackingMovementService->persistTrackingForArrivalPack(
                        $entityManager,
                        $pack,
                        $location,
                        $user,
                        $now,
                        $arrivage
                    );
                }
                $entityManager->persist($pack);
                $createdPacks[] = $pack;
            }
        }
        return $createdPacks;
    }

    public function launchPackDeliveryReminder(EntityManagerInterface $entityManager): void {
        $packRepository = $entityManager->getRepository(Pack::class);
        $waitingDaysRequested = [7, 15, 30, 42];
        $ongoingPacks = $packRepository->findOngoingPacksOnDeliveryPoints($waitingDaysRequested);
        foreach ($ongoingPacks as $packData) {
            $pack = $packData[0];
            $waitingDays = $packData['packWaitingDays'];

            $remindPosition = array_search($waitingDays, $waitingDaysRequested);
            $titleSuffix = match($remindPosition) {
                0 => ' - 1ère relance',
                1 => ' - 2ème relance',
                2 => ' - 3ème relance',
                3 => ' - dernière relance',
                default => ''
            };
            $arrival = $pack->getArrivage();
            $lastDrop = $pack->getLastDrop();

            $this->mailerService->sendMail(
                "Follow GT // Colis non récupéré$titleSuffix",
                $this->templating->render('mails/contents/mail-pack-delivery-done.html.twig', [
                    'title' => 'Votre colis est toujours présent dans votre magasin',
                    'orderNumber' => implode(', ', $arrival->getNumeroCommandeList()),
                    'colis' => FormatHelper::pack($pack),
                    'emplacement' => $lastDrop->getEmplacement(),
                    'date' => $lastDrop->getDatetime(),
                    'fournisseur' => FormatHelper::supplier($arrival->getFournisseur()),
                    'pjs' => $arrival->getAttachments()
                ]),
                $arrival->getDestinataire()
            );
        }
    }
}
