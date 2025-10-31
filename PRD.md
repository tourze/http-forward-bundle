# HTTP Forward Bundle 产品需求文档

## 1. 项目概述

### 1.1 背景

在微服务架构和API网关场景中，HTTP请求转发是一个核心功能。当前需要一个通用的、高性能的HTTP转发组件，能够：
- 透明地转发HTTP请求到目标服务
- 支持请求/响应的转换和适配
- 处理流式响应（SSE）
- 提供重试、超时、熔断等容错机制

### 1.2 目标

构建一个可复用的 Symfony Bundle，提供完整的HTTP请求转发能力，作为各种代理服务的基础设施组件。

### 1.3 定位

作为基础设施层组件，被上层业务Bundle（如open-ai-http-proxy-bundle）依赖，提供核心的HTTP转发能力。

## 2. 功能需求

### 2.1 核心功能

#### 2.1.1 请求转发

**基本转发**
- 支持所有HTTP方法（GET、POST、PUT、DELETE、PATCH、HEAD、OPTIONS）
- 保持原始请求头（可配置过滤）
- 保持请求体完整性
- 支持二进制内容

**URL处理**
- 动态目标URL构建
- 路径参数替换
- 查询参数合并
- URL编码处理

#### 2.1.2 流式响应支持

**SSE（Server-Sent Events）**
- 实时转发SSE流
- 保持连接活跃
- 错误时优雅关闭

**分块传输（Chunked Transfer）**
- 支持HTTP/1.1分块编码
- 流式读写，避免内存溢出

**WebSocket升级**（未来）
- 支持协议升级
- 双向通信代理

#### 2.1.3 请求/响应转换

**请求转换器链**
```php
interface RequestTransformerInterface {
    public function transform(Request $request, array $context): Request;
    public function supports(Request $request, array $context): bool;
}
```

**响应转换器链**
```php
interface ResponseTransformerInterface {
    public function transform(Response $response, array $context): Response;
    public function supports(Response $response, array $context): bool;
}
```

**内置转换器**
- HeaderTransformer：添加/删除/修改请求头
- BodyTransformer：JSON/XML格式转换
- AuthTransformer：认证信息转换
- CompressionTransformer：压缩/解压

### 2.2 容错机制

#### 2.2.1 重试策略

```php
interface RetryStrategyInterface {
    public function shouldRetry(int $attempt, \Throwable $exception): bool;
    public function getDelay(int $attempt): int;
}
```

**内置策略**
- ExponentialBackoff：指数退避
- LinearBackoff：线性退避
- FixedDelay：固定延迟
- CustomStrategy：自定义策略

#### 2.2.2 超时控制

- 连接超时
- 请求超时
- 流式响应超时
- 空闲超时

#### 2.2.3 熔断器

```php
interface CircuitBreakerInterface {
    public function isOpen(): bool;
    public function recordSuccess(): void;
    public function recordFailure(): void;
    public function tryReset(): bool;
}
```

**状态管理**
- CLOSED：正常状态
- OPEN：熔断状态
- HALF_OPEN：半开状态

### 2.3 监控与日志

#### 2.3.1 请求日志

```php
class ForwardLog {
    private string $requestId;
    private string $method;
    private string $sourceUrl;
    private string $targetUrl;
    private array $requestHeaders;
    private ?string $requestBody;
    private int $statusCode;
    private array $responseHeaders;
    private ?string $responseBody;
    private float $duration;
    private ?string $error;
    private array $metadata;
}
```

#### 2.3.2 性能指标

- 请求计数
- 响应时间分布
- 错误率
- 流量统计

#### 2.3.3 事件系统

```php
// 请求事件
class BeforeForwardEvent extends Event {
    private Request $request;
    private array $options;
}

class AfterForwardEvent extends Event {
    private Request $request;
    private Response $response;
    private float $duration;
}

class ForwardErrorEvent extends Event {
    private Request $request;
    private \Throwable $exception;
}
```

### 2.4 配置管理

#### 2.4.1 全局配置

```yaml
http_forward:
  defaults:
    timeout: 30
    connect_timeout: 5
    max_redirects: 5
    verify_ssl: true
    user_agent: 'HTTPForwardBundle/1.0'
    
  retry:
    max_attempts: 3
    strategy: exponential
    base_delay: 1000
    max_delay: 30000
    
  circuit_breaker:
    failure_threshold: 5
    recovery_timeout: 60
    
  logging:
    enabled: true
    log_body: false
    max_body_size: 1024
```

#### 2.4.2 目标配置

```yaml
http_forward:
  targets:
    api_service:
      base_url: 'https://api.example.com'
      headers:
        X-API-Key: '%env(API_KEY)%'
      timeout: 60
      
    legacy_service:
      base_url: 'http://legacy.internal'
      verify_ssl: false
      retry:
        max_attempts: 5
```

## 3. 技术架构

### 3.1 核心组件

```
HttpForwardBundle/
├── Forwarder/
│   ├── HttpForwarder.php          # 核心转发器
│   ├── StreamForwarder.php        # 流式转发器
│   └── ForwarderInterface.php     # 转发器接口
├── Transformer/
│   ├── RequestTransformerChain.php
│   ├── ResponseTransformerChain.php
│   └── Transformers/              # 内置转换器
├── Retry/
│   ├── RetryManager.php
│   └── Strategies/                # 重试策略
├── CircuitBreaker/
│   ├── CircuitBreaker.php
│   └── StateStore.php             # 状态存储
├── Logger/
│   ├── ForwardLogger.php
│   └── LoggerMiddleware.php
└── DependencyInjection/
    └── Configuration.php          # Bundle配置
```

### 3.2 服务定义

```php
// 主转发服务
class ForwardService {
    public function forward(
        Request $request,
        string $targetUrl,
        array $options = []
    ): Response;
    
    public function forwardStream(
        Request $request,
        string $targetUrl,
        array $options = []
    ): StreamedResponse;
}

// 转发工厂
class ForwarderFactory {
    public function create(string $target): ForwarderInterface;
}

// 中间件管理
class MiddlewareStack {
    public function push(MiddlewareInterface $middleware): void;
    public function process(Request $request, callable $next): Response;
}
```

### 3.3 扩展机制

#### 3.3.1 自定义转换器

```php
class CustomTransformer implements RequestTransformerInterface {
    public function transform(Request $request, array $context): Request {
        // 自定义转换逻辑
        return $request;
    }
    
    public function supports(Request $request, array $context): bool {
        return true;
    }
}
```

#### 3.3.2 自定义中间件

```php
class RateLimitMiddleware implements MiddlewareInterface {
    public function process(Request $request, callable $next): Response {
        // 速率限制检查
        if ($this->isRateLimited($request)) {
            return new Response('Too Many Requests', 429);
        }
        
        return $next($request);
    }
}
```

## 4. 性能要求

### 4.1 基准指标

- **延迟增加**：<10ms（不含网络延迟）
- **吞吐量**：>5000 QPS（单实例）
- **内存占用**：<100MB（基础）
- **并发连接**：>1000

### 4.2 优化策略

- 连接池复用
- DNS缓存
- HTTP/2支持
- 零拷贝流式传输

## 5. 使用示例

### 5.1 基本转发

```php
use HttpForwardBundle\Service\ForwardService;

class ProxyController {
    public function __construct(
        private ForwardService $forwardService
    ) {}
    
    public function proxy(Request $request): Response {
        return $this->forwardService->forward(
            $request,
            'https://api.example.com' . $request->getPathInfo(),
            [
                'headers' => [
                    'X-Forwarded-For' => $request->getClientIp()
                ]
            ]
        );
    }
}
```

### 5.2 流式转发

```php
public function stream(Request $request): StreamedResponse {
    return $this->forwardService->forwardStream(
        $request,
        'https://stream.example.com/events',
        [
            'timeout' => 0,  // 无超时
            'stream' => true
        ]
    );
}
```

### 5.3 自定义转换

```php
// 注册转换器
$this->forwardService->addRequestTransformer(
    new AuthTokenTransformer($tokenService)
);

$this->forwardService->addResponseTransformer(
    new JsonApiTransformer()
);
```

## 6. 测试策略

### 6.1 单元测试

- 转换器测试
- 重试策略测试
- 熔断器状态机测试

### 6.2 集成测试

- 模拟服务器测试
- 超时处理测试
- 错误恢复测试

### 6.3 性能测试

- 负载测试
- 并发测试
- 内存泄漏测试

## 7. 实施计划

### 第一阶段（1周）

- [x] 基本转发功能
- [x] 请求/响应转换器
- [x] 基础日志

### 第二阶段（1周）

- [ ] 流式响应支持
- [ ] 重试机制
- [ ] 超时控制

### 第三阶段（1周）

- [ ] 熔断器
- [ ] 性能监控
- [ ] 高级配置

### 第四阶段（3天）

- [ ] 文档完善
- [ ] 示例代码
- [ ] 性能优化

## 8. 风险评估

| 风险 | 影响 | 概率 | 缓解措施 |
|------|------|------|---------|
| 内存泄漏 | 高 | 中 | 严格的资源管理、定期测试 |
| 性能瓶颈 | 高 | 中 | 连接池、缓存优化 |
| 兼容性问题 | 中 | 低 | 完整的协议支持 |

## 9. 成功指标

- 被3个以上Bundle依赖使用
- 性能达到基准要求
- 零安全漏洞
- 测试覆盖率>85%

---

**文档版本**：1.0  
**创建日期**：2024-01-20  
**状态**：已批准