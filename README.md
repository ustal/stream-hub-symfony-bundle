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

When runtime services are configured, the bundle registers:

- `Ustal\StreamHub\Core\StreamHubInterface`

This is the main application-facing facade. It dispatches high-level commands through the guarded feature bus and exposes read operations such as stream lists and unread counters.

## Minimal Configuration

```yaml
# config/packages/stream_hub.yaml
stream_hub:
  backend_service: app.stream_backend
  context_service: app.stream_context
  id_generators: {}
```

If only one of `backend_service` or `context_service` is configured, the bundle throws during container loading.

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
