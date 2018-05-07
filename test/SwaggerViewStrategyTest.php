<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Apigility\Documentation\Swagger;

use PHPUnit\Framework\TestCase;
use Zend\EventManager\EventManager;
use Zend\EventManager\Test\EventListenerIntrospectionTrait;
use Zend\Http\Response as HttpResponse;
use Zend\Stdlib\Response as StdlibResponse;
use Zend\View\Renderer\JsonRenderer;
use Zend\View\ViewEvent;
use ZF\Apigility\Documentation\Swagger\SwaggerViewStrategy;
use ZF\Apigility\Documentation\Swagger\ViewModel;

class SwaggerViewStrategyTest extends TestCase
{
    use EventListenerIntrospectionTrait;

    protected function setUp()
    {
        $this->events   = new EventManager();
        $this->renderer = new JsonRenderer();
        $this->strategy = new SwaggerViewStrategy($this->renderer);
        $this->strategy->attach($this->events);
    }

    public function testStrategyAttachesToViewEventsAtPriority200()
    {
        $this->assertListenerAtPriority(
            [$this->strategy, 'selectRenderer'],
            200,
            ViewEvent::EVENT_RENDERER,
            $this->events
        );

        $this->assertListenerAtPriority(
            [$this->strategy, 'injectResponse'],
            200,
            ViewEvent::EVENT_RESPONSE,
            $this->events
        );
    }

    public function testSelectRendererReturnsJsonRendererWhenSwaggerViewModelIsPresentInEvent()
    {
        $event = new ViewEvent();
        $event->setName(ViewEvent::EVENT_RENDERER);
        $event->setModel(new ViewModel([]));

        $renderer = $this->strategy->selectRenderer($event);
        $this->assertSame($this->renderer, $renderer);
        return $event;
    }

    public function testSelectRendererReturnsNullWhenSwaggerViewModelIsNotPresentInEvent()
    {
        $event = new ViewEvent();
        $event->setName(ViewEvent::EVENT_RENDERER);

        $this->assertNull($this->strategy->selectRenderer($event));
        return $event;
    }

    /**
     * @depends testSelectRendererReturnsJsonRendererWhenSwaggerViewModelIsPresentInEvent
     */
    public function testInjectResponseSetsContentTypeWhenJsonRendererWasSelectedBySelectRendererEvent($event)
    {
        $response = new HttpResponse();
        $event->setResponse($response);
        $this->strategy->selectRenderer($event);
        $this->strategy->injectResponse($event);
        $headers = $response->getHeaders();
        $this->assertTrue($headers->has('Content-Type'), 'No Content-Type header in HTTP response!');
        $header = $headers->get('Content-Type');
        $this->assertContains('application/vnd.swagger+json', $header->getFieldValue());
    }

    /**
     * @depends testSelectRendererReturnsNullWhenSwaggerViewModelIsNotPresentInEvent
     */
    public function testInjectResponseDoesNothingWhenJsonRendererWasNotSelectedBySelectRendererEvent($event)
    {
        $response = new HttpResponse();
        $event->setResponse($response);
        $this->strategy->selectRenderer($event);
        $this->strategy->injectResponse($event);
        $headers = $response->getHeaders();
        $this->assertFalse($headers->has('Content-Type'), 'No Content-Type header in HTTP response!');
    }

    /**
     * @depends testSelectRendererReturnsJsonRendererWhenSwaggerViewModelIsPresentInEvent
     */
    public function testInjectResponseDoesNothingIfResponseIsNotHttpEnabled($event)
    {
        $response = new StdlibResponse();
        $event->setResponse($response);
        $this->strategy->selectRenderer($event);
        $this->strategy->injectResponse($event);
        $this->assertFalse(method_exists($response, 'getHeaders'));
    }
}
