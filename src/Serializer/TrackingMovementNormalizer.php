<?php

namespace App\Serializer;

use App\Entity\Tracking\TrackingMovement;
use App\Service\FormatService;
use Google\Service\AndroidPublisher\Track;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TrackingMovementNormalizer implements NormalizerInterface, NormalizerAwareInterface{

    use NormalizerAwareTrait;

    public function __construct(private FormatService $formatService) {}

    public function normalize(mixed $object, string $format = null, array $context = []): array {

        return [];
    }

    public function supportsNormalization(mixed $data, ?string $format = null): bool{
        return $data instanceof TrackingMovement;
    }
}
