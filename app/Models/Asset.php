<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $fillable = [
        'admin_id',
        'nama_barang',
        'merk',
        'has_identifier',
        'identifier',
        'tahun_perolehan',
        'kondisi_perolehan',
        'jumlah_barang',
        'harga_satuan',
        'harga_perolehan',
        'has_penyusutan',
        'nilai_penyusutan',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
