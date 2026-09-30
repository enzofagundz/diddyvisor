<?php

namespace Tests\Feature;

use Tests\TestCase;

class BrandTest extends TestCase
{
    public function test_login_displays_official_diddyvisor_identity(): void
    {
        $this->withoutVite();
        $this->get('/app/login')->assertOk()->assertSee('images/diddyvisor.png', false)->assertSee('DiddyVisor');
    }
}
