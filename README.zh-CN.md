# HTTP Forward Bundle

[English](README.md) | [中文](README.zh-CN.md)

**Symfony Bundle for HTTP request forwarding with middleware support**

一个企业级的 HTTP 请求转发组件，提供完整的转发能力、中间件系统、负载均衡和健康检查。

## 核心功能

- ✅ **HTTP 请求转发** - 支持所有 HTTP 方法（GET、POST、PUT、DELETE、PATCH、HEAD、OPTIONS）
- ✅ **流式响应支持** - 完整支持 SSE（Server-Sent Events）和分块传输
- ✅ **中间件系统** - 7 种内置中间件，支持请求/响应转换
- ✅ **负载均衡** - 多后端支持，自动选择健康节点
- ✅ **健康检查** - 主动探测后端健康状态
- ✅ **重试机制** - 自动重试失败请求，支持指数退避
- ✅ **降级处理** - 后端不可用时返回预设响应
- ✅ **访问控制** - 访问密钥认证，保护代理接口
- ✅ **可视化管理** - EasyAdmin 后台，拖拽式中间件配置
- ✅ **完整日志** - 记录请求/响应，支持问题追溯

## 安装

```bash
composer require tourze/http-forward-bundle
```

## 快速开始

### 1. 基本配置

通过 EasyAdmin 后台创建转发规则：

1. 访问 `/admin`
2. 进入 **HTTP Forward** → **Forward Rules**
3. 创建新规则，配置：
    - **匹配路径**：`/api/*`
    - **后端URL**：`https://api.example.com`
    - **中间件**：选择需要的中间件

### 2. 使用转发控制器

访问 `/forward/api/users` 会自动转发到配置的后端。

### 3. 编程方式使用

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

## 中间件系统

### 内置中间件

| 中间件 | 功能 | 使用场景 |
|--------|------|----------|
| `access_key_auth` | 访问密钥认证 | 保护代理接口，验证客户端身份 |
| `auth_header` | 授权头管理 | 添加/替换 Authorization 头 |
| `header_transform` | 请求头转换 | 添加、删除、重命名请求头 |
| `query_param` | 查询参数处理 | 修改 URL 查询参数 |
| `xml_to_json` | XML 转 JSON | 响应格式转换 |
| `retry` | 重试机制 | 自动重试失败请求 |
| `fallback` | 降级处理 | 后端不可用时返回默认响应 |

### 可视化配置

在 EasyAdmin 后台通过拖拽方式配置中间件：

1. 编辑转发规则
2. 在 **Middlewares** 字段：
    - 从下拉菜单选择中间件
    - 点击"添加"按钮
    - 展开配置表单填写参数
    - 拖拽调整执行顺序

详见：[中间件配置指南](MIDDLEWARE_CONFIG_GUIDE.md)

### 配置示例

#### 访问密钥认证

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

#### 授权头管理

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

#### 请求头转换

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

#### 重试机制

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

## 功能详解

### 负载均衡

支持多后端配置，自动选择健康节点：

```php
// 在 EasyAdmin 中为 ForwardRule 添加多个 Backend
// 系统会自动在健康的后端之间轮询
```

### 健康检查

使用命令行工具检查后端状态：

```bash
# 检查所有后端
php bin/console http-forward:health-check

# 检查指定后端
php bin/console http-forward:health-check --backend-id=1

# 设置超时时间（秒）
php bin/console http-forward:health-check --timeout=10

# 仅模拟检查，不修改状态
php bin/console http-forward:health-check --dry-run
```

### 流式响应

自动检测并支持 SSE 流式响应：

```php
// 在 ForwardRule 中启用 stream_enabled
// 或请求带有 Accept: text/event-stream
```

### 请求日志

所有转发请求都会记录在 `ForwardLog` 实体中：

- 请求/响应头
- 响应状态码
- 执行时间
- 错误信息
- 后端节点

在 EasyAdmin 后台查看：**HTTP Forward** → **Forward Logs**

## 配置参考

### 实体关系

```
ForwardRule (转发规则)
├── name: 规则名称
├── path_pattern: 匹配路径模式（如 /api/*）
├── priority: 优先级（数字越大越优先）
├── stream_enabled: 是否启用流式响应
├── middlewares: 中间件配置（JSON）
└── backends: 关联的后端列表

Backend (后端节点)
├── name: 节点名称
├── url: 后端URL
├── weight: 权重（负载均衡）
├── status: 健康状态
├── health_check_url: 健康检查URL
└── timeout: 超时时间（秒）

ForwardLog (转发日志)
├── forward_rule: 使用的规则
├── backend: 实际转发的后端
├── status: 请求状态
├── request_*: 请求信息
├── response_*: 响应信息
└── error_message: 错误信息
```

## 使用场景

### API 网关

```
客户端 → HTTP Forward Bundle → 多个后端服务
              ↓
        - 认证中间件
        - 请求头转换
        - 负载均衡
        - 健康检查
        - 请求日志
```

### OpenAI 代理

配合 `open-ai-http-proxy-bundle` 使用，提供完整的 OpenAI API 代理功能。

### 微服务路由

根据路径规则将请求路由到不同的微服务，支持：
- 动态路由规则
- 请求/响应转换
- 服务降级
- 流量控制

## 测试

```bash
# 运行所有测试
vendor/bin/phpunit

# 运行指定测试
vendor/bin/phpunit tests/Service/ForwarderServiceTest.php

# 代码质量检查
vendor/bin/phpstan analyse
```

## 相关文档

- [PRD（产品需求文档）](PRD.md)
- [功能需求文档](FEATURE_REQUEST.md)
- [中间件配置指南](MIDDLEWARE_CONFIG_GUIDE.md)

## 许可证

MIT License - 详见 [LICENSE](LICENSE) 文件

## 依赖项目

作为 `tourze/open-ai-http-proxy-bundle` 的基础设施组件提供核心转发能力。

---

**版本**：1.0
**维护**：Tourze Team
