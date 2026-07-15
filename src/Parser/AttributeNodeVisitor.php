<?php

declare(strict_types=1);

namespace PhpProtoLint\Parser;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\Node\UnionType;
use PhpParser\NodeVisitorAbstract;
use PhpProtoLint\Domain\DataType;
use PhpProtoLint\Domain\FieldInfo;
use PhpProtoLint\Domain\MessageInfo;
use PhpProtoLint\Domain\MethodInfo;
use PhpProtoLint\Domain\ServiceInfo;

/**
 * Traverses PHP AST to collect #[ProtoMethod]/#[ProtoField] attributes
 * and @proto-* docblock annotations, generating code-side metadata.
 *
 * Handles both PHP 8 Attribute syntax and PHP 7 docblock annotations,
 * unifying them into the same domain model.
 */
final class AttributeNodeVisitor extends NodeVisitorAbstract
{
    /** @var string|null Current namespace */
    private ?string $currentNamespace = null;

    /** @var string|null Current class FQCN */
    private ?string $currentClassFqcn = null;

    /** @var string|null Current class short name */
    private ?string $currentClassName = null;

    /** @var MethodInfo[] Methods collected for current class */
    private array $currentMethods = [];

    /** @var FieldInfo[] Fields collected for current class */
    private array $currentFields = [];

    /** @var ServiceInfo[] Collected services */
    private array $services = [];

    /** @var array<string, MessageInfo> Collected messages keyed by FQCN */
    private array $messagesByName = [];

    /**
     * @return ServiceInfo[]
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * @return array<string, MessageInfo>
     */
    public function getMessages(): array
    {
        return $this->messagesByName;
    }

    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof Namespace_) {
            $this->currentNamespace = $node->name !== null ? $node->name->toString() : null;

            return null;
        }

        if ($node instanceof Class_) {
            $this->currentClassName = $node->name !== null ? $node->name->name : null;
            $this->currentClassFqcn = $this->buildFqcn();
            $this->currentMethods = [];
            $this->currentFields = [];

            return null;
        }

        if ($node instanceof ClassMethod && $this->currentClassFqcn !== null) {
            $this->processMethod($node);

            return null;
        }

        if ($node instanceof Property && $this->currentClassFqcn !== null) {
            $this->processProperty($node);

            return null;
        }

        return null;
    }

    public function leaveNode(Node $node): ?Node
    {
        if ($node instanceof Class_) {
            $this->finalizeClass();

            return null;
        }

        if ($node instanceof Namespace_) {
            $this->currentNamespace = null;

            return null;
        }

        return null;
    }

    private function buildFqcn(): ?string
    {
        if ($this->currentClassName === null) {
            return null;
        }

        return $this->currentNamespace !== null
            ? $this->currentNamespace . '\\' . $this->currentClassName
            : $this->currentClassName;
    }

    /**
     * Finalize the current class: create ServiceInfo and/or MessageInfo.
     */
    private function finalizeClass(): void
    {
        if ($this->currentClassFqcn === null) {
            return;
        }

        if (!empty($this->currentMethods)) {
            $this->services[] = new ServiceInfo(
                $this->currentClassName ?? $this->currentClassFqcn,
                $this->currentMethods,
            );
        }

        if (!empty($this->currentFields)) {
            $this->messagesByName[$this->currentClassFqcn] = new MessageInfo(
                $this->currentClassName ?? $this->currentClassFqcn,
                $this->currentFields,
            );
        }

        $this->currentClassFqcn = null;
        $this->currentClassName = null;
        $this->currentMethods = [];
        $this->currentFields = [];
    }

    /**
     * Process a ClassMethod node: extract MethodInfo with positional params.
     * Tasks 3.2, 3.3, 3.4
     */
    private function processMethod(ClassMethod $method): void
    {
        $docComment = $method->getDocComment();
        $hasProtoMethodAttr = $this->hasAttribute($method->attrGroups, 'ProtoMethod');
        $hasProtoMethodDoc = $docComment !== null && $this->docblockHasAnnotation($docComment->getText(), '@proto-method');

        // Skip methods without Proto annotation
        if (!$hasProtoMethodAttr && !$hasProtoMethodDoc) {
            return;
        }

        $methodName = $method->name->name;
        $params = $method->getParams();
        $positionalParams = [];

        $position = 1;
        foreach ($params as $param) {
            $paramName = $this->getParamName($param);
            if ($paramName === null) {
                $position++;

                continue;
            }

            $tagNumber = $this->extractProtoFieldTagFromAttrs($param->attrGroups);
            $phpType = $this->typeToString($param->type);

            // Also check method docblock for @proto-field $name N
            if ($tagNumber === null && $docComment !== null) {
                $tagNumber = $this->extractProtoFieldTagFromDocblock($docComment->getText(), $paramName);
            }

            $dataType = $this->inferDataTypeFromType($param->type);
            $phpClassMapping = $this->extractClassMapping($param->type);

            $positionalParams[] = new FieldInfo(
                $paramName,
                $position,
                $tagNumber ?? 0,
                $dataType,
                $phpClassMapping,
                $phpType,
            );
            $position++;
        }

        // Extract return type from @proto-return docblock or method return type
        $returnType = $this->typeToString($method->returnType);
        if ($returnType === null && $docComment !== null) {
            $returnType = $this->extractProtoReturnFromDocblock($docComment->getText());
        }

        $this->currentMethods[] = new MethodInfo(
            $methodName,
            $positionalParams,
            $returnType,
        );
    }

    /**
     * Process a Property node: extract FieldInfo for each property.
     * Tasks 3.2, 3.3, 3.5
     */
    private function processProperty(Property $property): void
    {
        $docComment = $property->getDocComment();
        $hasProtoFieldAttr = $this->hasAttribute($property->attrGroups, 'ProtoField');
        $phpType = $this->typeToString($property->type);
        $dataType = $this->inferDataTypeFromType($property->type);
        $phpClassMapping = $this->extractClassMapping($property->type);

        foreach ($property->props as $propItem) {
            if (!$propItem instanceof PropertyProperty) {
                continue;
            }
            $propName = $propItem->name->name;

            $tagNumber = $this->extractProtoFieldTagFromAttrs($property->attrGroups);

            // Check docblock for @proto-field $name N
            if ($tagNumber === null && $docComment !== null) {
                $tagNumber = $this->extractProtoFieldTagFromDocblock($docComment->getText(), $propName);
            }

            // Skip properties without Proto annotation
            if ($tagNumber === null && !$hasProtoFieldAttr) {
                continue;
            }

            $this->currentFields[] = new FieldInfo(
                $propName,
                count($this->currentFields) + 1,
                $tagNumber ?? 0,
                $dataType,
                $phpClassMapping,
                $phpType,
            );
        }
    }

    /**
     * Get parameter name from Node\Param.
     */
    private function getParamName(Node\Param $param): ?string
    {
        $var = $param->var;
        if ($var instanceof Variable && is_string($var->name)) {
            return $var->name;
        }

        return null;
    }

    // ── Attribute extraction helpers ──

    /**
     * Check if any AttributeGroup contains an attribute with the given short name.
     *
     * @param AttributeGroup[] $attrGroups
     */
    private function hasAttribute(array $attrGroups, string $shortName): bool
    {
        foreach ($attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if ($this->matchAttributeName($attr, $shortName)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Extract ProtoField tag number from attribute groups.
     *
     * @param AttributeGroup[] $attrGroups
     */
    private function extractProtoFieldTagFromAttrs(array $attrGroups): ?int
    {
        foreach ($attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if ($this->matchAttributeName($attr, 'ProtoField')) {
                    return $this->extractTagFromAttribute($attr);
                }
            }
        }

        return null;
    }

    private function matchAttributeName(Attribute $attr, string $shortName): bool
    {
        $name = $attr->name;

        // Match by last segment of the name (short name)
        return $name->getLast() === $shortName;
    }

    /**
     * Extract the integer tag number from #[ProtoField(X)].
     */
    private function extractTagFromAttribute(Attribute $attr): ?int
    {
        foreach ($attr->args as $arg) {
            if (!$arg instanceof Arg) {
                continue;
            }
            $value = $arg->value;
            if ($value instanceof Int_) {
                return $value->value;
            }
        }

        return null;
    }

    // ── Docblock parsing helpers (Task 3.3) ──

    /**
     * Check if a docblock text contains a specific annotation.
     */
    private function docblockHasAnnotation(string $docblockText, string $annotation): bool
    {
        return str_contains($docblockText, $annotation);
    }

    /**
     * Extract tag number from @proto-field $name N docblock annotation.
     *
     * @param string $docblockText Full docblock text
     * @param string $fieldName Property/param name (without $)
     * @return int|null Tag number or null if not found
     */
    private function extractProtoFieldTagFromDocblock(string $docblockText, string $fieldName): ?int
    {
        // Match: @proto-field $fieldName N
        $pattern = '/@proto-field\s+\$' . preg_quote($fieldName, '/') . '\s+(\d+)/';
        if (preg_match($pattern, $docblockText, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Extract return type from @proto-return Type docblock annotation.
     */
    private function extractProtoReturnFromDocblock(string $docblockText): ?string
    {
        // Match: @proto-return TypeName
        if (preg_match('/@proto-return\s+([^\s*\/]+)/', $docblockText, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    // ── Type hint helpers ──

    /**
     * Convert a type node to its string representation.
     * Returns null if no type hint present.
     */
    private function typeToString(?Node $type): ?string
    {
        if ($type === null) {
            return null;
        }
        if ($type instanceof Identifier) {
            return $type->name;
        }
        if ($type instanceof Name) {
            return $type->toCodeString();
        }
        if ($type instanceof NullableType) {
            return '?' . $this->typeToString($type->type);
        }
        if ($type instanceof UnionType) {
            $parts = [];
            foreach ($type->types as $t) {
                $parts[] = $this->typeToString($t);
            }

            return implode('|', $parts);
        }
        if ($type instanceof IntersectionType) {
            $parts = [];
            foreach ($type->types as $t) {
                $parts[] = $this->typeToString($t);
            }

            return implode('&', $parts);
        }

        return null;
    }

    /**
     * Determine DataType from a PHP type hint node.
     */
    private function inferDataTypeFromType(?Node $type): DataType
    {
        if ($type === null) {
            return DataType::SCALAR;
        }
        $typeStr = $this->typeToString($type);
        if ($typeStr === null) {
            return DataType::SCALAR;
        }
        // Strip nullable prefix
        $typeStr = ltrim($typeStr, '?');

        // Built-in scalar types
        $scalars = [
            'int', 'float', 'double', 'string', 'bool', 'false', 'true',
            'null', 'void', 'mixed', 'object', 'callable', 'iterable',
            'never', 'resource', 'self', 'static', 'parent', 'numeric',
        ];
        if (in_array(strtolower($typeStr), $scalars, true)) {
            return DataType::SCALAR;
        }

        // Union types are treated as scalar (Rule-03 will flag them)
        if (str_contains($typeStr, '|') || str_contains($typeStr, '&')) {
            return DataType::SCALAR;
        }

        // array → LIST (could be MAP, but PHP alone can't distinguish)
        if ($typeStr === 'array') {
            return DataType::LIST;
        }

        // Everything else is a class type → MESSAGE
        return DataType::MESSAGE;
    }

    /**
     * Extract the FQCN from a type hint if it's a class type.
     */
    private function extractClassMapping(?Node $type): ?string
    {
        if ($type === null) {
            return null;
        }
        // Unwrap nullable
        if ($type instanceof NullableType) {
            return $this->extractClassMapping($type->type);
        }
        if ($type instanceof Name) {
            return $type->toCodeString();
        }
        if ($type instanceof Identifier) {
            // Only return if it's not a built-in scalar
            $scalars = [
                'int', 'float', 'double', 'string', 'bool', 'false', 'true',
                'null', 'void', 'mixed', 'object', 'callable', 'iterable',
                'never', 'resource', 'self', 'static', 'parent', 'array', 'numeric',
            ];
            if (in_array(strtolower($type->name), $scalars, true)) {
                return null;
            }

            return $type->name;
        }

        return null;
    }
}
