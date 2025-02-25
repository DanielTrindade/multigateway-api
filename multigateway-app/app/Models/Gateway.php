<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gateway extends Model
{
    protected $fillable = ['name', 'is_active', 'priority', 'credentials'];
    protected $casts = [
        'is_active' => 'boolean',
        'credentials' => 'array',
    ];

    public function transactions() {
        return $this->hasMany(Transaction::class);
    }
}
