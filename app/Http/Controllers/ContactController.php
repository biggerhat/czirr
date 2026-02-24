<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\FamilyMember;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = $request->query('search', '');
        $ownerId = $user->id;

        // If user is a linked family member, show the family owner's contacts
        $linkedMember = FamilyMember::where('linked_user_id', $user->id)->first();
        if ($linkedMember) {
            $ownerId = $linkedMember->user_id;
        }

        $query = Contact::where('user_id', $ownerId)->orderBy('last_name')->orderBy('first_name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        $user = $request->user();

        return Inertia::render('contacts/Index', [
            'contacts' => $query->get(),
            'search' => $search,
            'can' => [
                'create' => $user->can('contacts.create'),
                'edit' => $user->can('contacts.edit'),
                'delete' => $user->can('contacts.delete'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $contact = $request->user()->contacts()->create($validated);

        $this->syncBirthdayEvent($contact);

        return response()->json($contact, 201);
    }

    public function update(Request $request, Contact $contact): JsonResponse
    {
        if ($contact->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $contact->update($validated);

        $this->syncBirthdayEvent($contact);

        return response()->json($contact);
    }

    public function destroy(Request $request, Contact $contact): JsonResponse
    {
        if ($contact->user_id !== $request->user()->id) {
            abort(403);
        }

        $contact->birthdayEvent?->delete();
        $contact->delete();

        return response()->json(null, 204);
    }

    private function syncBirthdayEvent(Contact $contact): void
    {
        $existing = $contact->birthdayEvent;

        if (! $contact->date_of_birth) {
            $existing?->delete();

            return;
        }

        $name = trim($contact->first_name.' '.$contact->last_name);
        $dob = $contact->date_of_birth;
        $startsAt = Carbon::create($dob->year, $dob->month, $dob->day, 12, 0, 0, 'UTC');

        $data = [
            'user_id' => $contact->user_id,
            'title' => "{$name}'s Birthday",
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy(),
            'is_all_day' => true,
            'rrule' => 'FREQ=YEARLY',
        ];

        if ($existing) {
            $existing->update($data);
        } else {
            $contact->birthdayEvent()->create($data);
        }
    }
}
