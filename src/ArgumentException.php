<?php

declare(strict_types=1);

namespace Yiisoft\Injector;

use ReflectionNamedType;
use ReflectionUnionType;

abstract class ArgumentException extends \InvalidArgumentException
{
    protected const EXCEPTION_MESSAGE = 'Something is wrong with argument "%s" when calling "%s"%s.';

    public function __construct(\ReflectionFunctionAbstract $reflection, string $parameter)
    {
        $function = $reflection->getName();
        /** @psalm-var class-string|null $class */
        $class = $reflection->class ?? null;

        if ($class === null) {
            $method = $function;
            if (substr($method, -9) === '{closure}') {
                $method = $this->getClosureSignature($reflection);
            }
        } else {
            $method = "{$class}::{$function}";
        }

        $fileName = $reflection->getFileName();
        $line = $reflection->getStartLine();

        $fileAndLine = '';
        if (!empty($fileName)) {
            $fileAndLine = " in \"$fileName\" at line $line";
        }

        parent::__construct(sprintf(static::EXCEPTION_MESSAGE, $parameter, $method, $fileAndLine));
    }

    private function getClosureSignature(\ReflectionFunctionAbstract $reflection): string
    {
        $closureParameters = [];
        $append = static function (bool $condition, string $postfix) use (&$parameterString): void {
            if ($condition) {
                /** @var string $parameterString */
                $parameterString .= $postfix;
            }
        };
        foreach ($reflection->getParameters() as $parameter) {
            $parameterString = '';
            $type = $parameter->getType();
            /**
             * @psalm-suppress TypeDoesNotContainType
             * @psalm-suppress UndefinedClass
             */
            if ($type instanceof ReflectionNamedType) {
                $append($parameter->allowsNull(), '?');
                /** @var string $parameterString */
                $parameterString .= $type->getName() . ' ';
            } elseif ($type instanceof ReflectionUnionType) {
                /** @var ReflectionNamedType[] */
                $types = $type->getTypes();
                $parameterString .= implode('|', array_map(
                    static fn (ReflectionNamedType $r) => $r->getName(),
                    $types
                )) . ' ';
            }
            $append($parameter->isPassedByReference(), '&');
            $append($parameter->isVariadic(), '...');
            /** @var string $parameterString */
            $parameterString .= '$' . $parameter->name;
            if ($parameter->isDefaultValueAvailable()) {
                $parameterString .= ' = ' . var_export($parameter->getDefaultValue(), true);
            }
            $closureParameters[] = $parameterString;
        }
        return 'function (' . implode(', ', $closureParameters) . ')';
    }
}
