<?php

namespace SlothsTest\View\Helper;

use Sloths\View\View;

/**
 * @covers \Sloths\View\Helper\TextAreaTag<extended>
 */
class TextAreaTagTest extends \PHPUnit_Framework_TestCase
{
    public function test()
    {
        $view = new View();
        $expected = '<textarea placeholder="Foo" name="foo">bar</textarea>';
        $this->assertSame($expected, (String) $view->textAreaTag('foo', 'bar', ['placeholder' => 'Foo']));
    }
}