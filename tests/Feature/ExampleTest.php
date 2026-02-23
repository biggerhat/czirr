<?php

it('returns a successful response', function () {
    $user = createAdminUser();

    $response = $this->actingAs($user)->get('/');

    $response->assertStatus(200);
});
