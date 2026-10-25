<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_raiz_lleva_al_panel_del_auditor(): void
    {
        $this->get('/')->assertRedirect('/panel');
    }
}
