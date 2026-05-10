<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToTenant;

class Invoice extends Model
{
    use BelongsToTenant;
    protected $guarded = [];

    protected $casts = [
        'price' => 'integer',
        'amount_paid' => 'decimal:2',
        'underpayment' => 'decimal:2',
        'carried_underpayment' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}