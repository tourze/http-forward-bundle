<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Enum\BackendStatus;
use Tourze\HttpForwardBundle\Repository\ForwardRuleRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @template-extends AbstractRepositoryTestCase<ForwardRule>
 * @internal
 */
#[CoversClass(ForwardRuleRepository::class)]
#[RunTestsInSeparateProcesses]
final class ForwardRuleRepositoryTest extends AbstractRepositoryTestCase
{
    private ?ForwardRuleRepository $repository = null;

    protected function getRepository(): ForwardRuleRepository
    {
        if (null === $this->repository) {
            $repository = self::getContainer()->get(ForwardRuleRepository::class);
            $this->assertInstanceOf(ForwardRuleRepository::class, $repository);
            $this->repository = $repository;
        }

        return $this->repository;
    }

    protected function getRepositoryClass(): string
    {
        return ForwardRuleRepository::class;
    }

    protected function getEntityClass(): string
    {
        return ForwardRule::class;
    }

    protected function onSetUp(): void
    {
        // 初始化测试环境
    }

    protected function createNewEntity(): ForwardRule
    {
        $rule = new ForwardRule();
        $rule->setName('Test Rule');
        $rule->setSourcePath('/test');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);
        $rule->setHttpMethods(['GET']);
        $rule->setEnabled(true);
        $rule->setPriority(100);

        return $rule;
    }

    public function testFindEnabledRulesOrderedByPriority(): void
    {
        $manager = self::getEntityManager();

        // 禁用所有现有的规则以避免影响测试
        foreach ($this->getRepository()->findAll() as $existingRule) {
            $existingRule->setEnabled(false);
        }
        $manager->flush();

        $rule1 = new ForwardRule();
        $rule1->setName('Low Priority');
        $rule1->setSourcePath('/low');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule1->addBackend($backend);
        $rule1->setHttpMethods(['GET']);
        $rule1->setEnabled(true);
        $rule1->setPriority(10);
        $manager->persist($rule1);

        $rule2 = new ForwardRule();
        $rule2->setName('High Priority');
        $rule2->setSourcePath('/high');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule2->addBackend($backend);
        $rule2->setHttpMethods(['GET']);
        $rule2->setEnabled(true);
        $rule2->setPriority(100);
        $manager->persist($rule2);

        $manager->flush();

        $rules = $this->getRepository()->findEnabledRulesOrderedByPriority();

        $this->assertCount(2, $rules);
        $this->assertEquals('High Priority', $rules[0]->getName());
        $this->assertEquals('Low Priority', $rules[1]->getName());
    }

    public function testSave(): void
    {
        $rule = new ForwardRule();
        $rule->setName('Save Test');
        $rule->setSourcePath('/save');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);
        $rule->setHttpMethods(['GET']);
        $rule->setEnabled(true);
        $rule->setPriority(100);

        $this->getRepository()->save($rule);

        $this->assertNotNull($rule->getId());
        $foundRule = $this->getRepository()->find($rule->getId());
        $this->assertNotNull($foundRule);
        $this->assertEquals('Save Test', $foundRule->getName());
    }

    public function testFindEnabledRules(): void
    {
        $manager = self::getEntityManager();

        // 禁用所有现有的规则以避免影响测试
        foreach ($this->getRepository()->findAll() as $existingRule) {
            $existingRule->setEnabled(false);
        }
        $manager->flush();

        $enabledRule = new ForwardRule();
        $enabledRule->setName('Enabled Rule');
        $enabledRule->setSourcePath('/enabled/*');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://enabled.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $enabledRule->addBackend($backend);
        $enabledRule->setHttpMethods(['GET']);
        $enabledRule->setEnabled(true);
        $enabledRule->setPriority(100);
        $manager->persist($enabledRule);

        $disabledRule = new ForwardRule();
        $disabledRule->setName('Disabled Rule');
        $disabledRule->setSourcePath('/disabled/*');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://disabled.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $disabledRule->addBackend($backend);
        $disabledRule->setHttpMethods(['GET']);
        $disabledRule->setEnabled(false);
        $disabledRule->setPriority(100);
        $manager->persist($disabledRule);

        $manager->flush();

        $enabledRules = $this->getRepository()->findEnabledRules();

        $this->assertCount(1, $enabledRules);
        $this->assertEquals('Enabled Rule', $enabledRules[0]->getName());
    }

    public function testFindByPathPattern(): void
    {
        $manager = self::getEntityManager();

        // 清理可能存在的匹配规则
        foreach ($this->getRepository()->findAll() as $existingRule) {
            $manager->remove($existingRule);
        }
        $manager->flush();

        $rule1 = new ForwardRule();
        $rule1->setName('API Rule');
        $rule1->setSourcePath('/api/*');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://api.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule1->addBackend($backend);
        $rule1->setHttpMethods(['GET']);
        $rule1->setEnabled(true);
        $rule1->setPriority(100);
        $manager->persist($rule1);

        $rule2 = new ForwardRule();
        $rule2->setName('Admin Rule');
        $rule2->setSourcePath('/admin/*');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://admin.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule2->addBackend($backend);
        $rule2->setHttpMethods(['GET']);
        $rule2->setEnabled(true);
        $rule2->setPriority(90);
        $manager->persist($rule2);

        $manager->flush();

        $apiRules = $this->getRepository()->findByPathPattern('/api');

        $this->assertCount(1, $apiRules);
        $this->assertEquals('API Rule', $apiRules[0]->getName());
    }

    public function testFindByHttpMethod(): void
    {
        $manager = self::getEntityManager();

        // 清理可能存在的规则
        foreach ($this->getRepository()->findAll() as $existingRule) {
            $manager->remove($existingRule);
        }
        $manager->flush();

        $getRule = new ForwardRule();
        $getRule->setName('GET Rule');
        $getRule->setSourcePath('/get/*');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://get.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $getRule->addBackend($backend);
        $getRule->setHttpMethods(['GET']);
        $getRule->setEnabled(true);
        $getRule->setPriority(100);
        $manager->persist($getRule);

        $postRule = new ForwardRule();
        $postRule->setName('POST Rule');
        $postRule->setSourcePath('/post/*');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://post.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $postRule->addBackend($backend);
        $postRule->setHttpMethods(['POST']);
        $postRule->setEnabled(true);
        $postRule->setPriority(100);
        $manager->persist($postRule);

        $manager->flush();

        $postRules = $this->getRepository()->findByHttpMethod('POST');

        $this->assertCount(1, $postRules);
        $this->assertEquals('POST Rule', $postRules[0]->getName());
    }

    public function testRemove(): void
    {
        $manager = self::getEntityManager();

        $rule = new ForwardRule();
        $rule->setName('To Remove');
        $rule->setSourcePath('/remove/*');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://remove.example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);
        $rule->setHttpMethods(['GET']);
        $rule->setEnabled(true);
        $rule->setPriority(100);
        $manager->persist($rule);
        $manager->flush();

        $id = $rule->getId();
        $this->assertNotNull($this->getRepository()->find($id));

        $this->getRepository()->remove($rule);

        $this->assertNull($this->getRepository()->find($id));
    }

    public function testUpdate(): void
    {
        $manager = self::getEntityManager();

        $rule = new ForwardRule();
        $rule->setName('Update Test');
        $rule->setSourcePath('/update');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);
        $rule->setHttpMethods(['GET']);
        $rule->setEnabled(true);
        $rule->setPriority(100);

        $this->getRepository()->save($rule);
        $id = $rule->getId();
        $this->assertNotNull($id);

        // 修改规则
        $rule->setName('Updated Name');
        $rule->setPriority(200);
        $this->getRepository()->update($rule);

        // 清除实体管理器缓存并重新查询
        $manager->clear();
        $updatedRule = $this->getRepository()->find($id);

        $this->assertNotNull($updatedRule);
        $this->assertSame('Updated Name', $updatedRule->getName());
        $this->assertSame(200, $updatedRule->getPriority());
    }

    public function testUpdateWithoutFlush(): void
    {
        $manager = self::getEntityManager();

        $rule = new ForwardRule();
        $rule->setName('Update No Flush');
        $rule->setSourcePath('/update-no-flush');

        // 创建Backend并关联到规则
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://example.com');
        $backend->setWeight(100);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $rule->addBackend($backend);
        $rule->setHttpMethods(['GET']);
        $rule->setEnabled(true);
        $rule->setPriority(100);

        $this->getRepository()->save($rule);
        $id = $rule->getId();
        $this->assertNotNull($id);

        // 修改规则但不flush
        $rule->setName('Modified Name');
        $this->getRepository()->update($rule, false);

        // 验证entity在内存中已经修改
        $this->assertSame('Modified Name', $rule->getName());

        // 手动flush后再查询，应该是新名称
        $manager->flush();
        $manager->clear();
        $nowUpdated = $this->getRepository()->find($id);
        $this->assertNotNull($nowUpdated);
        $this->assertSame('Modified Name', $nowUpdated->getName());
    }
}
