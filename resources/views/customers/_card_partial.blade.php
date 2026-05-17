<div class="card">
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
</div>
