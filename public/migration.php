<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Gunakan Contracts, bukan Facades untuk inisialisasi awal
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;

echo "<h1>Laravel Migration Bridge</h1>";

$laravel_path = __DIR__ . '/../laravel_tjtech'; // Sesuaikan dengan nama folder Anda

try {
    echo "Memuat Autoload... <br>";
    require $laravel_path . '/vendor/autoload.php';

    echo "Memuat App... <br>";
    $app = require_once $laravel_path . '/bootstrap/app.php';

    echo "Bootstrapping Kernel... <br>";
    // Menggunakan Contracts untuk mendapatkan instance Kernel Console
    $kernel = $app->make(ConsoleKernel::class);
    $kernel->bootstrap();

    echo "Menjalankan Migrasi... <br><pre>";
    
    // Memanggil perintah artisan secara langsung melalui instance Kernel
    $status = Artisan::call('migrate', ['--force' => true]);
    
    echo Artisan::output();
    echo "</pre>";
    
    if ($status === 0) {
        echo "<b style='color:green'>MIGRASI BERHASIL!</b>";
    } else {
        echo "<b style='color:orange'>Selesai dengan status kode: $status</b>";
    }

} catch (\Exception $e) {
    echo "<br><b style='color:red'>Terjadi Error:</b><br>";
    echo "Pesan: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " baris " . $e->getLine();
}