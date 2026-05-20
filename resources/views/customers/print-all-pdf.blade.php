<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Cetak Semua Kartu Router</title>
    <style>
        /* Ukuran F4 Landscape: 936pt x 612pt */
        @page {
            margin: 0;
            size: 936pt 612pt;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
        }
        
        .page {
            width: 936pt;
            height: 612pt;
            padding: 20pt;
            box-sizing: border-box;
            page-break-after: always;
        }
        .page:last-child {
            page-break-after: auto;
        }

        .grid-table {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
        }
        .grid-cell {
            width: 50%;
            height: 50%;
            padding: 10pt;
            vertical-align: middle;
            text-align: center;
        }

        /* Ukuran persis 12cm x 8cm */
        .card {
            width: 340.15pt;
            height: 226.77pt;
            margin: 0 auto; /* Center in cell */
            box-sizing: border-box;
            border: 2px solid #352f99;
            position: relative;
            background-color: #ffffff;
            text-align: left;
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
            color: white;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            background-color: #352f99;
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

    @foreach($customers->chunk(4) as $chunk)
        <div class="page">
            <table class="grid-table">
                @php $items = $chunk->values(); @endphp
                <tr>
                    <td class="grid-cell">
                        @if(isset($items[0]))
                            @include('customers._card_partial', ['customer' => $items[0]])
                        @endif
                    </td>
                    <td class="grid-cell">
                        @if(isset($items[1]))
                            @include('customers._card_partial', ['customer' => $items[1]])
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="grid-cell">
                        @if(isset($items[2]))
                            @include('customers._card_partial', ['customer' => $items[2]])
                        @endif
                    </td>
                    <td class="grid-cell">
                        @if(isset($items[3]))
                            @include('customers._card_partial', ['customer' => $items[3]])
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    @endforeach
</body>
</html>
