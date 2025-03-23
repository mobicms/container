<?php

declare(strict_types=1);

use Mobicms\Container\Container;
use Mobicms\Container\Exception\AlreadyExistsException;
use Mobicms\Container\Exception\InvalidAliasException;
use Mobicms\Container\Exception\NotFoundException;
use MobicmsTest\Container\FakeClassWithDependencies;
use MobicmsTest\Container\FakeClassWithoutConstructor;
use MobicmsTest\Container\FakeFactory;
use MobicmsTest\Container\FakeInvalidClass;
use Psr\Container\ContainerInterface;

describe('Configuration:', function () {
    test('ability to pass', function () {
        $container = new Container(
            [
                'services'    => ['foo' => []],
                'factories'   => ['bar' => fn() => new ArrayObject()],
                'definitions' => ['baz' => ArrayObject::class],
                'aliases'     => ['bat' => 'foo'],
            ]
        );
        expect($container->has('foo'))->toBeTrue()
            ->and($container->has('bar'))->toBeTrue()
            ->and($container->has('baz'))->toBeTrue()
            ->and($container->has('bat'))->toBeTrue();
    });

    it('throw exception on factory with duplicated key', function () {
        new Container(
            [
                'services'  => ['foo' => []],
                'factories' => ['foo' => fn() => new ArrayObject()],
            ]
        );
    })->throws(AlreadyExistsException::class);

    it('throw exception on definition with duplicated key', function () {
        new Container(
            [
                'services'    => ['foo' => []],
                'definitions' => ['foo' => ArrayObject::class],
            ]
        );
    })->throws(AlreadyExistsException::class);

    it('throw exception on alias with duplicated key', function () {
        new Container(
            [
                'services'  => ['foo' => []],
                'factories' => ['bar' => fn() => new ArrayObject()],
                'aliases'   => ['foo' => 'bar'],
            ]
        );
    })->throws(AlreadyExistsException::class);
});

describe('setService() method:', function () {
    test('can set service', function () {
        $container = new Container();
        expect($container->has('foo'))->toBeFalse();
        $container->setService('foo', []);
        expect($container->has('foo'))->toBeTrue();
    });

    it('throw exception on duplicated id', function () {
        $container = new Container(['services' => ['foo' => []],]);
        $container->setService('foo', []);
    })->throws(AlreadyExistsException::class);
});

describe('setFactory() method:', function () {
    test('can set factory', function () {
        $container = new Container();
        expect($container->has('foo'))->toBeFalse();
        $container->setFactory('foo', fn() => new ArrayObject());
        expect($container->has('foo'))->toBeTrue();
    });

    it('throw exception on duplicated id', function () {
        $container = new Container(['services' => ['foo' => []],]);
        $container->setFactory('foo', fn() => new ArrayObject());
    })->throws(AlreadyExistsException::class);
});

describe('setDefinition() method:', function () {
    test('can set definition', function () {
        $container = new Container();
        expect($container->has('foo'))->toBeFalse();
        $container->setDefinition('foo', 'bar');
        expect($container->has('foo'))->toBeTrue();
    });

    it('throw exception on duplicated id', function () {
        $container = new Container(['services' => ['foo' => []],]);
        $container->setDefinition('foo', 'bar');
    })->throws(AlreadyExistsException::class);
});

describe('setAlias() method:', function () {
    test('can set alias', function () {
        $container = new Container(
            [
                'services'    => ['foo' => []],
                'factories'   => ['bar' => fn() => new ArrayObject()],
                'definitions' => ['baz' => ArrayObject::class],
            ]
        );
        expect($container->has('alias1'))->toBeFalse()
            ->and($container->has('alias2'))->toBeFalse()
            ->and($container->has('alias3'))->toBeFalse();
        $container->setAlias('alias1', 'foo');
        $container->setAlias('alias2', 'bar');
        $container->setAlias('alias3', 'baz');
        expect($container->has('alias1'))->toBeTrue()
            ->and($container->has('alias2'))->toBeTrue()
            ->and($container->has('alias3'))->toBeTrue();
    });

    it('throw exception on undefined service', function () {
        $container = new Container();
        $container->setAlias('alias1', 'foo');
    })->throws(InvalidAliasException::class);
});

describe('get() method:', function () {
    $container = new Container(
        [
            'services'    => ['foo' => ['bar', 'baz', 'bat']],
            'aliases'     => ['foo_alias' => 'foo'],
            'factories'   =>
                [
                    'bar'             => fn() => new ArrayObject(['test' => 'string']),
                    'fake_factory'    => FakeFactory::class,
                    'invalid_factory' => FakeClassWithoutConstructor::class,
                    'unknown_factory' => Unknown::class,
                ],
            'definitions' =>
                [
                    'class_with_dependencies'    => FakeClassWithDependencies::class,
                    'class_without_dependencies' => FakeClassWithoutConstructor::class,
                    'unknown_class'              => Unknown::class,
                ],
        ]
    );
    $container->setService(ContainerInterface::class, $container);

    test('returns defined service', function () use ($container) {
        expect($container->get('foo'))->toEqual(['bar', 'baz', 'bat']);
    });

    test('returns alias of defined service', function () use ($container) {
        expect($container->get('foo_alias'))->toEqual(['bar', 'baz', 'bat']);
    });

    test('returns factory defined via closure function', function () use ($container) {
        $closure = $container->get('bar');
        expect($closure->offsetGet('test'))->toEqual('string');
    });

    test('returns factory defined via factory class', function () use ($container) {
        $class = $container->get('fake_factory');
        expect($class->offsetGet('faketest'))->toEqual('fakestring');
    });

    test('returns defined class with dependencies', function () use ($container) {
        $class = $container->get('class_with_dependencies');
        expect($class->get())->toEqual(['bar', 'baz', 'bat']);
    });

    test('returns undefined class with dependencies', function () use ($container) {
        $class = $container->get(FakeClassWithDependencies::class);
        expect($class->get())->toEqual(['bar', 'baz', 'bat']);
    });

    test('returns defined class without dependencies', function () use ($container) {
        $class = $container->get('class_without_dependencies');
        expect($class->get())->toEqual('test');
    });

    test('returns undefined class without dependencies', function () use ($container) {
        $class = $container->get(FakeClassWithoutConstructor::class);
        expect($class->get())->toEqual('test');
    });

    it('throw exception on unknown defined class', function () use ($container) {
        $container->get('unknown_class');
    })->throws(NotFoundException::class);

    it('throw exception on unknown undefined class', function () use ($container) {
        $container->get(Unknown::class);
    })->throws(NotFoundException::class);

    it('throw exception on invalid factory', function () use ($container) {
        $container->get('invalid_factory');
    })->throws(NotFoundException::class);

    it('throw exception on unknown factory', function () use ($container) {
        $container->get('unknown_factory');
    })->throws(NotFoundException::class);

    it('throw exception on invalid class', function () use ($container) {
        $container->get(FakeInvalidClass::class);
    })->throws(ReflectionException::class);
});
