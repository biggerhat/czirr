<?php

namespace App\Http\Controllers;

use App\Models\ChoreAssignment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $today = Carbon::today();

        $bills = $request->user()
            ->bills()
            ->with('category')
            ->where('is_active', true)
            ->get();

        $upcomingBills = $bills->map(function ($bill) use ($today) {
            $bill->next_due_date = $bill->nextDueDate($today)->toDateString();
            $bill->is_paid_this_month = $bill->isPaidForMonth($today);
            return $bill;
        })
            ->filter(fn ($bill) => Carbon::parse($bill->next_due_date)->diffInDays($today, false) >= -14)
            ->sortBy('next_due_date')
            ->values();

        $todaysChores = ChoreAssignment::where('day_of_week', Carbon::now()->dayOfWeek)
            ->whereHas('chore', fn ($q) => $q->where('user_id', $request->user()->id)->where('is_active', true))
            ->with(['chore', 'familyMember'])
            ->get();

        return Inertia::render('Dashboard', [
            'upcomingBills' => $upcomingBills,
            'todaysChores' => $todaysChores,
        ]);
    }
}
