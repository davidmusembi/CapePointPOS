<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Stores uploaded documents on the private "local" disk (never publicly reachable) and links them to a record.
 */
class AttachmentService
{
    /** Validation rules for an upload field (driven by config/pos.php). */
    public static function rules(): array
    {
        $cfg = config('pos.attachments');

        return ['file', 'mimes:'.implode(',', $cfg['mimes']), 'max:'.(int) $cfg['max_kb']];
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function storeMany(Model $owner, array $files, string $category = 'general'): void
    {
        foreach ($files as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $this->store($owner, $file, $category);
            }
        }
    }

    public function store(Model $owner, UploadedFile $file, string $category = 'general'): Attachment
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $path = $file->storeAs('attachments/'.now()->format('Y/m'), Str::uuid().'.'.$ext, 'local');

        return Attachment::create([
            'attachable_type' => $owner->getMorphClass(),
            'attachable_id' => $owner->getKey(),
            'category' => $category,
            'disk' => 'local',
            'path' => $path,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }
}
