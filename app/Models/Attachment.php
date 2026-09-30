<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * File attached to any record (e.g. shipping documents on a sales invoice). Stored on a private disk and
 * only served through the authorised download route.
 */
class Attachment extends Model
{
    protected $fillable = ['attachable_type', 'attachable_id', 'category', 'disk', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by'];

    protected $casts = ['size' => 'integer'];

    protected static function booted(): void
    {
        static::creating(function (Attachment $a) {
            $a->uploaded_by ??= auth()->id();
        });
        static::deleted(function (Attachment $a) {
            Storage::disk($a->disk)->delete($a->path);
        });
    }

    public function attachable()
    {
        return $this->morphTo()->withTrashed();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    public function getSizeLabelAttribute(): string
    {
        return $this->size >= 1048576 ? round($this->size / 1048576, 1).' MB' : max(1, round($this->size / 1024)).' KB';
    }

    public function getIconAttribute(): string
    {
        return match (strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION))) {
            'pdf' => 'far fa-file-pdf text-danger',
            'doc', 'docx' => 'far fa-file-word text-primary',
            'csv' => 'fas fa-file-csv text-success',
            'zip' => 'far fa-file-archive text-warning',
            'jpg', 'jpeg', 'png' => 'far fa-file-image text-info',
            default => 'far fa-file',
        };
    }
}
