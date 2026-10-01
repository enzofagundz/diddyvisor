<?php

test('login displays official diddyvisor identity', function () {
    $this->withoutVite();
    $this->get('/app/login')->assertOk()->assertSee('images/diddy/diddy-avatar.png', false)->assertSee('DiddyVisor');
});

test('error page shows the diddy mascot', function () {
    $this->get('/endereco-inexistente')->assertNotFound()->assertSee('images/diddy/diddy-erro-404.png', false);
});
