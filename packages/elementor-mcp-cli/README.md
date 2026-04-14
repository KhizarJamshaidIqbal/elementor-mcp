# Elementor MCP CLI

`@elementor-mcp/cli` is the companion package for **MCP Tools for Elementor**. It gives desktop MCP clients a simpler install target than copying plugin files around, while keeping the WordPress plugin as the source of truth for Elementor-aware behavior.

## Modes

### `endpoint` mode (default)

Runs a framed stdio bridge to the plugin's HTTP MCP endpoint:

```bash
npx @elementor-mcp/cli \
  --mode=endpoint \
  --wp-url=https://example.com \
  --wp-username=admin \
  --wp-app-password="xxxx xxxx xxxx xxxx xxxx xxxx"
```

Use this when the site-side Elementor MCP endpoint is healthy and you mainly need a portable local launcher.

### `rest` mode

Runs a standalone stdio MCP server that uses the WordPress REST API directly for page-level fallback workflows:

```bash
npx @elementor-mcp/cli \
  --mode=rest \
  --wp-url=https://example.com \
  --wp-username=admin \
  --wp-app-password="xxxx xxxx xxxx xxxx xxxx xxxx"
```

REST fallback tools:

- `get_page`
- `get_page_by_slug`
- `get_page_id_by_slug`
- `download_page_to_file`
- `update_page_from_file`
- `create_page`
- `update_page`
- `duplicate_page`
- `delete_page`

## Environment variables

- `WP_URL`
- `WP_USERNAME` or `WP_APP_USER`
- `WP_APP_PASSWORD`
- `ELEMENTOR_MCP_MODE`
- `MCP_PROTOCOL_VERSION`
- `MCP_LOG_FILE`

## Notes

- The WordPress plugin remains the primary integration path.
- `rest` mode is intended for client-transport fallbacks and page/file workflows.
- Read-oriented REST fallback tools still need authenticated `context=edit` access on the core `wp/v2` endpoints so they can confirm Elementor metadata. If the user can authenticate but still gets `rest_forbidden_context`, use `endpoint` mode or a user with edit capability for that post type.
- Elementor meta writes over REST depend on the target site exposing those keys to the REST API. If the site rejects Elementor meta, switch back to `endpoint` mode or use the plugin MCP tools directly.
