<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Common;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Container\NotFoundExceptionInterface;
use stdClass;
use Yiisoft\Injector\Injector;
use Yiisoft\Injector\InvalidArgumentException;
use Yiisoft\Injector\MissingRequiredArgumentException;
use Yiisoft\Injector\Tests\Common\Support\ColorInterface;
use Yiisoft\Injector\Tests\Common\Support\EngineInterface;
use Yiisoft\Injector\Tests\Common\Support\EngineMarkTwo;
use Yiisoft\Injector\Tests\Common\Support\EngineVAZ2101;
use Yiisoft\Injector\Tests\Common\Support\EngineZIL130;
use Yiisoft\Injector\Tests\Common\Support\Invokeable;
use Yiisoft\Injector\Tests\Common\Support\LightEngine;
use Yiisoft\Injector\Tests\Common\Support\MakeEmptyConstructor;
use Yiisoft\Injector\Tests\Common\Support\MakeEngineCollector;
use Yiisoft\Injector\Tests\Common\Support\MakeEngineMatherWithParam;
use Yiisoft\Injector\Tests\Common\Support\MakeNoConstructor;
use Yiisoft\Injector\Tests\Common\Support\MakePrivateConstructor;

class InjectorTest extends BaseInjectorTest
{
    /**
     * Injector should be able to invoke closure.
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testInvokeClosure(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [EngineInterface::class => new EngineMarkTwo()]);

        $getEngineName = fn (EngineInterface $engine) => $engine->getName();

        $engineName = (new Injector($container))->invoke($getEngineName);

        $this->assertSame('Mark Two', $engineName);
    }

    /**
     * Injector should be able to invoke array callable.
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testInvokeCallableArray(callable $dependency): void
    {
        $container = $this->getContainer($dependency);

        $object = new EngineVAZ2101();

        $engine = (new Injector($container))->invoke([$object, 'rust'], ['index' => 5.0]);

        $this->assertInstanceOf(EngineVAZ2101::class, $engine);
    }

    /**
     * Injector should be able to invoke static method.
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testInvokeStatic(callable $dependency): void
    {
        $container = $this->getContainer($dependency);

        $result = (new Injector($container))->invoke([EngineVAZ2101::class, 'isWroomWroom']);

        $this->assertIsBool($result);
    }

    /**
     * Injector should be able to invoke static method.
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testInvokeAnonymousClass(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [EngineInterface::class => new EngineMarkTwo()]);
        $class = new class() {
            public EngineInterface $engine;

            public function setEngine(EngineInterface $engine): void
            {
                $this->engine = $engine;
            }
        };

        (new Injector($container))->invoke([$class, 'setEngine']);

        $this->assertInstanceOf(EngineInterface::class, $class->engine);
    }

    /**
     * Injector should be able to invoke method without arguments.
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testInvokeWithoutArguments(callable $dependency): void
    {
        $container = $this->getContainer($dependency);

        $true = fn () => true;

        $result = (new Injector($container))->invoke($true);

        $this->assertTrue($result);
    }

    /**
     * Nullable arguments should be searched in container.
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testWithNullableArgument(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [EngineInterface::class => new EngineMarkTwo()]);

        $nullable = fn (?EngineInterface $engine) => $engine;

        $result = (new Injector($container))->invoke($nullable);

        $this->assertNotNull($result);
    }

    /**
     * Nullable arguments not found in container should be passed as `null`.
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testWithNullableArgumentAndEmptyContainer(callable $dependency): void
    {
        $container = $this->getContainer($dependency);

        $nullable = fn (?EngineInterface $engine) => $engine;

        $result = (new Injector($container))->invoke($nullable);

        $this->assertNull($result);
    }

    /**
     * Nullable scalars should be set with `null` if not specified by name explicitly.
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testWithNullableScalarArgument(callable $dependency): void
    {
        $container = $this->getContainer($dependency);

        $nullableInt = fn (?int $number) => $number;

        $result = (new Injector($container))->invoke($nullableInt);

        $this->assertNull($result);
    }

    /**
     * Optional scalar arguments should be set with default value if not specified by name explicitly.
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testWithNullableOptionalArgument(callable $dependency): void
    {
        $container = $this->getContainer($dependency);

        $nullableInt = fn (?int $number = 6) => $number;

        $result = (new Injector($container))->invoke($nullableInt);

        $this->assertSame(6, $result);
    }

    /**
     * Optional arguments with `null` by default should be set with `null` if other value not specified in parameters
     * or container.
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testWithNullableOptionalArgumentThatNull(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [EngineInterface::class => new EngineMarkTwo()]);

        $callable = fn (EngineInterface $engine = null) => $engine;

        $result = (new Injector($container))->invoke($callable);

        $this->assertNotNull($result);
    }

    /**
     * An object for a typed argument can be specified in parameters without named key and without following the order.
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testCustomDependency(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [EngineInterface::class => new EngineMarkTwo()]);
        $needleEngine = new EngineZIL130();

        $getEngineName = fn (EngineInterface $engine) => $engine->getName();

        $engineName = (new Injector($container))->invoke(
            $getEngineName,
            [new stdClass(), $needleEngine, new DateTimeImmutable()]
        );

        $this->assertSame(EngineZIL130::NAME, $engineName);
    }

    /**
     * In this case, first argument will be set from parameters, and second argument from container.
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testTwoEqualCustomArgumentsWithOneCustom(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [EngineInterface::class => new EngineMarkTwo()]);

        $compareEngines = static function (EngineInterface $engine1, EngineInterface $engine2) {
            return $engine1->getPower() <=> $engine2->getPower();
        };
        $zilEngine = new EngineZIL130();

        $result = (new Injector($container))->invoke($compareEngines, [$zilEngine]);

        $this->assertSame(-1, $result);
    }

    /**
     * In this case, second argument will be set from parameters by name, and first argument from container.
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testTwoEqualCustomArgumentsWithOneCustomNamedParameter(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [EngineInterface::class => new EngineMarkTwo()]);

        $compareEngines = static function (EngineInterface $engine1, EngineInterface $engine2) {
            return $engine1->getPower() <=> $engine2->getPower();
        };
        $zilEngine = new EngineZIL130();

        $result = (new Injector($container))->invoke($compareEngines, ['engine2' => $zilEngine]);

        $this->assertSame(1, $result);
    }

    /**
     * Values for arguments are not matched by the greater similarity of parameter types and arguments, but simply pass
     * in order as is.
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testExtendedArgumentsWithOneCustomNamedParameter2(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [LightEngine::class => new EngineVAZ2101()]);

        $concatEngineNames = static function (EngineInterface $engine1, LightEngine $engine2) {
            return $engine1->getName() . $engine2->getName();
        };

        $result = (new Injector($container))->invoke($concatEngineNames, [
            new EngineMarkTwo(), // LightEngine, EngineInterface
            new EngineZIL130(), // EngineInterface
        ]);

        $this->assertSame(EngineMarkTwo::NAME . EngineVAZ2101::NAME, $result);
    }

    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testMissingRequiredTypedParameter(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [EngineInterface::class => new EngineMarkTwo()]);

        $getEngineName = static function (EngineInterface $engine, string $two) {
            return $engine->getName() . $two;
        };

        $injector = new Injector($container);

        $this->expectException(MissingRequiredArgumentException::class);
        $injector->invoke($getEngineName);
    }

    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testMissingRequiredNotTypedParameter(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [EngineInterface::class => new EngineMarkTwo()]);

        $getEngineName = static function (EngineInterface $engine, $two) {
            return $engine->getName() . $two;
        };
        $injector = new Injector($container);

        $this->expectException(MissingRequiredArgumentException::class);

        $injector->invoke($getEngineName);
    }

    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testNotFoundException(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [EngineInterface::class => new EngineMarkTwo()]);

        $getEngineName = static function (EngineInterface $engine, ColorInterface $color) {
            return $engine->getName() . $color->getColor();
        };

        $injector = new Injector($container);

        $this->expectException(NotFoundExceptionInterface::class);
        $injector->invoke($getEngineName);
    }

    /**
     * A values collection for a variadic argument can be passed as an array in a named parameter.
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testAloneScalarVariadicParameterAndNamedArrayArgument(callable $dependency): void
    {
        $container = $this->getContainer($dependency);

        $callable = fn (int ...$var) => array_sum($var);

        $result = (new Injector($container))->invoke($callable, ['var' => [1, 2, 3], new stdClass()]);

        $this->assertSame(6, $result);
    }

    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testAloneScalarVariadicParameterAndNamedAssocArrayArgument(callable $dependency): void
    {
        $container = $this->getContainer($dependency);

        $callable = fn (string $foo, string ...$bar) => $foo . '--' . implode('-', $bar);

        $result = (new Injector($container))
            ->invoke($callable, ['foo' => 'foo', 'bar' => ['foo' => 'baz', '0' => 'fiz']]);

        $this->assertSame('foo--baz-fiz', $result);
    }

    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testAloneScalarVariadicParameterAndNamedScalarArgument(callable $dependency): void
    {
        $container = $this->getContainer($dependency);

        $callable = fn (int ...$var) => array_sum($var);

        $result = (new Injector($container))->invoke($callable, ['var' => 42, new stdClass()]);

        $this->assertSame(42, $result);
    }

    /**
     * If type of a variadic argument is a class and named parameter with values collection is not set then injector
     * will search for objects by class name among all unnamed parameters.
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testVariadicArgumentUnnamedParams(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [DateTimeInterface::class => new DateTimeImmutable()]);

        $callable = fn (DateTimeInterface $dateTime, EngineInterface ...$engines) => count($engines);

        $result = (new Injector($container))->invoke(
            $callable,
            [new EngineZIL130(), new EngineVAZ2101(), new stdClass(), new EngineMarkTwo(), new stdClass()]
        );

        $this->assertSame(3, $result);
    }

    /**
     * If calling method have an untyped variadic argument then all remaining unnamed parameters will be passed.
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testVariadicMixedArgumentWithMixedParams(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [DateTimeInterface::class => new DateTimeImmutable()]);

        $callable = fn (...$engines) => $engines;

        $result = (new Injector($container))->invoke(
            $callable,
            [new EngineZIL130(), new EngineVAZ2101(), new EngineMarkTwo(), new stdClass()]
        );

        $this->assertCount(4, $result);
    }

    /**
     * Any unnamed parameter can only be an object. Scalar, array, null and other values can only be named parameters.
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testVariadicStringArgumentWithUnnamedStringsParams(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [DateTimeInterface::class => new DateTimeImmutable()]);

        $callable = fn (string ...$engines) => $engines;

        $this->expectException(\Exception::class);

        (new Injector($container))->invoke($callable, ['str 1', 'str 2', 'str 3']);
    }

    /**
     * In the absence of other values to a nullable variadic argument `null` is not passed by default.
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testNullableVariadicArgument(callable $dependency): void
    {
        $container = $this->getContainer($dependency);

        $callable = fn (?EngineInterface ...$engines) => $engines;

        $result = (new Injector($container))->invoke($callable, []);

        $this->assertSame([], $result);
    }

    /**
     * Parameters that were passed but were not used are appended to the call so they could be obtained
     * with func_get_args().
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testAppendingUnusedParams(callable $dependency): void
    {
        $container = $this->getContainer($dependency);

        $callable = static function (
            /** @scrutinizer ignore-unused */
            ?EngineInterface $engine,
            /** @scrutinizer ignore-unused */
            $id = 'test'
        ) {
            return func_num_args();
        };

        $result = (new Injector($container))->invoke($callable, [
            new DateTimeImmutable(),
            new DateTimeImmutable(),
            new EngineMarkTwo(),
            'named' => new EngineVAZ2101(),
        ]);

        $this->assertSame(4, $result);
    }

    /**
     * Object type may be passed as unnamed parameter
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testInvokeWithObjectType(callable $dependency): void
    {
        $container = $this->getContainer($dependency);
        $callable = fn (object $object) => get_class($object);

        $result = (new Injector($container))->invoke($callable, [new DateTimeImmutable()]);

        $this->assertSame(DateTimeImmutable::class, $result);
    }

    /**
     * Required `object` type should not be requested from the container
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testInvokeWithRequiredObjectTypeWithoutInstance(callable $dependency): void
    {
        $container = $this->getContainer($dependency);
        $callable = fn (object $object) => get_class($object);

        $this->expectException(MissingRequiredArgumentException::class);

        (new Injector($container))->invoke($callable);
    }

    /**
     * Arguments passed by reference
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testInvokeReferencedArguments(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [EngineInterface::class => new EngineMarkTwo()]);
        $foo = 1;
        $bar = new stdClass();
        $baz = null;
        $callable = static function (
            int &$foo,
            object &$bar,
            &$baz,
            ?ColorInterface &$nullable,
            EngineInterface &$object, // from container
            DateTimeInterface &...$dates // collect all unnamed DateTimeInterface objects
        ) {
            $return = func_get_args();
            $bar = new DateTimeImmutable();
            $baz = false;
            $foo = count($dates);
            return $return;
        };
        $result = (new Injector($container))
            ->invoke($callable, [
                new DateTimeImmutable(),
                new DateTime(),
                new DateTime(),
                'foo' => &$foo,
                'bar' => $bar,
                'baz' => &$baz,
            ]);

        // passed
        $this->assertSame(1, $result[0]);
        $this->assertInstanceOf(stdClass::class, $result[1]);
        $this->assertNull($result[2]);
        $this->assertNull($result[3]);
        $this->assertInstanceOf(EngineMarkTwo::class, $result[4]);
        // transformed
        $this->assertSame(3, $foo); // count of DateTimeInterface objects
        $this->assertInstanceOf(stdClass::class, $bar);
        $this->assertFalse($baz);
    }

    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
    */
    public function testInvokeReferencedAndRemovedArguments(callable $dependency): void
    {
        $container = $this->getContainer($dependency);
        $foo = new stdClass();
        $bar = new stdClass();
        $baz = new DateTimeImmutable();
        $fiz = new DateTime();
        $kus = new DateTime();
        $callable = static fn (
            stdClass &$foo,
            object &$bar,
            ?ColorInterface $null,
            DateTimeInterface &...$dates
        ) => func_num_args();

        $args = [&$foo, &$baz, &$fiz, &$kus, 'bar' => &$bar];
        unset($foo, $baz, $biz, $fiz, $kus, $bar);

        $result = (new Injector($container))
            ->invoke($callable, $args);

        $this->assertSame(6, $result);
    }

    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testInvokeReferencedArgumentNamedVariadic(callable $dependency): void
    {
        $container = $this->getContainer($dependency);

        $callable = static function (DateTimeInterface &...$dates) {
            $dates[0] = false;
            $dates[1] = false;
            return count($dates);
        };
        $foo = new DateTimeImmutable();
        $bar = new DateTimeImmutable();
        $baz = new DateTimeImmutable();
        $result = (new Injector($container))
            ->invoke($callable, [
                $foo,
                &$bar,
                &$baz,
                new DateTime(),
            ]);
        unset($baz);

        $this->assertSame(4, $result);
        $this->assertInstanceOf(DateTimeImmutable::class, $foo);
        $this->assertFalse($bar);
    }

    /**
     * If argument passed by reference but it is not supported by function
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testInvokeReferencedArgument(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [EngineInterface::class => new EngineMarkTwo()]);
        $foo = 1;
        $callable = fn (int $foo) => ++$foo;
        $result = (new Injector($container))->invoke($callable, ['foo' => &$foo]);

        // $foo has been not changed
        $this->assertSame(1, $foo);
        $this->assertSame(2, $result);
    }

    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testWrongNamedParam(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [EngineInterface::class => new EngineMarkTwo()]);

        $callable = fn (EngineInterface $engine) => $engine;

        $this->expectException(\Throwable::class);

        (new Injector($container))->invoke($callable, ['engine' => new DateTimeImmutable()]);
    }

    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testArrayArgumentWithUnnamedType(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [EngineInterface::class => new EngineMarkTwo()]);

        $callable = fn (array $arg) => $arg;

        $this->expectException(InvalidArgumentException::class);

        (new Injector($container))->invoke($callable, [['test']]);
    }
    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testCallableArgumentWithUnnamedType(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [EngineInterface::class => new EngineMarkTwo()]);

        $callable = fn (callable $arg) => $arg();

        $this->expectException(MissingRequiredArgumentException::class);

        (new Injector($container))->invoke($callable, [fn () => true]);
    }

    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testIterableArgumentWithUnnamedType(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [EngineInterface::class => new EngineMarkTwo()]);

        $callable = fn (iterable $arg) => $arg;

        $this->expectException(MissingRequiredArgumentException::class);

        (new Injector($container))->invoke($callable, [new \SplStack()]);
    }

    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testUnnamedScalarParam(callable $dependency): void
    {
        $container = $this->getContainer($dependency);

        $getEngineName = fn () => 42;

        $this->expectException(InvalidArgumentException::class);

        (new Injector($container))->invoke($getEngineName, ['test']);
    }

    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testInvokeable(callable $dependency): void
    {
        $container = $this->getContainer($dependency);
        $result = (new Injector($container))->invoke(new Invokeable());
        $this->assertSame(42, $result);
    }

    /**
     * Constructor method not defined
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testMakeWithoutConstructor(callable $dependency): void
    {
        $container = $this->getContainer($dependency);

        $object = (new Injector($container))->make(MakeNoConstructor::class);

        $this->assertInstanceOf(MakeNoConstructor::class, $object);
    }

    /**
     * Constructor without arguments
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testMakeWithoutArguments(callable $dependency): void
    {
        $container = $this->getContainer($dependency);

        $object = (new Injector($container))->make(MakeEmptyConstructor::class);

        $this->assertInstanceOf(MakeEmptyConstructor::class, $object);
    }

    /**
     * Private constructor unavailable from Injector context
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testMakeWithPrivateConstructor(callable $dependency): void
    {
        $container = $this->getContainer($dependency);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not instantiable/');

        (new Injector($container))->make(MakePrivateConstructor::class);
    }

    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testMakeInvalidClass(callable $dependency): void
    {
        $undefinedClass = '\\undefinedNameSpace\\UndefinedClassThatShouldNotBeDefined';
        $container = $this->getContainer($dependency);

        $this->assertFalse(class_exists($undefinedClass, true));
        $this->expectException(\ReflectionException::class);
        $this->expectExceptionMessageMatches('/does not exist/');

        (new Injector($container))->make($undefinedClass);
    }

    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testMakeInternalClass(callable $dependency): void
    {
        $container = $this->getContainer($dependency);
        $object = (new Injector($container))->make(DateTimeImmutable::class);
        $this->assertInstanceOf(DateTimeImmutable::class, $object);
    }

    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testMakeInternalClassWithUnusedArguments(callable $dependency): void
    {
        $container = $this->getContainer($dependency);
        $object = (new Injector($container))
            ->make(DateTimeImmutable::class, ['named_param' => null, new EngineVAZ2101()]);

        $this->assertInstanceOf(DateTimeImmutable::class, $object);
    }

    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testMakeAbstractClass(callable $dependency): void
    {
        $container = $this->getContainer($dependency);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not instantiable/');
        (new Injector($container))->make(LightEngine::class);
    }

    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testMakeInterface(callable $dependency): void
    {
        $container = $this->getContainer($dependency);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not instantiable/');
        (new Injector($container))->make(EngineInterface::class);
    }

    /**
     * If type of a variadic argument is a class and its value is not passed in parameters, then no arguments will be
     * passed, despite the fact that the container has a corresponding value.
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testMakeWithVariadicFromContainer(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [EngineInterface::class => new EngineMarkTwo()]);

        $object = (new Injector($container))->make(MakeEngineCollector::class, []);

        $this->assertCount(0, $object->engines);
    }

    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testMakeWithVariadicFromArguments(callable $dependency): void
    {
        $container = $this->getContainer($dependency);

        $values = [new EngineMarkTwo(), new EngineVAZ2101()];
        $object = (new Injector($container))->make(MakeEngineCollector::class, $values);

        $this->assertSame($values, $object->engines);
    }

    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testMakeWithCustomParam(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [EngineInterface::class => new EngineMarkTwo()]);

        $object = (new Injector($container))
            ->make(MakeEngineMatherWithParam::class, [new EngineVAZ2101(), 'parameter' => 'power']);

        $this->assertNotSame($object->engine1, $object->engine2);
        $this->assertInstanceOf(EngineVAZ2101::class, $object->engine1);
        $this->assertNotSame(EngineMarkTwo::class, $object->engine2);
        $this->assertSame($object->parameter, 'power');
    }

    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testMakeWithInvalidCustomParam(callable $dependency): void
    {
        $container = $this->getContainer($dependency, [EngineInterface::class => new EngineMarkTwo()]);

        $this->expectException(\TypeError::class);

        (new Injector($container))->make(MakeEngineMatherWithParam::class, ['parameter' => 100500]);
    }
}
