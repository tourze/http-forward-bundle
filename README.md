# HTTP Forward Bundle

[English](README.md) | [中文](README.zh-CN.md)

**Symfony Bundle for HTTP request forwarding with middleware support**

An enterprise-grade HTTP request forwarding component with complete forwarding capabilities, middleware system, load balancing, and health checks.

## Core Features

- ✅ **HTTP Request Forwarding** - Support all HTTP methods (GET, POST, PUT, DELETE, PATCH, HEAD, OPTIONS)
- ✅ **Streaming Response** - Full support for SSE (Server-Sent Events) and chunked transfer
- ✅ **Middleware System** - 7 built-in middlewares for request/response transformation
- ✅ **Load Balancing** - Multiple backend support with automatic healthy node selection
- ✅ **Health Checks** - Proactive backend health monitoring
- ✅ **Retry Mechanism** - Automatic retry with exponential backoff
- ✅ **Fallback Handling** - Return preset responses when backends are unavailable
- ✅ **Access Control** - API key authentication to protect proxy endpoints
- ✅ **Visual Management** - EasyAdmin backend with drag-and-drop middleware configuration
- ✅ **Complete Logging** - Record request/response for troubleshooting

## Installation

```bash
composer require tourze/http-forward-bundle
```

## Quick Start

### 1. Basic Configuration

Create forwarding rules via EasyAdmin:

1. Visit `/admin`
2. Navigate to **HTTP Forward** → **Forward Rules**
3. Create a new rule with:
   - **Path Pattern**: `/api/*`
   - **Backend URL**: `https://api.example.com`
   - **Middlewares**: Select required middlewares

### 2. Use Forward Controller

Accessing `/forward/api/users` will automatically forward to the configured backend.

### 3. Programmatic Usage

```php
use Tourze\HttpForwardBundle\Service\ForwarderService;
use Tourze\HttpForwardBundle\Entity\ForwardRule;

class YourController
{
    public function __construct(
        private readonly ForwarderService $forwarderService,
    ) {}

    public function proxy(Request $request, ForwardRule $rule): Response
    {
        return $this->forwarderService->forward($request, $rule);
    }
}
```

## Middleware System

### Built-in Middlewares

| Middleware | Function | Use Case |
|------------|----------|----------|
| `access_key_auth` | Access key authentication | Protect proxy endpoints, verify client identity |
| `auth_header` | Authorization header management | Add/replace Authorization header |
| `header_transform` | Request header transformation | Add, remove, rename request headers |
| `query_param` | Query parameter handling | Modify URL query parameters |
| `xml_to_json` | XML to JSON conversion | Response format transformation |
| `retry` | Retry mechanism | Automatically retry failed requests |
| `fallback` | Fallback handling | Return default response when backend unavailable |

### Visual Configuration

Configure middlewares via drag-and-drop in EasyAdmin:

1. Edit a forwarding rule
2. In the **Middlewares** field:
   - Select middleware from dropdown
   - Click "Add" button
   - Expand configuration form to fill parameters
   - Drag to adjust execution order

See: [Middleware Configuration Guide](MIDDLEWARE_CONFIG_GUIDE.md)

### Configuration Examples

#### Access Key Authentication

```json
{
  "type": "access_key_auth",
  "config": {
    "enabled": true,
    "required": true,
    "fallback_mode": "strict"
  }
}
```

#### Authorization Header Management

```json
{
  "type": "auth_header",
  "config": {
    "action": "add",
    "scheme": "Bearer",
    "token": "your-api-key"
  }
}
```

#### Header Transformation

```json
{
  "type": "header_transform",
  "config": {
    "add": {
      "X-Custom-Header": "value",
      "X-Client-Version": "1.0"
    },
    "remove": ["X-Internal-Header"],
    "rename": {
      "X-Old-Name": "X-New-Name"
    }
  }
}
```

#### Retry Mechanism

```json
{
  "type": "retry",
  "config": {
    "max_attempts": 3,
    "delay_ms": 1000,
    "backoff_multiplier": 2.0,
    "retry_on_status": [429, 500, 502, 503, 504]
  }
}
```

## Feature Details

### Load Balancing

Support multiple backend configuration with automatic healthy node selection:

```php
// Add multiple Backends to ForwardRule in EasyAdmin
// System will automatically round-robin among healthy backends
```

### Health Checks

Use CLI command to check backend status:

```bash
# Check all backends
php bin/console http-forward:health-check

# Check specific backend
php bin/console http-forward:health-check --backend-id=1

# Set timeout (seconds)
php bin/console http-forward:health-check --timeout=10

# Dry run without modifying status
php bin/console http-forward:health-check --dry-run
```

### Streaming Response

Automatically detect and support SSE streaming:

```php
// Enable stream_enabled in ForwardRule
// Or request with Accept: text/event-stream
```

### Request Logging

All forwarding requests are logged in `ForwardLog` entity:

- Request/response headers
- Response status code
- Execution time
- Error messages
- Backend node

View in EasyAdmin: **HTTP Forward** → **Forward Logs**

## Configuration Reference

### Entity Relationships

```
ForwardRule (Forwarding Rule)
├── name: Rule name
├── path_pattern: Path matching pattern (e.g., /api/*)
├── priority: Priority (higher number = higher priority)
├── stream_enabled: Enable streaming response
├── middlewares: Middleware configuration (JSON)
└── backends: Associated backend list

Backend (Backend Node)
├── name: Node name
├── url: Backend URL
├── weight: Weight (load balancing)
├── status: Health status
├── health_check_url: Health check URL
└── timeout: Timeout (seconds)

ForwardLog (Forward Log)
├── forward_rule: Applied rule
├── backend: Actual backend used
├── status: Request status
├── request_*: Request information
├── response_*: Response information
└── error_message: Error message
```

## Use Cases

### API Gateway

```
Client → HTTP Forward Bundle → Multiple Backend Services
              ↓
        - Authentication middleware
        - Header transformation
        - Load balancing
        - Health checks
        - Request logging
```

### OpenAI Proxy

Works with `open-ai-http-proxy-bundle` to provide complete OpenAI API proxy functionality.

### Microservice Routing

Route requests to different microservices based on path rules, supporting:
- Dynamic routing rules
- Request/response transformation
- Service degradation
- Traffic control

## Testing

```bash
# Run all tests
vendor/bin/phpunit

# Run specific tests
vendor/bin/phpunit tests/Service/ForwarderServiceTest.php

# Code quality check
vendor/bin/phpstan analyse
```

## Documentation

- [PRD (Product Requirements Document)](PRD.md)
- [Feature Request Document](FEATURE_REQUEST.md)
- [Middleware Configuration Guide](MIDDLEWARE_CONFIG_GUIDE.md)

## License

MIT License - See [LICENSE](LICENSE) file

## Related Projects

Provides core forwarding capabilities as infrastructure component for `tourze/open-ai-http-proxy-bundle`.

---

**Version**: 1.0
**Maintainer**: Tourze Team
