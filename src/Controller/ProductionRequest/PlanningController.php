<?php

namespace App\Controller\ProductionRequest;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\DaysWorked;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\FiltreSup;
use App\Entity\FreeField;
use App\Entity\Menu;
use App\Entity\ProductionRequest;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\WorkFreeDay;
use App\Service\StatusService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

#[Route('/production/planning', name: 'production_request_planning_')]
class PlanningController extends AbstractController {
    #[Route('/index', name: 'index', methods: self::GET)]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST_PLANNING], mode: HasPermission::IN_JSON)]
    public function index(EntityManagerInterface $entityManager, StatusService $statusService): Response {
        $typeRepository = $entityManager->getRepository(Type::class);
        $statusRepository = $entityManager->getRepository(Statut::class);

        $types = $typeRepository->findByCategoryLabels([CategoryType::PRODUCTION]);

        return $this->render('production_request/planning/index.html.twig', [
            "types" => Stream::from($types)
                ->map(fn(Type $type) => [
                    'id' => $type->getId(),
                    'label' => $this->getFormatter()->type($type)
                ])
                ->toArray(),
            "statuses" => $statusRepository->findByCategorieName(CategorieStatut::PRODUCTION),
            "statusStateValues" => Stream::from($statusService->getStatusStatesValues())
                ->reduce(function($status, $item) {
                    $status[$item['id']] = $item['label'];
                    return $status;
                }, []),
        ]);
    }

    #[Route('/api', name: 'api', options: ['expose' => true], methods: self::GET)]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST_PLANNING], mode: HasPermission::IN_JSON)]
    public function api(EntityManagerInterface $entityManager,
                        Request                $request): Response {
        $productionRequestRepository = $entityManager->getRepository(ProductionRequest::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $supFilterRepository = $entityManager->getRepository(FiltreSup::class);
        $daysWorkedRepository = $entityManager->getRepository(DaysWorked::class);
        $workFreeDayRepository = $entityManager->getRepository(WorkFreeDay::class);
        $fixedFieldRepository = $entityManager->getRepository(FixedFieldStandard::class);
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);

        $daysWorked = $daysWorkedRepository->getLabelWorkedDays();
        $workFreeDays = $workFreeDayRepository->getWorkFreeDaysToDateTime(true);
        $nbDaysOnPlanning = 7;

        $planningStart = $this->getFormatter()->parseDatetime($request->query->get('date'));
        $planningEnd = (clone $planningStart)->modify("+1 week");

        $filters = $supFilterRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PRODUCTION_PLANNING, $this->getUser());

        $statuses = Stream::from($statusRepository->findByCategorieName(CategorieStatut::PRODUCTION))
            ->filter(static fn(Statut $status) => $status->isDisplayedOnSchedule())
            ->toArray();

        $filters = Stream::from($filters)
            ->filter(static fn(array $filter) => (
                $filter["field"] === FiltreSup::FIELD_REQUEST_NUMBER
                || $filter["field"] === FiltreSup::FIELD_TYPE
                || $filter["field"] === FiltreSup::FIELD_OPERATORS
                || $filter["field"] === FiltreSup::FIELD_STATUT
            ))
            ->toArray();

        $productionRequests = $productionRequestRepository->findByStatusCodesAndExpectedAt($filters, $statuses, $planningStart, $planningEnd);
        $cards = Stream::from($productionRequests)
            ->keymap(function(ProductionRequest $productionRequest) use ($fixedFieldRepository, $freeFieldRepository) {
                $freeFields = $freeFieldRepository->findByTypeAndCategorieCLLabel($productionRequest->getType(), CategorieCL::PRODUCTION_REQUEST);
                $fields = Stream::from([
                    FixedFieldEnum::lineCount->name => $productionRequest->getLineCount(),
                    FixedFieldEnum::projectNumber->name => $productionRequest->getProjectNumber(),
                    FixedFieldEnum::comment->name => $this->getFormatter()->html($productionRequest->getComment()),
                    FixedFieldEnum::attachments->name => $this->getFormatter()->bool(!$productionRequest->getAttachments()->isEmpty()),
                    ...Stream::from($freeFields)
                        ->keymap(fn(FreeField $freeField) => [
                            $freeField->getLabel(),
                            $productionRequest->getFreeFieldValue($freeField->getId())
                        ])
                        ->toArray(),
                    ])
                    ->filter(static function (string $value, string $field) use ($fixedFieldRepository) {
                        $fixedField = $fixedFieldRepository->findByEntityAndCode(FixedFieldStandard::ENTITY_CODE_PRODUCTION, $field);

                        return (!$fixedField || ($fixedField->isDisplayedCreate() || $fixedField->isDisplayedEdit()))
                                && !in_array($value, [null, ""]);
                    })
                    ->keymap(static fn(string $value, string $field) => [
                        FixedFieldEnum::fromCase($field) ?: $field,
                        $value
                    ])
                    ->toArray();

                return [
                    $productionRequest->getExpectedAt()->format('Y-m-d'),
                    $this->renderView('production_request/planning/card.html.twig', [
                        "productionRequest" => $productionRequest,
                        "color" => $productionRequest->getType()->getColor() ?: Type::DEFAULT_COLOR,
                        "inPlanning" => true,
                        "fields" => $fields,
                    ])
                ];
            }, true)
            ->toArray();

        $dates = Stream::fill(0, $nbDaysOnPlanning, null)
            ->filterMap(function ($_, int $index) use ($planningStart, $productionRequests, $cards, $daysWorked, $workFreeDays) {
                $day = (clone $planningStart)->modify("+$index days");

                if(in_array(strtolower($day->format("l")), $daysWorked)
                    && !in_array($day->format("Y-m-d"), $workFreeDays)) {
                    $dayStr = $day->format('Y-m-d');
                    $count = count($cards[$dayStr] ?? []);
                    $sProduction = $count > 1 ? 's' : '';

                    return [
                        "label" => $this->getFormatter()->longDate($day, ["short" => true, "year" => false]),
                        "cardSelector" => $dayStr,
                        "columnClass" => "forced",
                        "columnHint" => "<span class='font-weight-bold'>$count demande$sProduction</span>",
                    ];
                } else {
                    return null;
                }
            })
            ->toArray();

        return $this->json([
            "success" => true,
            "template" => $this->renderView('production_request/planning/content.html.twig', [
                "planningDates" => $dates,
                "cards" => $cards,
            ]),
        ]);
    }

    #[Route("/external", name: "external")]
    public function external(): Response {
        return $this->render("production_request/planning/external.html.twig");
    }
}
