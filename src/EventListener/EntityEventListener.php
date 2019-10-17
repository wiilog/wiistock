<?php


namespace App\EventListener;


use App\Annotation\BarcodeAnnotation;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Exception;
use ReflectionClass;
use ReflectionException;


class EntityEventListener {

    /**
     * @var AnnotationReader
     */
    private $annotationReader;

    public function __construct() {
        $this->annotationReader = new AnnotationReader();
    }

    /**
     * Returns an array of events this subscriber wants to listen to.
     *
     * @return string[]
     */
    public function getSubscribedEvents(): array {
        return [
            Events::prePersist,
            Events::preUpdate
        ];
    }

    /**
     * Event saved in config/service.yaml
     * @param LifecycleEventArgs $args
     * @throws ReflectionException
     */
    public function prePersist(LifecycleEventArgs $args): void {
        $this->treatObject($args->getObject());
    }

    /**
     * Event saved in config/service.yaml
     * @param PreUpdateEventArgs $args
     * @throws ReflectionException
     */
    public function preUpdate(PreUpdateEventArgs $args): void {
        $this->treatObject($args->getObject());
    }

    /**
     * We read all annotations for all object properties
     * @param $object
     * @throws ReflectionException
     * @throws Exception
     */
    private function treatObject($object): void {
        $reflection = new ReflectionClass($object);
        $reflectionProperties = $reflection->getProperties();

        foreach ($reflectionProperties as $reflectionProperty) {
            /** @var BarcodeAnnotation $annotation */
            $annotation = $this->annotationReader->getPropertyAnnotation($reflectionProperty, BarcodeAnnotation::class);
            if ($annotation) {
                $value = $object->{$annotation->getter}();
                $this->treatBarcodeAnnotation($value);
            }
        }
    }

    /**
     * @param string|null $value
     * @throws Exception
     */
    private function treatBarcodeAnnotation(?string $value): void {
        if (!empty($value)) {
            if ((strlen($value) > BarcodeAnnotation::MAX_LENGTH) ||
                preg_match(BarcodeAnnotation::PREG_MATCH_INVALID, $value)) {
                throw new Exception('Invalid barcode');
            }
        }
    }
}