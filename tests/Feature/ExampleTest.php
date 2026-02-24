<?php

it('returns a successful response', function () {
    $user = createAdminUser();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);
});
