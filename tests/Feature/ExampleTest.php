<?php

test('the application root redirects to the panel', function () {
    $this->get('/')->assertRedirect('/app');
});
