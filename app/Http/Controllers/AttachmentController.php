<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Download / delete attached documents. Access follows the permissions of the record they belong to.
 */
class AttachmentController extends Controller
{
    /** Permission needed per owner type: [view, change]. */
    protected const PERMISSIONS = [
        Sale::class => ['sales.view', 'sales.edit'],
        \App\Models\Purchase::class => ['purchases.view', 'purchases.edit'],
    ];

    public function download(Request $request, Attachment $attachment)
    {
        $this->authorizeFor($request, $attachment, 0);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        $inline = $request->boolean('inline') && in_array($attachment->mime_type, ['application/pdf', 'image/png', 'image/jpeg'], true);

        return $inline
            ? Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name)
            : Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Request $request, Attachment $attachment)
    {
        $this->authorizeFor($request, $attachment, 1);
        $name = $attachment->original_name;
        $attachment->delete();

        return $this->success("Removed {$name}");
    }

    protected function authorizeFor(Request $request, Attachment $attachment, int $level): void
    {
        $owner = $attachment->attachable;
        $permission = $owner ? (self::PERMISSIONS[get_class($owner)][$level] ?? null) : null;
        abort_unless($permission && $request->user()->can($permission), 403);
    }
}
