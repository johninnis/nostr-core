<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Service;

use FFI;

final class FfiLibraryLoader
{
    public static function tryLoad(string $cdef, array $libraryNames): ?FFI
    {
        foreach ($libraryNames as $name) {
            try {
                return FFI::cdef($cdef, $name);
            } catch (FFI\Exception) {
                continue;
            }
        }

        return null;
    }

    public static function toBuffer(FFI $ffi, string $data): FFI\CData
    {
        $length = strlen($data);
        if (0 === $length) {
            return $ffi->new('unsigned char[1]');
        }

        $buffer = $ffi->new("unsigned char[{$length}]");
        FFI::memcpy($buffer, $data, $length);

        return $buffer;
    }
}
