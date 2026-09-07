<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Container;

final class Router
{
    /** @var array<string, array<string, array{handler: array, middleware: array}>> */
    private array $routes = [];

    /** @var string[] */
    private array $groupMiddleware = [];

    private string $groupPrefix = '';

    public function __construct(
        private readonly Container $container,
    ) {
    }

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix = $previousPrefix . $prefix;
        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    public function dispatch(Request $request): Response
    {
        $path = rtrim($request->path, '/') ?: '/';

        foreach ($this->routes[$request->method] ?? [] as $pattern => $route) {
            $params = $this->match($pattern, $path);

            if ($params !== null) {
                return $this->executeRoute($route, $request, $params);
            }
        }

        return Response::notFound('Route not found.');
    }

    private function addRoute(string $method, string $path, array $handler, array $middleware): void
    {
        $fullPath = $this->groupPrefix . $path;
        $fullPath = rtrim($fullPath, '/') ?: '/';
        $allMiddleware = array_merge($this->groupMiddleware, $middleware);

        $this->routes[$method][$fullPath] = [
            'handler' => $handler,
            'middleware' => $allMiddleware,
        ];
    }

    private function match(string $pattern, string $path): ?array
    {
        $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches)) {
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }

        return null;
    }

    private function executeRoute(array $route, Request $request, array $params): Response
    {
        // Run middleware
        foreach ($route['middleware'] as $middleware) {
            $instance = is_string($middleware) ? $this->container->get($middleware) : $middleware;

            $result = $instance->handle($request);

            if ($result instanceof Response) {
                return $result;
            }
        }

        [$controllerClass, $method] = $route['handler'];

        $controller = $this->container->get($controllerClass);

        return $controller->$method($request, $params);
    }
}
