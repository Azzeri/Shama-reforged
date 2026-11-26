<?php

use App\Models\User;

test('the home page sends guests to the login screen', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('the home page sends signed-in users to the meal plan', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('meals.index'));
});
