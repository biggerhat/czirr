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
        $this->budgetService->seedDefaultCategories($request->user());

        $start = $request->input('start');
        $end = $request->input('end');

        if ($start && $end) {
            $overview = $this->budgetService->getOverviewForRange(
                $request->user(),
                Carbon::parse($start)->startOfDay(),
                Carbon::parse($end)->endOfDay(),
            );
        } else {
            $monthParam = $request->input('month');
            $month = $monthParam ? Carbon::parse($monthParam.'-01') : Carbon::now();
            $overview = $this->budgetService->getMonthlyOverview($request->user(), $month);
        }

        return Inertia::render('budgeting/Index', $overview);
    }
}
