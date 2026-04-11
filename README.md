# stream-hub-symfony-bundle

[![CI](https://github.com/ustal/stream-hub-symfony-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/ustal/stream-hub-symfony-bundle/actions/workflows/ci.yml)

Thin Symfony wiring for Stream Hub.

This package targets:
- `stream-hub-core ^1.0`
- `stream-hub-plugins ^1.0`

In the `v1` direction this bundle focuses on:

- low-level and feature command bus wiring;
- guard wiring for high-level feature commands;
- registration of built-in identifier generators;
- registration of low-level core handlers;
- optional registration of feature handlers when their dependencies are configured.

It is intentionally not a rendering or asset-integration bundle anymore.

## Main Entry Point

The bundle registers:

- `Ustal\StreamHub\SymfonyBundle\Registry\StreamHubRegistry`
- `Ustal\StreamHub\Core\StreamHubInterface`

`StreamHubRegistry` is the preferred application-facing entry point.

`StreamHubInterface` remains as a backward-compatible alias to the `default` instance only.

## Minimal Configuration

```yaml
# config/packages/stream_hub.yaml
stream_hub:
  backend_service: app.stream_backend
  context_service: app.stream_context
  id_generators: {}
```

If only one of `backend_service` or `context_service` is configured, the bundle throws during container loading.

This legacy root-level configuration is treated as the `default` Stream Hub instance.

## Named Instances

The bundle also supports multiple named instances:

```yaml
stream_hub:
  backend_service: app.default_stream_backend
  context_service: app.default_stream_context

  instances:
    audit:
      backend_service: app.audit_stream_backend
      context_service: app.audit_stream_context
      id_generators:
        stream-lifecycle:
          system_event_id: uuid_v7
```

Generated service ids:

- `stream_hub.instance.default.stream_hub`
- `stream_hub.instance.audit.stream_hub`

Recommended usage:

```php
use Ustal\StreamHub\SymfonyBundle\Registry\StreamHubRegistry;

final class AuditController
{
    public function __construct(
        private readonly StreamHubRegistry $streamHubs,
    ) {}

    public function __invoke(): void
    {
        $default = $this->streamHubs->get();
        $audit = $this->streamHubs->get('audit');
    }
}
```

Instance services are internal implementation details. For multi-instance applications, inject the registry and resolve the needed Stream Hub explicitly.

The legacy aliases still point to the `default` instance:

- `Ustal\StreamHub\Core\StreamHubInterface`
- `Ustal\StreamHub\Core\Command\CommandBusInterface`
- `Ustal\StreamHub\Core\Command\ModelCommandBusInterface`

This means existing single-instance applications continue to work without changes:

```php
use Ustal\StreamHub\Core\StreamHubInterface;

final class LegacyController
{
    public function __construct(
        private readonly StreamHubInterface $streamHub,
    ) {}
}
```

## Optional Message Module Wiring

To enable the message module handler wiring:

```yaml
stream_hub:
  backend_service: app.stream_backend
  context_service: app.stream_context
  id_generators:
    message-composer:
      event_id: uuid_v7
```

Built-in generator names:

- `random_hex`
- `uuid_v4`
- `uuid_v7`

Custom Symfony service ids may also be used instead of a built-in generator name.

## Optional Stream Lifecycle Module Wiring

To enable the lifecycle module handler wiring:

```yaml
stream_hub:
  backend_service: app.stream_backend
  context_service: app.stream_context
  id_generators:
    stream-lifecycle:
      system_event_id: uuid_v7
```

## Guards

High-level commands may be protected by tagged guard services. Guards are applied only to the feature bus. The low-level model bus stays unguarded.

Register a guard as a regular Symfony service and tag it with:

```yaml
services:
  App\StreamHub\Guard\SendMessageGuard:
    tags:
      - { name: stream_hub.command_guard }
```

## Query Side

The bundle does not try to introduce a storage-agnostic query DSL.

For now the application-facing facade keeps only simple read operations:

- stream list
- single stream lookup
- unread counters

Anything more specific than that should stay in the project backend or in project-specific query services.

## Development

Install dependencies:

```bash
make install
```

Run tests:

```bash
make test
```

Run deptrac:

```bash
make deptrac
```
