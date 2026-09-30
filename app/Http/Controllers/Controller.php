<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\Middleware;

abstract class Controller
{
    /**
     * Standard permission middleware for a resource controller.
     * e.g. crudPermissions('sales', ['view' => ['print', 'due']])
     *
     * @return array<int, Middleware>
     */
    protected static function crudPermissions(string $module, array $extra = []): array
    {
        $map = [
            'view' => ['index', 'show'],
            'create' => ['create', 'store'],
            'edit' => ['edit', 'update'],
            'delete' => ['destroy'],
        ];
        foreach ($extra as $ability => $methods) {
            $map[$ability] = array_merge($map[$ability] ?? [], (array) $methods);
        }

        return collect($map)
            ->map(fn ($methods, $ability) => new Middleware("permission:{$module}.{$ability}", only: $methods))
            ->values()->all();
    }

    /**
     * Success response that works for both AJAX (JSON) and classic form posts (redirect + flash).
     */
    protected function success(string $message, ?string $redirect = null, array $extra = []): JsonResponse|RedirectResponse
    {
        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'redirect' => $redirect] + $extra);
        }

        return ($redirect ? redirect($redirect) : back())->with('success', $message);
    }

    protected function failure(string $message, int $status = 422): JsonResponse|RedirectResponse
    {
        if (request()->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], $status);
        }

        return back()->withInput()->with('error', $message);
    }

    /** Standard "Actions" dropdown for DataTables rows. $items: [ ['label','icon','url'|'modal'|'delete', 'can'] ] */
    protected function actions(array $items): string
    {
        return view('partials.actions', ['items' => $items])->render();
    }
}
