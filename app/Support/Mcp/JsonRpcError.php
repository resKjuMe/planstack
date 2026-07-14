<?php

namespace App\Support\Mcp;

use RuntimeException;

/**
 * A protocol-level JSON-RPC error (e.g. unknown method, malformed request).
 * Distinct from {@see McpToolException}, which is a tool result with isError.
 */
class JsonRpcError extends RuntimeException
{
}
