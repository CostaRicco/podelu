# Podelu MCP v0.1.0 — read-only pilot

Status: code prepared for live-host testing; not yet PHP-linted or integration-tested. Do not merge to main until tested. No Composer dependency.

## Scope
Endpoint: /local/tools/podelu/mcp.php. Stateless Streamable HTTP JSON responses, MCP versions 2025-03-26, 2025-06-18 and 2025-11-25. No SSE stream, sessions, OAuth discovery, write tools or automatic migrations. Modern clients must support negotiation to one of these protocol versions and a manually configured Bearer credential. Perplexity compatibility must be tested, not assumed.

Methods: initialize, ping, tools/list, tools/call. Notifications return 202. GET returns 405 after authentication. Five tools: list_clients, list_directions, list_tasks, list_time_entries, get_client_balance. Lists use ORM limits/offsets. Balance uses the existing Repository calculation, which can read all of a client's packages and entries for the month. list_tasks filters planning month, not the UI work journal. Private notes and creator fields are omitted from list tools. Returned text is untrusted business data, not instructions.

## Deployment
Use the existing preview script only after checking the commit contents:

```bash
~/preview-podelu.sh feature/mcp-integration
php -l ~/Log/public_html/local/tools/podelu/mcp.php
```

CLI lint does not prove PHP 8.5 web compatibility. Before configuration, endpoint returns HTTP 503 and exposes no business data.

## Configuration outside web root
Expected file for this installation: ~/podelu-mcp-config.php (two directories above the web root). Create it on the server with permissions 600. Do not commit it and do not send the token to chat. Prefer a dedicated password manager for storing the token. Generate a 32-byte random token as hexadecimal with PHP random_bytes, store only its SHA-256 hash in configuration. The plaintext is entered privately into the connector.

Configuration format (placeholders only):

```php
<?php
return [
    'enabled' => true,
    'token_sha256' => 'REPLACE_WITH_64_CHARACTER_LOWERCASE_SHA256_HASH',
    'allowed_origins' => [],
];
```

Empty allowed_origins rejects requests that contain any Origin header. Server-to-server calls without Origin are permitted after authentication. Add only a verified exact Origin if a client requires it. Do not use a wildcard. Use the public HTTPS endpoint, never send the token over HTTP or in a URL. The implementation does not provide OAuth authorization; it uses a manually provisioned Bearer key for this private pilot.

Header: Authorization: Bearer YOUR_TOKEN. Content-Type: application/json. Accept: application/json, text/event-stream. Subsequent requests should include MCP-Protocol-Version with the negotiated version. Verify the hosting forwards Authorization; do not work around missing headers with URL parameters.

## Tests before connector use
1. Run PHP lint. Do not continue on syntax errors.
2. No configuration => 503; configured endpoint without token or with incorrect token => 401.
3. Invalid Origin with valid token => 403. Unsupported method GET => 405.
4. Authenticated initialize => JSON-RPC result with protocolVersion and tools capability.
5. notifications/initialized without id => 202 and no response body.
6. tools/list => five read-only tools.
7. list_clients with limit 1 => no private notes, pagination metadata.
8. Read directions; select real client ID privately; verify its tasks, time and monthly balance against the UI.
9. Bad argument types, additional arguments and unknown tool => JSON-RPC errors, no SQL details.
10. Connect from Perplexity using custom remote MCP and private Bearer credential. Confirm initialize, listing and a real tool call. Exact UI credential format needs testing.

Example JSON request bodies (no credentials):

```json
{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"manual-test","version":"1"}}}
```

```json
{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"list_clients","arguments":{"limit":1}}}
```

## Security boundaries and limitations
All five tools are read-only in their application operations. Loading Bitrix can still cause normal framework/session side effects; NOT_CHECK_PERMISSIONS is intentional and scoped to this endpoint after token authorization. There is no call to USER->Authorize and no administrative browser session required. Data access is restricted to the fixed tool allowlist; possession of the token grants read access across clients. No multi-user scopes in this pilot.

Global authenticated quota: 120 JSON-RPC requests per minute; local rate file ~/podelu-mcp-rate.json. Minimal tool audit: ~/podelu-mcp-audit.log, method name, timestamp, success and duration only. No tokens, tool arguments or response bodies are written by the application audit. Invalid-argument and unauthorized requests are not included in that audit. Audit rotation is not automated. In production add hosting-level request limits and log retention. Underlying server/Bitrix logging is governed by hosting settings.

Request cap: 32 KiB; list page max 100; response cap 1 MiB after serialization. These caps are not a guarantee against all framework/database resource exhaustion. No remote execution, shell, arbitrary SQL or filesystem tools. No PHP unit/integration tests included yet; server tests above are required.

## Disable / rollback
Set enabled to false in the private config to disable access. To revoke a leaked key, replace its hash using a newly generated key. Return code to main with ~/preview-podelu.sh main while the branch remains unmerged. This removes the endpoint if absent in main but leaves external config and logs in the home directory. Deploy rollback is not a database rollback. Merge only after acceptance.
