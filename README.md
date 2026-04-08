# stream-hub-symfony-bundle

[![CI](https://github.com/ustal/stream-hub-symfony-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/ustal/stream-hub-symfony-bundle/actions/workflows/ci.yml)

Symfony bundle package for Stream Hub integration.

This repository is intended to own Symfony-specific wiring:

- service registration
- bundle configuration
- cache warmers
- console commands
- asset integration
- future framework-level glue for bridge and plugins

Out of the box, this bundle is opinionated: it depends on the official Stream Hub plugin pack and enables the starter scaffold plugins by default.

## Base Structure

```text
src/
  StreamHubBundle.php
  DependencyInjection/
  Command/
  CacheWarmer/
  Resources/config/
tests/
```

The `bridge` package should stay focused on rendering primitives such as Twig helpers and view renderers. Symfony lifecycle concerns belong here.

## Minimal Configuration

The bundle can wire the Stream Hub runtime only when the host application provides both:

- a service implementing `Ustal\StreamHub\Component\Storage\StreamBackendInterface`
- a service implementing `Ustal\StreamHub\Component\Context\StreamContextInterface`

Example:

```yaml
# config/packages/stream_hub.yaml
stream_hub:
  backend_service: app.stream_backend
  context_service: app.stream_context
  enabled_plugins:
    - Ustal\StreamHub\Plugins\TwoColumnLayout\TwoColumnLayoutPlugin
    - Ustal\StreamHub\Plugins\SidebarScaffold\SidebarScaffoldPlugin
    - Ustal\StreamHub\Plugins\DialogScaffold\DialogScaffoldPlugin
  root_slots:
    - main
  id_generators: {}
```

If only one of `backend_service` or `context_service` is configured, the bundle will throw an exception during container loading.

With both services configured, the bundle wires:

- `PluginDefinitionRegistry`
- `SlotTree`
- `PluginManager`
- `CommandBusInterface`
- `SlotRendererInterface`
- `StreamPageRendererInterface`
- Twig slot rendering through `render_slot(...)`
- `TwigViewRenderer` through `ViewRendererInterface`

By default the bundle enables the official starter scaffold:

- `TwoColumnLayoutPlugin`
- `SidebarScaffoldPlugin`
- `DialogScaffoldPlugin`

Optional official plugins can be added through `enabled_plugins`. For example, to enable the message composer widget:

```yaml
stream_hub:
  backend_service: app.stream_backend
  context_service: app.stream_context
  enabled_plugins:
    - Ustal\StreamHub\Plugins\TwoColumnLayout\TwoColumnLayoutPlugin
    - Ustal\StreamHub\Plugins\SidebarScaffold\SidebarScaffoldPlugin
    - Ustal\StreamHub\Plugins\DialogScaffold\DialogScaffoldPlugin
    - Ustal\StreamHub\Plugins\MessageComposer\MessageComposerPlugin
  id_generators:
    message-composer:
      event_id: uuid_v7
```

`MessageComposerPlugin` expects the project context to expose at least:

- `stream_hub.message_composer.stream_id`
- `stream_hub.message_composer.action_url`

It also uses `StreamContextInterface::getCsrfToken()` for the form token and dispatches message sending through the Stream Hub command bus.

The bundle does not provide identifier-generator defaults for plugins that declare generator requirements. If such a plugin is enabled, the configuration must explicitly map each required key.

Built-in generator names:

- `random_hex`
- `uuid_v4`
- `uuid_v7`

Custom service ids may also be used instead of a built-in generator name.

## Debug Commands

When the runtime is configured, the bundle also exposes Symfony console helpers:

```bash
php bin/console stream-hub:debug:plugins
php bin/console stream-hub:debug:slots
```

They print the enabled plugin set, public assets, widget/handler classes, and the slot tree resolved from the configured root slots.

## Template Overrides

The bundle can also participate in widget template overriding.

If the consuming Symfony application provides a service implementing `WidgetTemplateResolverInterface`, it will be exposed through the decorated stream context and widgets may use it to override their default template map on a per-project basis.

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
