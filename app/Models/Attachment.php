<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Arquivo enviado (comprovante etc.). Fica no disco privado e é entregue
 * pela rota attachments.show, que confere a permissão sobre o registro dono.
 *
 * @property int $id
 * @property string $attachable_type
 * @property int $attachable_id
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int $size
 * @property int|null $uploaded_by
 */
class Attachment extends Model
{
    /** @var list<string> */
    protected $fillable = ['path', 'original_name', 'mime', 'size', 'uploaded_by'];

    /** @return MorphTo<Model, $this> */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
