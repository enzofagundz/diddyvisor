<?php

test('login displays official diddyvisor identity', function () {
    $this->withoutVite();
    $this->get('/app/login')->assertOk()->assertSee('images/diddyvisor.png', false)->assertSee('DiddyVisor');
});
