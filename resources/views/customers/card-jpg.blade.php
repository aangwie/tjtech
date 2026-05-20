<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartu Router - {{ $customer->name }}</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Html2Canvas -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        /* Exact physical dimensions logic (1cm = 37.8px approx at 96dpi) */
        /* To make it high quality, we scale it up */
        .card-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f1f5f9;
            flex-direction: column;
            gap: 20px;
        }

        .card {
            width: 12cm;
            height: 8cm;
            background-color: white;
            position: relative;
            box-sizing: border-box;
            border: 2px solid #352f99;
            background-image: radial-gradient(#e5e7eb 1px, transparent 1px);
            background-size: 10px 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }

        .card-header {
            padding: 10px 15px;
            background-color: rgba(255, 255, 255, 0.9);
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 10px;
            height: 45px;
        }

        .card-header img {
            max-height: 25px;
            width: auto;
        }

        .card-body {
            padding: 20px 15px;
            text-align: center;
            background-color: rgba(255, 255, 255, 0.8);
            height: calc(100% - 45px - 35px); /* Header and Footer height subtracted */
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .card-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            background-color: #352f99;
            color: white;
            text-align: center;
            padding: 8px 0;
            font-size: 11px;
            font-weight: 500;
        }
    </style>
</head>
<body>

    <div class="card-wrapper" id="ui-wrapper">
        <div class="text-center">
            <h2 class="text-xl font-bold text-slate-800">Menyiapkan Kartu...</h2>
            <p class="text-slate-500">Gambar akan otomatis terunduh dalam beberapa detik.</p>
        </div>

        <!-- The Card to be captured -->
        <div class="card" id="capture-card">
            <div class="card-header">
                @if($company->logo2_base64)
                    <img src="{{ $company->logo2_base64 }}" alt="Logo 2">
                @endif
                <span class="text-[10px] text-slate-500 italic mx-1">Support by</span>
                @if($company->logo_path)
                    <img src="{{ asset('uploads/' . $company->logo_path) }}" alt="Logo 1">
                @else
                    <span class="font-bold text-xs">{{ $company->company_name }}</span>
                @endif
            </div>

            <div class="card-body flex flex-col justify-center">
                <div class="w-full text-left">
                    <div class="inline-block bg-[#352f99] text-white px-3 py-1.5 rounded text-sm font-bold tracking-wider mb-3 w-max">
                        KARTU INFORMASI ROUTER
                    </div>
                    
                    <div class="flex items-center justify-between gap-3 w-full">
                        <div class="bg-slate-50 border border-slate-200 border-dashed rounded-lg p-3 text-left flex-1 relative z-10 overflow-hidden">
                            @if($company->logo2_base64)
                                <div class="absolute inset-0 flex items-center justify-center -z-10">
                                    <img src="{{ $company->logo2_base64 }}" class="h-full max-h-10 opacity-[0.15] mt-1">
                                </div>
                            @endif
                            <div class="flex mb-2">
                                <div class="text-[11px] text-slate-500 w-24">No. Internet</div>
                                <div class="text-[12px] font-bold text-slate-900">: {{ $customer->internet_number }}</div>
                            </div>
                            <div class="flex">
                                <div class="text-[11px] text-slate-500 w-24">Pelanggan</div>
                                <div class="text-[12px] font-bold text-slate-900">: {{ strtoupper($customer->name) }}</div>
                            </div>
                        </div>
                        
                        <div class="text-center flex flex-col items-center justify-center w-[2.8cm] shrink-0">
                            @php
                                $qr_url = route('frontend.check', ['token' => encrypt($customer->internet_number)]);
                                $qr_svg = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(120)->margin(0)->generate($qr_url);
                                $qr_base64 = 'data:image/svg+xml;base64,' . base64_encode($qr_svg);
                            @endphp
                            <img src="{{ $qr_base64 }}" alt="QR Code" class="w-[2.5cm] h-[2.5cm]">
                            <div class="text-[8px] text-slate-500 mt-1 font-medium">Cek Tagihan</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer">
                Kontak ISP (Hotline / WA) : <strong>{{ $company->phone ?: '-' }}</strong>
            </div>
        </div>
        
        <button onclick="window.history.back()" class="mt-4 px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 rounded transition-colors text-sm font-medium">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali
        </button>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Beri sedikit jeda agar font/gambar termuat
            setTimeout(() => {
                const card = document.getElementById('capture-card');
                
                html2canvas(card, {
                    scale: 3, // Skala tinggi untuk kualitas cetak (resolusi tinggi)
                    useCORS: true,
                    backgroundColor: null
                }).then(canvas => {
                    // Konversi ke JPG
                    let imgData = canvas.toDataURL('image/jpeg', 1.0);
                    
                    // Buat link download
                    let link = document.createElement('a');
                    link.download = 'Kartu-Router-{{ $customer->internet_number }}.jpg';
                    link.href = imgData;
                    link.click();
                    
                    // Update UI info
                    document.querySelector('.text-center h2').innerText = "Selesai!";
                    document.querySelector('.text-center p').innerText = "Gambar telah terunduh. Anda bisa menutup halaman ini.";
                });
            }, 1000);
        });
    </script>
</body>
</html>
