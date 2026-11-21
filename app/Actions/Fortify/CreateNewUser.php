<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\FamilyRole;
use App\Models\FamilyInvite;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
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
        $this->ensureRegistrationAllowed();

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

    private function ensureRegistrationAllowed(): void
    {
        if (config('app.registration_open') || User::count() === 0) {
            return;
        }

        // Closed registration: only people arriving through a live invite link may sign up.
        $intended = (string) session('url.intended', '');
        if (preg_match('#/invite/([A-Za-z0-9]{48})$#', $intended, $m)
            && FamilyInvite::findByToken($m[1])?->isUsable()) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => 'Sign-ups are invite-only. Ask a family member for an invite link.',
        ]);
    }
}
