<?php

it('sends guests from the homepage to login', function () {
    $this->get(route('home'))
        ->assertRedirect(route('login'));
});

it('redirects legacy marketing pages to login', function () {
    foreach (['/features', '/about', '/pricing', '/contact', '/terms', '/privacy', '/cookies', '/legal', '/framework', '/cgv', '/mentions-legales'] as $path) {
        $this->get($path)->assertRedirect('/login');
    }
});

it('redirects public modules catalog to login', function () {
    $this->get(route('marketing.modules'))->assertRedirect(route('login'));
});

it('sends the public cart url to the modules page', function () {
    $this->get(route('marketing.cart'))
        ->assertRedirect(route('settings.modules', ['cart' => 1]));
});

it('lists login and register in the sitemap', function () {
    $this->get(route('marketing.sitemap'))
        ->assertOk()
        ->assertSee(route('login'), false)
        ->assertSee(route('register'), false)
        ->assertDontSee('/features', false)
        ->assertDontSee('/pricing', false)
        ->assertDontSee('/contact', false);
});
