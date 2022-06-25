<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Caster;

use FFI\CData;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

/**
 * @author Nesmeyanov Kirill <nesk@xakep.ru>
 */
class FFICasterTest extends TestCase
{
    use VarDumperTestTrait;

    public function setUp(): void
    {
        if (!\extension_loaded('ffi')) {
            $this->markTestSkipped('FFI not available');
        }

        if (!\in_array(\ini_get('ffi.enable'), ['1', 'preload'], true)) {
            $this->markTestSkipped('FFI not enabled');
        }

        parent::setUp();
    }

    public function testCastAnonymousStruct(): void
    {
        $this->assertDumpEquals(<<<'PHP'
        FFI\CData<struct <anonymous>> size 4 align 4 {
          +uint32_t x: 0
        }
        PHP, \FFI::new('struct { uint32_t x; }'));
    }

    public function testCastNamedStruct(): void
    {
        $this->assertDumpEquals(<<<'PHP'
        FFI\CData<struct Example> size 4 align 4 {
          +uint32_t x: 0
        }
        PHP, \FFI::new('struct Example { uint32_t x; }'));
    }

    public function testCastAnonymousUnion(): void
    {
        $this->assertDumpEquals(<<<'PHP'
        FFI\CData<union <anonymous>> size 4 align 4 {
          +uint32_t x: 0
          +uint32_t y: 0
        }
        PHP, \FFI::new('union { uint32_t x; uint32_t y; }'));
    }

    public function testCastNamedUnion(): void
    {
        $this->assertDumpEquals(<<<'PHP'
        FFI\CData<union Example> size 4 align 4 {
          +uint32_t x: 0
          +uint32_t y: 0
        }
        PHP, \FFI::new('union Example { uint32_t x; uint32_t y; }'));
    }

    public function testCastAnonymousEnum(): void
    {
        $this->assertDumpEquals(<<<'PHP'
        FFI\CData<enum <anonymous>> size 4 align 4 {
          +cdata: 0
        }
        PHP, \FFI::new('enum { a, b }'));
    }

    public function testCastNamedEnum(): void
    {
        $this->assertDumpEquals(<<<'PHP'
        FFI\CData<enum Example> size 4 align 4 {
          +cdata: 0
        }
        PHP, \FFI::new('enum Example { a, b }'));
    }

    public function scalarsDataProvider(): array
    {
        return [
            'int8_t' => ['int8_t', '0', 1, 1],
            'uint8_t' => ['uint8_t', '0', 1, 1],
            'int16_t' => ['int16_t', '0', 2, 2],
            'uint16_t' => ['uint16_t', '0', 2, 2],
            'int32_t' => ['int32_t', '0', 4, 4],
            'uint32_t' => ['uint32_t', '0', 4, 4],
            'int64_t' => ['int64_t', '0', 8, 8],
            'uint64_t' => ['uint64_t', '0', 8, 8],

            'bool' => ['bool', 'false', 1, 1],
            'char' => ['char', '"\x00"', 1, 1],
            'float' => ['float', '0.0', 4, 4],
            'double' => ['double', '0.0', 8, 8],
        ];
    }

    /**
     * @dataProvider scalarsDataProvider
     */
    public function testCastScalar(string $type, string $value, int $size, int $align): void
    {
        $this->assertDumpEquals(<<<PHP
        FFI\CData<$type> size $size align $align {
          +cdata: $value
        }
        PHP, \FFI::new($type));
    }

    public function testVoidFunction(): void
    {
        $this->assertDumpEquals(<<<'PHP'
        [cdecl] callable(): void {
          returnType: FFI\CType<void> size 1 align 1 {}
        }
        PHP, \FFI::new('void (*)(void)'));
    }

    public function testIntFunction(): void
    {
        $this->assertDumpEquals(<<<'PHP'
        [cdecl] callable(): uint64_t {
          returnType: FFI\CType<uint64_t> size 8 align 8 {}
        }
        PHP, \FFI::new('unsigned long long (*)(void)'));
    }

    public function testFunctionWithArguments(): void
    {
        $this->assertDumpEquals(<<<'PHP'
        [cdecl] callable(int32_t, char*): void {
          returnType: FFI\CType<void> size 1 align 1 {}
        }
        PHP, \FFI::new('void (*)(int a, const char* b)'));
    }

    public function testCompositeStruct(): void
    {
        $ffi = \FFI::cdef(<<<'CPP'
        typedef struct {
            int x;
            int y;
        } Point;
        typedef struct Example {
            uint8_t a[32];
            long b;
            __extension__ union {
                __extension__ struct {
                    short c;
                    long d;
                };
                struct {
                    Point point;
                    float e;
                };
            };
            short f;
            bool g;
            int (*func)(
                struct __sub *h
            );
        } Example;
        CPP);

        $var = $ffi->new('Example');
        $var->func = (static fn (object $p) => 42);

        $this->assertDumpEquals(<<<'PHP'
        FFI\CData<struct Example> size 64 align 8 {
          +a: FFI\CData<uint8_t[32]> size 32 align 1 {}
          +int32_t b: 0
          +int16_t c: 0
          +int32_t d: 0
          +point: FFI\CData<struct <anonymous>> size 8 align 4 {
            +int32_t x: 0
            +int32_t y: 0
          }
          +float e: 0.0
          +int16_t f: 0
          +bool g: false
          +func: [cdecl] callable(struct __sub*): int32_t {
            returnType: FFI\CType<int32_t> size 4 align 4 {}
          }
        }
        PHP, $var);
    }
}
