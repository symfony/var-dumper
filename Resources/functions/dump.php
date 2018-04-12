<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\VarDumper\VarDumper;

if (!function_exists('dump')) {
    /**
     * @author Nicolas Grekas <p@tchwork.com>
     */
    function dump($var)
    {
        foreach (func_get_args() as $var) {
            VarDumper::dump($var);
        }
    }
}

if (!function_exists('dd')) {
    /**
     * @author Giorgio Grasso <me@grag.io>
     */
    function dd()
    {
        foreach (func_get_args() as $obj) {
            VarDumper::dump($obj);
        }
        die;
    }
}
