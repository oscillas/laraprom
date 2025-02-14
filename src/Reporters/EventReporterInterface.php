<?php

declare(strict_types=1);

namespace Oscillas\Laraprom\Reporters;

interface EventReporterInterface
{
    public function putEvent(
        string $title,
        int $unixTimestampMillis,
        array $dimensions,
        string $text,
    ): void;
}