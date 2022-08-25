<?php


namespace App\Service;

use App\Entity\Language;
use App\Entity\Nature;
use App\Entity\Transport\TemperatureRange;
use App\Entity\Type;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class NatureService
{
    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public EntityManagerInterface $manager;

    #[Required]
    public Security $security;

    public function getDataForDatatable(InputBag $params)
    {
        $natureRepository = $this->manager->getRepository(Nature::class);
        $queryResult = $natureRepository->findByParams($params);

        $natures = $queryResult['data'];

        $rows = [];
        foreach ($natures as $nature) {
            $rows[] = $this->dataRowNature($nature);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowNature(Nature $nature): array
    {
        $typeRepository = $this->manager->getRepository(Type::class);
        $userLanguage = $this->security->getUser()->getLanguage();
        $label = $nature->getLabel();

        if ($userLanguage !== $this->manager->getRepository(Language::class)->find(1)
            && $nature->getLabelTranslation() && $nature->getLabelTranslation()->getTranslationIn($userLanguage->getSlug())) {
            $label = $nature->getLabelTranslation()->getTranslationIn($userLanguage->getSlug())->getTranslation();
        }

        return [
            'label' => $label,
            'code' => $nature->getCode(),
            'defaultQuantity' => $nature->getDefaultQuantity() ?? 'Non définie',
            'prefix' => $nature->getPrefix() ?? 'Non défini',
            'needsMobileSync' => FormatHelper::bool($nature->getNeedsMobileSync()),
            'displayedOnForms' => !empty($nature->getAllowedForms())
                ? Stream::from($nature->getAllowedForms())
                    ->map(fn(array|string $types, string $index) =>
                        Nature::ENTITIES[$index] .
                        (
                            is_array($types)
                                ? (' : ' . Stream::from($typeRepository->findBy(['id' => $types]))
                                        ->map(fn(Type $type) => $type->getLabel())
                                        ->join(", "))
                                : ''
                        )
                    )
                    ->join("; ")
                : 'non',
            'color' => $nature->getColor() ? '<div style="background-color:' . $nature->getColor() . ';"><br></div>' : 'Non définie',
            'description' => $nature->getDescription() ?? 'Non définie',
            'temperatures' => Stream::from($nature->getTemperatureRanges())->map(fn(TemperatureRange $temperature) => $temperature->getValue())->join(", "),
            'actions' => $this->templating->render('nature_param/datatableNatureRow.html.twig', [
                'natureId' => $nature->getId(),
            ]),
        ];
    }

    public function serializeNature(Nature $nature): array
    {
        return [
            'id' => $nature->getId(),
            'label' => $nature->getLabel(),
            'color' => $nature->getColor(),
            'hide' => !$nature->getNeedsMobileSync()
        ];
    }
}
