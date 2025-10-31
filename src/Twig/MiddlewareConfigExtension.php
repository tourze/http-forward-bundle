<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Twig;

use Tourze\HttpForwardBundle\Service\MiddlewareConfigManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MiddlewareConfigExtension extends AbstractExtension
{
    public function __construct(
        private readonly MiddlewareConfigManager $middlewareConfigManager,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('middleware_config_helper', $this->renderMiddlewareHelper(...), ['is_safe' => ['html']]),
            new TwigFunction('middleware_templates', $this->getMiddlewareTemplates(...)),
        ];
    }

    public function renderMiddlewareHelper(): string
    {
        $templates = $this->middlewareConfigManager->getMiddlewareConfigTemplates();
        $availableMiddlewares = $this->middlewareConfigManager->getAvailableMiddlewares();

        $html = '<div id="middleware-config-helper" style="display: none;">';
        $html .= '<script type="application/json" id="middleware-templates-data">';
        $html .= json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $html .= '</script>';
        $html .= '<script type="application/json" id="middleware-available-data">';
        $html .= json_encode($availableMiddlewares, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $html .= '</script>';
        $html .= '</div>';

        $html .= $this->renderJavaScript();
        $html .= $this->renderCSS();

        return $html;
    }

    private function renderJavaScript(): string
    {
        return <<<'JAVASCRIPT'
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // 中间件可视化配置管理器
                const MiddlewareVisualConfig = {
                    templates: {},
                    available: {},
                    instances: new Map(), // 每个textarea一个实例

                    init: function() {
                        const templatesData = document.getElementById('middleware-templates-data');
                        const availableData = document.getElementById('middleware-available-data');

                        if (templatesData) {
                            this.templates = JSON.parse(templatesData.textContent);
                        }
                        if (availableData) {
                            this.available = JSON.parse(availableData.textContent);
                        }

                        this.enhanceMiddlewareFields();

                        // 延迟执行强制隐藏，确保所有DOM元素都已加载
                        setTimeout(() => {
                            this.forceHideJsonEditors();
                        }, 500);

                        // 使用MutationObserver监听DOM变化
                        this.startDOMObserver();
                    },

                    enhanceMiddlewareFields: function() {
                        // 更精确的选择器，查找所有可能的middleware字段
                        const selectors = [
                            'textarea[name*="middlewares"]',
                            'textarea[name*="middlewareJson"]',
                            'textarea[name*="middlewaresJson"]',
                            'textarea[id*="middleware"]',
                            '.field-code_editor textarea[name*="middleware"]',
                            '.form-group textarea[name*="middleware"]'
                        ];

                        let foundFields = [];
                        selectors.forEach(selector => {
                            const fields = document.querySelectorAll(selector);
                            fields.forEach(field => {
                                if (!foundFields.includes(field)) {
                                    foundFields.push(field);
                                    console.log(`🔍 找到 middleware 字段:`, {
                                        name: field.name,
                                        id: field.id,
                                        value: field.value.substring(0, 100) + '...'
                                    });
                                }
                            });
                        });

                        foundFields.forEach(field => {
                            this.createVisualInterface(field);
                        });

                        console.log(`✅ 总共找到 ${foundFields.length} 个 middleware 字段`);

                        // 添加表单提交拦截器
                        this.interceptFormSubmission();
                    },

                    forceHideJsonEditors: function() {
                        console.log('🔧 强制隐藏JSON编辑器...');

                        // 简化选择器，避免使用不兼容的CSS选择器
                        try {
                            // 隐藏所有中间件相关的textarea
                            const textareas = document.querySelectorAll('textarea[name*="middleware"]');
                            textareas.forEach(textarea => {
                                textarea.style.display = 'none';
                                textarea.setAttribute('data-middleware-enhanced', 'true');
                                console.log('🙈 隐藏textarea:', textarea.name);
                            });

                            // 隐藏CodeMirror编辑器
                            const codeMirrors = document.querySelectorAll('.CodeMirror');
                            codeMirrors.forEach(cm => {
                                const parent = cm.closest('div');
                                if (parent && parent.querySelector('textarea[name*="middleware"]')) {
                                    cm.classList.add('middleware-hidden');
                                    console.log('🙈 隐藏CodeMirror:', cm);
                                }
                            });

                            // 隐藏help文本
                            const helpElements = document.querySelectorAll('.form-help, .help-text, .form-text');
                            helpElements.forEach(help => {
                                if (help.textContent && help.textContent.includes('中间件')) {
                                    help.classList.add('middleware-hidden');
                                    console.log('🙈 隐藏help文本:', help);
                                }
                            });

                        } catch (e) {
                            console.error('隐藏编辑器时出错:', e);
                        }
                    },

                    startDOMObserver: function() {
                        // 监听DOM变化，确保动态添加的元素也被隐藏
                        if (typeof MutationObserver !== 'undefined') {
                            const observer = new MutationObserver((mutations) => {
                                let needsHiding = false;
                                mutations.forEach(mutation => {
                                    mutation.addedNodes.forEach(node => {
                                        if (node.nodeType === 1) { // 元素节点
                                            // 检查是否是CodeMirror或包含中间件相关的元素
                                            if (node.classList && (
                                                node.classList.contains('CodeMirror') ||
                                                node.querySelector && node.querySelector('textarea[name*="middleware"]')
                                            )) {
                                                needsHiding = true;
                                            }
                                        }
                                    });
                                });

                                if (needsHiding) {
                                    console.log('🔍 检测到新的DOM元素，重新隐藏...');
                                    setTimeout(() => {
                                        this.forceHideJsonEditors();
                                    }, 100);
                                }
                            });

                            observer.observe(document.body, {
                                childList: true,
                                subtree: true
                            });

                            console.log('👁️ DOM观察器已启动');
                        }
                    },

                    interceptFormSubmission: function() {
                        const form = document.querySelector('form[name="ForwardRule"]');
                        if (form) {
                            form.addEventListener('submit', (e) => {
                                console.log('🚀 表单即将提交！检查所有textarea数据...');

                                // 查找所有middleware相关的textarea
                                const selectors = [
                                    'textarea[name*="middlewares"]',
                                    'textarea[name*="middlewareJson"]',
                                    'textarea[name*="middlewaresJson"]',
                                    'textarea[id*="middleware"]'
                                ];

                                let allTextareas = [];
                                selectors.forEach(selector => {
                                    const textareas = document.querySelectorAll(selector);
                                    textareas.forEach(textarea => {
                                        if (!allTextareas.includes(textarea)) {
                                            allTextareas.push(textarea);
                                        }
                                    });
                                });

                                console.log(`🔍 准备检查 ${allTextareas.length} 个 middleware textarea...`);

                                allTextareas.forEach((textarea, index) => {
                                    const textareaId = this.getTextareaId(textarea);
                                    const instance = this.getInstance(textareaId);

                                    console.log(`📋 Textarea #${index + 1} - ${textareaId}:`);
                                    console.log(`   - name: ${textarea.name}`);
                                    console.log(`   - id: ${textarea.id}`);
                                    console.log(`   - 当前值: ${textarea.value}`);
                                    console.log(`   - 内存数据: ${JSON.stringify(instance.currentData)}`);
                                    console.log(`   - 数据长度: ${instance.currentData.length} 个中间件`);

                                    // 强制同步数据
                                    this.syncToTextarea(textarea);

                                    console.log(`   - 同步后的值: ${textarea.value}`);
                                });

                                console.log('✅ 数据检查完成，继续提交...');
                            });
                        }
                    },

                    createVisualInterface: function(textarea) {
                        if (textarea.dataset.middlewareEnhanced) return;
                        textarea.dataset.middlewareEnhanced = 'true';

                        // 隐藏原始 textarea
                        textarea.style.display = 'none';

                        // 查找并隐藏整个字段容器
                        let fieldContainer = null;
                        try {
                            fieldContainer = textarea.closest('.form-group') ||
                                           textarea.closest('.field-group') ||
                                           textarea.closest('.field-code_editor') ||
                                           textarea.closest('.form-widget');

                            if (fieldContainer) {
                                // 完全隐藏整个字段容器
                                fieldContainer.style.display = 'none';
                                fieldContainer.setAttribute('data-original-middleware-field', 'true');
                                console.log('🙈 隐藏整个字段容器:', fieldContainer);
                            }
                        } catch (e) {
                            console.warn('查找字段容器时出错:', e);
                        }

                        // 为这个 textarea 创建独立的数据实例
                        const textareaId = this.getTextareaId(textarea);
                        this.instances.set(textareaId, { currentData: [] });

                        // 载入现有数据
                        this.loadExistingData(textarea);

                        // 创建可视化界面
                        const visualInterface = this.createVisualPanel(textarea);

                        // 找到合适的插入位置
                        try {
                            if (fieldContainer && fieldContainer.parentNode) {
                                // 如果找到了字段容器，在其前面插入
                                fieldContainer.parentNode.insertBefore(visualInterface, fieldContainer);
                            } else if (textarea.parentNode) {
                                // 否则在textarea前面插入
                                textarea.parentNode.insertBefore(visualInterface, textarea);
                            } else {
                                console.warn('无法找到合适的插入位置');
                            }
                        } catch (e) {
                            console.error('插入可视化界面时出错:', e);
                            // 降级处理：直接添加到body末尾
                            document.body.appendChild(visualInterface);
                        }

                        // 初始渲染
                        this.renderMiddlewareList(textarea);
                    },

                    getTextareaId: function(textarea) {
                        return textarea.id || textarea.name || 'middleware_' + Date.now();
                    },

                    getInstance: function(textareaId) {
                        if (!this.instances.has(textareaId)) {
                            this.instances.set(textareaId, { currentData: [] });
                        }
                        return this.instances.get(textareaId);
                    },

                    loadExistingData: function(textarea) {
                        const textareaId = this.getTextareaId(textarea);
                        const instance = this.getInstance(textareaId);

                        console.log('📖 载入现有数据 for textarea:', textareaId);
                        console.log('📄 Textarea 原始值:', textarea.value);

                        try {
                            if (textarea.value.trim()) {
                                const parsed = JSON.parse(textarea.value);
                                instance.currentData = this.normalizeMiddlewareData(parsed);
                            } else {
                                instance.currentData = [];
                            }
                        } catch (e) {
                            console.warn('❌ JSON 解析错误:', e);
                            instance.currentData = [];
                        }

                        console.log('✅ 载入的数据:', instance.currentData);
                    },

                    normalizeMiddlewareData: function(data) {
                        // 新功能，直接使用新格式，不需要兼容性转换
                        if (!Array.isArray(data)) {
                            return [];
                        }

                        return data.filter(item =>
                            item && typeof item === 'object' &&
                            typeof item.name === 'string'
                        ).map(item => ({
                            name: item.name,
                            config: item.config || {}
                        }));
                    },

                    createVisualPanel: function(textarea) {
                        const textareaId = this.getTextareaId(textarea);
                        const panel = document.createElement('div');
                        panel.className = 'middleware-visual-config';
                        panel.dataset.textareaId = textareaId;

                        panel.innerHTML = `
                            <div class="middleware-config-header">
                                <h6><i class="fa fa-cogs"></i> 中间件配置</h6>
                                <div class="middleware-actions">
                                    <select class="form-select form-select-sm middleware-selector">
                                        <option value="">选择要添加的中间件...</option>
                                        ${this.generateMiddlewareOptions()}
                                    </select>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="MiddlewareVisualConfig.addMiddleware(this)">
                                        <i class="fa fa-plus"></i> 添加
                                    </button>
                                </div>
                            </div>
                            <div class="middleware-list" id="middleware-list-${textareaId}">
                                <!-- 中间件列表将在这里渲染 -->
                            </div>
                            <div class="middleware-config-footer">
                                <small class="text-muted">
                                    <i class="fa fa-info-circle"></i>
                                    拖拽可调整执行顺序，优先级高的中间件会先执行
                                </small>
                            </div>
                        `;

                        return panel;
                    },

                    generateMiddlewareOptions: function() {
                        let options = '';
                        Object.entries(this.templates).forEach(([key, template]) => {
                            options += `<option value="${key}" data-priority="${template.priority}">${template.label}</option>`;
                        });
                        return options;
                    },

                    renderMiddlewareList: function(textarea) {
                        const textareaId = this.getTextareaId(textarea);
                        const instance = this.getInstance(textareaId);
                        const listContainer = document.getElementById(`middleware-list-${textareaId}`);
                        if (!listContainer) return;

                        if (instance.currentData.length === 0) {
                            listContainer.innerHTML = `
                                <div class="middleware-empty-state">
                                    <div class="text-center py-4">
                                        <i class="fa fa-cube fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">还没有配置任何中间件</p>
                                        <p class="text-muted small">从上方的下拉菜单中选择要添加的中间件</p>
                                    </div>
                                </div>
                            `;
                            return;
                        }

                        let html = '';
                        instance.currentData.forEach((middleware, index) => {
                            html += this.renderMiddlewareItem(middleware, index, textarea);
                        });

                        listContainer.innerHTML = html;
                        this.initializeSortable(listContainer, textarea);
                    },

                    renderMiddlewareItem: function(middleware, index, textarea) {
                        const textareaId = this.getTextareaId(textarea);
                        const template = this.templates[middleware.name] || {};
                        const config = middleware.config || {};

                        return `
                            <div class="middleware-item" data-index="${index}">
                                <div class="middleware-item-header">
                                    <div class="middleware-item-info">
                                        <i class="fa fa-grip-vertical middleware-drag-handle"></i>
                                        <div class="middleware-item-details">
                                            <div class="middleware-item-name">${template.label || middleware.name}</div>
                                            <div class="middleware-item-description">${template.description || ''}</div>
                                            <div class="middleware-item-meta">
                                                <span class="badge bg-secondary">优先级: ${template.priority || 0}</span>
                                                <span class="badge bg-info">${middleware.name}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="middleware-item-actions">
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                onclick="MiddlewareVisualConfig.toggleConfig(${index}, '${textareaId}')">
                                            <i class="fa fa-cog"></i> 配置
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                onclick="MiddlewareVisualConfig.removeMiddleware(${index}, '${textareaId}')">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="middleware-item-config" id="config-${index}-${textareaId}" style="display: none;">
                                    ${this.renderConfigForm(middleware, index, textarea)}
                                </div>
                            </div>
                        `;
                    },

                    renderConfigForm: function(middleware, index, textarea) {
                        const textareaId = this.getTextareaId(textarea);
                        const template = this.templates[middleware.name] || {};
                        const config = middleware.config || {};

                        if (!template.fields || Object.keys(template.fields).length === 0) {
                            return `
                                <div class="middleware-config-content">
                                    <div class="alert alert-info">
                                        <i class="fa fa-info-circle"></i>
                                        此中间件不需要额外配置
                                    </div>
                                </div>
                            `;
                        }

                        let html = '<div class="middleware-config-content"><div class="row">';

                        Object.entries(template.fields).forEach(([fieldKey, fieldTemplate]) => {
                            const value = config[fieldKey] !== undefined ? config[fieldKey] : (fieldTemplate.default || '');
                            const fieldId = `field-${index}-${fieldKey}-${textareaId}`;

                            html += `<div class="col-md-6">`;
                            html += `<div class="form-group mb-3">`;
                            html += `<label for="${fieldId}" class="form-label">${fieldTemplate.label}</label>`;

                            switch (fieldTemplate.type) {
                                case 'boolean':
                                    html += `
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="${fieldId}"
                                                   ${value ? 'checked' : ''}
                                                   onchange="MiddlewareVisualConfig.updateFieldValue('${textareaId}', ${index}, '${fieldKey}', this.checked)">
                                            <label class="form-check-label" for="${fieldId}">启用</label>
                                        </div>
                                    `;
                                    break;

                                case 'choice':
                                    html += `<select class="form-select" id="${fieldId}"
                                                    onchange="MiddlewareVisualConfig.updateFieldValue('${textareaId}', ${index}, '${fieldKey}', this.value)">`;
                                    Object.entries(fieldTemplate.choices || {}).forEach(([label, choiceValue]) => {
                                        html += `<option value="${choiceValue}" ${value === choiceValue ? 'selected' : ''}>${label}</option>`;
                                    });
                                    html += `</select>`;
                                    break;

                                case 'array':
                                    const arrayValue = Array.isArray(value) ? value.join('\\n') : '';
                                    html += `
                                        <textarea class="form-control" id="${fieldId}" rows="3"
                                                  onchange="MiddlewareVisualConfig.updateArrayValue('${textareaId}', ${index}, '${fieldKey}', this.value)">${arrayValue}</textarea>
                                        <div class="form-text">每行一个值</div>
                                    `;
                                    break;

                                case 'collection':
                                    const collectionValue = typeof value === 'object' && value !== null ? JSON.stringify(value, null, 2) : '{}';
                                    html += `
                                        <textarea class="form-control" id="${fieldId}" rows="4"
                                                  onchange="MiddlewareVisualConfig.updateJsonValue('${textareaId}', ${index}, '${fieldKey}', this.value)">${collectionValue}</textarea>
                                        <div class="form-text">JSON格式的键值对</div>
                                    `;
                                    break;

                                default:
                                    html += `
                                        <input type="text" class="form-control" id="${fieldId}" value="${value || ''}"
                                               onchange="MiddlewareVisualConfig.updateFieldValue('${textareaId}', ${index}, '${fieldKey}', this.value)">
                                    `;
                            }

                            html += `</div></div>`;
                        });

                        html += '</div></div>';
                        return html;
                    },

                    addMiddleware: function(button) {
                        const select = button.previousElementSibling;
                        const middlewareName = select.value;

                        if (!middlewareName) return;

                        console.log('➕ 添加中间件:', middlewareName);

                        const template = this.templates[middlewareName] || {};
                        const defaultConfig = {};

                        // 设置默认值
                        Object.entries(template.fields || {}).forEach(([key, field]) => {
                            if (field.default !== undefined) {
                                defaultConfig[key] = field.default;
                            }
                        });

                        const newMiddleware = {
                            name: middlewareName,
                            config: defaultConfig
                        };

                        // 找到对应的textarea
                        const panel = button.closest('.middleware-visual-config');
                        const textareaId = panel.dataset.textareaId;
                        const instance = this.getInstance(textareaId);
                        const textarea = document.querySelector(`[id="${textareaId}"], [name="${textareaId}"]`);

                        console.log('📝 添加前的数据:', [...instance.currentData]);
                        instance.currentData.push(newMiddleware);
                        console.log('📝 添加后的数据:', [...instance.currentData]);

                        this.syncToTextarea(textarea);
                        this.renderMiddlewareList(textarea);

                        select.value = '';
                    },

                    removeMiddleware: function(index, textareaId) {
                        const instance = this.getInstance(textareaId);
                        instance.currentData.splice(index, 1);

                        const textarea = document.querySelector(`[id="${textareaId}"], [name="${textareaId}"]`);
                        this.syncToTextarea(textarea);
                        this.renderMiddlewareList(textarea);
                    },

                    toggleConfig: function(index, textareaId) {
                        const configDiv = document.getElementById(`config-${index}-${textareaId}`);
                        if (configDiv) {
                            configDiv.style.display = configDiv.style.display === 'none' ? 'block' : 'none';
                        }
                    },

                    updateFieldValue: function(textareaId, index, fieldKey, value) {
                        const instance = this.getInstance(textareaId);
                        if (instance.currentData[index] && instance.currentData[index].config) {
                            console.log(`🔧 更新字段 ${fieldKey} = ${value} (中间件: ${instance.currentData[index].name})`);

                            instance.currentData[index].config[fieldKey] = value;
                            const textarea = document.querySelector(`[id="${textareaId}"], [name="${textareaId}"]`);
                            this.syncToTextarea(textarea);
                        } else {
                            console.warn(`❌ 无法更新字段 ${fieldKey}: 中间件 ${index} 不存在`);
                        }
                    },

                    updateArrayValue: function(textareaId, index, fieldKey, value) {
                        const arrayValue = value.split('\\n').filter(v => v.trim());
                        this.updateFieldValue(textareaId, index, fieldKey, arrayValue);
                    },

                    updateJsonValue: function(textareaId, index, fieldKey, value) {
                        try {
                            const jsonValue = JSON.parse(value);
                            this.updateFieldValue(textareaId, index, fieldKey, jsonValue);
                        } catch (e) {
                            console.warn('Invalid JSON:', e);
                        }
                    },

                    syncToTextarea: function(textarea) {
                        if (textarea) {
                            const textareaId = this.getTextareaId(textarea);
                            const instance = this.getInstance(textareaId);

                            // 调试信息
                            console.log('🔄 同步数据到 textarea:', textareaId);
                            console.log('📊 当前数据:', instance.currentData);

                            textarea.value = JSON.stringify(instance.currentData, null, 2);
                            textarea.dispatchEvent(new Event('input'));

                            console.log('✅ 已更新 textarea 值:', textarea.value);
                        }
                    },

                    initializeSortable: function(container, textarea) {
                        // 简单的拖拽排序实现
                        let draggedElement = null;
                        const self = this;

                        container.querySelectorAll('.middleware-item').forEach(item => {
                            item.draggable = true;

                            item.addEventListener('dragstart', (e) => {
                                draggedElement = item;
                                item.classList.add('dragging');
                            });

                            item.addEventListener('dragend', (e) => {
                                item.classList.remove('dragging');
                                draggedElement = null;
                            });

                            item.addEventListener('dragover', (e) => {
                                e.preventDefault();
                            });

                            item.addEventListener('drop', (e) => {
                                e.preventDefault();
                                if (draggedElement && draggedElement !== item) {
                                    const draggedIndex = parseInt(draggedElement.dataset.index);
                                    const targetIndex = parseInt(item.dataset.index);

                                    // 重新排序数据
                                    const textareaId = self.getTextareaId(textarea);
                                    const instance = self.getInstance(textareaId);
                                    const draggedData = instance.currentData.splice(draggedIndex, 1)[0];
                                    instance.currentData.splice(targetIndex, 0, draggedData);

                                    self.syncToTextarea(textarea);
                                    self.renderMiddlewareList(textarea);
                                }
                            });
                        });
                    }
                };

                // 全局暴露并初始化
                window.MiddlewareVisualConfig = MiddlewareVisualConfig;
                MiddlewareVisualConfig.init();
            });
            </script>
            JAVASCRIPT;
    }

    private function renderCSS(): string
    {
        return <<<'CSS'
            <style>
            /* 中间件可视化配置界面 */
            .middleware-visual-config {
                border: 1px solid #dee2e6;
                border-radius: 0.5rem;
                background-color: #ffffff;
                overflow: hidden;
            }

            .middleware-config-header {
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border-bottom: 1px solid #dee2e6;
                padding: 1rem;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .middleware-config-header h6 {
                margin: 0;
                color: #495057;
                font-weight: 600;
            }

            .middleware-actions {
                display: flex;
                gap: 0.5rem;
                align-items: center;
            }

            .middleware-selector {
                min-width: 200px;
            }

            /* 中间件列表 */
            .middleware-list {
                min-height: 200px;
                max-height: 600px;
                overflow-y: auto;
            }

            .middleware-empty-state {
                background-color: #f8f9fa;
                border: 2px dashed #dee2e6;
                border-radius: 0.375rem;
                margin: 1rem;
                transition: all 0.3s ease;
            }

            .middleware-empty-state:hover {
                border-color: #adb5bd;
                background-color: #f1f3f4;
            }

            /* 中间件项目 */
            .middleware-item {
                border-bottom: 1px solid #f1f3f4;
                transition: all 0.2s ease;
                cursor: grab;
            }

            .middleware-item:last-child {
                border-bottom: none;
            }

            .middleware-item:hover {
                background-color: #f8f9fa;
            }

            .middleware-item.dragging {
                opacity: 0.5;
                transform: rotate(2deg);
            }

            .middleware-item-header {
                padding: 1rem;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .middleware-item-info {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                flex: 1;
            }

            .middleware-drag-handle {
                color: #6c757d;
                font-size: 1.2em;
                cursor: grab;
                transition: color 0.2s ease;
            }

            .middleware-drag-handle:hover {
                color: #495057;
            }

            .middleware-item-details {
                flex: 1;
            }

            .middleware-item-name {
                font-weight: 600;
                color: #212529;
                margin-bottom: 0.25rem;
            }

            .middleware-item-description {
                font-size: 0.875rem;
                color: #6c757d;
                margin-bottom: 0.5rem;
            }

            .middleware-item-meta {
                display: flex;
                gap: 0.5rem;
            }

            .middleware-item-meta .badge {
                font-size: 0.75rem;
            }

            .middleware-item-actions {
                display: flex;
                gap: 0.25rem;
            }

            /* 中间件配置表单 */
            .middleware-item-config {
                border-top: 1px solid #f1f3f4;
                background-color: #f8f9fa;
                animation: slideDown 0.3s ease;
            }

            @keyframes slideDown {
                from {
                    opacity: 0;
                    max-height: 0;
                }
                to {
                    opacity: 1;
                    max-height: 500px;
                }
            }

            .middleware-config-content {
                padding: 1.5rem;
            }

            .middleware-config-content .form-group {
                margin-bottom: 1rem;
            }

            .middleware-config-content .form-label {
                font-weight: 500;
                color: #495057;
                margin-bottom: 0.5rem;
            }

            .middleware-config-content .form-control,
            .middleware-config-content .form-select {
                border-radius: 0.375rem;
                border: 1px solid #ced4da;
                transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            }

            .middleware-config-content .form-control:focus,
            .middleware-config-content .form-select:focus {
                border-color: #86b7fe;
                box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
            }

            .middleware-config-content .form-text {
                font-size: 0.8rem;
                color: #6c757d;
                margin-top: 0.25rem;
            }

            .middleware-config-content .alert {
                margin-bottom: 0;
                border-radius: 0.375rem;
            }

            /* 页脚 */
            .middleware-config-footer {
                background-color: #f8f9fa;
                border-top: 1px solid #dee2e6;
                padding: 0.75rem 1rem;
                text-align: center;
            }

            /* 响应式设计 */
            @media (max-width: 768px) {
                .middleware-config-header {
                    flex-direction: column;
                    gap: 1rem;
                    align-items: stretch;
                }

                .middleware-actions {
                    flex-direction: column;
                }

                .middleware-selector {
                    min-width: unset;
                }

                .middleware-item-header {
                    flex-direction: column;
                    align-items: stretch;
                    gap: 1rem;
                }

                .middleware-item-info {
                    flex-direction: column;
                    align-items: flex-start;
                }

                .middleware-config-content .row {
                    margin: 0;
                }

                .middleware-config-content .col-md-6 {
                    padding: 0;
                    margin-bottom: 1rem;
                }
            }

            /* 动画效果 */
            .middleware-item {
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .middleware-item:hover .middleware-drag-handle {
                transform: scale(1.1);
            }

            .btn {
                transition: all 0.15s ease-in-out;
            }

            .btn:hover {
                transform: translateY(-1px);
            }

            /* 加载状态 */
            .middleware-loading {
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 2rem;
                color: #6c757d;
            }

            .middleware-loading::after {
                content: '';
                width: 20px;
                height: 20px;
                border: 2px solid #f3f3f3;
                border-top: 2px solid #007bff;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin-left: 0.5rem;
            }

            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            /* 强制隐藏中间件字段的JSON编辑器 */
            textarea[name*="middleware"][data-middleware-enhanced="true"] {
                display: none !important;
            }

            /* 隐藏被标记的CodeMirror编辑器 */
            .CodeMirror.middleware-hidden {
                display: none !important;
            }

            /* 隐藏被标记的help文本 */
            .form-help.middleware-hidden,
            .help-text.middleware-hidden,
            .form-text.middleware-hidden {
                display: none !important;
            }
            </style>
            CSS;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getMiddlewareTemplates(): array
    {
        return $this->middlewareConfigManager->getMiddlewareConfigTemplates();
    }
}
