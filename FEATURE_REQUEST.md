# HTTP Forward Bundle 功能需求文档

## 概述

为支持 open-ai-http-proxy-bundle 实现完整的 OpenAI API 代理功能，需要对 HTTP Forward Bundle 进行功能开发和增强。

## 核心功能需求

### 1. 基础转发功能

#### 1.1 HTTP方法支持

**需求**：支持所有标准HTTP方法

```php
class HttpForwarder {
    public function forward(
        string $method,
        string $targetUrl,
        array $headers = [],
        $body = null,
        array $options = []
    ): Response;
}
```

**支持的方法**：
- GET、POST、PUT、DELETE
- PATCH、HEAD、OPTIONS
- 自定义方法

#### 1.2 请求构建

**需求**：灵活的请求构建器

```php
class RequestBuilder {
    public function build(array $config): Request {
        // 构建请求对象
        // 支持：headers、body、query参数、认证信息
    }
}
```

### 2. 流式响应处理

#### 2.1 SSE（Server-Sent Events）支持

**需求**：完整支持OpenAI的流式API

```php
interface StreamForwarderInterface {
    /**
     * 转发流式请求
     * @return \Generator 返回流式数据块
     */
    public function forwardStream(
        Request $request,
        string $targetUrl,
        array $options = []
    ): \Generator;
}
```

**实现要求**：
- 保持长连接
- 实时转发数据块
- 正确处理 `data: [DONE]` 结束标记
- 错误时优雅关闭流

#### 2.2 流式数据解析

```php
class SSEParser {
    /**
     * 解析SSE数据块
     */
    public function parse(string $chunk): ?array {
        // 解析格式：
        // data: {"choices":[{"delta":{"content":"Hello"}}]}
    }
}
```

### 3. 请求/响应转换

#### 3.1 请求转换器接口

```php
interface RequestTransformerInterface {
    /**
     * 转换请求
     */
    public function transform(Request $request, array $context): Request;
    
    /**
     * 获取优先级（数字越小优先级越高）
     */
    public function getPriority(): int;
}
```

#### 3.2 内置转换器

**HeaderTransformer**
```php
class HeaderTransformer implements RequestTransformerInterface {
    public function __construct(
        private array $add = [],      // 添加的头
        private array $remove = [],   // 删除的头
        private array $replace = []   // 替换的头
    ) {}
}
```

**AuthTransformer**
```php
class AuthTransformer implements RequestTransformerInterface {
    public function transform(Request $request, array $context): Request {
        // 将内部token转换为目标API的认证方式
        // 如：Bearer token -> api-key header
    }
}
```

**BodyTransformer**
```php
class BodyTransformer implements RequestTransformerInterface {
    public function transform(Request $request, array $context): Request {
        // 转换请求体
        // 如：模型名称替换
    }
}
```

#### 3.3 响应转换器

```php
interface ResponseTransformerInterface {
    public function transform(Response $response, array $context): Response;
}
```

### 4. 重试机制

#### 4.1 重试策略接口

```php
interface RetryStrategyInterface {
    /**
     * 判断是否应该重试
     */
    public function shouldRetry(
        int $attempt,
        Request $request,
        ?Response $response,
        ?\Throwable $exception
    ): bool;
    
    /**
     * 获取重试延迟（毫秒）
     */
    public function getDelay(int $attempt): int;
}
```

#### 4.2 内置策略

```php
// 指数退避
class ExponentialBackoffStrategy implements RetryStrategyInterface {
    public function __construct(
        private int $maxAttempts = 3,
        private int $baseDelay = 1000,
        private float $multiplier = 2.0,
        private int $maxDelay = 30000
    ) {}
}

// HTTP状态码策略
class StatusCodeStrategy implements RetryStrategyInterface {
    public function __construct(
        private array $retryableCodes = [429, 500, 502, 503, 504]
    ) {}
}
```

### 5. 超时控制

#### 5.1 超时配置

```php
class TimeoutOptions {
    public float $connectTimeout = 5.0;    // 连接超时
    public float $requestTimeout = 30.0;   // 请求超时
    public float $streamTimeout = 0;       // 流式超时（0=无限）
    public float $idleTimeout = 60.0;      // 空闲超时
}
```

#### 5.2 超时处理

```php
class TimeoutHandler {
    public function handle(Request $request, TimeoutOptions $options): Response {
        // 使用Symfony HttpClient的超时选项
        // 处理超时异常
    }
}
```

### 6. 熔断器

#### 6.1 熔断器实现

```php
class CircuitBreaker {
    private string $state = 'CLOSED';  // CLOSED, OPEN, HALF_OPEN
    private int $failureCount = 0;
    private ?\DateTime $lastFailureTime = null;
    
    public function call(callable $operation) {
        if ($this->state === 'OPEN') {
            if ($this->shouldAttemptReset()) {
                $this->state = 'HALF_OPEN';
            } else {
                throw new CircuitOpenException();
            }
        }
        
        try {
            $result = $operation();
            $this->onSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->onFailure();
            throw $e;
        }
    }
}
```

### 7. 日志和监控

#### 7.1 请求日志

```php
class ForwardLogger {
    public function logRequest(
        string $requestId,
        Request $request,
        string $targetUrl,
        array $context = []
    ): void;
    
    public function logResponse(
        string $requestId,
        Response $response,
        float $duration,
        array $context = []
    ): void;
    
    public function logError(
        string $requestId,
        \Throwable $exception,
        array $context = []
    ): void;
}
```

#### 7.2 性能指标收集

```php
interface MetricsCollectorInterface {
    public function recordRequest(string $target, string $method): void;
    public function recordResponse(string $target, int $statusCode, float $duration): void;
    public function recordError(string $target, string $errorType): void;
}
```

### 8. 配置系统

#### 8.1 Bundle配置

```yaml
http_forward:
  # 默认配置
  defaults:
    timeout: 30
    connect_timeout: 5
    user_agent: 'HttpForwardBundle/1.0'
    verify_ssl: true
    
  # 重试配置
  retry:
    enabled: true
    max_attempts: 3
    strategy: exponential_backoff
    retry_on: [429, 500, 502, 503, 504]
    
  # 熔断器配置
  circuit_breaker:
    enabled: true
    failure_threshold: 5
    recovery_timeout: 60
    half_open_requests: 3
    
  # 日志配置
  logging:
    enabled: true
    log_request: true
    log_response: true
    log_body: false  # 敏感信息保护
    
  # 目标服务配置
  targets:
    openai:
      base_url: 'https://api.openai.com/v1/'
      headers:
        Authorization: 'Bearer %env(OPENAI_API_KEY)%'
      timeout: 60
```

### 9. 服务接口

#### 9.1 主服务

```php
interface ForwardServiceInterface {
    /**
     * 转发HTTP请求
     */
    public function forward(
        Request $request,
        string $targetUrl,
        array $options = []
    ): Response;
    
    /**
     * 转发流式请求
     */
    public function forwardStream(
        Request $request,
        string $targetUrl,
        array $options = []
    ): StreamedResponse;
    
    /**
     * 添加请求转换器
     */
    public function addRequestTransformer(
        RequestTransformerInterface $transformer
    ): void;
    
    /**
     * 添加响应转换器
     */
    public function addResponseTransformer(
        ResponseTransformerInterface $transformer
    ): void;
}
```

## 实施优先级

### P0 - 必须（第一周）

1. **基础转发功能**
   - HTTP方法支持
   - 请求/响应处理
   - 基本错误处理

2. **流式响应支持**
   - SSE转发
   - 流式数据处理
   - 连接管理

3. **请求转换**
   - Header转换
   - Auth转换
   - Body转换

### P1 - 重要（第二周）

1. **重试机制**
   - 指数退避
   - 状态码判断
   - 自定义策略

2. **超时控制**
   - 各类超时设置
   - 超时异常处理

3. **基础日志**
   - 请求/响应日志
   - 错误日志

### P2 - 可选（第三周）

1. **熔断器**
   - 状态管理
   - 自动恢复

2. **高级监控**
   - 性能指标
   - 健康检查

3. **配置管理**
   - YAML配置
   - 动态配置

## 技术要求

### 依赖

```json
{
    "require": {
        "php": "^8.2",
        "symfony/framework-bundle": "^7.3",
        "symfony/http-client": "^7.3",
        "symfony/event-dispatcher": "^7.3",
        "psr/log": "^3.0"
    }
}
```

### 代码标准

- PSR-12编码规范
- PHPStan Level 8
- 测试覆盖率>85%
- 完整的PHPDoc

### 性能基准

- 转发延迟：<10ms
- 内存占用：<50MB
- 并发处理：>100 req/s

## 测试计划

### 单元测试

```php
class HttpForwarderTest extends TestCase {
    public function testBasicForward(): void;
    public function testStreamForward(): void;
    public function testRetryLogic(): void;
    public function testCircuitBreaker(): void;
}
```

### 集成测试

- Mock服务器测试
- 真实API测试（可选）
- 错误场景测试

### 性能测试

- 负载测试
- 内存泄漏测试
- 并发测试

## 交付标准

1. ✅ 所有P0功能完成
2. ✅ 测试覆盖率>85%
3. ✅ PHPStan无错误
4. ✅ 完整文档
5. ✅ 示例代码

## 时间估算

- 开发：3周
- 测试：1周
- 文档：3天
- **总计：约1个月**

---

**文档版本**：1.0  
**创建日期**：2024-01-20  
**负责人**：开发团队  
**状态**：待开发