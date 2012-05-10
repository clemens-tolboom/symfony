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

use Symfony\Component\Translation\Loader\PoFileLoader;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Translation\Gettext;

class PoFileLoaderTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp() {
        if (!class_exists('Symfony\Component\Config\Loader\Loader')) {
            $this->markTestSkipped('The "Config" component is not available');
        }
    }

    public function testLoad()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../fixtures/resources.po';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(array('foo' => 'bar'), $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
    }

    public function testLoadPlurals()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../fixtures/plurals.po';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(array('foo' => 'bar', 'foos' => '{0} bar|{1} bars'), $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
    }

    public function testLoadDoesNothingIfEmpty()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../fixtures/empty.po';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(array(), $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
    }

    public function testLoadMultiline()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../fixtures/multiline.po';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(3, count($catalogue->all('domain1')));

        $messages = $catalogue->all('domain1');
        $this->assertEquals('trans single line', $messages['both single line']);
        $this->assertEquals('trans multi line', $messages['source single line']);
        $this->assertEquals('trans single line', $messages['source multi line']);

    }

    /**
     * Read file with one item without whitespaces before and after.
     */
    public function testLoadMinimalFile()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../fixtures/minimal.po';
        $catalogue = $loader->load($resource, 'en', 'domain1');
        // TODO: This fails on 'source multi line'
        $this->assertEquals(1, count($catalogue->all('domain1')));
    }

    /**
     * Read the PO header and check it's available.
     */
    public function testLoadHeader()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../fixtures/header.po';
        $catalogue = $loader->load($resource, 'en', 'domain1');
        $messages = $catalogue->all('domain1');
        $this->assertEquals(1, count($catalogue->all('domain1')));
        // Header exists
        $header = Gettext::getHeader($messages);
        $this->assertNotNull($header, 'PoFileLoader has a header.');
        // Is header removed
        $header = Gettext::delHeader($messages);
        $header = Gettext::getHeader($messages);
        $this->assertNull($header, 'PoFileLoader has no header.');
        // Add header
        $header = Gettext::addHeader($messages, 'foo');
        $header = Gettext::getHeader($messages);
        $this->assertEquals($header, 'foo', 'PoFileLoader has a header.');
    }

}
