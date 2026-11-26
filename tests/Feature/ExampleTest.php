<?php

it('sends the homepage to login', function () {
    $this->get('/')->assertRedirect(route('login'));
});
