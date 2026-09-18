@extends(backpack_view('blank'))

@push('after_styles')
    <style>
        .insights-chart-shell { min-height: 320px; }
        .insights-bar-chart { min-height: 250px; }
        .insights-bar-group { min-width: 82px; }
        .insights-bar { min-height: 3px; transition: height .25s ease; }
        .insights-history-item { border-left: 3px solid transparent; }
        .insights-history-item:hover { background: var(--bs-tertiary-bg); }
        .insights-history-item.active { border-left-color: var(--bs-primary); background: var(--bs-primary-bg-subtle); }
    </style>
@endpush

@php
    $defaultBreadcrumbs = [
        trans('backpack::crud.admin') => backpack_url('dashboard'),
        'Performance Insights' => false,
    ];
    $breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;
    $result = $selectedQuery?->result_data ?? [];
    $chart = $result['chart'] ?? ['labels' => [], 'datasets' => []];
    $labels = $chart['labels'] ?? [];
    $datasets = $chart['datasets'] ?? [];
    $chartValues = collect($datasets)->flatMap(fn (array $dataset) => $dataset['values'] ?? []);
    $maxValue = max((float) $chartValues->max(), 1);
    $chartColors = ['#206bc4', '#d63939', '#2fb344', '#f76707', '#ae3ec9'];
@endphp

@section('content')
    <div class="row g-4">
        <div class="col-12">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <h2 class="mb-1">Performance Insights</h2>
                    <p class="text-muted mb-0">Ask a question in plain English to analyze approved task performance and compare staff progress.</p>
                </div>
                <span class="badge bg-primary-subtle text-primary-emphasis align-self-lg-start px-3 py-2">Admins only</span>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('performance-insights.store') }}" class="row g-3">
                        @csrf
                        <div class="col-12">
                            <label for="question" class="form-label fw-semibold">What would you like to know?</label>
                            <textarea name="question" id="question" rows="3" class="form-control @error('question') is-invalid @enderror" placeholder="Which staff person was the best performer in March 2026?" required>{{ old('question') }}</textarea>
                            @error('question')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary px-4"><i class="la la-magic me-1"></i> Run insight</button>
                        </div>
                    </form>
                    <div class="mt-4 pt-3 border-top">
                        <p class="small fw-semibold text-muted text-uppercase mb-2">Try asking</p>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-question="Which staff person was the best performer in March 2026?">Best performer in March</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-question="Compare Staff 1 and Staff 2 performance for 2026">Compare two staff in 2026</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-question="Show the performance distribution for 2026">Performance distribution</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-question="Show pending tasks for 2026">Pending work this year</button>
                        </div>
                    </div>
                </div>
            </div>

            @if ($selectedQuery)
                <div class="card shadow-sm">
                    <div class="card-header bg-transparent d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 py-3">
                        <div>
                            <div class="small text-muted text-uppercase fw-semibold">Answer</div>
                            <h4 class="mb-0">{{ $result['period_label'] ?? 'Performance result' }}</h4>
                        </div>
                        <span class="badge text-bg-light border">{{ $result['metric_label'] ?? 'Task performance' }}</span>
                    </div>
                    <div class="card-body p-4">
                        <p class="lead mb-4">{{ $result['answer'] ?? 'No saved result is available.' }}</p>

                        @if (count($labels) > 0 && count($datasets) > 0)
                            <div class="insights-chart-shell border rounded p-3 p-md-4">
                                <div class="d-flex flex-wrap gap-3 mb-3 small">
                                    @foreach ($datasets as $datasetIndex => $dataset)
                                        <span class="d-inline-flex align-items-center gap-2"><span class="rounded-circle" style="width: 10px; height: 10px; background: {{ $chartColors[$datasetIndex % count($chartColors)] }};"></span>{{ $dataset['label'] }}</span>
                                    @endforeach
                                </div>

                                @if (($chart['type'] ?? 'bar') === 'pie')
                                    @php
                                        $pieValues = collect($datasets[0]['values'] ?? []);
                                        $pieTotal = $pieValues->sum();
                                        $circumference = 2 * pi() * 42;
                                        $runningOffset = 0;
                                    @endphp
                                    <div class="d-flex flex-column flex-md-row align-items-center justify-content-center gap-4">
                                        <div class="position-relative flex-shrink-0" style="width: 240px; height: 240px;">
                                            <svg viewBox="0 0 120 120" class="w-100 h-100" role="img" aria-label="Performance pie chart">
                                                <circle cx="60" cy="60" r="42" fill="none" stroke="#e9ecef" stroke-width="18"></circle>
                                                @foreach ($labels as $labelIndex => $label)
                                                    @php
                                                        $value = (float) ($pieValues[$labelIndex] ?? 0);
                                                        $segmentLength = $pieTotal > 0 ? ($value / $pieTotal) * $circumference : 0;
                                                        $dashArray = $segmentLength.' '.max($circumference - $segmentLength, 0);
                                                        $dashOffset = -$runningOffset;
                                                        $runningOffset += $segmentLength;
                                                    @endphp
                                                    @if ($value > 0)
                                                        <circle cx="60" cy="60" r="42" fill="none" stroke="{{ $chartColors[$labelIndex % count($chartColors)] }}" stroke-width="18" stroke-dasharray="{{ $dashArray }}" stroke-dashoffset="{{ $dashOffset }}" transform="rotate(-90 60 60)"></circle>
                                                    @endif
                                                @endforeach
                                            </svg>
                                            <div class="position-absolute top-50 start-50 translate-middle text-center">
                                                <div class="small text-muted text-uppercase">Total</div>
                                                <div class="h3 mb-0">{{ number_format($pieTotal) }}</div>
                                            </div>
                                        </div>
                                        <div class="w-100" style="max-width: 320px;">
                                            @foreach ($labels as $labelIndex => $label)
                                                @php
                                                    $value = (float) ($pieValues[$labelIndex] ?? 0);
                                                @endphp
                                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                    <span class="d-inline-flex align-items-center gap-2"><span class="rounded-circle" style="width: 10px; height: 10px; background: {{ $chartColors[$labelIndex % count($chartColors)] }};"></span>{{ $label }}</span>
                                                    <span class="fw-semibold">{{ number_format($value) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @elseif (($chart['type'] ?? 'bar') === 'line')
                                    @php
                                        $chartWidth = 760;
                                        $chartHeight = 270;
                                        $padding = 34;
                                        $step = count($labels) > 1 ? ($chartWidth - (2 * $padding)) / (count($labels) - 1) : 0;
                                    @endphp
                                    <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="w-100" role="img" aria-label="Performance line chart">
                                        <line x1="{{ $padding }}" y1="{{ $chartHeight - $padding }}" x2="{{ $chartWidth - $padding }}" y2="{{ $chartHeight - $padding }}" stroke="#ced4da" />
                                        @foreach ($datasets as $datasetIndex => $dataset)
                                            @php
                                                $points = collect($dataset['values'])->map(function ($value, $valueIndex) use ($padding, $chartHeight, $step, $maxValue): string {
                                                    $x = $padding + ($valueIndex * $step);
                                                    $y = ($chartHeight - $padding) - (($value / $maxValue) * ($chartHeight - (2 * $padding)));

                                                    return round($x, 2).','.round($y, 2);
                                                })->implode(' ');
                                            @endphp
                                            <polyline fill="none" stroke="{{ $chartColors[$datasetIndex % count($chartColors)] }}" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" points="{{ $points }}" />
                                        @endforeach
                                        @foreach ($labels as $labelIndex => $label)
                                            <text x="{{ $padding + ($labelIndex * $step) }}" y="{{ $chartHeight - 10 }}" text-anchor="middle" fill="#6c757d" font-size="13">{{ $label }}</text>
                                        @endforeach
                                    </svg>
                                @else
                                    <div class="d-flex align-items-end gap-3 overflow-auto insights-bar-chart pb-4">
                                        @foreach ($labels as $labelIndex => $label)
                                            <div class="insights-bar-group flex-fill text-center">
                                                <div class="d-flex align-items-end justify-content-center gap-1" style="height: 210px;">
                                                    @foreach ($datasets as $datasetIndex => $dataset)
                                                        @php($value = (float) ($dataset['values'][$labelIndex] ?? 0))
                                                        <div class="insights-bar rounded-top" title="{{ $dataset['label'] }}: {{ $value }}" style="height: {{ max(($value / $maxValue) * 100, $value > 0 ? 3 : 0) }}%; width: {{ max(34 / max(count($datasets), 1), 10) }}px; background: {{ $chartColors[$datasetIndex % count($chartColors)] }};"></div>
                                                    @endforeach
                                                </div>
                                                <div class="small text-muted text-truncate mt-2" title="{{ $label }}">{{ $label }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="alert alert-info mb-0">There is no task data to chart for this period yet.</div>
                        @endif
                    </div>
                </div>
            @else
                <div class="card border-dashed">
                    <div class="card-body text-center py-5 text-muted"><i class="la la-chart-bar display-5 d-block mb-3"></i>Run your first question to see a saved performance insight here.</div>
                </div>
            @endif
        </div>

        <div class="col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-transparent py-3"><h4 class="mb-0">Previous queries</h4></div>
                <div class="list-group list-group-flush">
                    @forelse ($history as $historyItem)
                        <div class="list-group-item insights-history-item {{ $selectedQuery?->is($historyItem) ? 'active' : '' }} p-3">
                            <div class="d-flex gap-2 align-items-start">
                                <a href="{{ route('performance-insights.show', $historyItem) }}" class="text-decoration-none text-reset flex-grow-1">
                                    <div class="fw-semibold lh-sm">{{ $historyItem->question }}</div>
                                    <div class="small text-muted mt-2">{{ $historyItem->created_at->format('M j, Y · g:i A') }}</div>
                                </a>
                                <form method="POST" action="{{ route('performance-insights.destroy', $historyItem) }}" onsubmit="return confirm('Delete this saved query?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Delete query" aria-label="Delete query"><i class="la la-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-muted">Your saved questions will appear here.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@push('after_scripts')
    <script>
        document.querySelectorAll('[data-question]').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById('question').value = button.dataset.question;
                document.getElementById('question').focus();
            });
        });
    </script>
@endpush
