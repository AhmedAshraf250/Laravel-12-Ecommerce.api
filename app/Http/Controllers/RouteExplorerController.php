<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class RouteExplorerController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(app()->isLocal() || config('app.debug'), 404);

        $router = app('router');
        $routeCollection = collect(Route::getRoutes())->map(function (IlluminateRoute $route) use ($router) {
            $methods = array_values(array_diff($route->methods(), ['HEAD']));
            $rawMiddleware = $route->gatherMiddleware();
            $resolvedMiddleware = $router->gatherRouteMiddleware($route);
            $action = $route->getActionName();

            return [
                'methods' => $methods,
                'method_label' => implode('|', $methods),
                'uri' => '/'.$route->uri(),
                'name' => $route->getName(),
                'domain' => $route->getDomain(),
                'action' => $action === 'Closure' ? 'Closure' : $action,
                'controller' => $this->extractController($action),
                'raw_middleware' => $rawMiddleware,
                'resolved_middleware' => $resolvedMiddleware,
                'middleware_count' => count($resolvedMiddleware),
            ];
        })->sortBy([
            ['uri', 'asc'],
            ['method_label', 'asc'],
        ])->values();

        return view('debug.routes', [
            'routes' => $routeCollection,
            'summary' => $this->buildSummary($routeCollection),
            'middlewareAliases' => collect($router->getMiddleware())->sortKeys(),
            'middlewareGroups' => collect($router->getMiddlewareGroups())->sortKeys(),
        ]);
    }

    protected function extractController(string $action): ?string
    {
        if ($action === 'Closure' || !str_contains($action, '@')) {
            return null;
        }

        return explode('@', $action)[0];
    }

    protected function buildSummary(Collection $routes): array
    {
        return [
            'total_routes' => $routes->count(),
            'web_routes' => $routes->filter(fn (array $route) => in_array('web', $route['raw_middleware'], true))->count(),
            'api_routes' => $routes->filter(fn (array $route) => in_array('api', $route['raw_middleware'], true))->count(),
            'sanctum_routes' => $routes->filter(fn (array $route) => collect($route['raw_middleware'])->contains(fn ($middleware) => str_starts_with($middleware, 'auth:sanctum')))->count(),
            'named_routes' => $routes->filter(fn (array $route) => !empty($route['name']))->count(),
        ];
    }
}
