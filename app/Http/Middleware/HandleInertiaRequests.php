<?php

namespace App\Http\Middleware;

use App\Support\ShellData;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Das Root-Blade, in das Inertia beim ersten (Voll-)Laden rendert.
     * Enthält <head>, @vite und den persistenten Teil (Glocke) + @inertia.
     */
    protected $rootView = 'app-root';

    /**
     * Bei jeder Inertia-Antwort geteilte Props. `shell` versorgt das persistente
     * React-Grundgerüst (Navi/Menü/Labels) und wird pro Navigation neu berechnet.
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'shell' => fn () => ShellData::build(),
        ]);
    }
}
