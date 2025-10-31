<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HttpForwardBundle\Middleware\Auth\SetAuthHeaderMiddleware;
use Tourze\HttpForwardBundle\Middleware\Header\AddHeaderMiddleware;
use Tourze\HttpForwardBundle\Middleware\MiddlewareRegistry;
use Tourze\HttpForwardBundle\Service\MiddlewareValidator;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(MiddlewareValidator::class)]
#[RunTestsInSeparateProcesses]
final class MiddlewareValidatorTest extends AbstractIntegrationTestCase
{
    private MiddlewareValidator $validator;

    private MiddlewareRegistry $registry;

    public function testValidateMiddlewareConfigReturnsEmptyArrayForValidConfig(): void
    {
        $templates = [
            'set_auth_header' => [
                'fields' => [
                    'scheme' => [
                        'type' => 'text',
                        'required' => true,
                    ],
                    'token' => [
                        'type' => 'text',
                        'required' => true,
                    ],
                ],
            ],
        ];

        $middlewares = [
            [
                'name' => 'set_auth_header',
                'config' => [
                    'scheme' => 'Bearer',
                    'token' => 'test-token',
                ],
            ],
        ];

        $errors = $this->validator->validateMiddlewareConfig($middlewares, $templates);

        $this->assertEmpty($errors);
    }

    public function testValidateMiddlewareConfigDetectsMissingNameField(): void
    {
        $templates = [];
        $middlewares = [
            [
                'config' => [],
            ],
        ];

        $errors = $this->validator->validateMiddlewareConfig($middlewares, $templates);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('缺少必需的 name 字段', $errors[0]);
    }

    public function testValidateMiddlewareConfigDetectsInvalidNameType(): void
    {
        $templates = [];
        $middlewares = [
            [
                'name' => 123,
                'config' => [],
            ],
        ];

        $errors = $this->validator->validateMiddlewareConfig($middlewares, $templates);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('name 字段必须是字符串', $errors[0]);
    }

    public function testValidateMiddlewareConfigDetectsUnregisteredMiddleware(): void
    {
        $templates = [];
        $middlewares = [
            [
                'name' => 'nonexistent_middleware',
                'config' => [],
            ],
        ];

        $errors = $this->validator->validateMiddlewareConfig($middlewares, $templates);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('未注册或不可用', $errors[0]);
    }

    public function testValidateMiddlewareConfigDetectsInvalidConfigType(): void
    {
        $templates = [];
        $middlewares = [
            [
                'name' => 'set_auth_header',
                'config' => 'invalid',
            ],
        ];

        $errors = $this->validator->validateMiddlewareConfig($middlewares, $templates);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('配置必须是数组', $errors[0]);
    }

    public function testValidateMiddlewareConfigDetectsMissingRequiredField(): void
    {
        $templates = [
            'set_auth_header' => [
                'fields' => [
                    'token' => [
                        'type' => 'text',
                        'required' => true,
                    ],
                ],
            ],
        ];

        $middlewares = [
            [
                'name' => 'set_auth_header',
                'config' => [],
            ],
        ];

        $errors = $this->validator->validateMiddlewareConfig($middlewares, $templates);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('缺少必需字段', $errors[0]);
    }

    public function testValidateMiddlewareConfigValidatesBooleanType(): void
    {
        $templates = [
            'set_auth_header' => [
                'fields' => [
                    'enabled' => [
                        'type' => 'boolean',
                        'required' => false,
                    ],
                ],
            ],
        ];

        $middlewares = [
            [
                'name' => 'set_auth_header',
                'config' => [
                    'enabled' => 'true',
                ],
            ],
        ];

        $errors = $this->validator->validateMiddlewareConfig($middlewares, $templates);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('必须是布尔值', $errors[0]);
    }

    public function testValidateMiddlewareConfigValidatesTextType(): void
    {
        $templates = [
            'set_auth_header' => [
                'fields' => [
                    'token' => [
                        'type' => 'text',
                        'required' => false,
                    ],
                ],
            ],
        ];

        $middlewares = [
            [
                'name' => 'set_auth_header',
                'config' => [
                    'token' => 123,
                ],
            ],
        ];

        $errors = $this->validator->validateMiddlewareConfig($middlewares, $templates);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('必须是字符串', $errors[0]);
    }

    public function testValidateMiddlewareConfigValidatesChoiceType(): void
    {
        $templates = [
            'set_auth_header' => [
                'fields' => [
                    'mode' => [
                        'type' => 'choice',
                        'choices' => [
                            'Strict' => 'strict',
                            'Permissive' => 'permissive',
                        ],
                        'required' => false,
                    ],
                ],
            ],
        ];

        $middlewares = [
            [
                'name' => 'set_auth_header',
                'config' => [
                    'mode' => 'invalid',
                ],
            ],
        ];

        $errors = $this->validator->validateMiddlewareConfig($middlewares, $templates);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('值无效', $errors[0]);
    }

    public function testValidateMiddlewareConfigValidatesArrayType(): void
    {
        $templates = [
            'add_header' => [
                'fields' => [
                    'headers' => [
                        'type' => 'array',
                        'required' => false,
                    ],
                ],
            ],
        ];

        $middlewares = [
            [
                'name' => 'add_header',
                'config' => [
                    'headers' => 'not-an-array',
                ],
            ],
        ];

        $errors = $this->validator->validateMiddlewareConfig($middlewares, $templates);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('必须是数组', $errors[0]);
    }

    public function testValidateMiddlewareConfigHandlesMultipleErrors(): void
    {
        $templates = [
            'set_auth_header' => [
                'fields' => [
                    'token' => [
                        'type' => 'text',
                        'required' => true,
                    ],
                ],
            ],
        ];

        $middlewares = [
            [
                'name' => 'set_auth_header',
                'config' => [],
            ],
            [
                'name' => 'nonexistent',
                'config' => [],
            ],
        ];

        $errors = $this->validator->validateMiddlewareConfig($middlewares, $templates);

        $this->assertCount(2, $errors);
    }

    public function testValidateMiddlewareConfigAcceptsValidChoiceValue(): void
    {
        $templates = [
            'set_auth_header' => [
                'fields' => [
                    'mode' => [
                        'type' => 'choice',
                        'choices' => [
                            'Strict' => 'strict',
                            'Permissive' => 'permissive',
                        ],
                        'required' => false,
                    ],
                ],
            ],
        ];

        $middlewares = [
            [
                'name' => 'set_auth_header',
                'config' => [
                    'mode' => 'strict',
                ],
            ],
        ];

        $errors = $this->validator->validateMiddlewareConfig($middlewares, $templates);

        $this->assertEmpty($errors);
    }

    public function testValidateMiddlewareConfigAcceptsValidArrayValue(): void
    {
        $templates = [
            'add_header' => [
                'fields' => [
                    'headers' => [
                        'type' => 'array',
                        'required' => false,
                    ],
                ],
            ],
        ];

        $middlewares = [
            [
                'name' => 'add_header',
                'config' => [
                    'headers' => ['X-Custom' => 'value'],
                ],
            ],
        ];

        $errors = $this->validator->validateMiddlewareConfig($middlewares, $templates);

        $this->assertEmpty($errors);
    }

    protected function onSetUp(): void
    {
        // 从容器获取服务实例
        $this->registry = self::getService(MiddlewareRegistry::class);
        $this->validator = self::getService(MiddlewareValidator::class);

        // 注册测试所需的中间件
        $this->registry->register('set_auth_header', self::getService(SetAuthHeaderMiddleware::class));
        $this->registry->register('add_header', self::getService(AddHeaderMiddleware::class));
    }
}
