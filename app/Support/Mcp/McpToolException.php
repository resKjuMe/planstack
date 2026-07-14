<?php

namespace App\Support\Mcp;

use RuntimeException;

/**
 * A recoverable tool-level failure. The MCP controller turns this into a tool
 * result with isError=true (so the calling model sees the message), not a
 * protocol-level JSON-RPC error.
 */
class McpToolException extends RuntimeException
{
}
