<?php

declare(strict_types=1);

namespace MsgPhp\Domain\Tests\Entity;

use MsgPhp\Domain\Entity\ClassMappingEntityFactory;
use MsgPhp\Domain\Exception\UnknownEntityException;
use MsgPhp\Domain\Infra\InMemory\DomainId;
use PHPUnit\Framework\TestCase;

final class ClassMappingEntityFactoryTest extends TestCase
{
    public function testCreate()
    {
        $factory = new ClassMappingEntityFactory(['Foo' => TestEntity::class], []);
        $entity = $factory->create('Foo', ['arg_a' => 1, 'arg_b' => 'foo', 'bar' => 'baz']);

        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertSame(1, $entity->a);
        $this->assertSame('foo', $entity->b);

        $entityCi = $factory->create('\fOO', ['arg_a' => 1, 'arg_b' => 'foo', 'bar' => 'baz']);

        $this->assertInstanceOf(TestEntity::class, $entityCi);
        $this->assertSame(1, $entityCi->a);
        $this->assertSame('foo', $entityCi->b);

        $this->assertNotSame($entity, $entityCi);
    }

    public function testCreateWithUnknownEntity()
    {
        $factory = new ClassMappingEntityFactory(['Foo' => TestEntity::class], []);

        $this->expectException(UnknownEntityException::class);

        $factory->create('Bar');
    }

    public function testIdentify()
    {
        $factory = new ClassMappingEntityFactory(['FooId' => DomainId::class], ['Foo' => 'FooId']);
        $entityId = $factory->identify('Foo', '1');

        $this->assertInstanceOf(DomainId::class, $entityId);
        $this->assertSame('1', $entityId->toString());
    }

    public function testIdentifyWithUnknownEntity()
    {
        $factory = new ClassMappingEntityFactory(['FooId' => DomainId::class], ['Foo' => 'FooId']);

        $this->expectException(UnknownEntityException::class);

        $factory->identify('Bar', '1');
    }

    public function testNextIdentity()
    {
        $factory = new ClassMappingEntityFactory(['FooId' => DomainId::class], ['Foo' => 'FooId']);
        $entityId1 = $factory->nextIdentity('Foo');
        $entityId2 = $factory->nextIdentity('Foo');

        $this->assertInstanceOf(DomainId::class, $entityId1);
        $this->assertInstanceOf(DomainId::class, $entityId2);
        $this->assertFalse($entityId1->equals($entityId2));
    }

    public function testNextIdentityUnknownEntity()
    {
        $factory = new ClassMappingEntityFactory(['FooId' => DomainId::class], ['Foo' => 'FooId']);

        $this->expectException(UnknownEntityException::class);

        $factory->nextIdentity('Bar');
    }

    public function testNestedCreate()
    {
        $factory = new ClassMappingEntityFactory(['Bar' => NestedTestEntity::class, 'Foo' => TestEntity::class, 'FooId' => DomainId::class], ['Foo' => 'FooId']);

        $entity = $factory->create('Bar', [
            'test' => ['arg_a' => 'nested_a', 'arg_b' => 'nested_b'],
            'self' => ['test' => ['arg_a' => 'foo', 'arg_b' => 'bar'], 'other' => $other = new TestEntity(1, 2), 'id' => '123'],
            'id' => '456',
        ]);

        $this->assertInstanceOf(NestedTestEntity::class, $entity);
        $this->assertInstanceOf(TestEntity::class, $entity->test);
        $this->assertSame('nested_a', $entity->test->a);
        $this->assertSame('nested_b', $entity->test->b);
        $this->assertInstanceOf(NestedTestEntity::class, $entity->self);
        $this->assertSame('foo', $entity->self->test->a);
        $this->assertSame('bar', $entity->self->test->b);
        $this->assertSame($other, $entity->self->other);
        $this->assertSame('123', $entity->self->id->toString());
        $this->assertSame('456', $entity->id->toString());
    }

    public function testNestedCreateWithoutContext()
    {
        $factory = new ClassMappingEntityFactory(['Bar' => NestedTestEntity::class, 'Foo' => TestEntity::class, 'FooId' => DomainId::class], ['Foo' => 'FooId']);

        $this->expectException(\LogicException::class);

        $factory->create('Bar');
    }

    public function testCreateWithNumericArgs()
    {
        $factory = new ClassMappingEntityFactory(['Foo' => TestEntity::class], []);

        $entity = $factory->create('Foo', [1 => 'b', 'arg_a' => 'a', 0 => 'ignore']);

        $this->assertSame('a', $entity->a);
        $this->assertSame('b', $entity->b);
    }
}

class TestEntity
{
    public $a, $b;

    public function __construct($argA, $argB)
    {
        $this->a = $argA;
        $this->b = $argB;
    }
}

class NestedTestEntity
{
    public $test, $self, $other, $id;

    public function __construct(TestEntity $test, ?self $self, ?TestEntity $other, DomainId $id)
    {
        $this->test = $test;
        $this->self = $self;
        $this->other = $other;
        $this->id = $id;
    }
}
