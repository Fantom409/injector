<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Php7;

use Yiisoft\Injector\Injector;
use Yiisoft\Injector\MissingInternalArgumentException;
use Yiisoft\Injector\Tests\Common\BaseInjectorTest;

class InjectorTest extends BaseInjectorTest
{
    /**
     * @dataProvider containerDependencyProvider
     * @param callable $dependency
     */
    public function testMakeInternalClassWithOptionalMiddleArgumentSkipped(callable $dependency): void
    {
        $container = $this->getContainer($dependency);

        $this->expectException(MissingInternalArgumentException::class);
        $this->expectExceptionMessageMatches('/PHP internal/');

        (new Injector($container))->make(\SplFileObject::class, [
            'file_name' => __FILE__,
            // second parameter skipped
            // third parameter skipped
            'context' => null,
            'other-parameter' => true,
        ]);
    }
}
