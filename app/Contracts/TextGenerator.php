<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\GenerationData;
use App\Data\GenerationResult;
use App\Exceptions\ProviderException;

interface TextGenerator
{
    public function name(): string;

    public function model(): string;

    /** @throws ProviderException */
    public function generate(GenerationData $data): GenerationResult;
}
