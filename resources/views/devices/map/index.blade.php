@extends('layouts.app2')

@section('title', 'Map Perangkat')
@section('header', 'Map Jaringan Perangkat')
@section('subheader', 'Visualisasi topologi jaringan perangkat Anda')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #networkMap {
            height: calc(100vh - 200px);
            min-height: 500px;
            width: 100%;
            border-radius: 0.75rem;
            z-index: 10;
        }

        .device-icon {
            background: #352f99;
            border: 2px solid white;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .device-icon-online {
            background: #10b981;
            /* Tailwind emerald-500 */
            border: 2px solid white;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .device-icon-offline {
            background: #ef4444;
            /* Tailwind red-500 */
            border: 2px solid white;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        /* Styling swal2 toast untuk map popup */
        .swal2-container.swal2-toast-map {
            width: 320px !important;
        }

        .swal2-popup.swal2-toast {
            padding: 0.75rem !important;
            background: #023b35ff !important;
            /* Hijau Tosca */
            color: white !important;
        }

        .dark .swal2-popup.swal2-toast {
            background: #812b56ff !important;
            /* Pink */
            color: white !important;
        }
    </style>
@endpush

@section('content')
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <div id="networkMap" class="border border-slate-200 dark:border-slate-700"></div>
    </div>
@endsection

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        var map;
        var devices = @json($devices);
        var relations = @json($relations);

        // Store coordinates for easy access when drawing lines
        var coordinatesMap = {};

        document.addEventListener('DOMContentLoaded', function () {
            initNetworkMap();
        });

        function initNetworkMap() {
            // Set default center to Indonesia if no devices
            var centerLat = -2.5489;
            var centerLng = 118.0149;
            var zoom = 5;

            if (devices.length > 0) {
                centerLat = parseFloat(devices[0].latitude);
                centerLng = parseFloat(devices[0].longitude);
                zoom = 13;
            }

            map = L.map('networkMap').setView([centerLat, centerLng], zoom);

            // Define map tile (OpenStreetMap)
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            // Add markers for all devices
            devices.forEach(function (device) {
                if (device.latitude && device.longitude) {
                    var lat = parseFloat(device.latitude);
                    var lng = parseFloat(device.longitude);

                    coordinatesMap[device.id] = [lat, lng];

                    // Determine marker class based on connection status
                    var iconClass = 'device-icon';
                    if (device.is_online === true) {
                        iconClass = 'device-icon-online';
                    } else if (device.is_online === false) {
                        iconClass = 'device-icon-offline';
                    }

                    var customIcon = L.divIcon({
                        className: iconClass,
                        html: '<i class="fas fa-microchip text-xs"></i>',
                        iconSize: [24, 24],
                        iconAnchor: [12, 12]
                    });

                    var marker = L.marker([lat, lng], { icon: customIcon }).addTo(map);

                    // Construct HTML for SweetAlert Toast
                    var imgSrc = device.foto ? `/uploads/${device.foto}` : '';
                    var imgHtml = imgSrc ? `<img src="${imgSrc}" style="width: 32px; height: 32px; object-fit: cover; border-radius: 4px; border: 1px solid #e2e8f0;">` : `<div style="width: 32px; height: 32px; border-radius: 4px; background: #e2e8f0; display:flex; align-items:center; justify-content:center;"><i class="fas fa-image text-slate-400"></i></div>`;

                    var rasio = device.rasio ? device.rasio : '-';
                    var redaman = device.redaman ? device.redaman + ' dB' : '-';
                    var ket = device.keterangan ? device.keterangan : '-';

                    var ipHtml = '';
                    if (device.kategori === 'Router' || device.kategori === 'HTB') {
                        var ipVal = device.ip_address ? device.ip_address : '-';
                        ipHtml = `<div><strong>IP Address:</strong> ${ipVal}</div>`;
                    }

                    var htmlContent = `
                            <div style="display:flex; align-items:flex-start; gap: 10px; text-align: left;">
                                ${imgHtml}
                                <div style="flex:1;">
                                    <h4 style="margin:0; font-size:14px; font-weight:bold;">${device.nama}</h4>
                                    <div style="font-size:12px; margin-top:4px;">
                                        <div><strong>Kategori:</strong> ${device.kategori || 'ODP'}</div>
                                        ${ipHtml}
                                        <div><strong>Rasio:</strong> ${rasio}</div>
                                        <div><strong>Redaman:</strong> ${redaman}</div>
                                        <div><strong>Ket:</strong> ${ket}</div>
                                        <div style="margin-top:2px; font-size: 10px;"><i class="fas fa-map-marker-alt"></i> ${lat}, ${lng}</div>
                                    </div>
                                </div>
                            </div>
                        `;

                    // Attach mouseover event to show SweetAlert Toast
                    marker.on('mouseover', function (e) {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true,
                            html: htmlContent,
                            customClass: {
                                container: 'swal2-toast-map'
                            }
                        });
                    });

                    // Attach click event to open Google Maps
                    marker.on('click', function (e) {
                        window.open(`https://www.google.com/maps?q=${lat},${lng}`, '_blank');
                    });
                }
            });

            // Draw relation lines (Polylines)
            relations.forEach(function (rel) {
                if (coordinatesMap[rel.source_id] && coordinatesMap[rel.target_id]) {
                    var latlngs = [
                        coordinatesMap[rel.source_id],
                        coordinatesMap[rel.target_id]
                    ];

                    L.polyline(latlngs, {
                        color: '#ef4444', // Red color for lines
                        weight: 3,
                        opacity: 0.7,
                        dashArray: '5, 5' // Dashed line effect
                    }).addTo(map);
                }
            });

            // Fit bounds if we have multiple devices to show them all
            if (devices.length > 1) {
                var bounds = [];
                devices.forEach(function (d) {
                    if (d.latitude && d.longitude) {
                        bounds.push([parseFloat(d.latitude), parseFloat(d.longitude)]);
                    }
                });
                if (bounds.length > 0) {
                    map.fitBounds(bounds, { padding: [50, 50] });
                }
            }
        }
    </script>
@endpush