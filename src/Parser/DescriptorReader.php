<?php

declare(strict_types=1);

namespace ProtoLint\Parser;

use Google\Protobuf\Internal\DescriptorProto;
use Google\Protobuf\Internal\FieldDescriptorProto;
use Google\Protobuf\Internal\FieldDescriptorProto\Label;
use Google\Protobuf\Internal\FieldDescriptorProto\Type;
use Google\Protobuf\Internal\FileDescriptorSet;
use Google\Protobuf\Internal\MethodDescriptorProto;
use Google\Protobuf\Internal\ServiceDescriptorProto;
use ProtoLint\Domain\DataType;
use ProtoLint\Domain\FieldInfo;
use ProtoLint\Domain\MessageInfo;
use ProtoLint\Domain\MethodInfo;
use ProtoLint\Domain\ProtoMetadata;
use ProtoLint\Domain\ServiceInfo;

/**
 * Reads binary FileDescriptorSet and navigates the descriptor tree
 * to extract proto-side metadata (ServiceInfo, MethodInfo, MessageInfo, FieldInfo).
 */
final class DescriptorReader
{
    /**
     * Parse binary FileDescriptorSet data into ProtoMetadata.
     *
     * @param string $binaryData Binary FileDescriptorSet data
     * @return ProtoMetadata
     */
    public function read(string $binaryData): ProtoMetadata
    {
        $fileDescriptorSet = new FileDescriptorSet();
        $fileDescriptorSet->mergeFromString($binaryData);

        $services = [];
        $messagesByName = [];

        foreach ($fileDescriptorSet->getFile() as $fileDescriptor) {
            // Extract services
            foreach ($fileDescriptor->getService() as $serviceDescriptor) {
                $services[] = $this->extractService($serviceDescriptor);
            }

            // Extract messages (top-level + nested)
            $this->extractMessages($fileDescriptor->getMessageType(), $messagesByName);
        }

        return new ProtoMetadata($services, $messagesByName);
    }

    private function extractService(ServiceDescriptorProto $serviceDescriptor): ServiceInfo
    {
        $methods = [];
        foreach ($serviceDescriptor->getMethod() as $methodDescriptor) {
            $methods[] = $this->extractMethod($methodDescriptor);
        }

        return new ServiceInfo($serviceDescriptor->getName(), $methods);
    }

    private function extractMethod(MethodDescriptorProto $methodDescriptor): MethodInfo
    {
        // input_type and output_type are fully-qualified message names like ".Package.MessageName"
        // The positional params will be filled by the PHP AST side;
        // proto side stores the input/output message type names
        $inputType = $methodDescriptor->getInputType();
        $outputType = $methodDescriptor->getOutputType();

        return new MethodInfo(
            $methodDescriptor->getName(),
            [], // positionalParams filled by PHP AST extraction
            $outputType,
            $inputType,
        );
    }

    /**
     * @param iterable<DescriptorProto> $descriptors
     * @param array<string, MessageInfo> $messagesByName
     */
    private function extractMessages(iterable $descriptors, array &$messagesByName): void
    {
        foreach ($descriptors as $descriptor) {
            $messageName = $descriptor->getName();
            $mapEntryNames = $this->collectMapEntryTypeNames($descriptor);

            $fields = [];
            $position = 1;
            foreach ($descriptor->getField() as $fieldDescriptor) {
                $fields[] = $this->extractField($fieldDescriptor, $position, $mapEntryNames);
                $position++;
            }

            $messagesByName[$messageName] = new MessageInfo($messageName, $fields);

            // Recursively process nested types
            $this->extractMessages($descriptor->getNestedType(), $messagesByName);
        }
    }

    /**
     * Collect type names of nested types that are map entries.
     * These are used to distinguish map fields from repeated message fields.
     *
     * @return array<string, bool> Set of map entry type names
     */
    private function collectMapEntryTypeNames(DescriptorProto $descriptor): array
    {
        $mapEntryNames = [];
        foreach ($descriptor->getNestedType() as $nestedType) {
            $options = $nestedType->getOptions();
            if ($options !== null && $options->getMapEntry()) {
                // Map entry type name in the descriptor is ".ParentMessage.MapEntryName"
                $mapEntryNames[$nestedType->getName()] = true;
            }
        }

        return $mapEntryNames;
    }

    private function extractField(
        FieldDescriptorProto $fieldDescriptor,
        int $position,
        array $mapEntryNames,
    ): FieldInfo {
        $name = $fieldDescriptor->getName();
        $tagNumber = $fieldDescriptor->getNumber();
        $type = $fieldDescriptor->getType();
        $label = $fieldDescriptor->getLabel();
        $typeName = $fieldDescriptor->getTypeName();

        $dataType = $this->mapDataType($type, $label, $typeName, $mapEntryNames);

        // phpClassMapping is filled by the mapping config, not the proto side
        $phpClassMapping = null;

        return new FieldInfo(
            $name,
            $position,
            $tagNumber,
            $dataType,
            $phpClassMapping,
            null,
            $typeName,
        );
    }

    /**
     * Map proto FieldDescriptorProto type/label to DataType.
     */
    private function mapDataType(int $type, int $label, ?string $typeName, array $mapEntryNames): DataType
    {
        // Check for map type: repeated message whose type_name matches a map entry
        if ($label === Label::LABEL_REPEATED && $type === Type::TYPE_MESSAGE && $typeName !== null) {
            // Extract the short type name from ".Package.Message.MapEntry"
            $stripped = ltrim($typeName, '.');
            $parts = explode('.', $stripped);
            $shortTypeName = end($parts);

            if (isset($mapEntryNames[$shortTypeName])) {
                return DataType::MAP;
            }
        }

        // Repeated fields are lists
        if ($label === Label::LABEL_REPEATED) {
            return DataType::LIST;
        }

        // Message type (non-repeated)
        if ($type === Type::TYPE_MESSAGE) {
            return DataType::MESSAGE;
        }

        // Everything else is scalar
        return DataType::SCALAR;
    }
}
