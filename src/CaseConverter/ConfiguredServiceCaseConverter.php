<?php

declare(strict_types=1);

namespace Migrify\PhpConfigPrinter\CaseConverter;

use Migrify\PhpConfigPrinter\Contract\CaseConverterInterface;
use Migrify\PhpConfigPrinter\NodeFactory\ArgsNodeFactory;
use Migrify\PhpConfigPrinter\NodeFactory\Service\ServiceOptionNodeFactory;
use Migrify\PhpConfigPrinter\ValueObject\MethodName;
use Migrify\PhpConfigPrinter\ValueObject\VariableName;
use Migrify\PhpConfigPrinter\ValueObject\YamlKey;
use Nette\Utils\Strings;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;

/**
 * Handles this part:
 *
 * services:
 *     SomeNamespace\SomeClass: null <---
 */
final class ConfiguredServiceCaseConverter implements CaseConverterInterface
{
    /**
     * @var ArgsNodeFactory
     */
    private $argsNodeFactory;

    /**
     * @var ServiceOptionNodeFactory
     */
    private $serviceOptionNodeFactory;

    public function __construct(ArgsNodeFactory $argsNodeFactory, ServiceOptionNodeFactory $serviceOptionNodeFactory)
    {
        $this->argsNodeFactory = $argsNodeFactory;
        $this->serviceOptionNodeFactory = $serviceOptionNodeFactory;
    }

    public function convertToMethodCall($key, $values): Expression
    {
        $valuesForArgs = [$key];

        if (isset($values[YamlKey::CLASS_KEY])) {
            $valuesForArgs[] = $values[YamlKey::CLASS_KEY];
        }

        $args = $this->argsNodeFactory->createFromValues($valuesForArgs);
        $methodCall = new MethodCall(new Variable(VariableName::SERVICES), MethodName::SET, $args);

        $methodCall = $this->serviceOptionNodeFactory->convertServiceOptionsToNodes($values, $methodCall);

        $expression = new Expression($methodCall);
        $expression->setAttribute('comments', $methodCall->getComments());

        return $expression;
    }

    public function match(string $rootKey, $key, $values): bool
    {
        if ($rootKey !== YamlKey::SERVICES) {
            return false;
        }

        if ($key === YamlKey::_DEFAULTS) {
            return false;
        }

        if ($key === YamlKey::_INSTANCEOF) {
            return false;
        }

        if (isset($values[YamlKey::RESOURCE])) {
            return false;
        }

        // handled by @see \Migrify\PhpConfigPrinter\CaseConverter\CaseConverter\AliasCaseConverter
        if ($this->isAlias($values)) {
            return false;
        }

        if ($values === null) {
            return false;
        }

        return $values !== [];
    }

    private function isAlias($values): bool
    {
        if (isset($values[YamlKey::ALIAS])) {
            return true;
        }

        return is_string($values) && Strings::startsWith($values, '@');
    }
}
