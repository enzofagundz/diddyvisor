<?php

test('login displays official diddyvisor identity', function () {
    $this->withoutVite();
    $this->get('/app/login')->assertOk()->assertSee('images/diddyvisor.png', false)->assertSee('DiddyVisor');
});

test('login welcomes with the diddy mascot', function () {
    $this->withoutVite();
    $this->get('/app/login')->assertOk()->assertSee('images/diddy/diddy-boas-vindas.png', false);
});

test('error page shows the diddy mascot', function () {
    $this->get('/endereco-inexistente')->assertNotFound()->assertSee('images/diddy/diddy-erro-404.png', false);
});
