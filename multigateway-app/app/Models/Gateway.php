<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gateway extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'is_active', 'priority', 'credentials'];
    protected $casts = [
        'is_active' => 'boolean',
        'credentials' => 'array',
    ];

    public function transactions() {
        return $this->hasMany(Transaction::class);
    }
}
