<?php

namespace App\Validator;

use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\{Constraint, Constraints\ImageValidator};
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadImageValidator extends ImageValidator
{
  public function validate(mixed $value, Constraint $constraint): void
  {
    if (!$constraint instanceof UploadImage)
      throw new UnexpectedTypeException($constraint, UploadImage::class);

    if ($value instanceof UploadedFile) parent::validate($value, $constraint);
  }
}
