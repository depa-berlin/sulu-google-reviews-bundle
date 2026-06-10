<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class DepaGoogleReviewsBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
