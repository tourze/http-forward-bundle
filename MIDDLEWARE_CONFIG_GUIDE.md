# 🛠 中间件可视化配置界面使用指南

## 🎉 功能概述

全新的中间件可视化配置界面彻底改善了用户体验，您无需再手动编写复杂的 JSON 配置！

### ✨ 主要特性

- **🎨 可视化界面**：直观的拖拽式配置体验
- **🚀 智能表单**：每个中间件提供专门的配置表单
- **📋 模板支持**：预设的配置模板，开箱即用
- **🔄 拖拽排序**：轻松调整中间件执行顺序
- **✅ 实时验证**：配置错误即时提示
- **📱 响应式设计**：支持移动端操作

## 🔧 支持的中间件

| 中间件 | 功能描述 | 优先级 |
|--------|----------|--------|
| `access_key_auth` | 访问密钥认证 | 200 |
| `auth_header` | 授权头管理 | 100 |
| `header_transform` | 请求头转换 | 90 |
| `query_param` | 查询参数处理 | 80 |
| `xml_to_json` | XML转JSON | 70 |
| `retry` | 重试机制 | 60 |
| `fallback` | 降级处理 | 50 |

## 📖 使用方法

### 1. 添加中间件

1. 从下拉菜单选择要添加的中间件
2. 点击 "添加" 按钮
3. 中间件会自动添加到列表中，并设置默认配置

### 2. 配置中间件

1. 点击中间件项右侧的 "配置" 按钮
2. 在展开的配置表单中设置参数
3. 配置会自动保存到后台 JSON

### 3. 调整执行顺序

- 拖拽中间件项前的 ⋮⋮ 图标来调整顺序
- 优先级高的中间件会先执行

### 4. 删除中间件

- 点击中间件项右侧的删除按钮 🗑️

## 🌟 配置示例

### 访问密钥认证 (`access_key_auth`)

```javascript
{
  "enabled": true,
  "required": true,
  "fallback_mode": "strict"
}
```

### 授权头管理 (`auth_header`)

```javascript
{
  "action": "add",
  "scheme": "Bearer",
  "token": "your-access-token"
}
```

### 请求头转换 (`header_transform`)

```javascript
{
  "add": {
    "X-Custom-Header": "value"
  },
  "remove": ["X-Unwanted-Header"],
  "rename": {
    "Old-Header": "New-Header"
  }
}
```

### 查询参数处理 (`query_param`)

```javascript
{
  "add": {
    "api_version": "v1"
  },
  "remove": ["debug"],
  "override": {
    "format": "json"
  }
}
```

## 🎯 最佳实践

### 中间件顺序规划

1. **认证类**（优先级 150-200）
   - `access_key_auth` - 最先执行身份验证

2. **头部处理**（优先级 80-100）
   - `auth_header` - 添加认证头
   - `header_transform` - 转换请求头

3. **参数处理**（优先级 60-80）
   - `query_param` - 处理查询参数

4. **格式转换**（优先级 40-70）
   - `xml_to_json` - 响应格式转换

5. **错误处理**（优先级 20-60）
   - `retry` - 重试机制
   - `fallback` - 降级策略

### 配置建议

- **认证头配置**：使用环境变量管理敏感的 token
- **重试策略**：根据目标服务的稳定性调整重试次数
- **降级处理**：为关键服务配置备用响应

## 🔍 故障排除

### 常见问题

**Q: 中间件没有按预期顺序执行？**
A: 检查中间件的优先级设置，数值越高优先级越高。

**Q: 配置保存后不生效？**
A: 确保点击了表单的保存按钮，并且没有验证错误。

**Q: 拖拽排序不工作？**
A: 确保浏览器支持拖拽API，建议使用现代浏览器。

### 调试技巧

1. 检查浏览器开发者工具的控制台错误
2. 验证 JSON 配置格式是否正确
3. 确认中间件服务是否正确注册

## 🚀 技术实现

本功能基于以下技术栈：

- **后端**：Symfony + EasyAdmin + Twig扩展
- **前端**：原生JavaScript + Bootstrap CSS
- **数据格式**：JSON（向后兼容）

### 核心组件

- `MiddlewareConfigManager`：配置管理服务
- `MiddlewareCollectionField`：自定义字段类
- `MiddlewareConfigExtension`：Twig扩展
- `MiddlewareVisualConfig`：前端交互管理器

## 📄 更新日志

### v1.0.0 - 2024-01-15

- ✨ 全新可视化配置界面
- 🔧 支持 7 种内置中间件
- 🎨 响应式设计支持
- 📋 拖拽排序功能
- ✅ 实时配置验证

---

🎯 **提示**：该界面完全向后兼容，现有的 JSON 配置会自动转换为可视化界面。