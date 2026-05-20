<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Kartu Router - {{ $customer->name }}</title>
    <style>
        @page {
            margin: 0;
            size: 12cm 8cm;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            width: 12cm;
            height: 8cm;
            box-sizing: border-box;
            border: 2px solid #352f99; /* Outline for card */
            position: relative;
            background-color: #ffffff;
            /* Optional background pattern */
            background-image: radial-gradient(#e5e7eb 1px, transparent 1px);
            background-size: 10px 10px;
        }
        
        .header {
            padding: 10px 15px;
            height: 30px;
            background-color: rgba(255, 255, 255, 0.9);
            border-bottom: 1px solid #e5e7eb;
        }
        
        .logo-container {
            float: left;
        }
        
        .logo-container img {
            max-height: 25px;
            vertical-align: middle;
        }
        
        .support-text {
            font-size: 10px;
            color: #64748b;
            margin: 0 5px;
            vertical-align: middle;
            font-style: italic;
        }

        .content {
            padding: 15px;
            text-align: center;
            background-color: rgba(255, 255, 255, 0.8);
        }

        .title {
            font-size: 14px;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            background-color: #352f99;
            color: white;
            padding: 5px;
            border-radius: 4px;
            display: inline-block;
        }

        .customer-info {
            margin-top: 15px;
            text-align: left;
            border: 1px dashed #cbd5e1;
            padding: 10px;
            border-radius: 5px;
            background-color: #f8fafc;
        }

        .info-row {
            margin-bottom: 5px;
        }

        .info-label {
            font-size: 11px;
            color: #64748b;
            display: inline-block;
            width: 80px;
        }

        .info-value {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
        }

        .footer {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: #352f99;
            color: white;
            text-align: center;
            padding: 8px 0;
            font-size: 11px;
        }

        /* Float clearing */
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
    </style>
</head>
<body>
    @php
        $logo1_base64 = '';
        if ($company->logo_path) {
            $path = public_path('uploads/' . $company->logo_path);
            if (file_exists($path)) {
                $type = pathinfo($path, PATHINFO_EXTENSION);
                $data = file_get_contents($path);
                $logo1_base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
            }
        }
    @endphp

    <div class="header clearfix">
        <div class="logo-container">
            @if($company->logo2_base64)
                <img src="{{ $company->logo2_base64 }}" alt="Logo 2">
            @endif
            
            <span class="support-text">Support by</span>
            
            @if($logo1_base64)
                <img src="{{ $logo1_base64 }}" alt="Logo 1">
            @else
                <span style="font-size: 12px; font-weight: bold; vertical-align: middle;">{{ $company->company_name }}</span>
            @endif
        </div>
    </div>

    <div class="content">
        <div style="width: 100%; box-sizing: border-box; text-align: left;">
            <div class="title" style="margin-bottom: 8px;">Kartu Informasi Router</div>
            
            <div style="clear: both;"></div>

            <div style="float: right; text-align: center; width: 100px; margin-top: 5px;">
                @php
                    $qr_url = route('frontend.check', ['token' => encrypt($customer->internet_number)]);
                    $qr_svg = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(120)->margin(0)->generate($qr_url);
                    $qr_base64 = 'data:image/svg+xml;base64,' . base64_encode($qr_svg);
                @endphp
                <img src="{{ $qr_base64 }}" alt="QR Code" style="width: 2.5cm; height: 2.5cm;">
                <div style="font-size: 8px; color: #64748b; margin-top: 3px;">Cek Tagihan</div>
            </div>

            <div class="customer-info" style="position: relative; z-index: 1; margin-right: 110px; margin-top: 0;">
                @if($company->logo2_base64)
                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; text-align: center; z-index: -1;">
                        <img src="{{ $company->logo2_base64 }}" style="height: 100%; max-height: 40px; opacity: 0.15; margin-top: 5px;">
                    </div>
                @endif
                <div style="position: relative; z-index: 2;">
                    <div class="info-row">
                        <span class="info-label">No. Internet</span>
                        <span class="info-value">: {{ $customer->internet_number }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Pelanggan</span>
                        <span class="info-value">: {{ strtoupper($customer->name) }}</span>
                    </div>
                </div>
            </div>
            <div style="clear: both;"></div>
        </div>
    </div>

    <div class="footer">
        Kontak ISP (Hotline / WA) : <strong>{{ $company->phone ?: '-' }}</strong>
    </div>
</body>
</html>
