<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Loader;

use Symfony\Component\Translation\Gettext;

/**
 * Description of GettextText
 *
 * @author clemens
 */
class GettextTest extends \PHPUnit_Framework_TestCase
{
    function testHeaderExplode() {
        $header = "";
        $actual = Gettext::explodeHeader($header);
        $this->assertEquals($actual, array(), 'Empty header.');
        $header = "A:B\nC:D";
        $actual = Gettext::explodeHeader($header);
        $this->assertArrayHasKey('C', $actual, 'Header key A found');
        $this->assertEquals('D', $actual['C'], 'Header value D for C');
    }

    function testHeaderImplode() {
        $header = array();
        $actual = Gettext::implodeHeader($header);
        $this->assertEquals($actual, NULL, 'Empty header.');
        $header = array("A" => "B", "C" => "D");
        $actual = Gettext::implodeHeader($header);
        $expected = implode("\n", array('msgid ""', 'msgstr ""', '"A: B\n"','"C: D\n"'));
        $this->assertEquals($expected, $actual, 'Header string ok');
    }

    function testValidHeader() {
        $header = Gettext::emptyHeader();
        $this->assertEquals(Gettext::headerKeys(), array_keys($header));
        $this->assertEquals("", implode('', $header));
        $implode = Gettext::implodeHeader($header);
        $this->assertEquals($header, Gettext::explodeHeader($implode));
    }
    
    function testIdentityHeader() {
        $resource = __DIR__.'/fixtures/header.po';
        $header = file_get_contents($resource);
        // Make sure header keeps the same
        $exploded = Gettext::explodeHeader($header);
        $imploded = Gettext::implodeHeader($exploded);
        $this->assertEquals($header, $imploded, 'Header from file maps to internal version');
    }

}
