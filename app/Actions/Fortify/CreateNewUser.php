<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\FamilyRole;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        // First registered user becomes the family admin
        if (User::count() === 1) {
            $user->familyMembers()->create([
                'name' => $user->name,
                'role' => FamilyRole::Parent,
                'color' => 'blue',
                'linked_user_id' => $user->id,
            ]);

            $user->assignRole('admin');
        }

        return $user;
    }
}
