<?php

namespace Ustal\StreamHub\SymfonyBundle\Tests\Fake;

use Ustal\StreamHub\Component\Context\StreamContextInterface;
use Ustal\StreamHub\Component\Model\Stream;
use Ustal\StreamHub\Component\Model\StreamCollection;
use Ustal\StreamHub\Component\Model\StreamEvent;
use Ustal\StreamHub\Component\Model\StreamEventCollection;
use Ustal\StreamHub\Component\Model\StreamParticipant;
use Ustal\StreamHub\Component\Storage\StreamBackendInterface;

final class InMemoryBackend implements StreamBackendInterface
{
    public function createStream(StreamContextInterface $context, array $participants): Stream
    {
        return $this->createDefaultStream($participants);
    }

    public function joinStream(StreamContextInterface $context, string $streamId, StreamParticipant $participant): Stream
    {
        return $this->createDefaultStream([$participant], $streamId);
    }

    public function getStream(StreamContextInterface $context, string $streamId): ?Stream
    {
        return $this->createDefaultStream([], $streamId);
    }

    public function getStreams(StreamContextInterface $context): StreamCollection
    {
        return new StreamCollection($this->createDefaultStream());
    }

    public function appendEvent(StreamContextInterface $context, string $streamId, StreamEvent $event): StreamEvent
    {
        return $event;
    }

    public function markRead(StreamContextInterface $context, string $streamId): void
    {
    }

    public function getUnreadStreamCount(StreamContextInterface $context): int
    {
        return 0;
    }

    public function getUnreadEventCount(StreamContextInterface $context): int
    {
        return 0;
    }

    public function getUnreadEventCountForStream(StreamContextInterface $context, string $streamId): int
    {
        return 0;
    }

    /**
     * @param StreamParticipant[] $participants
     */
    private function createDefaultStream(array $participants = [], string $streamId = 'stream-1'): Stream
    {
        return new Stream(
            id: $streamId,
            participants: $participants,
            events: new StreamEventCollection(),
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }
}
