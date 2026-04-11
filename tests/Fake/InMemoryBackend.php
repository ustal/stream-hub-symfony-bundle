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
    /** @var array<string, Stream> */
    private array $streams = [];

    public function createStream(StreamContextInterface $context, string $streamId, array $participants): Stream
    {
        if (isset($this->streams[$streamId])) {
            return $this->streams[$streamId];
        }

        $stream = new Stream(
            id: $streamId,
            participants: $participants,
            events: new StreamEventCollection(),
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        $this->streams[$streamId] = $stream;

        return $stream;
    }

    public function joinStream(StreamContextInterface $context, string $streamId, StreamParticipant $participant): Stream
    {
        $stream = $this->streams[$streamId] ?? $this->createStream($context, $streamId, []);
        $participants = [...$stream->participants];

        foreach ($participants as $index => $existingParticipant) {
            if ($existingParticipant->userId !== $participant->userId) {
                continue;
            }

            $participants[$index] = $participant;

            return $this->storeUpdatedStream($stream, $participants, $stream->events);
        }

        $participants[] = $participant;

        return $this->storeUpdatedStream($stream, $participants, $stream->events);
    }

    public function leaveStream(StreamContextInterface $context, string $streamId, string $userId, \DateTimeImmutable $leftAt): Stream
    {
        $stream = $this->streams[$streamId] ?? $this->createStream($context, $streamId, []);
        $participants = array_map(
            static function (StreamParticipant $participant) use ($userId, $leftAt): StreamParticipant {
                if ($participant->userId !== $userId) {
                    return $participant;
                }

                return new StreamParticipant(
                    userId: $participant->userId,
                    displayName: $participant->displayName,
                    active: false,
                    createdAt: $participant->createdAt,
                    settings: $participant->settings,
                    leftAt: $leftAt,
                    lastReadAt: $participant->lastReadAt,
                );
            },
            $stream->participants
        );

        return $this->storeUpdatedStream($stream, $participants, $stream->events);
    }

    public function getStream(StreamContextInterface $context, string $streamId): ?Stream
    {
        return $this->streams[$streamId] ?? null;
    }

    public function getStreams(StreamContextInterface $context): StreamCollection
    {
        return new StreamCollection(...array_values($this->streams));
    }

    public function appendEvent(StreamContextInterface $context, string $streamId, StreamEvent $event): StreamEvent
    {
        $stream = $this->streams[$streamId] ?? $this->createStream($context, $streamId, []);
        $events = iterator_to_array($stream->events->getIterator());
        $events[] = $event;

        $this->storeUpdatedStream($stream, $stream->participants, new StreamEventCollection(...$events));

        return $event;
    }

    public function markRead(StreamContextInterface $context, string $streamId): void
    {
        $stream = $this->streams[$streamId] ?? null;

        if ($stream === null) {
            return;
        }

        $participants = array_map(
            static function (StreamParticipant $participant) use ($context): StreamParticipant {
                if ($participant->userId !== $context->getUserId()) {
                    return $participant;
                }

                return new StreamParticipant(
                    userId: $participant->userId,
                    displayName: $participant->displayName,
                    active: $participant->active,
                    createdAt: $participant->createdAt,
                    settings: $participant->settings,
                    leftAt: $participant->leftAt,
                    lastReadAt: new \DateTimeImmutable(),
                );
            },
            $stream->participants
        );

        $this->storeUpdatedStream($stream, $participants, $stream->events);
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

    public function streamCount(): int
    {
        return count($this->streams);
    }

    public function eventCountFor(string $streamId): int
    {
        $stream = $this->streams[$streamId] ?? null;

        if ($stream === null) {
            return 0;
        }

        return count(iterator_to_array($stream->events->getIterator()));
    }

    /**
     * @param StreamParticipant[] $participants
     */
    private function storeUpdatedStream(Stream $stream, array $participants, StreamEventCollection $events): Stream
    {
        $updatedStream = new Stream(
            id: $stream->id,
            participants: $participants,
            events: $events,
            createdAt: $stream->createdAt,
            updatedAt: new \DateTimeImmutable(),
        );

        $this->streams[$stream->id] = $updatedStream;

        return $updatedStream;
    }
}
