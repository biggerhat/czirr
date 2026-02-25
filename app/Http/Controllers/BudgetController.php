<?php

namespace App\Http\Controllers;

use App\Services\BudgetService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function __construct(
        private BudgetService $budgetService
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
            $month = $monthParam ? Carbon::parse($monthParam.'-01') : Carbon::now();
            $overview = $this->budgetService->getMonthlyOverview($owner, $month);
        }

        return Inertia::render('budgeting/Index', $overview);
    }
}
