<?php

declare(strict_types=1);

namespace ProtoLint\Domain;

/**
 * Enum representing the data type classification of a proto field.
 */
enum DataType: string
{
    case SCALAR = 'scalar';
    case MESSAGE = 'message';
    case MAP = 'map';
    case LIST = 'list';
}
