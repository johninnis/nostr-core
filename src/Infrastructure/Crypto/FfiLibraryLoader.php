<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use FFI;
use FFI\CData;
use FFI\Exception as FfiException;

final class FfiLibraryLoader
{
    private function __construct()
    {
    }

    /**
     * @param list<string> $libraryNames
     */
    public static function tryLoad(string $cdef, array $libraryNames): ?FFI
    {
        if (!extension_loaded('ffi')) {
            return null;
        }

        foreach ($libraryNames as $name) {
            try {
                return FFI::cdef($cdef, $name);
            } catch (FfiException) {
                continue;
            }
        }

        return null;
    }

    public static function toBuffer(FFI $ffi, string $data): CData
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
