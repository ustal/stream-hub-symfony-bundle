# stream-hub-symfony-bundle

[![CI](https://github.com/ustal/stream-hub-symfony-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/ustal/stream-hub-symfony-bundle/actions/workflows/ci.yml)

Thin Symfony wiring for Stream Hub.

In the `v1` direction this bundle focuses on:

- low-level and feature command bus wiring;
- registration of built-in identifier generators;
- registration of low-level core handlers;
- optional registration of feature handlers when their dependencies are configured.

It is intentionally not a rendering or asset-integration bundle anymore.

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
