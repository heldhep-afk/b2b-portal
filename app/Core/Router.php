<?php
class Router
{
    private array $routes = [];

    public function add(string $method, string $path, string $controller, string $action): void
    {
        $this->routes[strtoupper($method)][$path] = [
            'controller' => $controller,
            'action' => $action,
        ];
    }

    public function dispatch(string $uri, string $method): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';
        $method = strtoupper($method);

        if (isset($this->routes[$method][$path])) {
            $route = $this->routes[$method][$path];
            $controllerFile = BASE_PATH . '/app/Controllers/' . $route['controller'] . '.php';

            if (!is_file($controllerFile)) {
                http_response_code(500);
                exit('Контроллер не найден.');
            }

            require_once $controllerFile;
            $controllerClass = $route['controller'];

            if (!class_exists($controllerClass)) {
                http_response_code(500);
                exit('Класс контроллера не найден.');
            }

            $controller = new $controllerClass($GLOBALS['pdo']);
            $action = $route['action'];

            if (!method_exists($controller, $action)) {
                http_response_code(500);
                exit('Метод контроллера не найден.');
            }

            $controller->$action();
            return;
        }

        // Несуществующий адрес или неподходящий метод (например, GET на POST-маршрут).
        http_response_code(404);
        require BASE_PATH . '/views/404.php';
        exit;
    }
}
