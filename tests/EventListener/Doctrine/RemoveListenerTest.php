<?php

namespace Vich\UploaderBundle\Tests\EventListener\Doctrine;

use Doctrine\Common\Proxy\Proxy;
use PHPUnit\Framework\MockObject\MockObject;
use Vich\UploaderBundle\EventListener\Doctrine\RemoveListener;
use Vich\UploaderBundle\Tests\DummyEntity;

/**
 * Doctrine RemoveListener test.
 *
 * @author Kévin Gomez <contact@kevingomez.fr>
 */
final class RemoveListenerTest extends ListenerTestCase
{
    /**
     * Sets up the test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->listener = new RemoveListener(self::MAPPING_NAME, $this->adapter, $this->metadata, $this->handler);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = $this->listener->getSubscribedEvents();

        self::assertSame(['preRemove', 'postFlush'], $events);
    }

    public function testPreRemove(): void
    {
        $this->object = $this->getEntityProxyMock();

        $this->metadata
            ->expects(self::once())
            ->method('isUploadable')
            ->with('VichUploaderEntityProxy')
            ->willReturn(true);

        $this->object
            ->expects(self::once())
            ->method('__load');

        $this->listener->preRemove($this->event);
    }

    public function testPreRemoveSkipNonUploadable(): void
    {
        $this->object = $this->getEntityProxyMock();
        $this->object
            ->expects($this->never())
            ->method('__load');

        $this->metadata
            ->expects(self::once())
            ->method('isUploadable')
            ->with('VichUploaderEntityProxy')
            ->willReturn(false);

        $this->listener->preRemove($this->event);
    }

    public function testPostFlush(): void
    {
        // isUploadable
        $this->metadata
            ->expects(self::once())
            ->method('isUploadable')
            ->with(DummyEntity::class)
            ->willReturn(true);

        $this->listener->preRemove($this->event);

        $this->metadata
            ->expects(self::once())
            ->method('getUploadableFields')
            ->with(DummyEntity::class)
            ->willReturn([['propertyName' => 'field_name']])
        ;

        $this->handler
            ->expects(self::once())
            ->method('remove')
            ->with($this->object, 'field_name')
        ;

        $this->listener->postFlush();
    }

    /**
     * Test that postRemove skips non uploadable entity.
     */
    public function testPostFlushSkipsNonUploadable(): void
    {
        // isUploadable
        $this->metadata
            ->expects(self::once())
            ->method('isUploadable')
            ->with(DummyEntity::class)
            ->willReturn(false);

        $this->listener->preRemove($this->event);

        $this->metadata
            ->expects(self::never())
            ->method('getUploadableFields')
            ->with(DummyEntity::class)
            ->willReturn([['propertyName' => 'field_name']])
        ;

        $this->handler
            ->expects(self::never())
            ->method('remove')
            ->with($this->object, 'field_name')
        ;

        $this->listener->postFlush();
    }

    /**
     * @return Proxy&MockObject
     */
    protected function getEntityProxyMock(): MockObject
    {
        return $this->getMockBuilder(Proxy::class)
            ->setMockClassName('VichUploaderEntityProxy')
            ->getMock();
    }
}
