# Architecture Book

## Executive Summary

This application implements a sophisticated enterprise architecture combining Hexagonal Architecture (Ports & Adapters) with Domain-Driven Design (DDD) principles. The codebase demonstrates strict architectural boundaries enforced through automated testing, ensuring maintainability, testability, and scalability.

## Core Architectural Pattern: Hexagonal Architecture with DDD

### Hexagonal (Ports & Adapters) Architecture

The application follows a hexagonal architecture where:
- **Domain (Inner Hexagon)**: Pure business logic at the center
- **Ports (Boundaries)**: Facade interfaces define public contracts
- **Adapters (Outer Layers)**: Infrastructure and Presentation layers adapt external interactions

```
┌─────────────────────────────────────────────────┐
│                 Presentation Layer              │
│                    (Adapter)                    │
├─────────────────────────────────────────────────┤
│          ┌─────────────────────────┐           │
│          │     Facade Layer        │           │
│          │       (Port)            │           │
│          ├─────────────────────────┤           │
│          │     Domain Layer        │           │
│          │   (Inner Hexagon)       │           │
│          └─────────────────────────┘           │
├─────────────────────────────────────────────────┤
│              Infrastructure Layer               │
│                   (Adapter)                     │
└─────────────────────────────────────────────────┘
```

### Domain-Driven Design (DDD)

Each feature module represents a bounded context with:
- **Entities**: Core business objects with identity
- **Value Objects**: Immutable objects without identity (Enums)
- **Domain Services**: Business logic and rules
- **Repository Pattern**: Data access abstraction

## Feature Module Structure

Every feature follows this strict structure:

```
src/
└── FeatureName/
    ├── Domain/           # Pure business logic
    │   ├── Entity/       # Domain entities
    │   ├── Service/      # Domain services
    │   ├── Enum/         # Value objects
    │   └── Command/      # CLI commands
    ├── Facade/           # Public API (Port - Optional if feature is self-contained and not used by other features)
    │   ├── Dto/          # Transfer objects
    │   ├── Service/      # Facade implementations
    │   ├── Exception/    # Public exceptions
    │   └── *Interface.php # Public contracts
    ├── Infrastructure/   # External systems (Adapter - Optional)
    │   ├── Service/      # External adapters
    │   ├── Dto/          # Infrastructure DTOs
    │   └── *Interface.php # Port definitions
    ├── Api/              # RESTful HTTP API (Adapter - Optional)
    │   ├── Controller/   # API controllers
    │   ├── Dto/          # Request/Response DTOs
    │   ├── Serializer/   # JSON serialization
    │   └── Exception/    # API exceptions
    └── Presentation/     # UI/Web (Adapter - Optional)
        ├── Controller/   # Symfony controllers
        ├── Components/   # Live components
        ├── Dto/          # View models
        ├── Resources/    # Feature-specific assets
        │   ├── assets/   # Client-side code
        │   │   └── controllers/  # Stimulus controllers
        │   └── templates/  # Twig templates
        └── Templates/    # Legacy template location (deprecated)
```

**Note**: Features may implement `Api`, `Presentation`, or both adapter layers depending on their access requirements.

## Architectural Rules & Patterns

### 1. Dependency Inversion Principle (DIP)
All dependencies point inward toward the domain:
- Domain depends on nothing (except own Infrastructure interfaces)
- Infrastructure implements interfaces defined by Domain
- Api and Presentation use Domain services directly
- Facades provide external API for other features

### 2. Layer Isolation & Boundaries
Strict boundaries enforced by architecture tests:
```php
arch("{$from} must not use {$to} internals")
    ->expect("App\\{$from}")
    ->not->toUse([
        "App\\{$to}\\Domain",
        "App\\{$to}\\Infrastructure",
        "App\\{$to}\\Api",
        "App\\{$to}\\Presentation",
    ]);
```

### 3. DTO Pattern with Layer Separation
Each layer has specific DTO types:
- **Facade DTOs**: Inter-feature communication
- **Infrastructure DTOs**: External system data
- **Api DTOs**: HTTP request/response structures optimized for JSON
- **Presentation DTOs**: UI view models

All DTOs are immutable with `readonly` properties.

### 4. Service Layer Architecture

Three distinct service types:

#### Domain Services
Core business logic and orchestration:
```php
class McpInstancesDomainService implements McpInstancesDomainServiceInterface
{
    public function createMcpInstance(string $accountCoreId): McpInstance
    {
        // Business logic, validation, entity creation
    }
}
```

#### Facade Services
Simplified external API:
```php
class DockerManagementFacade implements DockerManagementFacadeInterface
{
    public function createAndStartContainer(McpInstanceDto $instance): bool
    {
        // Delegates to domain service, returns simple result
    }
}
```

#### Presentation Services
UI-specific orchestration:
```php
class McpInstancesPresentationService
{
    public function getAdminOverviewData(): array
    {
        // Aggregates data from multiple sources for UI
    }
}
```

#### Api Controllers
HTTP API endpoints (typically thin wrappers over Facade):
```php
#[Route('/api/instances', methods: ['POST'])]
class InstanceApiController
{
    public function create(Request $request): JsonResponse
    {
        // Parse request, call domain service, return JSON
        $dto = $this->domainService->createInstance($data);
        return $this->json($dto, 201);
    }
}
```

**Note**: Api controllers may adapt Facade interfaces to HTTP semantics (status codes, headers) without strict 1:1 mapping.

### 5. Repository Pattern
Data access through Doctrine repositories:
```php
$repo = $this->entityManager->getRepository(McpInstance::class);
$instance = $repo->findOneBy(['accountCoreId' => $accountId]);
```

### 6. Value Objects & Enums
Type-safe constants and value objects:
```php
enum ContainerState: string
{
    case CREATED = 'created';
    case RUNNING = 'running';
    case STOPPED = 'stopped';
    case ERROR = 'error';
}
```

### 7. Entity Patterns

#### Factory Methods
```php
public static function generateRandomPassword(int $length = 24): string
{
    return rtrim(strtr(base64_encode(random_bytes($length)), '+/', '-_'), '=');
}
```

#### DTO Conversion
```php
public function toDto(): McpInstanceDto
{
    return new McpInstanceDto(
        $this->getId() ?? '',
        $this->getCreatedAt(),
        // ... map all fields
    );
}
```

### 8. Command-Query Separation (CQS)
- **Commands**: Modify state (`createMcpInstance`, `stopAndRemove`)
- **Queries**: Return data (`getMcpInstanceById`, `getStatus`)

### 9. Null Object Pattern
Methods return null instead of throwing for missing data:
```php
public function getMcpInstanceById(string $id): ?McpInstanceDto
```

### 10. Configuration as Code
Type-safe configuration with DTOs:
```php
class YamlInstanceTypesConfigProvider implements InstanceTypesConfigProviderInterface
{
    public function getConfig(): McpInstanceTypesConfig
    {
        // Parse YAML into strongly-typed DTOs
    }
}
```

## Client-Side Architecture

### Stimulus Controller Organization

Client-side JavaScript follows the same feature isolation principles as server-side code. Each feature manages its own Stimulus controllers within its presentation layer:

```
src/FeatureName/Presentation/Resources/assets/controllers/
├── feature_specific_controller.js
├── another_controller.js
└── shared_utilities.js
```

### AssetMapper Configuration

Feature-specific assets are included via AssetMapper configuration:

```yaml
# config/packages/asset_mapper.yaml
framework:
    asset_mapper:
        paths:
            - assets/                                    # Global assets
            - src/*/Presentation/Resources/assets/       # Feature assets
```

### Stimulus Controller Registration

Controllers are registered with feature-specific namespacing to prevent conflicts:

```typescript
// assets/bootstrap.ts
app.register('feature-name-controller-name', 
    () => import('../src/FeatureName/Presentation/Resources/assets/controllers/controller_name.js'));
```

### Twig Integration with Stimulus

Symfony provides native Twig helper functions for clean Stimulus integration:

```twig
{# Use stimulus_controller() for controller binding #}
<div {{ stimulus_controller('feature-name-controller', { 
    'maxItems': 10,
    'apiUrl': path('api_endpoint') 
}) }}>

    {# Use stimulus_target() for element targeting #}
    <div {{ stimulus_target('feature-name-controller', 'container') }}>
        <!-- content -->
    </div>

    {# Use stimulus_action() for event handling #}
    <button {{ stimulus_action('feature-name-controller', 'add') }}>
        Add Item
    </button>
</div>
```

### Benefits of Feature-Specific Client-Side Organization

1. **Boundary Enforcement**: Client-side code respects the same hexagonal boundaries as server-side code
2. **Reusability**: Controllers can be shared across templates within the same feature
3. **Maintainability**: Changes to one feature's client-side code don't affect others
4. **Testing Isolation**: Each feature's JavaScript can be tested independently
5. **Type Safety**: Twig helpers provide compile-time validation of controller names and parameters

### Client-Side Architectural Rules

- **Feature Isolation**: Stimulus controllers belong to specific features, not global assets
- **Naming Convention**: Use feature-prefixed controller names (`feature-name-action`)
- **Twig Helpers**: Always use `stimulus_controller()`, `stimulus_action()`, and `stimulus_target()` instead of manual `data-*` attributes
- **Asset Organization**: Place controllers in `src/FeatureName/Presentation/Resources/assets/controllers/`
- **No Cross-Feature Dependencies**: Controllers should not directly reference other features' controllers

### Legacy Inline JavaScript Migration

When migrating from inline JavaScript to Stimulus controllers:

1. **Extract Logic**: Move JavaScript functions to dedicated Stimulus controller files
2. **Use Templates**: Replace `innerHTML` manipulation with HTML `<template>` elements
3. **Apply Twig Helpers**: Replace manual `data-*` attributes with Twig helper functions
4. **Maintain Functionality**: Ensure all existing behavior is preserved during migration

## Testing Architecture

### Client-Side Testing

Client-side testing follows the same isolation principles as server-side testing:

- **Unit Testing**: Test Stimulus controllers in isolation using Jest or similar frameworks
- **Integration Testing**: Test client-server interactions through browser automation
- **UI Testing**: Use stable `data-test-*` attributes for reliable element selection

```javascript
// Example Stimulus controller test
import { Application } from "@hotwired/stimulus"
import EnvironmentVariablesController from "./environment_variables_controller"

describe("EnvironmentVariablesController", () => {
    let application, controller

    beforeEach(() => {
        application = Application.start()
        application.register("environment-variables", EnvironmentVariablesController)
    })

    test("adds new environment variable row", () => {
        // Test implementation
    })
})
```

### Test Organization
- **Unit Tests** (`tests/Unit/`): Isolated class testing with mocks
- **Integration Tests** (`tests/Integration/`): Feature integration testing
- **Architecture Tests** (`tests/Architecture/`): Boundary enforcement

### UI Testing Strategy
Templates use stable `data-test` attributes:
```twig
<h1 data-test-id="page-title">Title</h1>
<tr data-test-class="instances-row">...</tr>
```

Tests select using these attributes:
```php
$title = $crawler->filter('[data-test-id="page-title"]');
```

## Security Patterns

### Authentication & Authorization
- Constant-time token comparison: `hash_equals()`
- Role-based access control (RBAC)
- Bearer token authentication for APIs
- Forward authentication pattern for edge protection

### Defensive Programming
```php
if (!$containerName || !$instanceSlug) {
    $this->logger->error('[Service] Required fields missing');
    return false;
}
```

## Environment-Specific Behavior

Services adapt based on environment:
```php
$appEnv = $this->params->get('kernel.environment');
if ($appEnv === 'dev') {
    // Development behavior
} else {
    // Production behavior
}
```

## Caching Strategies

Time-based caching with Symfony Cache:
```php
private const int CACHE_TTL = 300; // 5 minutes

$cacheKey = 'mcp_auth_' . md5($instanceSlug);
$cachedItem = $this->cache->getItem($cacheKey);
```

## Code Quality Enforcement

### Static Analysis
- PHPStan with maximum strictness
- PHP CS Fixer for code style
- ESLint & Prettier for frontend

### Type Safety
- No untyped parameters or returns
- No mixed types (except legacy interfaces)
- No associative arrays as data structures
- Immutable DTOs with `readonly`

## Dependency Management

### Three Systems
1. **PHP (Composer)**: Backend dependencies in `vendor/`
2. **Node.js (NPM)**: Development tools in `node_modules/`
3. **AssetMapper**: Frontend JS via importmaps in `assets/vendor/`

## Runtime Architecture

### High-Level Overview
- **Reverse Proxy**: Traefik container (ports 80/443)
- **Web Server**: nginx on port 8090 (no TLS)
- **Application**: Symfony with PHP-FPM
- **Containers**: One Docker container per MCP instance

### Request Flow
```
Internet → Traefik → {
    app.* → nginx:8090 → Symfony
    mcp-<id>.* → container:8080 (MCP)
    vnc-<id>.* → container:6080 (noVNC)
}
```

## Architectural Decisions

### Why Hexagonal Architecture?
- **Testability**: Domain logic isolated from frameworks
- **Flexibility**: Easy to swap adapters (e.g., different Docker implementations)
- **Maintainability**: Clear boundaries prevent coupling

### Why DDD?
- **Complex Domain**: MCP instance management has rich business rules
- **Bounded Contexts**: Features are naturally isolated domains
- **Ubiquitous Language**: Code reflects business terminology

### Why Strict Type Safety?
- **Early Error Detection**: Catch issues at development time
- **Self-Documentation**: Types document intent
- **Refactoring Safety**: IDE support for automated refactoring

### Why Architecture Tests?
- **Enforce Boundaries**: Prevent architectural drift
- **Documentation**: Tests document intended architecture
- **Team Scalability**: New developers can't accidentally violate patterns

## Future Considerations

### Event Sourcing Readiness
Entities track creation time and state changes, preparing for potential event sourcing adoption.

### Scalability Patterns
- Stateless services enable horizontal scaling
- Cache layer reduces database load
- Docker orchestration enables container distribution

### Observability
- Structured logging with context
- Health checks for all endpoints
- Metrics-ready architecture

## Conclusion

### Understanding Hexagonal Architecture in This Codebase

For newcomers to this codebase, it's essential to understand the Hexagonal Architecture (also known as Ports & Adapters) that forms the foundation of our design. This pattern, created by Alistair Cockburn, fundamentally changes how we think about application structure.

#### The Hexagon Metaphor

Imagine your business logic as sitting inside a hexagon. The hexagon shape itself isn't special - it's just a visual way to show that the core can have multiple sides/interfaces. What matters is the **inside vs. outside** distinction:

- **Inside the Hexagon (Domain)**: Your pure business logic - the code that would remain the same whether you're building a web app, CLI tool, or mobile API
- **The Hexagon's Edges (Ports)**: Interfaces that define how the outside world can communicate with your business logic
- **Outside the Hexagon (Adapters)**: All the technical details - databases, web frameworks, file systems, external APIs

#### Why Hexagonal Architecture?

Traditional layered architectures often lead to business logic becoming entangled with technical concerns. You've probably seen code where a simple business rule is buried in a controller, mixed with HTTP handling, database queries, and view rendering. Hexagonal Architecture prevents this by enforcing strict boundaries.

**Key Advantages in Our Implementation:**

1. **Technology Independence**: Our Domain layer doesn't know if it's being called by a REST API, a Symfony controller, or a CLI command. This means we could swap Symfony for another framework without touching our business logic.

2. **Testability**: We can test our business logic without spinning up a database, web server, or Docker containers. The Domain only depends on interfaces, which we can easily mock.

3. **Parallel Development**: Teams can work on different adapters simultaneously. One team can build the API layer while another works on the web UI, both using the same Domain interfaces.

4. **Clear Boundaries**: New developers can't accidentally create dependencies in the wrong direction - our architecture tests will catch it immediately.

#### How It's Applied in This Codebase

Let's look at a concrete example with the `McpInstancesManagement` feature:

```
McpInstancesManagement/
├── Domain/                      # The Hexagon (Business Logic)
│   ├── Entity/McpInstance       # Core business object
│   ├── Service/                 # Business rules
│   └── Enum/ContainerState      # Domain concepts
│
├── Facade/                                    # Port (External Interface)
│   ├── McpInstancesManagementFacadeInterface  # The contract
│   └── McpInstancesManagementFacade           # The implementation
│
├── Infrastructure/              # Adapters (Technical Details)
│   └── [External system integrations]
│
├── Api/                        # Adapter (REST API)
│   └── Controller/             # HTTP endpoints
│
└── Presentation/               # Adapter (Web UI)
    └── Controller/             # Web pages
```

**The Flow:**

1. A user clicks "Create Instance" in the web UI
2. The Presentation Controller (Adapter) receives the HTTP request
3. It calls the Domain Service directly (not through Facade - that's for external features)
4. The Domain Service contains all business logic (validation, limits, etc.)
5. If the Domain needs Docker, it uses the `DockerManagementFacadeInterface` (Port)
6. The actual Docker interaction happens in `DockerManagement/Infrastructure` (Adapter)
7. The Domain never knows it's talking to Docker - it just knows it has a "container management" capability

#### Critical Rules for Newcomers

1. **Domain is Sacred**: Never put framework-specific code in Domain. No `@Route` annotations, no `Request` objects, no `EntityManager` usage (except through repositories).

2. **Facades are for Others**: Your feature's own controllers should use Domain services directly. Facades are only for other features to use.

3. **Dependencies Point Inward**: Infrastructure can depend on Domain, but Domain can never depend on Infrastructure (except through interfaces).

4. **Test the Hexagon First**: Always write tests for your Domain logic before worrying about controllers or infrastructure.

#### Practical Benefits We've Achieved

- **Docker Abstraction**: We could switch from Docker to Podman by writing a new adapter - no Domain changes needed
- **Multi-Channel Access**: The same MCP instance logic serves both web UI and potential mobile apps
- **Database Flexibility**: We use Doctrine, but could switch to MongoDB by changing only the Infrastructure layer
- **Testing Speed**: Our unit tests run in milliseconds because they don't need real infrastructure

### Final Thoughts

This architecture provides a robust foundation for enterprise-scale applications with:
- Clear separation of concerns
- Testable, maintainable code
- Type-safe, self-documenting patterns
- Automated quality enforcement
- Production-ready security and scalability

The hexagonal pattern might feel like overkill for simple CRUD operations, but as soon as you have complex business logic, multiple integration points, or need to support multiple interfaces (web, API, CLI), the benefits become clear. The initial investment in understanding and following these patterns pays dividends in long-term maintainability and flexibility.

For newcomers: embrace the constraints. They're not arbitrary rules but guardrails that keep the codebase clean and maintainable as it grows. When you're unsure where code belongs, ask yourself: "Is this business logic or technical detail?" Business logic goes in the hexagon; everything else stays outside.
