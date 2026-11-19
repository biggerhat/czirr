<?php

test('responses carry baseline security headers', function () {
    $res = $this->get('/');

    $res->assertHeader('X-Content-Type-Options', 'nosniff');
    $res->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $res->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    expect($res->headers->has('Strict-Transport-Security'))->toBeFalse(); // plain http in tests
});

test('hsts is only sent over https', function () {
    $this->get('https://localhost/')->assertHeader('Strict-Transport-Security');
});

test('the authenticated app is rate limited per user', function () {
    $user = createAdminUser();

    foreach (range(1, 300) as $i) {
        $this->actingAs($user)->get('/calendar')->assertSuccessful();
    }
    $this->actingAs($user)->get('/calendar')->assertStatus(429);
});
