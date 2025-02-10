<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Workbench\App\Models\User;

class ExampleTest extends TestCase
{
    use WithWorkbench;
    use RefreshDatabase;

    #[Test]
    public function can_talk_to_database_test(): void
    {
        User::factory()->create();
        $this->assertDatabaseCount('users', 1);
    }
}