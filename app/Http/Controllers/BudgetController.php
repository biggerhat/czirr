<?php

namespace App\Http\Controllers;

use App\Services\BudgetInsightsService;
use App\Services\BudgetService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function __construct(
        private BudgetService $budgetService,
        private BudgetInsightsService $insights,
    ) {}

    public function index(Request $request): Response
    {
        $owner = $request->user()->familyOwner();
        $this->budgetService->seedDefaultCategories($owner);

        $start = $request->input('start');
        $end = $request->input('end');

        if ($start && $end) {
            $overview = $this->budgetService->getOverviewForRange(
                $owner,
                Carbon::parse($start)->startOfDay(),
                Carbon::parse($end)->endOfDay(),
            );
        } else {
            $monthParam = $request->input('month');
            $month = $monthParam ? Carbon::parse($monthParam.'-01') : $request->user()->localNow();
            $overview = $this->budgetService->getMonthlyOverview($owner, $month);
        }

        // Deferred: the six-month trend is the heaviest part and isn't needed to paint the page.
        $insightsMonth = Carbon::parse(($overview['month'] ?? $request->user()->localNow()->format('Y-m')).'-01');

        return Inertia::render('budgeting/Index', [
            ...$overview,
            'insights' => Inertia::defer(fn () => $this->insights->build($owner, $insightsMonth)),
        ]);
    }
}
