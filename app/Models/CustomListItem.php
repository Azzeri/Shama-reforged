<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'is_checked'])]
class CustomListItem extends Model
{
    protected function casts(): array
    {
        return ['is_checked' => 'boolean'];
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(CustomList::class, 'custom_list_id');
    }
}
