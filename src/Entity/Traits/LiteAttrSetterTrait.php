<?php

namespace App\Entity\Traits;


trait LiteAttrSetterTrait {
    public function setAttributes(array $attributes): void {
        foreach ($attributes as $name => $value) {
            if (property_exists($this, $name)) {
                $this->{$name} = $value;
            }
            else {
                throw new \Exception("Property {$name} does not exists for class " . self::class);
            }
        }
    }
}
