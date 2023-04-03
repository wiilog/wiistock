<?php


namespace App\Service;


use App\Entity\Arrivage;
use App\Entity\FieldsParam;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\Setting;
use App\Entity\Transporteur;
use App\Entity\Urgence;
use App\Entity\Utilisateur;
use DateTime;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\EntityManagerInterface;

class UrgenceService
{
    private $templating;

    private $entityManager;

    private $security;

    #[Required]
    public FormatService $formatService;

    public function __construct(EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
								Security $security)
    {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public function getDataForDatatable($params = null)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $urgenceRepository = $this->entityManager->getRepository(Urgence::class);
		$filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_URGENCES, $this->security->getUser());

		$queryResult = $urgenceRepository->findByParamsAndFilters($params, $filters);

        $urgenceArray = $queryResult['data'];

        $rows = [];
        foreach ($urgenceArray as $urgence) {
            $rows[] = $this->dataRowUrgence($urgence);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowUrgence(Urgence $urgence)
    {
        $user = $this->security->getUser();
        $format = $user && $user->getDateFormat() ? ($user->getDateFormat() . ' H:i') : 'd/m/Y H:i';
        return [
            'start' => $urgence->getDateStart()->format($format),
            'end' => $urgence->getDateEnd()->format($format),
            'commande' => $urgence->getCommande(),
            'arrivalDate' => $urgence->getLastArrival() && $urgence->getLastArrival()->getDate() ? $urgence->getLastArrival()->getDate()->format($format) : '',
            'buyer' => $urgence->getBuyer() ? $urgence->getBuyer()->getUsername() : '',
            'provider' => $urgence->getProvider() ? $urgence->getProvider()->getNom() : '',
            'carrier' => $urgence->getCarrier() ? $urgence->getCarrier()->getLabel() : '',
            'trackingNb' => $urgence->getTrackingNb() ?? '',
            'arrivalNb' => $urgence->getLastArrival() ? $urgence->getLastArrival()->getNumeroArrivage() : '',
            'createdAt' =>$urgence->getCreatedAt() ? $urgence->getCreatedAt()->format($format) : '' ,
            'postNb' => $urgence->getPostNb() ?? '',
            'actions' => $this->templating->render('urgence/datatableUrgenceRow.html.twig', [
                'urgence' => $urgence
            ]),
            'type' => $urgence->getType(),
        ];
    }

    public function updateUrgence(Urgence $emergency, $data, FormatService $formatService): Urgence {
        $user = $this->security->getUser();
        $utilisateurRepository = $this->entityManager->getRepository(Utilisateur::class);
        $fournisseurRepository = $this->entityManager->getRepository(Fournisseur::class);
        $transporteurRepository = $this->entityManager->getRepository(Transporteur::class);

        $dateStart = $formatService->parseDatetime($data['dateStart']);
        $dateEnd = $formatService->parseDatetime($data['dateEnd']);
        $emergency
            ->setDateStart($dateStart)
            ->setDateEnd($dateEnd);

        if (isset($data[FieldsParam::FIELD_CODE_EMERGENCY_BUYER])) {
            $buyer = $utilisateurRepository->find($data[FieldsParam::FIELD_CODE_EMERGENCY_BUYER]);
            $emergency->setBuyer($buyer);
        }
        if (isset($data[FieldsParam::FIELD_CODE_EMERGENCY_PROVIDER])) {
            $provider = $fournisseurRepository->find($data[FieldsParam::FIELD_CODE_EMERGENCY_PROVIDER]);
            $emergency->setProvider($provider);
        }
        if (isset($data[FieldsParam::FIELD_CODE_EMERGENCY_CARRIER])) {
            $carrier = $transporteurRepository->find($data[FieldsParam::FIELD_CODE_EMERGENCY_CARRIER]);
            $emergency->setCarrier($carrier);
        }
        if (isset($data[FieldsParam::FIELD_CODE_EMERGENCY_COMMAND_NUMBER])) {
            $emergency->setCommande($data[FieldsParam::FIELD_CODE_EMERGENCY_COMMAND_NUMBER]);
        }
        if (isset($data[FieldsParam::FIELD_CODE_EMERGENCY_POST_NUMBER])) {
            $emergency->setPostNb($data[FieldsParam::FIELD_CODE_EMERGENCY_POST_NUMBER]);
        }
        if (isset($data[FieldsParam::FIELD_CODE_EMERGENCY_CARRIER_TRACKING_NUMBER])) {
            $emergency->setTrackingNb($data[FieldsParam::FIELD_CODE_EMERGENCY_CARRIER_TRACKING_NUMBER]);
        }
        if (isset($data[FieldsParam::FIELD_CODE_EMERGENCY_TYPE])) {
            $emergency->setType($data[FieldsParam::FIELD_CODE_EMERGENCY_TYPE]);
        }

        return $emergency;
    }

    /**
     * @return Urgence[]
     */
    public function matchingEmergencies(Arrivage $arrival, ?string $orderNumber, ?string $post, bool $excludeTriggered = false) {
        $urgenceRepository = $this->entityManager->getRepository(Urgence::class);

        if(!isset($this->__arrival_emergency_fields)) {
            $arrivalEmergencyFields = $this->entityManager
                ->getRepository(Setting::class)
                ->getOneParamByLabel(Setting::ARRIVAL_EMERGENCY_TRIGGERING_FIELDS);
            $this->__arrival_emergency_fields = $arrivalEmergencyFields ? explode(',', $arrivalEmergencyFields) : [];
        }

        return $urgenceRepository->findUrgencesMatching(
            $this->__arrival_emergency_fields,
            $arrival,
            $orderNumber,
            $post,
            $excludeTriggered,
        );
    }



    public function serializeEmergency(Urgence     $emergency,
                                       Utilisateur $user): array {
        return [
            'dateStart' => $this->formatService->datetime($emergency->getDateStart(), "", false, $user),
            'dateEnd' => $this->formatService->datetime($emergency->getDateEnd(), "", false, $user),
            'commande' => $emergency->getCommande() ?: '',
            'numposte' => $emergency->getPostNb() ?: '',
            'buyer' => $this->formatService->user($emergency->getBuyer()),
            'provider' => $this->formatService->supplier($emergency->getProvider()),
            'carrier' => $emergency->getCarrier() ? $emergency->getCarrier()->getLabel() : '',
            'trackingnum' => $emergency->getTrackingNb() ?: '',
            'datearrival' => $emergency->getLastArrival() ? $this->formatService->datetime($emergency->getLastArrival()->getDate(), "", false, $user) : '',
            'arrivageNumber' => $emergency->getLastArrival() ? $emergency->getLastArrival()->getNumeroArrivage() : '',
            'creationDate' => $this->formatService->datetime($emergency->getCreatedAt(), "", false, $user),
        ];
    }

}
