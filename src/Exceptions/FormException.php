<?php

namespace App\Exceptions;

use Exception;

class FormException extends Exception {
    private ?array $data = null;

    public function getData(): ?array {
        return $this->data;
    }

    public function setData(?array $data): self {
        $this->data = $data;
        return $this;
    }


}
