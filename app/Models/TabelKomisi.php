<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TabelKomisi extends Model
{
    protected $table = 'tabel_komisi';

    protected $fillable = [
        'operator_id',
        'month',
        'year',
        'komisi_percent',
        'komisi_value',
    ];

    public $timestamps = true;
}

