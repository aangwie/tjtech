<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Aset</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #352f99;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            color: #352f99;
            font-size: 18px;
            text-transform: uppercase;
        }
        .header p {
            margin: 5px 0 0 0;
            color: #666;
        }
        .info-table {
            margin-bottom: 15px;
            width: 100%;
        }
        .info-table td {
            padding: 2px 0;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.data th, table.data td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        table.data th {
            background-color: #f4f4f5;
            color: #333;
            font-weight: bold;
            text-align: left;
        }
        table.data th.right, table.data td.right {
            text-align: right;
        }
        table.data th.center, table.data td.center {
            text-align: center;
        }
        .footer {
            margin-top: 30px;
            font-size: 10px;
            color: #777;
            text-align: right;
        }
        .text-bold {
            font-weight: bold;
        }
        .bg-gray {
            background-color: #f9fafb;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>LAPORAN ASET PERUSAHAAN</h1>
        <p>Tahun Cetak: {{ date('Y') }}</p>
    </div>

    <table class="info-table">
        <tr>
            <td width="120"><b>Tanggal Cetak</b></td>
            <td width="10">:</td>
            <td>{{ date('d-m-Y H:i') }}</td>
        </tr>
        <tr>
            <td><b>Filter Tahun</b></td>
            <td>:</td>
            <td>{{ $tahunFilter ? $tahunFilter : 'Semua Tahun' }}</td>
        </tr>
        <tr>
            <td><b>Status Penyusutan</b></td>
            <td>:</td>
            <td>{{ $hitungPenyusutan ? 'Ditampilkan' : 'Tidak Ditampilkan' }}</td>
        </tr>
        @if(auth()->user()->isSuperAdmin())
        <tr>
            <td><b>Dicetak Oleh</b></td>
            <td>:</td>
            <td>{{ auth()->user()->name }} (Superadmin)</td>
        </tr>
        @else
        <tr>
            <td><b>Pemilik Aset</b></td>
            <td>:</td>
            <td>{{ auth()->user()->name }}</td>
        </tr>
        @endif
    </table>

    <table class="data">
        <thead>
            <tr>
                <th class="center" width="30">No</th>
                <th>Nama Barang / Merk</th>
                <th>Identifier (SN/Kode)</th>
                @if(auth()->user()->isSuperAdmin())
                <th>Pemilik (Admin)</th>
                @endif
                <th class="center">Tahun</th>
                <th class="center">Kondisi</th>
                <th class="right">Harga Perolehan</th>
                @if($hitungPenyusutan)
                    <th class="right">Penyusutan / Thn</th>
                    <th class="center">Umur</th>
                    <th class="right">Akumulasi Penyusutan</th>
                    <th class="right">Nilai Buku (Saat Ini)</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @php 
                $totalHarga = 0; 
                $totalAkumulasi = 0;
                $totalNilaiBuku = 0;
            @endphp
            @forelse($assets as $index => $asset)
                @php
                    $totalHarga += $asset->harga_perolehan;
                    $umur = 0;
                    $akumulasiPenyusutan = 0;
                    $nilaiBuku = $asset->harga_perolehan;
                    
                    if ($hitungPenyusutan && $asset->has_penyusutan && $asset->nilai_penyusutan > 0) {
                        $umur = $currentYear - $asset->tahun_perolehan;
                        if ($umur < 0) $umur = 0;
                        
                        $akumulasiPenyusutan = $umur * $asset->nilai_penyusutan;
                        
                        // Nilai buku tidak boleh minus
                        $nilaiBuku = $asset->harga_perolehan - $akumulasiPenyusutan;
                        if ($nilaiBuku < 0) $nilaiBuku = 0;
                        
                        // Batasi akumulasi tidak melebihi harga perolehan jika nilai buku diset ke 0
                        if ($akumulasiPenyusutan > $asset->harga_perolehan) {
                            $akumulasiPenyusutan = $asset->harga_perolehan;
                        }
                    }

                    $totalAkumulasi += $akumulasiPenyusutan;
                    $totalNilaiBuku += $nilaiBuku;
                @endphp
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>
                        <span class="text-bold">{{ $asset->nama_barang }}</span><br>
                        <span style="font-size: 10px; color: #555;">{{ $asset->merk }}</span>
                    </td>
                    <td>{{ $asset->has_identifier ? $asset->identifier : '-' }}</td>
                    @if(auth()->user()->isSuperAdmin())
                    <td>{{ $asset->admin->name ?? '-' }}</td>
                    @endif
                    <td class="center">{{ $asset->tahun_perolehan }}</td>
                    <td class="center">{{ $asset->kondisi_perolehan }}</td>
                    <td class="right">Rp {{ number_format($asset->harga_perolehan, 0, ',', '.') }}</td>
                    
                    @if($hitungPenyusutan)
                        <td class="right">
                            @if($asset->has_penyusutan)
                                Rp {{ number_format($asset->nilai_penyusutan, 0, ',', '.') }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="center">{{ $umur }} thn</td>
                        <td class="right text-bold" style="color:#d97706;">Rp {{ number_format($akumulasiPenyusutan, 0, ',', '.') }}</td>
                        <td class="right text-bold" style="color:#15803d;">Rp {{ number_format($nilaiBuku, 0, ',', '.') }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $hitungPenyusutan ? (auth()->user()->isSuperAdmin() ? 11 : 10) : (auth()->user()->isSuperAdmin() ? 7 : 6) }}" class="center">
                        Tidak ada data aset yang ditemukan.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if(count($assets) > 0)
        <tfoot>
            <tr class="bg-gray">
                <th colspan="{{ auth()->user()->isSuperAdmin() ? 6 : 5 }}" class="right">TOTAL KESELURUHAN</th>
                <th class="right">Rp {{ number_format($totalHarga, 0, ',', '.') }}</th>
                @if($hitungPenyusutan)
                    <th colspan="2"></th>
                    <th class="right" style="color:#d97706;">Rp {{ number_format($totalAkumulasi, 0, ',', '.') }}</th>
                    <th class="right" style="color:#15803d;">Rp {{ number_format($totalNilaiBuku, 0, ',', '.') }}</th>
                @endif
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="footer">
        Dicetak otomatis oleh Sistem Manajemen - {{ env('APP_NAME', 'TJ-TECH') }}
    </div>

</body>
</html>
