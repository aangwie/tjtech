<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetDisposal extends Model
{
    protected $fillable = [
        'asset_id',
        'admin_id',
        'nama_barang',
        'jumlah_dihapus',
        'alasan',
        'keterangan',
        'tanggal_jual',
        'harga_jual',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
