<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proses Cetak Massal JPG</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Html2Canvas & JSZip -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <style>
        body { background-color: #f1f5f9; }
        
        .card-wrapper {
            /* Keep it off-screen but rendered */
            position: absolute;
            top: -9999px;
            left: -9999px;
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
            font-family: Arial, sans-serif;
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
<body class="flex items-center justify-center min-h-screen">

    <div class="bg-white p-8 rounded-2xl shadow-lg w-full max-w-md text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 mb-6">
            <svg class="h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
        </div>
        
        <h2 class="text-2xl font-bold text-slate-800 mb-2" id="status-title">Memproses Gambar...</h2>
        <p class="text-sm text-slate-500 mb-6" id="status-text">Mohon jangan tutup halaman ini selama proses berjalan.</p>
        
        <div class="w-full bg-slate-200 rounded-full h-3 mb-2">
            <div class="bg-blue-600 h-3 rounded-full transition-all duration-300" id="progress-bar" style="width: 0%"></div>
        </div>
        <div class="flex justify-between text-xs text-slate-500 font-medium">
            <span id="progress-count">0 / {{ count($customers) }}</span>
            <span id="progress-percent">0%</span>
        </div>

        <button onclick="window.close()" id="btn-close" class="mt-8 px-6 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 rounded transition-colors text-sm font-bold hidden w-full">
            Tutup Halaman
        </button>
    </div>

    <!-- Hidden Template -->
    <div class="card-wrapper">
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
                                <div class="text-[12px] font-bold text-slate-900" id="card-inet">: -</div>
                            </div>
                            <div class="flex">
                                <div class="text-[11px] text-slate-500 w-24">Pelanggan</div>
                                <div class="text-[12px] font-bold text-slate-900" id="card-name">: -</div>
                            </div>
                        </div>
                        
                        <div class="text-center flex flex-col items-center justify-center w-[2.8cm] shrink-0">
                            <img id="card-qr" src="" alt="QR Code" class="w-[2.5cm] h-[2.5cm]">
                            <div class="text-[8px] text-slate-500 mt-1 font-medium">Cek Tagihan</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer">
                Kontak ISP (Hotline / WA) : <strong>{{ $company->phone ?: '-' }}</strong>
            </div>
        </div>
    </div>

    <script>
        // Data pelanggan dari backend
        const customers = @json($customers_json);

        document.addEventListener("DOMContentLoaded", async function() {
            const zip = new JSZip();
            const folder = zip.folder("Kartu_Router");
            const card = document.getElementById('capture-card');
            
            const elInet = document.getElementById('card-inet');
            const elName = document.getElementById('card-name');
            const elBar = document.getElementById('progress-bar');
            const elCount = document.getElementById('progress-count');
            const elPercent = document.getElementById('progress-percent');
            
            const total = customers.length;
            
            // Tunggu sebentar agar font/asset termuat
            await new Promise(r => setTimeout(r, 1000));

            for (let i = 0; i < total; i++) {
                let cust = customers[i];
                
                // 1. Update DOM
                elInet.innerText = ': ' + cust.inet;
                elName.innerText = ': ' + cust.name;
                document.getElementById('card-qr').src = cust.qr_base64;
                
                // Sedikit jeda agar DOM ter-render
                await new Promise(r => setTimeout(r, 50));
                
                // 2. Render Canvas
                const canvas = await html2canvas(card, {
                    scale: 3,
                    useCORS: true,
                    backgroundColor: null
                });
                
                // 3. Convert ke base64 (tanpa header data:image/jpeg;base64,)
                let imgData = canvas.toDataURL('image/jpeg', 0.9).split(',')[1];
                
                // 4. Tambahkan ke ZIP
                folder.file(`Kartu_${cust.inet}_${cust.filename}.jpg`, imgData, {base64: true});
                
                // 5. Update Progress UI
                let percent = Math.round(((i + 1) / total) * 100);
                elBar.style.width = percent + '%';
                elCount.innerText = `${i + 1} / ${total}`;
                elPercent.innerText = percent + '%';
            }
            
            // 6. Generate dan Download ZIP
            document.getElementById('status-title').innerText = "Mengompresi File ZIP...";
            document.getElementById('status-text').innerText = "Sedang menyatukan gambar. Mohon tunggu...";
            
            zip.generateAsync({type:"blob"}).then(function(content) {
                saveAs(content, "Kartu_Semua_Pelanggan.zip");
                
                document.getElementById('status-title').innerText = "Selesai!";
                document.getElementById('status-text').innerText = "File ZIP berhasil diunduh.";
                document.getElementById('status-title').classList.replace('text-slate-800', 'text-green-600');
                document.getElementById('btn-close').classList.remove('hidden');
            });
        });
    </script>
</body>
</html>
