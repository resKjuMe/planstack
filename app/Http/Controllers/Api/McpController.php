<?php

namespace App\Http\Controllers\Api;

use App\Models\Project;
use App\Models\User;
use App\Support\Mcp\JsonRpcError;
use App\Support\Mcp\McpServer;
use App\Support\Mcp\McpToolException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * Remote MCP server (Streamable-HTTP transport, stateless) for one project.
 *
 * Speaks JSON-RPC 2.0 over a single POST endpoint: initialize, ping,
 * tools/list and tools/call. Authentication is the same Sanctum bearer token
 * as the rest of the API; the caller must have project access. Tool execution
 * and the tool catalogue live in {@see McpServer}.
 */
class McpController extends ApiController
{
    public function __construct(private readonly McpServer $server) {}

    public function __invoke(Request $request, Project $project): JsonResponse|Response
    {
        // We don't offer a server-initiated SSE stream (stateless server).
        if ($request->isMethod('GET')) {
            return response('Method Not Allowed', 405);
        }

        // Gate the whole server to project members; individual write abilities
        // are additionally checked per tool.
        abort_unless($request->user()?->can('view', $project) ?? false, 403);

        $payload = $request->json()->all();

        $isBatch = is_array($payload) && array_is_list($payload) && $payload !== [];
        $messages = $isBatch ? $payload : [$payload];

        $responses = [];
        foreach ($messages as $message) {
            $response = $this->handle($request->user(), $project, is_array($message) ? $message : []);
            if ($response !== null) {
                $responses[] = $response;
            }
        }

        // Only notifications/responses in the batch → nothing to return.
        if ($responses === []) {
            return response()->noContent(202);
        }

        return response()->json($isBatch ? $responses : $responses[0]);
    }

    /**
     * Handle one JSON-RPC message. Returns the response object, or null for a
     * notification (a message without an id).
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>|null
     */
    private function handle(User $user, Project $project, array $message): ?array
    {
        $method = $message['method'] ?? null;
        $hasId = array_key_exists('id', $message);
        $id = $message['id'] ?? null;

        // Notifications (no id) get no response — e.g. notifications/initialized.
        if (! $hasId) {
            return null;
        }

        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        try {
            $result = match ($method) {
                'initialize' => [
                    'protocolVersion' => $this->server->negotiateProtocol($params['protocolVersion'] ?? null),
                    'capabilities' => ['tools' => (object) []],
                    'serverInfo' => $this->server->serverInfo(),
                ],
                'ping' => (object) [],
                'tools/list' => ['tools' => $this->server->tools()],
                'tools/call' => $this->callTool($user, $project, $params),
                default => throw new JsonRpcError("Unbekannte Methode: \"{$method}\".", -32601),
            };
        } catch (JsonRpcError $e) {
            return $this->errorResponse($id, (int) $e->getCode(), $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse($id, -32603, 'Interner Fehler.');
        }

        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /**
     * Run a tool. Recoverable tool failures come back as a result with
     * isError=true so the calling model can read and react to them.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function callTool(User $user, Project $project, array $params): array
    {
        $name = is_string($params['name'] ?? null) ? $params['name'] : '';
        $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        try {
            $text = $this->server->callTool($project, $user, $name, $args);
        } catch (McpToolException $e) {
            return [
                'content' => [['type' => 'text', 'text' => 'Fehler: '.$e->getMessage()]],
                'isError' => true,
            ];
        }

        return ['content' => [['type' => 'text', 'text' => $text]]];
    }

    /**
     * @return array<string, mixed>
     */
    private function errorResponse(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => ['code' => $code, 'message' => $message],
        ];
    }
}
