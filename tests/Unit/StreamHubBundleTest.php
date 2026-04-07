<?php

namespace Ustal\StreamHub\SymfonyBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ustal\StreamHub\SymfonyBundle\DependencyInjection\StreamHubExtension;
use Ustal\StreamHub\SymfonyBundle\StreamHubBundle;

final class StreamHubBundleTest extends TestCase
{
    public function testBundleBuildsExpectedContainerExtension(): void
    {
        $bundle = new StreamHubBundle();

        $this->assertInstanceOf(StreamHubExtension::class, $bundle->getContainerExtension());
    }
}
