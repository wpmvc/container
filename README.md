<p align="center">
  <a href="https://packagist.org/packages/wpmvc/container"><img src="https://img.shields.io/packagist/dt/wpmvc/container" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/wpmvc/container"><img src="https://img.shields.io/packagist/v/wpmvc/container" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/wpmvc/container"><img src="https://img.shields.io/packagist/l/wpmvc/container" alt="License"></a>
</p>

# WpMVC Container

A lightweight, powerful Dependency Injection (DI) Container designed for the WpMVC framework. It provides automatic dependency resolution, singleton management, and flexible method invocation capabilities.

## Installation

```bash
composer require wpmvc/container
```

## Features

- **Zero Configuration**: Automatically resolves dependencies using PHP Reflection.
- **Singleton Pattern**: Maintains single instances of services via `get()`.
- **Factory Pattern**: Creates fresh instances on demand via `make()`.
- **Method Injection**: Supports dependency injection for method calls via `call()`.
- **Circular Dependency Detection**: Prevents infinite loops during resolution errors.
- **Parameter Overrides**: Allows passing specific parameters to constructors and methods.
- **PSR-4 Compatible**: designed for modern PHP applications.

## Usage

### Basic Instantiation

```php
use WpMVC\Container;

$container = new Container();
```

### Retrieving Services (Singleton)

The `get` method retrieves a service. If the service has not been created yet, the container will attempt to instantiate it, resolving any dependencies automatically. Subsequent calls return the *same* instance.

```php
$service = $container->get(MyService::class);
```

### Creating New Instances (Factory)

The `make` method always creates a *new* instance of the requested class, resolving dependencies afresh.

```php
$freshInstance = $container->make(MyService::class);
```

### Manual Binding

You can manually register an existing instance into the container.

```php
$container->set(MyInterface::class, new MyConcreteImplementation());
```

### Checking Availability

Check if a service is available (either instantiated or the class exists).

```php
if ($container->has(MyService::class)) {
    // ...
}
```

## Dependency Injection

The container uses PHP's Reflection API to inspect class constructors.

### Automatic Injection

If your class looks like this:

```php
class Database { ... }

class UserRepository {
    protected $db;
    public function __construct(Database $db) {
        $this->db = $db;
    }
}
```

Calling `$container->get(UserRepository::class)` will automatically:
1. Detect `Database` dependency.
2. Resolve `Database` via `$container->get(Database::class)`.
3. Instantiate `UserRepository` with the resolved `Database` instance.

### Primitive Parameters & Overrides

You can pass an array of parameters to `get()` or `make()` to override dependencies or provide primitive values (strings, ints, etc.). Keys must match the constructor parameter names.

```php
class ApiClient {
    public function __construct(HttpClient $client, string $apiKey) { ... }
}

$client = $container->get(ApiClient::class, [
    'apiKey' => 'secret_123', // Matches $apiKey
    // 'client' => $mockClient // Optional: could also override the object dependency
]);
```

## Advanced Features

### Method Invocation (`call`)

The `call` method allows you to execute any callable (function, closure, object method) while automatically injecting its dependencies.

```php
class ReportController {
    public function generate(ReportService $service, $format = 'json') {
        return $service->build($format);
    }
}

// Automatically resolves ReportService and injects it.
// $format uses the default value 'json' unless overridden in params.
$result = $container->call([ReportController::class, 'generate'], [
    'format' => 'pdf'
]);
```

Supported callback formats:
- `'ClassName::method'` (Static calls, or auto-resolves instance for non-static)
- `[$object, 'method']`
- `[ClassName::class, 'method']`
- Closures / Anonymous functions

### Circular Dependency Detection

The container tracks currently resolving classes. If Class A depends on Class B, and Class B depends on Class A, the container will throw an `Exception` to prevent an infinite loop / stack overflow.

```
Exception: Circular dependency detected while resolving: WpMVC\ClassA
```

## Requirements

- PHP 7.4 or higher
