<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('an authenticated user can update their full name', function () {
    $departmentId = DB::table('departments')->insertGetId([
        'name' => 'Support',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = User::factory()->create([
        'department_id' => $departmentId,
    ]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'fullname' => 'Updated Full Name',
            'email' => $user->email,
        ])
        ->assertRedirect(route('profile.edit'));

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'fullname' => 'Updated Full Name',
    ]);
});
