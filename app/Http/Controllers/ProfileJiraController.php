<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\Jira\JiraStatusSync;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Persönlicher Jira-Zugang (Basis-URL, E-Mail, API-Token) für den
 * serverseitigen Jira-Abgleich. Beim Speichern wird der Zugang gegen Jira geprüft.
 */
class ProfileJiraController extends Controller
{
    public function update(Request $request, JiraStatusSync $sync): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validateWithBag('jira', [
            'jira_base_url' => ['required', 'url:https', 'max:255'],
            'jira_email' => ['required', 'email', 'max:255'],
            // Leer lassen = bisherigen Token behalten.
            'jira_api_token' => [$user->jira_api_token ? 'nullable' : 'required', 'string', 'max:2000'],
        ]);

        $user->jira_base_url = rtrim($data['jira_base_url'], '/');
        $user->jira_email = $data['jira_email'];
        if (filled($data['jira_api_token'] ?? null)) {
            $user->jira_api_token = $data['jira_api_token'];
        }

        try {
            $response = $sync->client($user)->get('/rest/api/3/myself');
            $reason = $response->successful() ? null : 'HTTP '.$response->status();
        } catch (ConnectionException $e) {
            $reason = $e->getMessage();
        }

        if ($reason !== null) {
            throw ValidationException::withMessages([
                'jira_api_token' => __('flash.jira_credentials_invalid', ['reason' => $reason]),
            ])->errorBag('jira');
        }

        $user->save();

        return back()->with('status', 'jira-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill(['jira_base_url' => null, 'jira_email' => null, 'jira_api_token' => null])->save();
        // Projekte, die über diesen Zugang abgeglichen wurden, verlieren den Abgleich.
        Project::query()->where('jira_sync_user_id', $user->id)->update(['jira_sync_user_id' => null]);

        return back()->with('status', 'jira-removed');
    }
}
