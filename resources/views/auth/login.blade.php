<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in &mdash; Treklite Outdoor</title>
    <style>
        :root {
            --green-900: #0f3a1f;
            --green-800: #14472a;
            --green-700: #1e5b31;
            --green-600: #26753f;
            --green-100: #e6f2ea;
            --ink: #1b2a21;
            --muted: #66756c;
            --line: #d9e3dc;
            --sand: #f6f3ec;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--ink);
            background: var(--sand);
            -webkit-font-smoothing: antialiased;
        }
        svg.i { width: 1em; height: 1em; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; flex: none; }

        .page { min-height: 100vh; display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr); }

        /* ---------- Left: about the system ---------- */
        .hero {
            position: relative; overflow: hidden; color: #fff;
            background: linear-gradient(160deg, var(--green-900) 0%, var(--green-700) 60%, #2c8a4b 100%);
            padding: 48px 56px 0; display: flex; flex-direction: column;
        }
        .brand { display: flex; align-items: center; gap: 12px; position: relative; z-index: 2; }
        .brand-mark { width: 44px; height: 44px; border-radius: 12px; background: rgba(255,255,255,.14); display: grid; place-items: center; border: 1px solid rgba(255,255,255,.25); }
        .brand-mark svg { width: 26px; height: 26px; }
        .brand-name { font-weight: 700; font-size: 1.15rem; letter-spacing: .2px; line-height: 1.1; }
        .brand-sub { font-size: .78rem; opacity: .8; letter-spacing: .6px; text-transform: uppercase; }

        .hero-body { position: relative; z-index: 2; margin-top: 44px; max-width: 580px; }
        .hero h1 { font-size: clamp(1.7rem, 2.6vw, 2.4rem); line-height: 1.15; margin: 0 0 12px; font-weight: 800; letter-spacing: -.3px; }
        .hero h1 span { color: #b9f0c9; }
        .lead { margin: 0 0 28px; font-size: 1rem; line-height: 1.55; color: rgba(255,255,255,.88); }

        .features { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .feature {
            background: rgba(255,255,255,.09); border: 1px solid rgba(255,255,255,.16); border-radius: 14px;
            padding: 14px 16px; display: flex; gap: 12px; backdrop-filter: blur(2px);
            opacity: 0; transform: translateY(10px); animation: rise .6s ease forwards;
        }
        .feature:nth-child(1) { animation-delay: .05s; } .feature:nth-child(2) { animation-delay: .12s; }
        .feature:nth-child(3) { animation-delay: .19s; } .feature:nth-child(4) { animation-delay: .26s; }
        .feature:nth-child(5) { animation-delay: .33s; } .feature:nth-child(6) { animation-delay: .40s; }
        .feature .ico { width: 36px; height: 36px; border-radius: 10px; background: rgba(185,240,201,.18); color: #b9f0c9; display: grid; place-items: center; font-size: 1.1rem; flex: none; }
        .feature h3 { margin: 0 0 3px; font-size: .95rem; font-weight: 700; }
        .feature p { margin: 0; font-size: .82rem; line-height: 1.45; color: rgba(255,255,255,.8); }

        .roles { margin-top: 22px; display: flex; flex-wrap: wrap; gap: 10px; }
        .role { font-size: .8rem; padding: 7px 12px; border-radius: 999px; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2); display: inline-flex; gap: 7px; align-items: center; }
        .role b { font-weight: 700; color: #b9f0c9; }

        .mountains { position: relative; z-index: 1; margin: auto -56px 0; line-height: 0; padding-top: 28px; }
        .mountains svg { width: 100%; height: auto; display: block; }

        /* ---------- Right: sign in ---------- */
        .panel { display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 40px 28px; }
        .card { width: 100%; max-width: 410px; }
        .mobile-brand { display: none; }
        .card h2 { margin: 0 0 6px; font-size: 1.8rem; font-weight: 800; letter-spacing: -.3px; }
        .card .sub { margin: 0 0 26px; color: var(--muted); font-size: .95rem; }

        .alert { border-radius: 10px; padding: 11px 14px; font-size: .88rem; margin-bottom: 16px; display: flex; gap: 9px; align-items: flex-start; }
        .alert-ok { background: #e7f6ec; color: #17602e; border: 1px solid #b8e0c4; }
        .alert-err { background: #fdecec; color: #9b1c1c; border: 1px solid #f3c1c1; }

        .field { margin-bottom: 18px; }
        .field label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: 6px; }
        .input { position: relative; }
        .input .lead-ico { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #8aa093; font-size: 1.05rem; pointer-events: none; }
        .input input {
            width: 100%; height: 48px; border: 1.5px solid var(--line); border-radius: 12px; background: #fff;
            padding: 0 44px 0 42px; font-size: .98rem; color: var(--ink); outline: none; transition: border-color .15s, box-shadow .15s;
        }
        .input input:focus { border-color: var(--green-600); box-shadow: 0 0 0 4px rgba(38,117,63,.15); }
        .input input.bad { border-color: #d64545; }
        .toggle { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); width: 36px; height: 36px; border: 0; background: transparent; color: #7d9487; border-radius: 8px; cursor: pointer; font-size: 1.1rem; display: grid; place-items: center; }
        .toggle:hover { background: var(--green-100); color: var(--green-700); }

        .row { display: flex; align-items: center; justify-content: space-between; margin: 4px 0 22px; font-size: .88rem; }
        .check { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; color: var(--muted); }
        .check input { width: 17px; height: 17px; accent-color: var(--green-700); }

        .btn {
            width: 100%; height: 50px; border: 0; border-radius: 12px; cursor: pointer; font-size: 1rem; font-weight: 700; color: #fff;
            background: linear-gradient(180deg, var(--green-600), var(--green-700)); box-shadow: 0 6px 16px rgba(30,91,49,.28);
            display: inline-flex; align-items: center; justify-content: center; gap: 10px; transition: transform .1s, box-shadow .15s, filter .15s;
        }
        .btn:hover { filter: brightness(1.06); box-shadow: 0 8px 20px rgba(30,91,49,.34); }
        .btn:active { transform: translateY(1px); }
        .btn[disabled] { cursor: wait; filter: saturate(.7); }
        .spin { width: 18px; height: 18px; border-radius: 50%; border: 2.5px solid rgba(255,255,255,.4); border-top-color: #fff; display: none; animation: turn .8s linear infinite; }
        .btn.loading .spin { display: inline-block; }

        .help { margin-top: 22px; text-align: center; font-size: .85rem; color: var(--muted); }
        .foot { margin-top: 34px; text-align: center; font-size: .78rem; color: #8c9a92; }

        @keyframes rise { to { opacity: 1; transform: none; } }
        @keyframes turn { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .feature { animation: none; opacity: 1; transform: none; } .spin { animation-duration: 2s; } }

        /* ---------- Tablet & phone ---------- */
        @media (max-width: 991px) {
            .page { grid-template-columns: 1fr; }
            .panel { order: 1; padding: 28px 20px 8px; }
            .hero { order: 2; padding: 36px 24px 0; }
            .hero .brand { display: none; }
            .hero-body { margin-top: 0; max-width: none; }
            .mobile-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 22px; color: var(--green-700); }
            .mobile-brand .brand-mark { background: var(--green-100); border-color: var(--line); }
            .mobile-brand .brand-mark svg { color: var(--green-700); }
            .mobile-brand .brand-sub { color: var(--muted); opacity: 1; }
            .mountains { margin: 24px -24px 0; }
        }
        @media (max-width: 560px) {
            .features { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
@php
    // Shared mountain / tent mark used in the brand badges
    $mark = '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 27 12 8l6 11 3-5 9 13z"/><path d="M12 8l2 4"/></svg>';
@endphp

<div class="page">

    {{-- ======== About the system ======== --}}
    <section class="hero" aria-label="About Treklite Outdoor Sales and Inventory System">
        <div class="brand">
            <div class="brand-mark" style="color:#fff">{!! $mark !!}</div>
            <div>
                <div class="brand-name">Treklite Outdoor</div>
                <div class="brand-sub">Sales &amp; Inventory System</div>
            </div>
        </div>

        <div class="hero-body">
            <h1>Run your outdoor store with <span>confidence</span>.</h1>
            <p class="lead">One place for the store&rsquo;s sales, stock and reports &mdash; built for the daily work of selling shoes, sandals and outdoor gear.</p>

            <ul class="features">
                <li class="feature">
                    <span class="ico"><svg class="i" viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg></span>
                    <div><h3>Fast point of sale</h3><p>Ring up a sale and issue an invoice. Pay by cash, GCash or bank transfer, with a photo of the payment.</p></div>
                </li>
                <li class="feature">
                    <span class="ico"><svg class="i" viewBox="0 0 24 24"><path d="M16.5 9.4l-9-5.19M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></span>
                    <div><h3>Stock by size and color</h3><p>Every variant keeps its own quantity. Deliveries and physical counts are saved in a stock history.</p></div>
                </li>
                <li class="feature">
                    <span class="ico"><svg class="i" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></span>
                    <div><h3>Low-stock alerts</h3><p>The dashboard shows what is running low, so you can restock before an item runs out.</p></div>
                </li>
                <li class="feature">
                    <span class="ico"><svg class="i" viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></span>
                    <div><h3>Organized products</h3><p>Items are grouped by brand and category, with sizes, colors and product photos.</p></div>
                </li>
                <li class="feature">
                    <span class="ico"><svg class="i" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>
                    <div><h3>Reports you can filter</h3><p>Look back at sales and inventory by date, cashier or product, and export them when needed.</p></div>
                </li>
                <li class="feature">
                    <span class="ico"><svg class="i" viewBox="0 0 24 24"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg></span>
                    <div><h3>Safe and traceable</h3><p>Voided sales need a reason, important actions are logged, and backups keep the saved photos.</p></div>
                </li>
            </ul>

            <div class="roles">
                <span class="role"><svg class="i" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><span><b>Owner</b> &mdash; full access</span></span>
                <span class="role"><svg class="i" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><span><b>Staff</b> &mdash; sales and stock check</span></span>
            </div>
        </div>

        <div class="mountains" aria-hidden="true">
            <svg viewBox="0 0 800 150" preserveAspectRatio="none">
                <path d="M0 150V92l90-58 70 46 95-72 110 84 85-50 120 80 90-52 140 70v80z" fill="#0f3a1f" opacity=".55"/>
                <path d="M0 150v-40l70-30 80 38 100-52 90 54 90-36 110 56 80-40 180 50v50z" fill="#0b2d18"/>
            </svg>
        </div>
    </section>

    {{-- ======== Sign in ======== --}}
    <main class="panel">
        <div class="card">
            <div class="mobile-brand">
                <div class="brand-mark">{!! $mark !!}</div>
                <div>
                    <div class="brand-name" style="color:var(--ink)">Treklite Outdoor</div>
                    <div class="brand-sub">Sales &amp; Inventory System</div>
                </div>
            </div>

            <h2>Welcome back</h2>
            <p class="sub">Sign in to continue to your dashboard.</p>

            @if (session('status'))
                <div class="alert alert-ok">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-err" role="alert">
                    <svg class="i" viewBox="0 0 24 24" style="margin-top:2px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" id="login-form" novalidate>
                @csrf

                <div class="field">
                    <label for="email">Email</label>
                    <div class="input">
                        <svg class="i lead-ico" viewBox="0 0 24 24"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" class="@error('email') bad @enderror" placeholder="you@example.com" autocomplete="username" required autofocus>
                    </div>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="input">
                        <svg class="i lead-ico" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                        <button type="button" class="toggle" id="toggle-pw" aria-label="Show password" title="Show password">
                            <svg class="i" id="eye" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="row">
                    <label class="check"><input type="checkbox" name="remember" id="remember"> Keep me signed in</label>
                </div>

                <button class="btn" id="submit-btn" type="submit">
                    <span class="spin"></span><span id="btn-text">Log in</span>
                </button>
            </form>

            <p class="help">Trouble signing in? Ask the store Owner to check your account.</p>
            <p class="foot">&copy; {{ date('Y') }} Treklite Outdoor &middot; Sales &amp; Inventory System</p>
        </div>
    </main>
</div>

<script>
    (function () {
        var pw = document.getElementById('password');
        var toggle = document.getElementById('toggle-pw');
        var eye = document.getElementById('eye');
        var shown = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
        var hidden = eye.innerHTML;

        toggle.addEventListener('click', function () {
            var show = pw.type === 'password';
            pw.type = show ? 'text' : 'password';
            eye.innerHTML = show ? shown : hidden;
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            toggle.title = show ? 'Hide password' : 'Show password';
        });

        // Shows a "Signing in..." state, because the first request can be slow while the server wakes up.
        var form = document.getElementById('login-form');
        var btn = document.getElementById('submit-btn');
        form.addEventListener('submit', function () {
            if (!form.email.value || !form.password.value) { return; }
            btn.classList.add('loading');
            btn.disabled = true;
            document.getElementById('btn-text').textContent = 'Signing in...';
        });
    })();
</script>
</body>
</html>
