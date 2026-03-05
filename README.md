<p align="center">
  <a href="https://packagist.org/packages/wpmvc/container"><img src="https://img.shields.io/packagist/dt/wpmvc/container" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/wpmvc/container"><img src="https://img.shields.io/packagist/v/wpmvc/container" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/wpmvc/container"><img src="https://img.shields.io/packagist/l/wpmvc/container" alt="License"></a>
</p>

# WpMVC Container

A lightweight, powerful, and **PSR-11 compliant** Dependency Injection (DI) Container designed for the WpMVC framework. It provides automatic dependency resolution, singleton management, flexible method invocation, and advanced autowiring capabilities.

## Installation

```bash
composer require wpmvc/container
```

## Features

- **Zero Configuration**: Automatically resolves dependencies using PHP Reflection.
- **Singleton by Default**: Maintains single instances of services unless bound otherwise.
- **Flexible Bindings**: Explicitly register transient, shared, or existing instances.
- **Aliasing & Tagging**: Group and reference services using flexible identifiers.
- **Advanced Autowiring**: Supports positional/variadic parameters, nullable types, and default values.
- **Method Injection**: Supports DI for method calls via `call()`, including static methods and invokables.
- **Circular Dependency Detection**: Prevents infinite loops with clear exception reporting.
- **Fluent Interface**: Supports method chaining for configuration.
- **PSR-11 Compatible**: Implements `Psr\Container\ContainerInterface`.

## Usage

### Basic Instantiation

```php
use WpMVC\Container;

$container = new Container();
```

### Service Registration

By default, any class requested via `get()` is treated as a **singleton**. However, you can explicitly define how services are resolved.

#### Transient Bindings
Creates a fresh instance every time it is resolved.
```php
$container->bind(MyService::class, MyService::class);
```

#### Shared Bindings (Singletons)
Ensures only one instance exists within the container.
```php
$container->singleton(MyLogger::class, MyLogger::class);
```

#### Closure Bindings
Use a closure for complex instantiation logic. The container is passed as the first argument.
```php
$container->bind(Mailer::class, function($container, $params) {
    return new Mailer($container->get(Config::class), $params['transport'] ?? 'smtp');
});
```

#### Interface Binding
Bind an interface to a concrete implementation.
```php
$container->singleton(UserRepositoryInterface::class, MysqlUserRepository::class);

// The container will automatically resolve the concrete when the interface is requested.
$repo = $container->get(UserRepositoryInterface::class);
```

#### Array Callback Bindings
Bind an abstract to a static method or class method callback.
```php
$container->bind('api.client', [ApiClientFactory::class, 'create']);
```

#### Primitive Bindings
The container can store and retrieve raw scalars or arrays.
```php
$container->set('database.config', ['host' => 'localhost', 'user' => 'root']);
$container->set('app.version', '1.0.0');
```

#### Instance Binding
Register an already-instantiated object.
```php
$container->set(Configuration::class, new Configuration(['debug' => true]));
```

### Retrieving Services

The `get` method retrieves a service. Subsequent calls return the *same* instance for singletons.

```php
$service = $container->get(MyService::class);
```

> [!NOTE]
> **Tri-cache Behavior**: For shared services, the container caches the instance under the requested ID, the terminal resolved ID, and the concrete class name. This ensures consistent resolution regardless of how the service is accessed.

> [!IMPORTANT]
> Passing parameters to `get()` for an already-instantiated shared service will throw a `ContainerException`. Use `make()` if you need a fresh instance with custom parameters.

### Creating New Instances (Factory)

The `make` method always creates a *new* instance, even if it was registered as a singleton.

```php
$freshInstance = $container->make(MyService::class, ['param' => 'value']);
```

### Checking Availability (`has`)

Check if a service is available. The `has()` method follows a "Slow Path" logic:
1. Check if already instantiated.
2. Check if manually registered in the registry.
3. Check if the terminal resolved ID exists as a class (`class_exists`).

```php
if ($container->has(MyService::class)) {
    // ...
}
```

### Aliasing & Tagging

#### Aliasing
Give a service a shorter or more descriptive name. The container supports **recursive alias resolution**, meaning aliases can point to other aliases.
```php
$container->alias(UserRepository::class, 'repo.user');
$container->alias('repo.user', 'users');

$repo = $container->get('users'); // Resolves to UserRepository
```

#### Tagging
Group related services (e.g., for plugins or extensions).
```php
$container->tag([MyExtension::class, AnotherExtension::class], 'app.plugins');

// Retrieve all tagged services
$plugins = $container->tagged('app.plugins'); // Returns iterable
```

## Dependency Injection

The container uses PHP's Reflection API to inspect constructors and automatically resolve dependencies.

### Automatic Injection

```php
class UserRepository {
    public function __construct(Database $db) { ... }
}

// Automatically resolves and injects Database
$repo = $container->get(UserRepository::class);
```

### Advanced Resolution Strategies

When resolving constructor or method arguments, the container follows a strict **Precedence of Resolution**:

1.  **Named Parameters**: Explicit keys in the `$params` array matching the argument name.
2.  **Type Hint (Auto-Substitution)**: If a provided parameter in `$params` is an object matching the required type hint, it is used immediately.
3.  **Type Hint (Recursive)**: Resolves the type-hinted class/interface from the container.
4.  **Variadic Parameters**: Collects all remaining arguments from the `$params` array.
5.  **Positional Parameters**: Uses unkeyed arguments from `$params`. These are **type-guarded**; they are only used if they match the expected type hint.
6.  **Default Values & Nullable Types**: Fallback to `$param = 'default'` or `null` (if allowed).

## Advanced Method Invocation (`call`)

Execute any callable while automatically injecting its dependencies.

```php
// 1. Closures
$container->call(function(MailService $mailer) { ... });

// 2. Class methods (auto-resolves instance)
$container->call([ReportController::class, 'generate'], ['format' => 'pdf']);

// 3. Static methods
$container->call('Utility::process');

// 4. Invokable objects
$container->call(new ActionHandler());
```

## Lifecycle & State

Reset or modify the container state using the following methods:

- `$container->forget_instances()`: Clears all cached singleton instances.
- `$container->flush()`: Clears all bindings, aliases, tags, and instances.
- **Fluent Chaining**: All registration methods support method chaining.
  ```php
  $container->singleton(S1::class)->alias(S1::class, 's1')->tag('s1', 'group');
  ```

## Exceptions

The container throws specific exceptions, all of which are **PSR-11 compliant**:

- `WpMVC\Container\Exception\NotFoundException`: Implements `Psr\Container\NotFoundExceptionInterface`.
- `WpMVC\Container\Exception\CircularDependencyException`: Thrown when a resolution loop is detected.
- `WpMVC\Container\Exception\ContainerException`: Implements `Psr\Container\ContainerExceptionInterface`.
