<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function attendingEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_attendees')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function familyMembers(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    private bool $linkedFamilyMemberResolved = false;

    private ?FamilyMember $linkedFamilyMemberCache = null;

    /**
     * Get the FamilyMember record linking this user into another user's family.
     * Returns null if the user IS the family owner (not linked elsewhere).
     *
     * Result is cached per-instance so repeated calls within the same request
     * (e.g. familyOwnerId(), familyOwner(), middleware) only hit the DB once.
     */
    public function linkedFamilyMember(): ?FamilyMember
    {
        if (! $this->linkedFamilyMemberResolved) {
            $this->linkedFamilyMemberCache = FamilyMember::where('linked_user_id', $this->id)
                ->where('user_id', '!=', $this->id)
                ->first();
            $this->linkedFamilyMemberResolved = true;
        }

        return $this->linkedFamilyMemberCache;
    }

    /**
     * Resolve the family owner's user ID.
     * For family owners this returns their own ID; for linked members it
     * returns the ID of the user who owns the family.
     */
    public function familyOwnerId(): int
    {
        return $this->linkedFamilyMember()?->user_id ?? $this->id;
    }

    /**
     * Return the family owner User instance.
     * For family owners this returns $this; for linked members it returns
     * the User who owns the family. Use this when you need to create
     * resources via Eloquent relationships (e.g. $owner->chores()->create()).
     */
    public function familyOwner(): self
    {
        $ownerId = $this->familyOwnerId();

        return $ownerId === $this->id ? $this : self::findOrFail($ownerId);
    }

    public function budgetCategories(): HasMany
    {
        return $this->hasMany(BudgetCategory::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function chores(): HasMany
    {
        return $this->hasMany(Chore::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function familyLists(): HasMany
    {
        return $this->hasMany(FamilyList::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    public function cookbooks(): HasMany
    {
        return $this->hasMany(Cookbook::class);
    }

    public function cuisines(): HasMany
    {
        return $this->hasMany(Cuisine::class);
    }

    public function recipeTags(): HasMany
    {
        return $this->hasMany(RecipeTag::class);
    }

    public function mealPlanEntries(): HasMany
    {
        return $this->hasMany(MealPlanEntry::class);
    }
}
