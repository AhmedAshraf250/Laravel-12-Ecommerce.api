<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Route Explorer</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4efe6;
            --surface: #fffdf8;
            --surface-alt: #f7f1e6;
            --border: #d8cbb5;
            --text: #1f2933;
            --muted: #667085;
            --accent: #0f766e;
            --accent-soft: #d9f3ef;
            --get: #1d4ed8;
            --post: #047857;
            --put: #b45309;
            --delete: #b91c1c;
            --patch: #7c3aed;
            --any: #475467;
            --shadow: 0 18px 48px rgba(84, 63, 38, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "IBM Plex Sans", "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at top left, rgba(15, 118, 110, 0.08), transparent 26%),
                linear-gradient(180deg, #fbf7f0 0%, var(--bg) 100%);
            color: var(--text);
        }

        .page {
            width: min(1500px, calc(100% - 32px));
            margin: 24px auto 40px;
        }

        .hero,
        .panel,
        .route-card {
            background: rgba(255, 253, 248, 0.94);
            border: 1px solid rgba(216, 203, 181, 0.9);
            border-radius: 24px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(8px);
        }

        .hero {
            padding: 24px;
            margin-bottom: 18px;
        }

        .eyebrow {
            display: inline-flex;
            padding: 6px 10px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        h1 {
            margin: 14px 0 10px;
            font-size: clamp(30px, 6vw, 50px);
            line-height: 1;
            letter-spacing: -0.04em;
        }

        .lead {
            margin: 0;
            max-width: 780px;
            color: var(--muted);
            font-size: 15px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-top: 18px;
        }

        .stat {
            padding: 16px;
            border-radius: 18px;
            background: var(--surface-alt);
            border: 1px solid var(--border);
        }

        .stat strong {
            display: block;
            font-size: 28px;
            line-height: 1;
        }

        .stat span {
            display: block;
            margin-top: 8px;
            color: var(--muted);
            font-size: 13px;
        }

        .toolbar {
            display: grid;
            grid-template-columns: 1.8fr repeat(3, minmax(140px, 1fr));
            gap: 12px;
            padding: 16px;
            margin-bottom: 18px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field label {
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .field input,
        .field select {
            width: 100%;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: white;
            font: inherit;
            color: var(--text);
        }

        .route-list {
            display: grid;
            gap: 14px;
        }

        .route-card {
            padding: 18px;
        }

        .route-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 12px;
        }

        .route-uri {
            font-family: "IBM Plex Mono", "Consolas", monospace;
            font-size: 18px;
            line-height: 1.35;
            word-break: break-word;
        }

        .route-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: #f4efe4;
            border: 1px solid var(--border);
            font-size: 12px;
            color: #4b5563;
        }

        .method {
            font-weight: 800;
            color: white;
            border-color: transparent;
        }

        .method.GET { background: var(--get); }
        .method.POST { background: var(--post); }
        .method.PUT { background: var(--put); }
        .method.PATCH { background: var(--patch); }
        .method.DELETE { background: var(--delete); }
        .method.ANY { background: var(--any); }

        .method-stack {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .detail-block {
            padding: 14px;
            border-radius: 18px;
            background: var(--surface-alt);
            border: 1px solid var(--border);
            min-width: 0;
        }

        .detail-block h3 {
            margin: 0 0 10px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
        }

        .stack {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        code {
            font-family: "IBM Plex Mono", "Consolas", monospace;
            font-size: 13px;
        }

        .inline-code {
            display: inline-block;
            max-width: 100%;
            padding: 8px 10px;
            border-radius: 12px;
            background: white;
            border: 1px solid var(--border);
            overflow-wrap: anywhere;
        }

        .footer-panels {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-top: 18px;
        }

        .panel {
            padding: 18px;
        }

        .panel h2 {
            margin: 0 0 14px;
            font-size: 18px;
        }

        .list-table {
            display: grid;
            gap: 10px;
        }

        .list-row {
            display: grid;
            grid-template-columns: minmax(120px, 220px) 1fr;
            gap: 12px;
            align-items: start;
            padding: 12px 0;
            border-top: 1px solid rgba(216, 203, 181, 0.7);
        }

        .list-row:first-child {
            border-top: 0;
            padding-top: 0;
        }

        .list-key {
            font-family: "IBM Plex Mono", "Consolas", monospace;
            font-size: 12px;
            color: var(--accent);
            overflow-wrap: anywhere;
        }

        .empty {
            padding: 28px;
            text-align: center;
            color: var(--muted);
            border: 1px dashed var(--border);
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.65);
        }

        [hidden] {
            display: none !important;
        }

        @media (max-width: 980px) {
            .toolbar,
            .details-grid,
            .footer-panels {
                grid-template-columns: 1fr;
            }

            .route-top {
                flex-direction: column;
            }

            .method-stack {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <section class="hero">
            <span class="eyebrow">Internal Route Debugger</span>
            <h1>Route Explorer</h1>
            <p class="lead">
                صفحة داخلية سريعة لتتبع كل routes في النظام مع الـ middleware الخام والـ middleware الفعلية بعد فك aliases والجروبات.
            </p>

            <div class="summary-grid">
                <div class="stat">
                    <strong>{{ $summary['total_routes'] }}</strong>
                    <span>Total Routes</span>
                </div>
                <div class="stat">
                    <strong>{{ $summary['web_routes'] }}</strong>
                    <span>Routes Using `web` Group</span>
                </div>
                <div class="stat">
                    <strong>{{ $summary['api_routes'] }}</strong>
                    <span>Routes Using `api` Group</span>
                </div>
                <div class="stat">
                    <strong>{{ $summary['sanctum_routes'] }}</strong>
                    <span>Routes Using `auth:sanctum`</span>
                </div>
                <div class="stat">
                    <strong>{{ $summary['named_routes'] }}</strong>
                    <span>Named Routes</span>
                </div>
            </div>
        </section>

        <section class="toolbar panel">
            <div class="field">
                <label for="search">Search</label>
                <input id="search" type="search" placeholder="uri, name, action, middleware...">
            </div>

            <div class="field">
                <label for="group-filter">Route Group</label>
                <select id="group-filter">
                    <option value="">All</option>
                    <option value="web">web</option>
                    <option value="api">api</option>
                    <option value="none">none</option>
                </select>
            </div>

            <div class="field">
                <label for="method-filter">HTTP Method</label>
                <select id="method-filter">
                    <option value="">All</option>
                    <option value="GET">GET</option>
                    <option value="POST">POST</option>
                    <option value="PUT">PUT</option>
                    <option value="PATCH">PATCH</option>
                    <option value="DELETE">DELETE</option>
                </select>
            </div>

            <div class="field">
                <label for="middleware-filter">Middleware Contains</label>
                <input id="middleware-filter" type="search" placeholder="auth:sanctum, permission...">
            </div>
        </section>

        <section class="route-list" id="route-list">
            @forelse ($routes as $route)
                @php
                    $searchBlob = strtolower(implode(' ', array_filter([
                        $route['uri'],
                        $route['name'],
                        $route['action'],
                        implode(' ', $route['raw_middleware']),
                        implode(' ', $route['resolved_middleware']),
                    ])));
                    $groupType = in_array('api', $route['raw_middleware'], true)
                        ? 'api'
                        : (in_array('web', $route['raw_middleware'], true) ? 'web' : 'none');
                @endphp
                <article
                    class="route-card route-item"
                    data-search="{{ $searchBlob }}"
                    data-group="{{ $groupType }}"
                    data-methods="{{ implode(',', $route['methods']) }}"
                    data-middleware="{{ strtolower(implode(' ', array_merge($route['raw_middleware'], $route['resolved_middleware']))) }}"
                >
                    <div class="route-top">
                        <div>
                            <div class="route-uri">{{ $route['uri'] }}</div>
                            <div class="route-meta">
                                @if ($route['name'])
                                    <span class="chip"><code>{{ $route['name'] }}</code></span>
                                @endif
                                @if ($route['domain'])
                                    <span class="chip">domain: <code>{{ $route['domain'] }}</code></span>
                                @endif
                                <span class="chip">{{ $route['middleware_count'] }} resolved middleware</span>
                                @if ($route['controller'])
                                    <span class="chip"><code>{{ class_basename($route['controller']) }}</code></span>
                                @endif
                            </div>
                        </div>

                        <div class="method-stack">
                            @foreach ($route['methods'] as $method)
                                <span class="chip method {{ $method }}">{{ $method }}</span>
                            @endforeach
                        </div>
                    </div>

                    <div class="details-grid">
                        <div class="detail-block">
                            <h3>Action</h3>
                            <span class="inline-code"><code>{{ $route['action'] }}</code></span>
                        </div>

                        <div class="detail-block">
                            <h3>Raw Middleware</h3>
                            <div class="stack">
                                @forelse ($route['raw_middleware'] as $middleware)
                                    <span class="chip"><code>{{ $middleware }}</code></span>
                                @empty
                                    <span class="chip">none</span>
                                @endforelse
                            </div>
                        </div>

                        <div class="detail-block">
                            <h3>Resolved Middleware</h3>
                            <div class="stack">
                                @forelse ($route['resolved_middleware'] as $middleware)
                                    <span class="chip"><code>{{ is_string($middleware) ? $middleware : 'Closure middleware' }}</code></span>
                                @empty
                                    <span class="chip">none</span>
                                @endforelse
                            </div>
                        </div>

                        <div class="detail-block">
                            <h3>Controller</h3>
                            <span class="inline-code"><code>{{ $route['controller'] ?? 'Closure / invokable action' }}</code></span>
                        </div>
                    </div>
                </article>
            @empty
                <div class="empty">No routes found.</div>
            @endforelse
        </section>

        <section class="footer-panels">
            <div class="panel">
                <h2>Middleware Aliases</h2>
                <div class="list-table">
                    @foreach ($middlewareAliases as $alias => $class)
                        <div class="list-row">
                            <div class="list-key">{{ $alias }}</div>
                            <div><code>{{ is_string($class) ? $class : 'Closure middleware' }}</code></div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="panel">
                <h2>Middleware Groups</h2>
                <div class="list-table">
                    @foreach ($middlewareGroups as $group => $middlewares)
                        <div class="list-row">
                            <div class="list-key">{{ $group }}</div>
                            <div class="stack">
                                @foreach ($middlewares as $middleware)
                                    <span class="chip"><code>{{ $middleware }}</code></span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>

    <script>
        const searchInput = document.getElementById('search');
        const groupFilter = document.getElementById('group-filter');
        const methodFilter = document.getElementById('method-filter');
        const middlewareFilter = document.getElementById('middleware-filter');
        const routeItems = [...document.querySelectorAll('.route-item')];

        function applyFilters() {
            const search = searchInput.value.trim().toLowerCase();
            const group = groupFilter.value;
            const method = methodFilter.value;
            const middlewareNeedle = middlewareFilter.value.trim().toLowerCase();

            let visible = 0;

            routeItems.forEach((item) => {
                const matchesSearch = !search || item.dataset.search.includes(search);
                const matchesGroup = !group || item.dataset.group === group;
                const matchesMethod = !method || item.dataset.methods.split(',').includes(method);
                const matchesMiddleware = !middlewareNeedle || item.dataset.middleware.includes(middlewareNeedle);

                const show = matchesSearch && matchesGroup && matchesMethod && matchesMiddleware;
                item.hidden = !show;

                if (show) {
                    visible += 1;
                }
            });

            const emptyState = document.getElementById('live-empty');

            if (visible === 0) {
                if (!emptyState) {
                    const node = document.createElement('div');
                    node.id = 'live-empty';
                    node.className = 'empty';
                    node.textContent = 'No routes match the current filters.';
                    document.getElementById('route-list').appendChild(node);
                }
            } else if (emptyState) {
                emptyState.remove();
            }
        }

        [searchInput, groupFilter, methodFilter, middlewareFilter].forEach((input) => {
            input.addEventListener('input', applyFilters);
            input.addEventListener('change', applyFilters);
        });
    </script>
</body>
</html>
