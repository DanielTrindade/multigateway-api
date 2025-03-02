<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes, HasFactory;
    protected $fillable = ['name', 'amount'];

    public function transactions() {
        return $this->belongsToMany(Transaction::class, 'transaction_products')
                    ->withPivot('quantity');
    }
}
