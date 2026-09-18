<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePerformanceQueryRequest;
use App\Models\PerformanceQuery;
use App\Services\PerformanceInsights\PerformanceQuestionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;

class PerformanceInsightsController extends Controller
{
    public function __construct(private PerformanceQuestionService $performanceQuestions) {}

    public function index(): View
    {
        $this->ensureAdmin();

        $history = $this->history();

        return view('admin.performance-insights.index', [
            'history' => $history,
            'selectedQuery' => $history->first(),
        ]);
    }

    public function show(PerformanceQuery $performanceQuery): View
    {
        $this->ensureOwner($performanceQuery);

        return view('admin.performance-insights.index', [
            'history' => $this->history(),
            'selectedQuery' => $performanceQuery,
        ]);
    }

    public function store(StorePerformanceQueryRequest $request): RedirectResponse
    {
        $admin = backpack_user();
        abort_unless($admin?->isAdmin(), 403);

        $validated = $request->validated();
        $resultData = $this->performanceQuestions->answer($validated['question']);
        $performanceQuery = PerformanceQuery::query()->create([
            'admin_id' => $admin->getKey(),
            'question' => $validated['question'],
            'chart_type' => $resultData['chart']['type'],
            'result_data' => $resultData,
        ]);

        return to_route('performance-insights.show', $performanceQuery);
    }

    public function destroy(PerformanceQuery $performanceQuery): RedirectResponse
    {
        $this->ensureOwner($performanceQuery);
        $performanceQuery->delete();

        return to_route('performance-insights.index');
    }

    /**
     * @return Collection<int, PerformanceQuery>
     */
    private function history(): Collection
    {
        return PerformanceQuery::query()
            ->where('admin_id', backpack_user()?->getKey())
            ->latest()
            ->get();
    }

    private function ensureAdmin(): void
    {
        abort_unless(backpack_user()?->isAdmin(), 403);
    }

    private function ensureOwner(PerformanceQuery $performanceQuery): void
    {
        $this->ensureAdmin();
        abort_unless($performanceQuery->admin_id === backpack_user()?->getKey(), 404);
    }
}
