<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

use FFI\CData;
use FFI\CType;
use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * Casts FFI extension classes to array representation.
 *
 * @author Nesmeyanov Kirill <nesk@xakep.ru>
 *
 * @final
 */
class FFICaster
{
    /**
     * In case of "char*" contains a string, the length of which depends on
     * some other parameter, then during the generation of the string it is
     * possible to go beyond the allowable memory area.
     *
     * This restriction serves to ensure that during processing does not take
     * up the entire allowable memory area.
     */
    private const MAX_STRING_LENGTH = 255;

    /**
     * List of FFI scalar types
     */
    private const FFI_SCALAR_TYPES = [
        CType::TYPE_FLOAT,
        CType::TYPE_DOUBLE,
        CType::TYPE_UINT8,
        CType::TYPE_SINT8,
        CType::TYPE_UINT16,
        CType::TYPE_SINT16,
        CType::TYPE_UINT32,
        CType::TYPE_SINT32,
        CType::TYPE_UINT64,
        CType::TYPE_SINT64,
        CType::TYPE_BOOL,
        CType::TYPE_CHAR,
    ];

    public static function castCData(CData $data, array $args, Stub $stub): array
    {
        $type = \FFI::typeof($data);
        $stub->class = self::getClassName($type, $data);

        return match (true) {
            self::isScalar($type) => self::castFFIScalar($data),
            self::isEnum($type) => self::castFFIEnum($data),
            self::isPointer($type) => self::castFFIPointer($stub, $type, $data),
            self::isStruct($type) => self::castFFIStruct($type, $data),
            self::isFunction($type) => self::castFFIFunction($stub, $type, $data),
            default => $args,
        };
    }

    public static function castCType(CType $type, array $args, Stub $stub): array
    {
        $stub->class = self::getClassName($type, $type);

        return match (true) {
            self::isScalar($type) => self::castFFIScalar(),
            self::isEnum($type) => self::castFFIEnum(),
            self::isPointer($type) => self::castFFIPointer($stub, $type),
            self::isStruct($type) => self::castFFIStruct($type),
            self::isFunction($type) => self::castFFIFunction($stub, $type),
            default => $args,
        };
    }

    private static function isFunction(CType $type): bool
    {
        return $type->getKind() === CType::TYPE_FUNC;
    }

    private static function isPointer(CType $type): bool
    {
        return $type->getKind() === CType::TYPE_POINTER;
    }

    private static function isEnum(CType $type): bool
    {
        return $type->getKind() === CType::TYPE_ENUM;
    }

    private static function isStruct(CType $type): bool
    {
        return $type->getKind() === CType::TYPE_STRUCT;
    }

    private static function isScalar(CType $type): bool
    {
        $scalars = self::FFI_SCALAR_TYPES;

        if (\defined('\\FFI\\CType::TYPE_LONG_DOUBLE')) {
            $scalars[] = CType::TYPE_LONG_DOUBLE;
        }

        return \in_array($type->getKind(), $scalars, true);
    }

    private static function castFFIFunction(Stub $stub, CType $type, ?CData $data = null): array
    {
        $arguments = [];

        for ($i = 0, $count = $type->getFuncParameterCount(); $i < $count; ++$i) {
            $arguments[] = self::getTypeName($type->getFuncParameterType($i));
        }

        $abi = match ($type->getFuncABI()) {
            CType::ABI_DEFAULT,
            CType::ABI_CDECL => '[cdecl]',
            CType::ABI_FASTCALL => '[fastcall]',
            CType::ABI_THISCALL => '[thiscall]',
            CType::ABI_STDCALL => '[stdcall]',
            CType::ABI_PASCAL => '[pascal]',
            CType::ABI_REGISTER => '[register]',
            CType::ABI_MS => '[ms]',
            CType::ABI_SYSV => '[sysv]',
            CType::ABI_VECTORCALL => '[vectorcall]',
        };

        $stub->class = $abi . ' callable(' . \implode(', ', $arguments) . '): '
            . self::getTypeName($type->getFuncReturnType());

        return [Caster::PREFIX_VIRTUAL . 'returnType' => $type->getFuncReturnType()];
    }

    private static function castFFIPointer(Stub $stub, CType $type, ?CData $data = null): array
    {
        $ptr = $type->getPointerType();

        if ($data === null) {
            return [0 => $ptr];
        }

        return match ($ptr->getKind()) {
            CType::TYPE_CHAR => self::castFFIStringValue($data),
            CType::TYPE_FUNC => self::castFFIFunction($stub, $ptr, $data[0]),
            default => [Caster::PREFIX_VIRTUAL . 0 => $data[0]],
        };
    }

    private static function castFFIStringValue(CData $data): array
    {
        $result = [];
        $trimmed = true;

        for ($i = 0; $i < self::MAX_STRING_LENGTH; ++$i) {
            $result[$i] = $data[$i];

            if ($result[$i] === "\0") {
                $trimmed = false;
                break;
            }
        }

        $string = \implode('', $result);
        $length = \count($result);

        if (!$trimmed) {
            return match (\strlen($string)) {
                0 => [],
                1 => [0 => $string],
                default => [Caster::PREFIX_VIRTUAL . "[0...$length]" => $string]
            };
        }

        return [Caster::PREFIX_VIRTUAL . "[0...$length+]" => $string . '...'];
    }

    private static function castFFIEnum(?CData $data = null): array
    {
        return $data !== [] ? ['cdata' => $data->cdata] : [];
    }

    private static function castFFIScalar(?CData $data = null): array
    {
        if ($data === null) {
            return [];
        }

        return ['cdata' => $data->cdata];
    }

    private static function castFFIStruct(CType $type, ?CData $data = null): array
    {
        $result = [];

        foreach ($type->getStructFieldNames() as $name) {
            $field = $type->getStructFieldType($name);
            $value = $data === null ? $field : $data->{$name};

            if (self::isScalar($field)) {
                $result[$field->getName() . ' ' . $name] = $value;
            } else {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    private static function getTypeName(CType $type): string
    {
        return $type->getName();
    }

    private static function getClassName(CType $type, object $data = null): string
    {
        return \vsprintf('%s<%s> size %d align %d', [
            ($data ?? $type)::class,
            self::getTypeName($type),
            $type->getSize(),
            $type->getAlignment(),
        ]);
    }
}
