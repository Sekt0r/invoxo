<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet"/>

    <!-- Styles / Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] flex items-center lg:justify-center min-h-screen flex-col">

<div class="mx-auto lg:grow">
    <header class="w-full text-sm not-has-[nav]:hidden">
        @if (Route::has('login'))
            <nav class="flex items-center justify-end gap-2 py-2">
                @auth
                    <a href="{{ url('/dashboard') }}"
                       class="inline-flex h-8 items-center justify-center rounded-md border border-slate-300 px-4 text-sm font-medium text-slate-800 hover:bg-slate-50">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="inline-flex h-8 items-center justify-center rounded-md border border-slate-300 px-4 text-sm font-medium text-slate-800 hover:bg-slate-50">
                        Log in
                    </a>

                    @if (Route::has('register'))
                        <a href="{{ route('register') }}"
                           class="inline-flex h-8 items-center justify-center rounded-md bg-sky-700 px-4 text-sm font-medium text-white hover:bg-sky-800">
                            Register
                        </a>
                    @endif
                @endauth
            </nav>
        @endif
    </header>

    <main class="min-h-screen bg-white">
        <!-- Hero -->
        <section class="border-b border-slate-300">
            <div class="container mx-auto max-w-4xl px-4 py-16 md:py-24">
                <div class="space-y-6 text-center">
                    <h1 class="text-balance text-4xl font-bold tracking-tight text-slate-800 md:text-5xl lg:text-6xl">
                        EU-compliant invoicing with VAT correctness built in
                    </h1>
                    <p class="mx-auto max-w-2xl text-pretty text-lg text-slate-600 md:text-xl">
                        Create invoices, validate VAT numbers, and reduce EU VAT mistakes.
                    </p>

                    <div class="flex flex-col items-center justify-center gap-4 pt-4 sm:flex-row">
                        <div class="flex flex-col items-center gap-2">
                            <a href="#pricing"
                               class="inline-flex h-11 items-center justify-center rounded-md bg-sky-700 px-8 text-sm font-medium text-white transition-colors hover:bg-sky-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-700 focus-visible:ring-offset-2"
                               data-cta="hero-pro"> Start for €39 / month </a>
                            <p class="text-xs text-slate-600">Cancel anytime • 14-day no-questions refund</p>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <a href="#pricing"
                               class="inline-flex h-11 items-center justify-center rounded-md border border-slate-300 bg-transparent px-8 text-sm font-medium text-slate-800 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300 focus-visible:ring-offset-2"
                            >View pricing</a>
                            <p class="text-xs text-slate-600">&nbsp;</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Value props -->
        <section class="border-b border-slate-300">
            <div class="container mx-auto px-4 py-16 md:py-20">
                <div class="mx-auto grid max-w-5xl gap-8 md:grid-cols-3">
                    <div class="space-y-3">
                        <h3 class="text-xl font-semibold text-slate-800">VAT-correct invoices</h3>
                        <p class="leading-relaxed text-slate-600">Apply the right VAT treatment for every EU
                            cross-border transaction automatically.</p>
                    </div>
                    <div class="space-y-3">
                        <h3 class="text-xl font-semibold text-slate-800">VIES VAT number validation</h3>
                        <p class="leading-relaxed text-slate-600">Verify customer VAT numbers against the official EU
                            VIES database when available.</p>
                    </div>
                    <div class="space-y-3">
                        <h3 class="text-xl font-semibold text-slate-800">Built for EU cross-border freelancers</h3>
                        <p class="leading-relaxed text-slate-600">Designed specifically for solo professionals
                            navigating EU VAT regulations.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing -->
        <section id="pricing" class="border-b border-slate-300">
            <div class="container mx-auto px-4 py-16 md:py-20">
                <div class="mb-12 text-center">
                    <h2 class="mb-4 text-3xl font-bold text-slate-800 md:text-4xl">Simple, transparent pricing</h2>
                    <p class="text-lg text-slate-600">Choose the plan that fits your business needs</p>
                </div>

                <!-- Billing toggle -->
                <div class="mb-10 flex justify-center">
                    <div class="inline-flex items-center gap-1 rounded-lg bg-slate-100 p-1">
                        <button
                            type="button"
                            class="rounded-md px-6 py-2 text-sm font-medium transition-colors bg-sky-700 text-white"
                            data-billing="monthly"
                            aria-pressed="true"
                        >
                            Monthly
                        </button>
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-md bg-transparent px-6 py-2 text-sm font-medium text-slate-600 transition-colors hover:text-slate-800"
                            data-billing="yearly"
                            aria-pressed="false"
                        >
                            Yearly <span class="text-xs" data-yearly-note hidden>2 months free</span>
                        </button>
                    </div>
                </div>

                <div class="mx-auto grid max-w-6xl gap-6 md:grid-cols-3">
                    <!-- Starter -->
                    <article class="flex flex-col rounded-lg border border-slate-300 bg-white">
                        <div class="flex flex-1 flex-col p-6">
                            <header>
                                <h3 class="text-2xl font-semibold text-slate-800">Starter</h3>
                                <p class="mt-3 text-3xl font-bold text-slate-800">
                                    <span data-price="starter">€19</span>
                                    <span class="text-base font-normal text-slate-600"> / <span data-period>month</span></span>
                                </p>
                            </header>

                            <ul class="mt-6 space-y-3">
                                <li class="flex gap-3 text-sm text-slate-600"><span
                                        class="mt-0.5 h-5 w-5 shrink-0 rounded-full border border-slate-300"></span>1
                                    company
                                </li>
                                <li class="flex gap-3 text-sm text-slate-600"><span
                                        class="mt-0.5 h-5 w-5 shrink-0 rounded-full border border-slate-300"></span>1
                                    base currency
                                </li>
                                <li class="flex gap-3 text-sm text-slate-600"><span
                                        class="mt-0.5 h-5 w-5 shrink-0 rounded-full border border-slate-300"></span>EU
                                    invoice templates
                                </li>
                                <li class="flex gap-3 text-sm text-slate-600"><span
                                        class="mt-0.5 h-5 w-5 shrink-0 rounded-full border border-slate-300"></span>VAT
                                    ID format check
                                </li>
                                <li class="flex gap-3 text-sm text-slate-600"><span
                                        class="mt-0.5 h-5 w-5 shrink-0 rounded-full border border-slate-300"></span>Manual
                                    VAT rates
                                </li>
                                <li class="flex gap-3 text-sm text-slate-600"><span
                                        class="mt-0.5 h-5 w-5 shrink-0 rounded-full border border-slate-300"></span>PDF
                                    export
                                </li>
                            </ul>

                            <footer class="mt-8">
                                <a
                                    class="inline-flex h-11 w-full items-center justify-center rounded-md border border-slate-300 bg-transparent px-4 text-sm font-medium text-slate-800 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300 focus-visible:ring-offset-2"
                                    href="/register?plan=starter"
                                    data-plan-link="starter"
                                >
                                    Start for <span class="mx-1">€</span><span data-cta-price="starter">19</span> /
                                    <span class="ml-1" data-period>month</span>
                                </a>
                                <p class="mt-2 text-center text-xs text-slate-600">Cancel anytime • 14-day no-questions
                                    refund</p>
                            </footer>
                        </div>
                    </article>

                    <!-- Pro -->
                    <article class="relative flex flex-col rounded-lg border-2 border-sky-700 bg-white shadow-lg">
                        <div class="flex flex-1 flex-col p-6">
                            <div class="absolute -top-4 left-1/2 -translate-x-1/2">
                                <span class="rounded-full bg-sky-700 px-4 py-1 text-sm font-medium text-white">Recommended</span>
                            </div>

                            <header>
                                <h3 class="text-2xl font-semibold text-slate-800">Pro</h3>
                                <p class="mt-3 text-3xl font-bold text-sky-700">
                                    <span data-price="pro">€39</span>
                                    <span class="text-base font-normal text-slate-600"> / <span data-period>month</span></span>
                                </p>
                            </header>

                            <p class="mb-4 mt-6 text-sm font-medium text-slate-800">Everything in Starter, plus:</p>
                            <ul class="space-y-3">
                                <li class="flex gap-3 text-sm text-slate-600"><span
                                        class="mt-0.5 h-5 w-5 shrink-0 rounded-full border border-slate-300"></span>VIES
                                    VAT validation
                                </li>
                                <li class="flex gap-3 text-sm text-slate-600"><span
                                        class="mt-0.5 h-5 w-5 shrink-0 rounded-full border border-slate-300"></span>Automatic
                                    VAT rate detection
                                </li>
                                <li class="flex gap-3 text-sm text-slate-600"><span
                                        class="mt-0.5 h-5 w-5 shrink-0 rounded-full border border-slate-300"></span>Cross-border
                                    EU B2B logic
                                </li>
                                <li class="flex gap-3 text-sm text-slate-600"><span
                                        class="mt-0.5 h-5 w-5 shrink-0 rounded-full border border-slate-300"></span>Invoice
                                    numbering &amp; prefixes
                                </li>
                                <li class="flex gap-3 text-sm text-slate-600"><span
                                        class="mt-0.5 h-5 w-5 shrink-0 rounded-full border border-slate-300"></span>Accountant-ready
                                    exports
                                </li>
                            </ul>

                            <footer class="mt-8">
                                <a
                                    class="inline-flex h-11 w-full items-center justify-center rounded-md bg-sky-700 px-4 text-sm font-medium text-white transition-colors hover:bg-sky-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-700 focus-visible:ring-offset-2"
                                    href="/register?plan=pro"
                                    data-plan-link="pro"
                                >
                                    Start for <span class="mx-1">€</span><span data-cta-price="pro">39</span> / <span
                                        class="ml-1" data-period>month</span>
                                </a>
                                <p class="mt-2 text-center text-xs text-slate-600">Cancel anytime • 14-day no-questions
                                    refund</p>
                            </footer>
                        </div>
                    </article>

                    <!-- Business -->
                    <article class="flex flex-col rounded-lg border border-slate-300 bg-white">
                        <div class="flex flex-1 flex-col p-6">
                            <header>
                                <h3 class="text-2xl font-semibold text-slate-800">Business</h3>
                                <p class="mt-3 text-3xl font-bold text-slate-800">
                                    <span data-price="business">€79</span>
                                    <span class="text-base font-normal text-slate-600"> / <span data-period>month</span></span>
                                </p>
                            </header>

                            <p class="mb-4 mt-6 text-sm font-medium text-slate-800">Everything in Pro, plus:</p>
                            <ul class="space-y-3">
                                <li class="flex gap-3 text-sm text-slate-600"><span
                                        class="mt-0.5 h-5 w-5 shrink-0 rounded-full border border-slate-300"></span>Priority
                                    support
                                </li>
                                <li class="flex gap-3 text-sm text-slate-600"><span
                                        class="mt-0.5 h-5 w-5 shrink-0 rounded-full border border-slate-300"></span>Audit
                                    trail (planned)
                                </li>
                                <li class="flex gap-3 text-sm text-slate-600"><span
                                        class="mt-0.5 h-5 w-5 shrink-0 rounded-full border border-slate-300"></span>Peppol
                                    e-invoicing (coming soon)
                                </li>
                            </ul>

                            <footer class="mt-8 space-y-2">
                                <a
                                    class="inline-flex h-11 w-full items-center justify-center rounded-md border border-slate-300 bg-transparent px-4 text-sm font-medium text-slate-800 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300 focus-visible:ring-offset-2"
                                    href="/register?plan=business"
                                    data-plan-link="business"
                                >
                                    Start for <span class="mx-1">€</span><span data-cta-price="business">79</span> /
                                    <span class="ml-1" data-period>month</span>
                                </a>
                                <p class="text-center text-xs text-slate-600">Cancel anytime • 14-day no-questions
                                    refund</p>
                                <p class="text-center text-xs text-slate-600">Planned features. Availability may vary by
                                    country.</p>
                            </footer>
                        </div>
                    </article>
                </div>

                <div class="mt-8 text-center">
                    <p class="text-sm text-slate-600">No free trial. 14-day no-questions refund on your first
                        payment.</p>
                </div>
            </div>
        </section>

        <!-- Trust -->
        <section class="border-b border-slate-300">
            <div class="container mx-auto px-4 py-16 md:py-20">
                <div class="mx-auto max-w-3xl">
                    <h2 class="mb-12 text-center text-2xl font-bold text-slate-800 md:text-3xl">Why Invoxo</h2>
                    <div class="grid gap-8 sm:grid-cols-2">
                        <div class="space-y-2">
                            <h3 class="text-lg font-semibold text-slate-800">Built for EU VAT rules</h3>
                            <p class="text-sm leading-relaxed text-slate-600">Designed around EU cross-border invoicing
                                requirements and VAT regulations from day one.</p>
                        </div>
                        <div class="space-y-2">
                            <h3 class="text-lg font-semibold text-slate-800">Uses official VIES validation</h3>
                            <p class="text-sm leading-relaxed text-slate-600">Connect to the EU VAT validation service
                                for verification when available.</p>
                        </div>
                        <div class="space-y-2">
                            <h3 class="text-lg font-semibold text-slate-800">Single company, single base currency</h3>
                            <p class="text-sm leading-relaxed text-slate-600">Focused on solo freelancers and
                                professionals who need simplicity, not complexity.</p>
                        </div>
                        <div class="space-y-2">
                            <h3 class="text-lg font-semibold text-slate-800">No full accounting, just invoicing done
                                right</h3>
                            <p class="text-sm leading-relaxed text-slate-600">No bookkeeping, expenses, or tax filing.
                                Exports are designed to work with your accountant.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ (no component library; semantic) -->
        <section class="border-b border-slate-300">
            <div class="container mx-auto px-4 py-16 md:py-20">
                <div class="mx-auto max-w-2xl">
                    <h2 class="mb-10 text-center text-2xl font-bold text-slate-800 md:text-3xl">Frequently asked
                        questions</h2>

                    <div class="divide-y divide-slate-300 rounded-lg border border-slate-300">
                        <details class="group p-5">
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between text-left font-medium text-slate-800">
                                Do I need OSS?
                                <span class="ml-4 text-slate-600 group-open:rotate-180 transition-transform">⌄</span>
                            </summary>
                            <div class="mt-3 text-sm leading-relaxed text-slate-600">
                                OSS typically applies to certain B2C cross-border sales. B2B invoicing often relies on
                                reverse charge. Invoxo helps apply the correct treatment based on context.
                            </div>
                        </details>

                        <details class="group p-5">
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between text-left font-medium text-slate-800">
                                What happens if VIES is unavailable?
                                <span class="ml-4 text-slate-600 group-open:rotate-180 transition-transform">⌄</span>
                            </summary>
                            <div class="mt-3 text-sm leading-relaxed text-slate-600">
                                VIES downtime happens. You can still invoice using format checks and re-validate later.
                                Keep records of validation attempts for compliance.
                            </div>
                        </details>

                        <details class="group p-5">
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between text-left font-medium text-slate-800">
                                Is this a full accounting app?
                                <span class="ml-4 text-slate-600 group-open:rotate-180 transition-transform">⌄</span>
                            </summary>
                            <div class="mt-3 text-sm leading-relaxed text-slate-600">
                                No. Invoxo focuses on invoicing and VAT correctness. Accounting and tax filing stay with
                                your accountant or external tools.
                            </div>
                        </details>

                        <details class="group p-5">
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between text-left font-medium text-slate-800">
                                Is there a free trial or refund policy?
                                <span class="ml-4 text-slate-600 group-open:rotate-180 transition-transform">⌄</span>
                            </summary>
                            <div class="mt-3 text-sm leading-relaxed text-slate-600">
                                There is no free trial. Cancel within 14 days of your first payment for a full,
                                no-questions refund.
                            </div>
                        </details>

                        <details class="group p-5">
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between text-left font-medium text-slate-800">
                                Can I change plans later?
                                <span class="ml-4 text-slate-600 group-open:rotate-180 transition-transform">⌄</span>
                            </summary>
                            <div class="mt-3 text-sm leading-relaxed text-slate-600">
                                Yes. Upgrade anytime for immediate access. Downgrades take effect next billing cycle.
                            </div>
                        </details>
                    </div>
                </div>
            </div>
        </section>

        <!-- Final CTA -->
        <section>
            <div class="container mx-auto px-4 py-16 md:py-24">
                <div class="mx-auto max-w-2xl space-y-6 text-center">
                    <h2 class="text-balance text-3xl font-bold text-slate-800 md:text-4xl">Invoice with confidence
                        across the EU</h2>
                    <div class="flex flex-col items-center gap-2">
                        <a
                            href="#pricing"
                            class="inline-flex h-11 items-center justify-center rounded-md bg-sky-700 px-8 text-sm font-medium text-white transition-colors hover:bg-sky-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-700 focus-visible:ring-offset-2"
                            data-cta="footer-pro"
                        >
                            Start for €39 / month
                        </a>
                        <p class="text-sm text-slate-600">Cancel anytime • 14-day no-questions refund</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Tiny toggle JS (vanilla) -->
        <script>
            (function () {
                const pricing = {
                    monthly: {starter: 19, pro: 39, business: 79, period: "month"},
                    yearly: {starter: 190, pro: 390, business: 790, period: "year"},
                };

                const btnMonthly = document.querySelector('[data-billing="monthly"]');
                const btnYearly = document.querySelector('[data-billing="yearly"]');
                const yearlyNote = document.querySelector("[data-yearly-note]");

                const priceEls = {
                    starter: document.querySelector('[data-price="starter"]'),
                    pro: document.querySelector('[data-price="pro"]'),
                    business: document.querySelector('[data-price="business"]'),
                };

                const ctaPriceEls = {
                    starter: document.querySelector('[data-cta-price="starter"]'),
                    pro: document.querySelector('[data-cta-price="pro"]'),
                    business: document.querySelector('[data-cta-price="business"]'),
                };

                const periodEls = Array.from(document.querySelectorAll("[data-period]"));
                const heroCta = document.querySelector('[data-cta="hero-pro"]');
                const footerCta = document.querySelector('[data-cta="footer-pro"]');

                function setActive(activeBtn, inactiveBtn) {
                    activeBtn.classList.add("bg-sky-700", "text-white");
                    activeBtn.classList.remove("text-slate-600", "hover:text-slate-800", "bg-transparent");
                    activeBtn.setAttribute("aria-pressed", "true");

                    inactiveBtn.classList.remove("bg-sky-700", "text-white");
                    inactiveBtn.classList.add("text-slate-600", "hover:text-slate-800", "bg-transparent");
                    inactiveBtn.setAttribute("aria-pressed", "false");
                }

                function apply(periodKey) {
                    const p = pricing[periodKey];

                    priceEls.starter.textContent = "€" + p.starter;
                    priceEls.pro.textContent = "€" + p.pro;
                    priceEls.business.textContent = "€" + p.business;

                    ctaPriceEls.starter.textContent = String(p.starter);
                    ctaPriceEls.pro.textContent = String(p.pro);
                    ctaPriceEls.business.textContent = String(p.business);

                    periodEls.forEach((el) => (el.textContent = p.period));

                    if (heroCta) heroCta.textContent = "Start for €" + p.pro + " / " + p.period;
                    if (footerCta) footerCta.textContent = "Start for €" + p.pro + " / " + p.period;

                    if (yearlyNote) yearlyNote.hidden = periodKey !== "yearly";
                }

                btnMonthly.addEventListener("click", function () {
                    setActive(btnMonthly, btnYearly);
                    apply("monthly");
                });

                btnYearly.addEventListener("click", function () {
                    setActive(btnYearly, btnMonthly);
                    apply("yearly");
                });

                setActive(btnMonthly, btnYearly);
                apply("monthly");
            })();
        </script>
    </main>


</div>

@if (Route::has('login'))
    <div class="h-14.5 hidden lg:block"></div>
@endif
</body>
</html>
