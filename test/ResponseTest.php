<?php

/**
 * @see       https://github.com/laminas/laminas-xmlrpc for the canonical source repository
 * @copyright https://github.com/laminas/laminas-xmlrpc/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-xmlrpc/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\XmlRpc;

use Laminas\XmlRpc\AbstractValue;
use Laminas\XmlRpc\Response;

/**
 * @group      Laminas_XmlRpc
 */
class ResponseTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Response object
     * @var Response
     */
    protected $_response;

    /**
     * @var bool
     */
    protected $_errorOccurred = false;

    /**
     * Setup environment
     */
    public function setUp()
    {
        $this->_response = new Response();
    }

    /**
     * Teardown environment
     */
    public function tearDown()
    {
        unset($this->_response);
    }

    /**
     * __construct() test
     */
    public function test__construct()
    {
        $this->assertTrue($this->_response instanceof Response);
    }

    /**
     * get/setReturnValue() test
     */
    public function testReturnValue()
    {
        $this->_response->setReturnValue('string');
        $this->assertEquals('string', $this->_response->getReturnValue());

        $this->_response->setReturnValue(array('one', 'two'));
        $this->assertSame(array('one', 'two'), $this->_response->getReturnValue());
    }

    /**
     * isFault() test
     *
     * Call as method call
     *
     * Returns: bool
     */
    public function testIsFault()
    {
        $this->assertFalse($this->_response->isFault());
        $this->_response->loadXml('foo');
        $this->assertTrue($this->_response->isFault());
    }

    /**
     * Tests getFault() returns NULL (no fault) or the fault object
     */
    public function testGetFault()
    {
        $this->assertNull($this->_response->getFault());
        $this->_response->loadXml('foo');
        $this->assertInstanceOf('Laminas\\XmlRpc\\Fault', $this->_response->getFault());
    }

    /**
     * loadXml() test
     *
     * Call as method call
     *
     * Expects:
     * - response:
     *
     * Returns: bool
     */
    public function testLoadXml()
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $response = $dom->appendChild($dom->createElement('methodResponse'));
        $params   = $response->appendChild($dom->createElement('params'));
        $param    = $params->appendChild($dom->createElement('param'));
        $value    = $param->appendChild($dom->createElement('value'));
        $value->appendChild($dom->createElement('string', 'Return value'));

        $xml = $dom->saveXml();

        $parsed = $this->_response->loadXml($xml);
        $this->assertTrue($parsed, $xml);
        $this->assertEquals('Return value', $this->_response->getReturnValue());
    }

    public function testLoadXmlWithInvalidValue()
    {
        $this->assertFalse($this->_response->loadXml(new \stdClass()));
        $this->assertTrue($this->_response->isFault());
        $this->assertSame(650, $this->_response->getFault()->getCode());
    }

    /**
     * @group Laminas-9039
     */
    public function testExceptionIsThrownWhenInvalidXmlIsReturnedByServer()
    {
        set_error_handler(array($this, 'trackError'));
        $invalidResponse = 'foo';
        $response = new Response();
        $this->assertFalse($this->_errorOccurred);
        $this->assertFalse($response->loadXml($invalidResponse));
        $this->assertFalse($this->_errorOccurred);
    }

    /**
     * @group Laminas-5404
     */
    public function testNilResponseFromXmlRpcServer()
    {
        $rawResponse = <<<EOD
<methodResponse><params><param><value><array><data><value><struct><member><name>id</name><value><string>1</string></value></member><member><name>name</name><value><string>birdy num num!</string></value></member><member><name>description</name><value><nil/></value></member></struct></value></data></array></value></param></params></methodResponse>
EOD;

        $response = new Response();
        $ret      = $response->loadXml($rawResponse);

        $this->assertTrue($ret);
        $this->assertEquals(array(
            0 => array(
                'id'            => 1,
                'name'          => 'birdy num num!',
                'description'   => null,
            )
        ), $response->getReturnValue());
    }

    /**
     * helper for saveXml() and __toString() tests
     *
     * @param string $xml
     * @return void
     */
    protected function _testXmlResponse($xml)
    {
        $sx = new \SimpleXMLElement($xml);

        $this->assertTrue((bool) $sx->params);
        $this->assertTrue((bool) $sx->params->param);
        $this->assertTrue((bool) $sx->params->param->value);
        $this->assertTrue((bool) $sx->params->param->value->string);
        $this->assertEquals('return value', (string) $sx->params->param->value->string);
    }

    /**
     * saveXml() test
     */
    public function testSaveXML()
    {
        $this->_response->setReturnValue('return value');
        $xml = $this->_response->saveXml();
        $this->_testXmlResponse($xml);
    }

    /**
     * __toString() test
     */
    public function test__toString()
    {
        $this->_response->setReturnValue('return value');
        $xml = $this->_response->__toString();
        $this->_testXmlResponse($xml);
    }

    /**
     * Test encoding settings
     */
    public function testSetGetEncoding()
    {
        $this->assertEquals('UTF-8', $this->_response->getEncoding());
        $this->assertEquals('UTF-8', AbstractValue::getGenerator()->getEncoding());
        $this->assertSame($this->_response, $this->_response->setEncoding('ISO-8859-1'));
        $this->assertEquals('ISO-8859-1', $this->_response->getEncoding());
        $this->assertEquals('ISO-8859-1', AbstractValue::getGenerator()->getEncoding());
    }

    public function testLoadXmlCreatesFaultWithMissingNodes()
    {
        $sxl = new \SimpleXMLElement('<?xml version="1.0"?><methodResponse><params><param>foo</param></params></methodResponse>');

        $this->assertFalse($this->_response->loadXml($sxl->asXML()));
        $this->assertTrue($this->_response->isFault());
        $fault = $this->_response->getFault();
        $this->assertEquals(653, $fault->getCode());
    }

    public function testLoadXmlCreatesFaultWithMissingNodes2()
    {
        $sxl = new \SimpleXMLElement('<?xml version="1.0"?><methodResponse><params>foo</params></methodResponse>');

        $this->assertFalse($this->_response->loadXml($sxl->asXML()));
        $this->assertTrue($this->_response->isFault());
        $fault = $this->_response->getFault();
        $this->assertEquals(653, $fault->getCode());
    }

    public function testLoadXmlThrowsExceptionWithMissingNodes3()
    {
        $sxl = new \SimpleXMLElement('<?xml version="1.0"?><methodResponse><bar>foo</bar></methodResponse>');

        $this->assertFalse($this->_response->loadXml($sxl->asXML()));
        $this->assertTrue($this->_response->isFault());
        $fault = $this->_response->getFault();
        $this->assertEquals(652, $fault->getCode());
    }


    public function trackError($error)
    {
        $this->_errorOccurred = true;
    }

    /**
     * @group Laminas-12293
     */
    public function testDoesNotAllowExternalEntities()
    {
        $payload = file_get_contents(dirname(__FILE__) . '/_files/Laminas12293-response.xml');
        $payload = sprintf($payload, 'file://' . realpath(dirname(__FILE__) . '/_files/Laminas12293-payload.txt'));
        $this->_response->loadXml($payload);
        $value = $this->_response->getReturnValue();
        $this->assertTrue(empty($value));
        if (is_string($value)) {
            $this->assertNotContains('Local file inclusion', $value);
        }
    }

    public function testShouldDisallowsDoctypeInRequestXmlAndReturnFalseOnLoading()
    {
        $payload = file_get_contents(dirname(__FILE__) . '/_files/Laminas12293-response.xml');
        $payload = sprintf($payload, 'file://' . realpath(dirname(__FILE__) . '/_files/Laminas12293-payload.txt'));
        $this->assertFalse($this->_response->loadXml($payload));
    }
}
