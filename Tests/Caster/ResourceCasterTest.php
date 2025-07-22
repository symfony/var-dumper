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

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

class ResourceCasterTest extends TestCase
{
    use VarDumperTestTrait;

    /**
     * @requires extension dba
     */
    public function testCastDba()
    {
        $dba = dba_open(sys_get_temp_dir().'/test.db', 'c');

        $this->assertDumpMatchesFormat(
            <<<'EODUMP'
Dba\Connection {
  +file: %s
}
EODUMP, $dba);
    }
}
