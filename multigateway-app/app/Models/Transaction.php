<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'client_id', 'gateway_id', 'external_id',
        'status', 'amount', 'card_last_numbers'
    ];

    public function client() {
        return $this->belongsTo(Client::class);
    }

    public function gateway() {
        return $this->belongsTo(Gateway::class);
    }

    public function products() {
        return $this->belongsToMany(Product::class, 'transaction_products')
                    ->withPivot('quantity');
    }
}
