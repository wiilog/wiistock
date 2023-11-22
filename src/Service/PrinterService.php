<?php

namespace App\Service;

use App\Entity\Dispatch;
use App\Entity\DispatchLabelConfiguration;
use App\Entity\DispatchLabelConfigurationField;
use App\Entity\DispatchPack;
use App\Entity\Printer;
use App\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment;
use WiiCommon\Helper\Stream;
use ZplGenerator\Client\SocketClient;
use ZplGenerator\Elements\Codes\QrCode;
use ZplGenerator\Elements\Common\Align;
use ZplGenerator\Elements\Element;
use ZplGenerator\Elements\Image;
use ZplGenerator\Elements\Text\Text;
use ZplGenerator\Elements\Text\TextConfig;
use ZplGenerator\Printer\Printer as ZebraPrinter;

class PrinterService
{
    private const DEFAULT_PRINTER_PORT = 9100;
    private const DEFAULT_PRINTER_TIMEOUT = 5;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public KernelInterface $kernel;

    #[Required]
    public Environment $twig;

    private bool $initialized = false;

    private ?array $configurations = null;

    private ?array $printers = null;

    public function test(Printer $printer): void
    {
        $zebraPrinter = $this->getPrinter($printer);
        $label = '~WC';

        $client = SocketClient::create("127.0.0.1", 9100, 10);
        $zebraPrinter->print($client, $label);
    }

    public function printDispatchPacks(ZebraPrinter $printer, Dispatch $dispatch, array $packs, bool $isSeparation): void
    {
        $settingsRepository = $this->entityManager->getRepository(Setting::class);
        $logo = $settingsRepository->getOneParamByLabel(Setting::LABEL_LOGO);

        $configuration = $this->configurationOf($dispatch);
        $labels = Stream::from($packs)
            ->reindex()
            ->map(function (DispatchPack $pack, int $i) use ($printer, $dispatch, $configuration, $logo, $packs, $isSeparation) {
                if ($configuration->getCodeType() === "packNumber") {
                    $code = $pack->getPack()->getCode();
                } else if ($configuration->getCodeType() === "dispatchNumber") {
                    $code = $pack->getDispatch()->getNumber();
                } else {
                    throw new RuntimeException("Unknown configuration code {$configuration->getCodeType()}");
                }

                $labelStyle = new TextConfig(null, 7);
                $paginationStyle = new TextConfig(null, 5);

                $qr = QrCode::create(0, 20)
                    ->setSize(9)
                    ->setAlignment(Align::CENTER)
                    ->setContent($code)
                    ->setDisplayContent(true);

                $label = $printer->createLabel();
                $label->with(
                    Image::fromPath(3, 3, "{$this->kernel->getProjectDir()}/public/uploads/attachments/{$logo}")
                        ->setWidth(30)
                        ->setHeight(15)
                )
                    ->with($qr);

                if ($isSeparation) {
                    $label->with(Text::create(0, 89)
                        ->setAlignment(Align::CENTER)
                        ->setText(($i + 1) . "/" . count($packs))
                        ->setConfig($paginationStyle)
                        ->setMaxLines(1));
                }

                if ($configuration->getShowPacksCount()) {
                    $reindexed = Stream::from($pack->getDispatch()->getDispatchPacks()->getValues())
                        ->reindex();

                    $label->with(Text::create(0, 95)
                        ->setAlignment(Align::CENTER)
                        ->setText(($reindexed->indexOf($pack) + 1) . "/" . $reindexed->count())
                        ->setConfig($paginationStyle)
                        ->setMaxLines(1));
                }

                [, $height] = $labelStyle->dimensions();

                Stream::from($configuration->getFields())
                    ->sort(fn($a, $b) => $a->getPosition() <=> $b->getPosition())
                    ->map(fn(DispatchLabelConfigurationField $field) => [$field->getFieldId(), trim($field->getValue($dispatch))])
                    ->filter(fn(array $field) => $field[1])
                    ->reindex()
                    ->flatMap(function (array $field, int $i) use ($dispatch, $labelStyle, $height) {
                        [$key, $value] = $field;
                        $elements = [];

                        if (trim($value)) {
                            $elements[] = Text::create(0, 57 + $i * $height)
                                ->setText($value)
                                ->setConfig($labelStyle)
                                ->setAlignment(Align::CENTER)
                                ->setSpacing(7)
                                ->setMaxLines(8);
                        }

                        if ($key === DispatchLabelConfigurationField::FIELD_TYPE && $dispatch->getType()->getLogo()?->getFullPath()) {
                            $path = "{$this->kernel->getProjectDir()}/public/{$dispatch->getType()->getLogo()?->getFullPath()}";
                            $x = 58 - $labelStyle->dimensionsOf($value)[0] / 2;
                            $y = 57 + $i * $height - 1;

                            $elements[] = Image::fromPath($x, $y, $path)
                                ->setWidth(5)
                                ->setHeight(5);
                        }

                        return $elements;
                    })
                    ->each(fn(Element $element) => $label->with($element));

                return $label;
            })
            ->toArray();

        $client = SocketClient::create("127.0.0.1", 9100, 10);
        $printer->print($client, ...$labels);
    }

    private function initialize(): void
    {
        if (!$this->initialized) {
            $this->initialized = true;

            $configRepository = $this->entityManager->getRepository(DispatchLabelConfiguration::class);
            $this->configurations = Stream::from($configRepository->findAll())
                ->keymap(fn(DispatchLabelConfiguration $config) => [$config->getType()->getId(), $config])
                ->toArray();
        }
    }

    public function configurationOf($entity): ?DispatchLabelConfiguration
    {
        $this->initialize();

        if ($entity instanceof Dispatch) {
            return $this->configurations[$entity->getType()->getId()] ?? null;
        } else {
            throw new RuntimeException("Unknown entity type");
        }
    }

    public function getPrinter(Printer $printer): ZebraPrinter
    {
        if (!isset($this->printers[$printer->getId()])) {
            $this->printers[$printer->getId()] = ZebraPrinter::create($printer->getAddress(), self::DEFAULT_PRINTER_PORT, self::DEFAULT_PRINTER_TIMEOUT)
                ->setDimension($printer->getWidth(), $printer->getHeight())
                ->setDPI($printer->getDPI());
        }

        return $this->printers[$printer->getId()];
    }

}
