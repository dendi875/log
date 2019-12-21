<?php
/**
 * Walle\Monolog\Helper
 *
 * @author     <dendi875@163.com>
 * @createDate 2019-12-21 14:12:32
 * @copyright  Copyright (c) 2019 https://github.com/dendi875
 */

namespace Walle\Modules\Helper;

use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    /**
     * @covers Walle\Modules\Helper\Utils
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp /json_decode error: Syntax error/
     */
    public function testBadJsonDataDecode()
    {
        $badJson = '{"id":1, "name":"zq"';

        Utils::jsonDecode($badJson);
    }

    /**
     * @covers Walle\Modules\Helper\Utils
     */
    public function testGoodJsonDataDecode()
    {
        $badJson = '{"id":1, "name":"zq"}';

        $result = Utils::jsonDecode($badJson, true);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
    }
}