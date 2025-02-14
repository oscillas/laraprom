<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    #[Test]
    public function does_the_test_suite_work(): void
    {
        /** @phpstan-ignore-next-line */
        $this->assertTrue(true);
    }
}