<?php

declare(strict_types=1);

namespace ProtoLint\Injector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute as AttrNode;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\NodeVisitorAbstract;

/**
 * NodeVisitor that injects/updates #[ProtoField(X)] and #[ProtoMethod]
 * attributes on PHP 8+ AST nodes.
 *
 * Tasks 6.2, 6.3: Proto Attribute 节点识别与覆写
 * Non-Proto attributes (#[Route], #[Inject]) are preserved unchanged.
 */
final class AttributeInjectionVisitor extends NodeVisitorAbstract
{
    /** @var array<string, array{params: array<string, int>, returnType: ?string}> */
    private array $methodPlans;

    /** @var array<string, int> */
    private array $propertyPlans;

    /**
     * @param array<string, array{params: array<string, int>, returnType: ?string}> $methodPlans
     * @param array<string, int> $propertyPlans propertyName => tagNumber
     */
    public function __construct(array $methodPlans, array $propertyPlans)
    {
        $this->methodPlans = $methodPlans;
        $this->propertyPlans = $propertyPlans;
    }

    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof Namespace_) {
            return null;
        }

        if ($node instanceof Class_) {
            return null;
        }

        if ($node instanceof ClassMethod) {
            $this->processMethod($node);

            return null;
        }

        if ($node instanceof Property) {
            $this->processProperty($node);

            return null;
        }

        return null;
    }

    public function leaveNode(Node $node): ?Node
    {
        return null;
    }

    /**
     * Inject #[ProtoMethod] and #[ProtoField(X)] on method params.
     */
    private function processMethod(ClassMethod $method): void
    {
        $methodName = $method->name->name;

        // Add #[ProtoMethod] if missing
        if (!$this->hasAttribute($method->attrGroups, 'ProtoMethod')) {
            $method->attrGroups[] = $this->createAttributeGroup('ProtoMethod');
        }

        // Inject #[ProtoField(X)] on params
        if (!isset($this->methodPlans[$methodName])) {
            return;
        }

        $paramPlans = $this->methodPlans[$methodName]['params'];
        foreach ($method->params as $param) {
            $paramName = $this->getParamName($param);
            if ($paramName === null) {
                continue;
            }

            if (!isset($paramPlans[$paramName])) {
                continue;
            }

            $tagNumber = $paramPlans[$paramName];
            $this->setOrUpdateProtoFieldAttribute($param->attrGroups, $tagNumber);
        }
    }

    /**
     * Inject #[ProtoField(X)] on properties.
     */
    private function processProperty(Property $property): void
    {
        foreach ($property->props as $propItem) {
            if (!$propItem instanceof PropertyProperty) {
                continue;
            }
            $propName = $propItem->name->name;

            if (!isset($this->propertyPlans[$propName])) {
                continue;
            }

            $tagNumber = $this->propertyPlans[$propName];
            $this->setOrUpdateProtoFieldAttribute($property->attrGroups, $tagNumber);
        }
    }

    /**
     * Get param name from Node\Param.
     */
    private function getParamName(Node\Param $param): ?string
    {
        $var = $param->var;
        if ($var instanceof Node\Expr\Variable && is_string($var->name)) {
            return $var->name;
        }

        return null;
    }

    /**
     * Check if any AttributeGroup contains an attribute with the given short name.
     *
     * @param AttributeGroup[] $attrGroups
     */
    private function hasAttribute(array $attrGroups, string $shortName): bool
    {
        foreach ($attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if ($attr->name->getLast() === $shortName) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Set or update #[ProtoField(X)] in the given attrGroups.
     * Removes old ProtoField attributes, adds new one. Preserves non-Proto attributes.
     *
     * @param AttributeGroup[] $attrGroups (modified in place)
     */
    private function setOrUpdateProtoFieldAttribute(array &$attrGroups, int $tagNumber): void
    {
        // Remove existing ProtoField attributes from all groups
        $foundProtoGroup = false;
        foreach ($attrGroups as &$group) {
            $newAttrs = [];
            foreach ($group->attrs as $attr) {
                if ($attr->name->getLast() === 'ProtoField') {
                    // Skip old ProtoField — will be replaced
                    continue;
                }
                $newAttrs[] = $attr;
            }
            if (count($newAttrs) < count($group->attrs)) {
                // We removed a ProtoField from this group
                $newAttrs[] = $this->createProtoFieldAttribute($tagNumber);
                $foundProtoGroup = true;
            }
            $group->attrs = $newAttrs;
        }
        unset($group);

        // If no existing ProtoField group found, add a new one
        if (!$foundProtoGroup) {
            $attrGroups[] = $this->createProtoFieldGroup($tagNumber);
        }
    }

    /**
     * Create a #[ProtoField(X)] Attribute node.
     */
    private function createProtoFieldAttribute(int $tagNumber): AttrNode
    {
        return new AttrNode(
            new Name('ProtoField'),
            [new Arg(new Int_($tagNumber))],
        );
    }

    /**
     * Create an AttributeGroup containing a single #[ProtoField(X)].
     */
    private function createProtoFieldGroup(int $tagNumber): AttributeGroup
    {
        return new AttributeGroup([$this->createProtoFieldAttribute($tagNumber)]);
    }

    /**
     * Create an AttributeGroup containing a single attribute with no args.
     */
    private function createAttributeGroup(string $name): AttributeGroup
    {
        return new AttributeGroup([new AttrNode(new Name($name))]);
    }
}
