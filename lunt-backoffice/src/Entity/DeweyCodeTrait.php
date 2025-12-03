<?php

namespace App\Entity;

trait DeweyCodeTrait
{
    public function getNumericCodeWithSpace(): ?string
    {
        $numericCode = $this->getNumericCode();
        if (str_contains($numericCode, '.')) {
            $parts = explode('.', $numericCode);
            $parts[1] = wordwrap($parts[1], 3, ' ', true);
            $numericCode = implode('.', $parts);
        }
        return $numericCode;
    }

    public function getNumericCode(): ?string
    {
        $numericCode = str_replace('http://dewey.info/class/', '', $this->code);
        return rtrim($numericCode, "/");
    }
}