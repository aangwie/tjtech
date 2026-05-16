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
        <div class="title">Kartu Informasi Router</div>
        
        <div class="customer-info" style="position: relative; z-index: 1;">
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
    </div>

    <div class="footer">
        Kontak ISP (Hotline / WA) : <strong>{{ $company->phone ?: '-' }}</strong>
    </div>
</div>
