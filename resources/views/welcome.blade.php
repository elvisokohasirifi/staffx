<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="StaffX brings daily work, ownership, progress, and proof into one focused workspace.">
        <title>StaffX | Work, made visible</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>
                body { margin: 0; background: #f7f5ee; color: #0d2431; font-family: sans-serif; }
                main { max-width: 1200px; margin: auto; padding: 2rem; }
                a { color: inherit; }
            </style>
        @endif
    </head>
    <body class="bg-[#f7f5ee] font-sans text-[#0d2431] antialiased">
        <div class="relative isolate overflow-hidden">
            <div aria-hidden="true" class="absolute inset-x-0 top-0 -z-10 h-[42rem] bg-[#0d2431]"></div>
            <div aria-hidden="true" class="absolute -top-36 right-[-9rem] -z-10 h-96 w-96 rounded-full bg-[#d7ef67] blur-3xl opacity-35"></div>
            <div aria-hidden="true" class="absolute left-[-10rem] top-80 -z-10 h-80 w-80 rounded-full bg-[#fa7459] blur-3xl opacity-25"></div>

            <header class="mx-auto flex max-w-7xl items-center justify-between px-6 py-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-3 text-white" aria-label="StaffX home">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#d7ef67] text-lg font-black text-[#0d2431] shadow-[4px_4px_0_#fa7459]">SX</span>
                    <span class="text-xl font-extrabold tracking-tight">Staff<span class="text-[#d7ef67]">X</span></span>
                </a>

                <div class="flex items-center gap-3">
                    <a href="{{ $accessUrl }}" class="hidden text-sm font-semibold text-white/80 transition hover:text-white sm:block">Sign in</a>
                    @if ($organizationRegistrationUrl)
                        <a href="{{ $organizationRegistrationUrl }}" class="rounded-full bg-white px-4 py-2.5 text-sm font-bold text-[#0d2431] transition hover:-translate-y-0.5 hover:bg-[#d7ef67]">Create organization</a>
                    @else
                        <a href="{{ $accessUrl }}" class="rounded-full bg-[#d7ef67] px-4 py-2.5 text-sm font-bold text-[#0d2431] transition hover:-translate-y-0.5 hover:bg-white">Open StaffX</a>
                    @endif
                </div>
            </header>

            <main>
                <section class="mx-auto grid max-w-7xl gap-14 px-6 pb-24 pt-16 lg:grid-cols-[1.04fr_.96fr] lg:items-center lg:px-8 lg:pb-32 lg:pt-24">
                    <div>
                        <p class="mb-6 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-[#d7ef67]">
                            <span class="h-2 w-2 rounded-full bg-[#d7ef67]"></span>
                            Work clarity, every day
                        </p>
                        <h1 class="max-w-3xl text-5xl font-black leading-[0.96] tracking-[-0.055em] text-white sm:text-6xl lg:text-7xl">
                            The calm command center for work that <span class="text-[#d7ef67]">must get done.</span>
                        </h1>
                        <p class="mt-7 max-w-xl text-lg leading-8 text-slate-200 sm:text-xl">
                            StaffX turns plans into visible progress. Assign the day, keep conversations where the work happens, and see what was truly achieved.
                        </p>
                        <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                            <a href="{{ $accessUrl }}" class="inline-flex items-center justify-center gap-2 rounded-full bg-[#d7ef67] px-6 py-3.5 text-sm font-extrabold text-[#0d2431] transition hover:-translate-y-0.5 hover:bg-white">
                                {{ backpack_auth()->check() ? 'Open my workspace' : 'Sign in to StaffX' }}
                                <span aria-hidden="true">→</span>
                            </a>
                            @if ($organizationRegistrationUrl)
                                <a href="{{ $organizationRegistrationUrl }}" class="inline-flex items-center justify-center rounded-full bg-white px-6 py-3.5 text-sm font-bold text-[#0d2431] shadow-sm transition hover:-translate-y-0.5 hover:bg-[#d7ef67] hover:shadow-lg">Set up an organization</a>
                            @endif
                        </div>
                        <div class="mt-10 flex flex-wrap gap-3 text-sm font-semibold text-[#0d2431]">
                            <span class="inline-flex items-center gap-2 rounded-full bg-white/90 px-3 py-2 shadow-sm"><span class="grid h-5 w-5 place-items-center rounded-full bg-[#d7ef67] text-xs font-black text-[#0d2431]">✓</span> Built for admins and staff</span>
                            <span class="inline-flex items-center gap-2 rounded-full bg-white/90 px-3 py-2 shadow-sm"><span class="grid h-5 w-5 place-items-center rounded-full bg-[#d7ef67] text-xs font-black text-[#0d2431]">✓</span> Clear, accountable outcomes</span>
                        </div>
                    </div>

                    <div class="relative mx-auto w-full max-w-xl lg:mx-0">
                        <div aria-hidden="true" class="absolute -inset-4 rounded-[2rem] border border-white/10 bg-white/5 rotate-3"></div>
                        <div class="relative overflow-hidden rounded-[1.7rem] border border-white/10 bg-[#f9faf7] p-4 shadow-2xl shadow-black/30 sm:p-5">
                            <div class="mb-5 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="h-3 w-3 rounded-full bg-[#fa7459]"></span>
                                    <span class="h-3 w-3 rounded-full bg-[#f7bd55]"></span>
                                    <span class="h-3 w-3 rounded-full bg-[#63c69c]"></span>
                                </div>
                                <span class="rounded-full bg-[#e7f0ed] px-3 py-1 text-xs font-bold text-[#1d5a65]">Today</span>
                            </div>
                            <div class="rounded-2xl bg-[#0d2431] p-5 text-white">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#d7ef67]">Tuesday briefing</p>
                                        <p class="mt-2 text-xl font-extrabold">Your team is moving.</p>
                                    </div>
                                    <div class="grid h-14 w-14 place-items-center rounded-full border-[7px] border-[#d7ef67] text-sm font-black">71%</div>
                                </div>
                                <div class="mt-5 grid grid-cols-3 gap-3 text-center">
                                    <div class="rounded-xl bg-white/10 px-2 py-3"><strong class="block text-lg">24</strong><span class="text-[10px] font-bold uppercase tracking-wide text-slate-300">Assigned</span></div>
                                    <div class="rounded-xl bg-white/10 px-2 py-3"><strong class="block text-lg text-[#d7ef67]">17</strong><span class="text-[10px] font-bold uppercase tracking-wide text-slate-300">Approved</span></div>
                                    <div class="rounded-xl bg-white/10 px-2 py-3"><strong class="block text-lg text-[#f7bd55]">5</strong><span class="text-[10px] font-bold uppercase tracking-wide text-slate-300">Moving</span></div>
                                </div>
                            </div>
                            <div class="mt-4 space-y-3">
                                <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
                                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-[#dff3a0] text-xs font-black text-[#0d2431]">✓</span>
                                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-bold">Prepare the weekly service report</p><p class="text-xs text-slate-500">Approved as completed</p></div>
                                </div>
                                <div class="flex items-center gap-3 rounded-xl border border-[#f2d7a1] bg-[#fffaf0] px-4 py-3">
                                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-[#f7bd55] text-xs font-black text-[#0d2431]">→</span>
                                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-bold">Follow up with department leads</p><p class="text-xs text-slate-500">In progress · due 3:00 PM</p></div>
                                </div>
                                <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
                                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-black text-slate-500">○</span>
                                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-bold">Review community feedback</p><p class="text-xs text-slate-500">Pending · assigned to Naomi</p></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="mx-auto max-w-7xl px-6 pb-24 lg:px-8">
                    <div class="grid gap-5 md:grid-cols-3">
                        <div class="rounded-3xl bg-[#fa7459] p-7 text-[#0d2431] shadow-[8px_8px_0_#0d2431]"><p class="text-sm font-bold uppercase tracking-[0.14em]">For admins</p><p class="mt-6 text-3xl font-black leading-tight">Direct the day without chasing updates.</p></div>
                        <div class="rounded-3xl bg-[#d7ef67] p-7 text-[#0d2431] shadow-[8px_8px_0_#0d2431]"><p class="text-sm font-bold uppercase tracking-[0.14em]">For staff</p><p class="mt-6 text-3xl font-black leading-tight">Know the work, the priority, and the next move.</p></div>
                        <div class="rounded-3xl bg-[#1d5a65] p-7 text-white shadow-[8px_8px_0_#0d2431]"><p class="text-sm font-bold uppercase tracking-[0.14em] text-[#d7ef67]">For teams</p><p class="mt-6 text-3xl font-black leading-tight">Turn progress into a shared habit.</p></div>
                    </div>
                </section>

                <section class="border-y border-[#d5d8cf] bg-[#ecece5] py-24">
                    <div class="mx-auto max-w-7xl px-6 lg:px-8">
                        <div class="max-w-2xl"><p class="text-sm font-extrabold uppercase tracking-[0.18em] text-[#1d5a65]">One reliable rhythm</p><h2 class="mt-4 text-4xl font-black tracking-[-0.04em] sm:text-5xl">A workday that is easy to run and impossible to lose sight of.</h2></div>
                        <div class="mt-14 grid gap-5 md:grid-cols-3">
                            <article class="rounded-3xl bg-white p-7"><span class="grid h-11 w-11 place-items-center rounded-2xl bg-[#0d2431] text-sm font-black text-[#d7ef67]">01</span><h3 class="mt-7 text-2xl font-black">Plan</h3><p class="mt-3 leading-7 text-slate-600">Assign one task or a whole list. Set dates, times, owners, recurring schedules, and priorities in minutes.</p></article>
                            <article class="rounded-3xl bg-white p-7"><span class="grid h-11 w-11 place-items-center rounded-2xl bg-[#fa7459] text-sm font-black text-[#0d2431]">02</span><h3 class="mt-7 text-2xl font-black">Move</h3><p class="mt-3 leading-7 text-slate-600">Staff start work, complete it, or explain what blocked it. Remarks keep feedback attached to the exact task.</p></article>
                            <article class="rounded-3xl bg-white p-7"><span class="grid h-11 w-11 place-items-center rounded-2xl bg-[#d7ef67] text-sm font-black text-[#0d2431]">03</span><h3 class="mt-7 text-2xl font-black">Prove</h3><p class="mt-3 leading-7 text-slate-600">Approve completed work and use live dashboards, filters, and reports to understand real delivery.</p></article>
                        </div>
                    </div>
                </section>

                <section class="mx-auto max-w-7xl px-6 py-24 lg:px-8">
                    <div class="grid gap-6 lg:grid-cols-[.8fr_1.2fr]">
                        <div class="rounded-3xl bg-[#0d2431] p-8 text-white sm:p-10"><p class="text-sm font-extrabold uppercase tracking-[0.18em] text-[#d7ef67]">Built for follow-through</p><h2 class="mt-5 text-4xl font-black tracking-[-0.04em]">The details that make accountability feel practical.</h2><a href="{{ $accessUrl }}" class="mt-10 inline-flex rounded-full bg-[#d7ef67] px-5 py-3 text-sm font-extrabold text-[#0d2431] transition hover:bg-white">Enter StaffX <span class="ml-2">→</span></a></div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="rounded-3xl border border-[#d5d8cf] bg-white p-7"><h3 class="font-black">Bulk task creation</h3><p class="mt-3 text-sm leading-6 text-slate-600">Turn a prepared list into assigned work without repetitive data entry.</p></div>
                            <div class="rounded-3xl border border-[#d5d8cf] bg-white p-7"><h3 class="font-black">Recurring work</h3><p class="mt-3 text-sm leading-6 text-slate-600">Keep repeatable responsibilities on schedule on the exact days they matter.</p></div>
                            <div class="rounded-3xl border border-[#d5d8cf] bg-white p-7"><h3 class="font-black">Meaningful reporting</h3><p class="mt-3 text-sm leading-6 text-slate-600">See assigned, completed, pending, blocked, and approved work by person or department.</p></div>
                            <div class="rounded-3xl border border-[#d5d8cf] bg-white p-7"><h3 class="font-black">Private workspaces</h3><p class="mt-3 text-sm leading-6 text-slate-600">Organizations and departments keep people focused on the work that belongs to them.</p></div>
                        </div>
                    </div>
                </section>

                <section class="mx-auto max-w-7xl px-6 pb-24 lg:px-8">
                    <div class="overflow-hidden rounded-[2rem] bg-[#fa7459] px-7 py-12 text-[#0d2431] sm:px-12 sm:py-16">
                        <p class="text-sm font-extrabold uppercase tracking-[0.18em]">Make today count</p>
                        <div class="mt-4 flex flex-col justify-between gap-8 md:flex-row md:items-end"><h2 class="max-w-3xl text-4xl font-black leading-tight tracking-[-0.04em] sm:text-5xl">Every task deserves a clear owner, a visible outcome, and a next step.</h2><a href="{{ $accessUrl }}" class="shrink-0 rounded-full bg-[#0d2431] px-6 py-3.5 text-sm font-extrabold text-white transition hover:-translate-y-0.5 hover:bg-[#1d5a65]">Open StaffX <span class="ml-2 text-[#d7ef67]">→</span></a></div>
                    </div>
                </section>
            </main>

            <footer class="border-t border-[#d5d8cf] px-6 py-8 lg:px-8"><div class="mx-auto flex max-w-7xl flex-col gap-2 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between"><p><span class="font-bold text-[#0d2431]">StaffX</span> · Work, made visible.</p><p>Clear plans. Real progress. Better days.</p></div></footer>
        </div>
    </body>
</html>
