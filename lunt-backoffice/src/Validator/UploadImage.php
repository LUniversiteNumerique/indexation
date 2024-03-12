<?php

namespace App\Validator;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class UploadImage extends \Symfony\Component\Validator\Constraints\Image
{

}
