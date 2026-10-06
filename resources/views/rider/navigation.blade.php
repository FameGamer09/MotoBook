<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0F172A">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <title>Navigation &middot; Order {{ $order->order_number }} &middot; Motobook</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin="" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />

    <style>
        :root {
            color-scheme: dark;
            --surface: #0F172A;
            --card: #1E293B;
            --card-hover: #273548;
            --border: #334155;
            --text-primary: #F8FAFC;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
            --accent: #0891B2;
            --accent-hover: #06B6D4;
            --accent-soft: rgba(8, 145, 178, 0.15);
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --customer: #8B5CF6;
            --merchant: #F97316;
        }

        * { -webkit-tap-highlight-color: transparent; }

        html, body {
            margin: 0;
            padding: 0;
            height: 100dvh;
            width: 100%;
            overflow: hidden;
            background: var(--surface);
            color: var(--text-primary);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            font-size: 14px;
            -webkit-font-smoothing: antialiased;
        }

        #app {
            display: flex;
            flex-direction: column;
            height: 100dvh;
            max-width: 430px;
            margin: 0 auto;
            position: relative;
        }

        .status-bar {
            padding: 14px 16px;
            background: var(--card);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: calc(14px + env(safe-area-inset-top));
        }

        .status-left { display: flex; align-items: center; gap: 10px; min-width: 0; }

        .order-ref {
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 0.02em;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            background: var(--accent-soft);
            color: var(--accent-hover);
            flex-shrink: 0;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.3); }
        }

        #map {
            flex: 1;
            width: 100%;
            background: #1a2744;
            position: relative;
        }

        .map-overlay {
            position: absolute;
            top: 12px;
            left: 12px;
            right: 12px;
            z-index: 450;
            display: flex;
            gap: 8px;
            pointer-events: none;
        }

        .gps-badge {
            pointer-events: auto;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 9999px;
            background: rgba(15, 23, 42, 0.92);
            border: 1px solid var(--border);
            backdrop-filter: blur(8px);
            font-size: 11px;
            font-weight: 600;
            color: var(--success);
        }

        .gps-badge.gps-error {
            color: var(--danger);
            border-color: rgba(239, 68, 68, 0.4);
        }

        .accuracy-badge {
            pointer-events: auto;
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 9999px;
            background: rgba(15, 23, 42, 0.92);
            border: 1px solid var(--border);
            backdrop-filter: blur(8px);
            font-size: 11px;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .bottom-panel {
            background: var(--card);
            border-top: 1px solid var(--border);
            max-height: 45vh;
            display: flex;
            flex-direction: column;
            padding-bottom: env(safe-area-inset-bottom);
        }

        .destination-card {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .dest-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .dest-icon.merchant { background: rgba(249, 115, 22, 0.15); color: var(--merchant); }
        .dest-icon.customer { background: rgba(139, 92, 246, 0.15); color: var(--customer); }

        .dest-info { flex: 1; min-width: 0; }
        .dest-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin-bottom: 3px;
        }
        .dest-address {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-primary);
            line-height: 1.4;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .distance-pill {
            padding: 6px 10px;
            border-radius: 8px;
            background: var(--accent-soft);
            color: var(--accent-hover);
            font-size: 12px;
            font-weight: 700;
            flex-shrink: 0;
            align-self: center;
            white-space: nowrap;
        }

        .instructions-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            background: var(--card-hover);
            cursor: pointer;
            user-select: none;
            transition: background-color 150ms;
        }
        .instructions-toggle:hover { background: #303f54; }
        .instructions-toggle:active { background: #3a4d66; }

        .instructions-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chev { transition: transform 200ms ease; }
        .chev.open { transform: rotate(180deg); }

        .instructions-list {
            overflow-y: auto;
            max-height: 0;
            transition: max-height 300ms ease;
            -webkit-overflow-scrolling: touch;
        }
        .instructions-list.open { max-height: 280px; }

        .instr-item {
            display: flex;
            gap: 12px;
            padding: 10px 16px;
            border-bottom: 1px solid rgba(51, 65, 85, 0.5);
            align-items: flex-start;
            transition: background-color 150ms;
        }
        .instr-item:last-child { border-bottom: none; }
        .instr-item.current {
            background: var(--accent-soft);
        }

        .instr-icon {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: var(--surface);
            display: grid;
            place-items: center;
            flex-shrink: 0;
            color: var(--accent-hover);
            font-size: 13px;
            font-weight: 700;
        }
        .instr-item.current .instr-icon {
            background: var(--accent);
            color: white;
        }

        .instr-text { flex: 1; min-width: 0; }
        .instr-road {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-primary);
            line-height: 1.35;
            margin-bottom: 3px;
        }
        .instr-dist {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .telemetry {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            padding: 12px 16px;
            border-top: 1px solid var(--border);
            background: var(--surface);
        }

        .tel-item {
            padding: 8px 10px;
            border-radius: 8px;
            background: var(--card);
            border: 1px solid var(--border);
            text-align: center;
        }
        .tel-label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-muted);
            margin-bottom: 3px;
        }
        .tel-value {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-primary);
            font-variant-numeric: tabular-nums;
        }
        .tel-value.accent { color: var(--accent-hover); }

        .leaflet-container {
            background: #1a2744;
            font-family: 'Inter', sans-serif;
        }
        .leaflet-control-attribution {
            background: rgba(15, 23, 42, 0.85) !important;
            color: var(--text-muted) !important;
            font-size: 10px !important;
        }
        .leaflet-control-attribution a {
            color: var(--text-secondary) !important;
        }
        .leaflet-control-zoom { border: none !important; }
        .leaflet-control-zoom a {
            background: var(--card) !important;
            border: 1px solid var(--border) !important;
            color: var(--text-primary) !important;
            border-radius: 8px !important;
            margin-bottom: 4px !important;
            width: 34px !important;
            height: 34px !important;
            line-height: 32px !important;
        }
        .leaflet-control-zoom-in { border-bottom-right-radius: 8px !important; border-bottom-left-radius: 8px !important; }
        .leaflet-control-zoom-out { border-top-right-radius: 8px !important; border-top-left-radius: 8px !important; }

        .rider-pulse {
            position: absolute;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(8, 145, 178, 0.35);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation: riderPulse 1.8s ease-out infinite;
            pointer-events: none;
            z-index: -1;
        }
        @keyframes riderPulse {
            0%   { transform: translate(-50%, -50%) scale(0.8); opacity: 0.9; }
            100% { transform: translate(-50%, -50%) scale(2.2); opacity: 0; }
        }

        .custom-marker {
            background: transparent;
            border: none;
        }
        .marker-icon {
            position: relative;
            display: grid;
            place-items: center;
        }
        .marker-pin {
            filter: drop-shadow(0 2px 6px rgba(0,0,0,0.45));
        }
        .marker-label {
            position: absolute;
            bottom: -18px;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            color: white;
            letter-spacing: 0.02em;
        }

        .leaflet-routing-container {
            display: none !important;
        }

        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.9);
            display: grid;
            place-items: center;
            z-index: 9999;
            backdrop-filter: blur(4px);
        }
        .loading-overlay.hidden { display: none; }
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-text {
            margin-top: 14px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
        }
    </style>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""></script>
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
    <script src="https://unpkg.com/@turf/turf@6.5.0/turf.min.js"></script>
</head>
<body>
<div id="app">
    <div class="loading-overlay" id="loadingOverlay">
        <div style="text-align:center;">
            <div class="spinner" role="status" aria-label="Loading"></div>
            <div class="loading-text" id="loadingText">Acquiring GPS signal...</div>
        </div>
    </div>

    <div class="status-bar">
        <div class="status-left">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
                 style="color: var(--accent-hover); flex-shrink: 0;" aria-hidden="true">
                <path d="M14 16H9m10 0h3v-3.15a1 1 0 0 0-.84-.99L16 11l-2.7-3.6a1 1 0 0 0-.8-.4H5.24a2 2 0 0 0-1.8 1.1l-.8 1.63A6 6 0 0 0 2 12.42V16h2"/>
                <circle cx="6.5" cy="16.5" r="2.5"/>
                <circle cx="16.5" cy="16.5" r="2.5"/>
            </svg>
            <div style="min-width: 0;">
                <div class="order-ref">#{{ $order->order_number }}</div>
            </div>
        </div>
        <div class="status-pill">
            <span class="status-dot"></span>
            <span>{{ $order->status }}</span>
        </div>
    </div>

    <div id="map">
        <div class="map-overlay">
            <div class="gps-badge" id="gpsBadge">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/>
                    <path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/>
                    <path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>
                    <circle cx="12" cy="12" r="4"/>
                </svg>
                <span id="gpsStatusText">GPS ACTIVE</span>
            </div>
            <div class="accuracy-badge" id="accuracyBadge" style="display: none;">
                <span id="accuracyText">&plusmn; 0m</span>
            </div>
        </div>
    </div>

    <div class="bottom-panel">
        <div class="destination-card" id="destinationCard">
            <div class="dest-icon customer">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 10c0 7-8 13-8 13s-8-6-8-13a8 8 0 0 1 16 0Z"/>
                    <circle cx="12" cy="10" r="3"/>
                </svg>
            </div>
            <div class="dest-info">
                <div class="dest-label">Drop-off &middot; Customer</div>
                <div class="dest-address">{{ $order->dropoff_address }}</div>
            </div>
            <div class="distance-pill" id="distancePill">-- m</div>
        </div>

        <div class="instructions-toggle" id="instructionsToggle" role="button" tabindex="0" aria-expanded="false">
            <span class="instructions-label">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 18h6"/><path d="M10 22h4"/>
                    <path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"/>
                </svg>
                Turn-by-Turn Instructions
            </span>
            <svg class="chev" id="chevIcon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 style="color: var(--text-muted);" aria-hidden="true">
                <path d="m6 9 6 6 6-6"/>
            </svg>
        </div>

        <div class="instructions-list" id="instructionsList" role="list" aria-label="Route instructions">
            <div style="padding: 20px 16px; text-align:center; color: var(--text-muted); font-size: 12px;">
                Waiting for route to load...
            </div>
        </div>

        <div class="telemetry">
            <div class="tel-item">
                <div class="tel-label">ETA</div>
                <div class="tel-value accent" id="etaValue">-- min</div>
            </div>
            <div class="tel-item">
                <div class="tel-label">Speed</div>
                <div class="tel-value" id="speedValue">0 km/h</div>
            </div>
            <div class="tel-item">
                <div class="tel-label">Heading</div>
                <div class="tel-value" id="headingValue">--&deg;</div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const ORDER_ID = @json($order->id);
    const API_BASE_URL = @json(url('/'));
    const TRACKING_URL = `${API_BASE_URL}/orders/${ORDER_ID}/tracking`;
    const LOCATION_URL = `${API_BASE_URL}/orders/${ORDER_ID}/location`;
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

    const CUSTOMER_LAT = {{ $order->customer_lat }};
    const CUSTOMER_LNG = {{ $order->customer_lng }};

    const MERCHANT_LAT = {{ $order->merchant_lat ?? 'null' }};
    const MERCHANT_LNG = {{ $order->merchant_lng ?? 'null' }};

    const STATUS = @json($order->status);

    const loadingOverlay = document.getElementById('loadingOverlay');
    const loadingText = document.getElementById('loadingText');
    const gpsBadge = document.getElementById('gpsBadge');
    const gpsStatusText = document.getElementById('gpsStatusText');
    const accuracyBadge = document.getElementById('accuracyBadge');
    const accuracyText = document.getElementById('accuracyText');
    const distancePill = document.getElementById('distancePill');
    const etaValue = document.getElementById('etaValue');
    const speedValue = document.getElementById('speedValue');
    const headingValue = document.getElementById('headingValue');
    const instructionsToggle = document.getElementById('instructionsToggle');
    const instructionsList = document.getElementById('instructionsList');
    const chevIcon = document.getElementById('chevIcon');
    const destinationCard = document.getElementById('destinationCard');

    let map = null;
    let routingControl = null;
    let riderMarker = null;
    let customerMarker = null;
    let merchantMarker = null;
    let accuracyCircle = null;
    let watchId = null;
    let lastApiUpdate = 0;
    let currentRiderLatLng = null;
    let currentInstructions = [];
    let instructionsOpen = false;
    let lastSpeed = 0;

    function showLoading(text) {
        loadingText.textContent = text;
        loadingOverlay.classList.remove('hidden');
    }
    function hideLoading() { loadingOverlay.classList.add('hidden'); }

    function setGpsError(msg) {
        gpsBadge.classList.add('gps-error');
        gpsStatusText.textContent = msg;
    }
    function setGpsOk() {
        gpsBadge.classList.remove('gps-error');
        gpsStatusText.textContent = 'GPS ACTIVE';
    }

    function formatDistance(meters) {
        if (meters == null || isNaN(meters)) return '-- m';
        if (meters >= 1000) return (meters / 1000).toFixed(1) + ' km';
        return Math.round(meters) + ' m';
    }
    function formatDuration(seconds) {
        if (seconds == null || isNaN(seconds)) return '-- min';
        const mins = Math.round(seconds / 60);
        if (mins < 1) return '< 1 min';
        return mins + ' min';
    }
    function formatSpeed(mps) {
        if (mps == null || isNaN(mps)) return '0 km/h';
        const kmh = Math.round(mps * 3.6);
        lastSpeed = kmh;
        return kmh + ' km/h';
    }
    function formatHeading(deg) {
        if (deg == null || isNaN(deg) || !isFinite(deg)) return '--\u00B0';
        return Math.round(deg) + '\u00B0';
    }

    function pinSvg(color) {
        return `
        <svg xmlns="http://www.w3.org/2000/svg" width="34" height="42" viewBox="0 0 34 42" fill="none" class="marker-pin">
            <path d="M17 0C7.61 0 0 7.61 0 17c0 12.75 17 25 17 25s17-12.25 17-25C34 7.61 26.39 0 17 0z" fill="${color}"/>
            <circle cx="17" cy="17" r="8" fill="white"/>
        </svg>`;
    }
    function riderPinSvg() {
        return `
        <div class="marker-icon">
            <div class="rider-pulse"></div>
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40" fill="none" class="marker-pin">
                <circle cx="20" cy="20" r="18" fill="#0891B2" stroke="#0F172A" stroke-width="3"/>
                <path d="M26 25.5h-4.1l-1.3-4h-5.2l-1.3 4H14M18 14.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM24.5 30a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0zM20 30a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0z"
                      stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
            </svg>
        </div>`;
    }

    function destinationPoint() {
        if (STATUS === 'NAVIGATING_TO_PICKUP' || STATUS === 'ARRIVED_AT_PICKUP') {
            if (MERCHANT_LAT != null) return { lat: MERCHANT_LAT, lng: MERCHANT_LNG, type: 'merchant' };
        }
        return { lat: CUSTOMER_LAT, lng: CUSTOMER_LNG, type: 'customer' };
    }

    function updateDestinationCard(dest) {
        const iconEl = destinationCard.querySelector('.dest-icon');
        const labelEl = destinationCard.querySelector('.dest-label');
        const addrEl = destinationCard.querySelector('.dest-address');

        if (dest.type === 'merchant') {
            iconEl.classList.remove('customer');
            iconEl.classList.add('merchant');
            labelEl.textContent = 'Pickup \u00B7 Merchant';
            addrEl.textContent = @json($order->pickup_address ?? 'Pickup location');
        } else {
            iconEl.classList.remove('merchant');
            iconEl.classList.add('customer');
            labelEl.textContent = 'Drop-off \u00B7 Customer';
            addrEl.textContent = @json($order->dropoff_address);
        }
    }

    function initMap(startLat, startLng) {
        const dest = destinationPoint();
        updateDestinationCard(dest);

        map = L.map('map', {
            zoomControl: true,
            attributionControl: true,
            preferCanvas: true,
        }).setView([startLat, startLng], 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        customerMarker = L.marker([CUSTOMER_LAT, CUSTOMER_LNG], {
            icon: L.divIcon({
                className: 'custom-marker',
                html: pinSvg('#8B5CF6') + '<div class="marker-label" style="background:#8B5CF6;">CUSTOMER</div>',
                iconSize: [34, 58],
                iconAnchor: [17, 42],
            })
        }).addTo(map);
        customerMarker.bindPopup('<strong>Customer Drop-off</strong><br>' + @json($order->dropoff_address));

        if (MERCHANT_LAT != null && MERCHANT_LNG != null) {
            merchantMarker = L.marker([MERCHANT_LAT, MERCHANT_LNG], {
                icon: L.divIcon({
                    className: 'custom-marker',
                    html: pinSvg('#F97316') + '<div class="marker-label" style="background:#F97316;">PICKUP</div>',
                    iconSize: [34, 58],
                    iconAnchor: [17, 42],
                })
            }).addTo(map);
            merchantMarker.bindPopup('<strong>Merchant Pickup</strong><br>' + @json($order->pickup_address ?? 'Pickup location'));
        }

        riderMarker = L.marker([startLat, startLng], {
            icon: L.divIcon({
                className: 'custom-marker',
                html: riderPinSvg(),
                iconSize: [40, 40],
                iconAnchor: [20, 20],
            }),
            zIndexOffset: 1000,
        }).addTo(map);

        accuracyCircle = L.circle([startLat, startLng], {
            radius: 20,
            color: '#0891B2',
            weight: 0,
            fillColor: '#0891B2',
            fillOpacity: 0.12,
            interactive: false,
        }).addTo(map);

        const waypoints = [
            L.latLng(startLat, startLng),
            L.latLng(dest.lat, dest.lng),
        ];

        routingControl = L.Routing.control({
            waypoints: waypoints,
            router: L.Routing.osrmv1({
                serviceUrl: 'https://router.project-osrm.org/route/v1',
                profile: 'driving',
                timeout: 15000,
                urlParameters: {
                    alternatives: false,
                    steps: true,
                    overview: 'full',
                    geometries: 'geojson',
                }
            }),
            lineOptions: {
                styles: [
                    { color: '#0891B2', opacity: 0.3, weight: 9 },
                    { color: '#06B6D4', opacity: 0.95, weight: 5 },
                ],
                addWaypoints: false,
                extendToWaypoints: false,
                missingRouteTolerance: 0,
            },
            altLineOptions: { styles: [{ opacity: 0 }] },
            show: false,
            addWaypoints: false,
            draggableWaypoints: false,
            fitSelectedRoutes: true,
            routeWhileDragging: false,
            autoRoute: true,
            geocoder: null,
            createMarker: function() { return null; },
        }).addTo(map);

        routingControl.on('routesfound', function (e) {
            const route = e.routes[0];
            const summary = route.summary;
            etaValue.textContent = formatDuration(summary.totalTime);
            distancePill.textContent = formatDistance(summary.totalDistance);
            buildInstructionsList(route.instructions || []);
        });

        routingControl.on('routingerror', function (e) {
            instructionsList.innerHTML =
                '<div style="padding: 20px 16px; text-align:center; color: var(--danger); font-size: 12px;">' +
                'Unable to load route. Showing straight-line distance instead.</div>';
            const d = haversine(currentRiderLatLng.lat, currentRiderLatLng.lng, dest.lat, dest.lng);
            distancePill.textContent = formatDistance(d);
            etaValue.textContent = formatDuration((d / 1000) / 25 * 3600);
        });

        const group = L.featureGroup([riderMarker, customerMarker]);
        if (merchantMarker) group.addLayer(merchantMarker);
        map.fitBounds(group.getBounds().pad(0.35));
    }

    function buildInstructionsList(instructions) {
        currentInstructions = instructions;
        if (!instructions || instructions.length === 0) {
            instructionsList.innerHTML =
                '<div style="padding: 20px 16px; text-align:center; color: var(--text-muted); font-size: 12px;">No instructions available.</div>';
            return;
        }

        let html = '';
        instructions.forEach((instr, idx) => {
            const icon = instructionIcon(instr);
            html += `
            <div class="instr-item" role="listitem" data-index="${idx}">
                <div class="instr-icon">${icon}</div>
                <div class="instr-text">
                    <div class="instr-road">${escapeHtml(instr.text || 'Continue')}</div>
                    <div class="instr-dist">${formatDistance(instr.distance)} &middot; then ${formatDuration(instr.time || 0)}</div>
                </div>
            </div>`;
        });
        instructionsList.innerHTML = html;
    }

    function instructionIcon(instr) {
        const t = (instr.type || '').toLowerCase();
        const d = instr.direction || '';
        if (t.includes('arrive') || t.includes('destination')) return '&#127968;';
        if (t.includes('start')) return '&#128659;';
        if (t.includes('roundabout')) return '&#8635;';
        if (t.includes('uturn')) return '&#8634;';
        if (t.includes('turn')) {
            if (d.includes('slight left') || d === 'left') return '&#8592;';
            if (d.includes('sharp left')) return '&#8624;';
            if (d.includes('slight right') || d === 'right') return '&#8594;';
            if (d.includes('sharp right')) return '&#8625;';
            return '&#8631;';
        }
        if (t.includes('merge')) return '&#8649;';
        if (t.includes('ramp')) return '&#8600;';
        if (t.includes('fork')) return '&#8650;';
        return (d === 'straight' || t.includes('continue') || t.includes('head')) ? '&#8593;' : '&#8226;';
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function haversine(lat1, lng1, lat2, lng2) {
        const R = 6371000;
        const toRad = Math.PI / 180;
        const dLat = (lat2 - lat1) * toRad;
        const dLng = (lng2 - lng1) * toRad;
        const a = Math.sin(dLat/2)**2 + Math.cos(lat1*toRad)*Math.cos(lat2*toRad)*Math.sin(dLng/2)**2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    }

    function updateRiderPosition(lat, lng, accuracy, heading, speed) {
        currentRiderLatLng = { lat, lng };

        if (map) {
            riderMarker.setLatLng([lat, lng]);
            accuracyCircle.setLatLng([lat, lng]);
            accuracyCircle.setRadius(Math.min(accuracy || 10, 150));

            const dest = destinationPoint();
            const dist = haversine(lat, lng, dest.lat, dest.lng);
            distancePill.textContent = formatDistance(dist);

            const isMoving = (speed || 0) > 0.5;
            if (isMoving) {
                map.panTo([lat, lng], { animate: true, duration: 0.6, easeLinearity: 0.25, noMoveStart: true });
            }

            const interval = isMoving ? 3000 : 15000;
            const now = Date.now();
            if (now - lastApiUpdate > interval - 500) {
                sendLocationUpdate(lat, lng, accuracy, heading, speed);
                lastApiUpdate = now;
            }
        }

        speedValue.textContent = formatSpeed(speed);
        headingValue.textContent = formatHeading(heading);
        if (accuracy != null) {
            accuracyBadge.style.display = 'inline-flex';
            accuracyText.textContent = '\u00B1 ' + Math.round(accuracy) + 'm';
        }
    }

    async function sendLocationUpdate(lat, lng, accuracy, heading, speed) {
        const payload = {
            rider_lat: Number(lat.toFixed(8)),
            rider_lng: Number(lng.toFixed(8)),
        };
        if (accuracy != null) payload.accuracy = Number(accuracy.toFixed(2));
        if (heading != null && isFinite(heading)) payload.heading = Number(heading.toFixed(2));
        if (speed != null) payload.speed = Number(speed.toFixed(3));

        try {
            await fetch(LOCATION_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
        } catch (err) {
            console.warn('[GPS] API update failed:', err);
        }
    }

    function startGpsWatch() {
        function fallbackStart(msg) {
            let fallbackLat = {{ $order->rider_lat ?? 'null' }};
            let fallbackLng = {{ $order->rider_lng ?? 'null' }};
            if (fallbackLat == null || fallbackLng == null) {
                const dest = destinationPoint();
                fallbackLat = dest.lat;
                fallbackLng = dest.lng;
            }
            if (!map) initMap(fallbackLat, fallbackLng);
            hideLoading();
            if (msg) loadingText.textContent = msg;
            setTimeout(() => hideLoading(), 800);
            updateRiderPosition(fallbackLat, fallbackLng, 25, null, 0);
        }

        if (!('geolocation' in navigator)) {
            setGpsError('NO GPS');
            fallbackStart('Geolocation is not supported by this browser.');
            return;
        }

        showLoading('Acquiring GPS signal...');

        watchId = navigator.geolocation.watchPosition(
            function success(pos) {
                const c = pos.coords;
                if (!map) {
                    initMap(c.latitude, c.longitude);
                    hideLoading();
                }
                setGpsOk();
                updateRiderPosition(c.latitude, c.longitude, c.accuracy, c.heading, c.speed);
            },
            function error(err) {
                console.error('[GPS] watch error:', err);
                let msg = 'GPS ERROR';
                switch (err.code) {
                    case 1: msg = 'PERMISSION DENIED'; fallbackStart('Location permission denied. Showing estimated position.'); break;
                    case 2: msg = 'POSITION UNAVAILABLE'; fallbackStart('GPS signal unavailable. Showing estimated position.'); break;
                    case 3: msg = 'GPS TIMEOUT'; fallbackStart('GPS timed out. Showing estimated position.'); break;
                    default: fallbackStart('GPS unavailable. Showing estimated position.');
                }
                setGpsError(msg);
            },
            {
                enableHighAccuracy: true,
                maximumAge: 1000,
                timeout: 20000,
            }
        );
    }

    instructionsToggle.addEventListener('click', () => {
        instructionsOpen = !instructionsOpen;
        instructionsList.classList.toggle('open', instructionsOpen);
        chevIcon.classList.toggle('open', instructionsOpen);
        instructionsToggle.setAttribute('aria-expanded', instructionsOpen ? 'true' : 'false');
    });
    instructionsToggle.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            instructionsToggle.click();
        }
    });

    window.addEventListener('beforeunload', () => {
        if (watchId != null) navigator.geolocation.clearWatch(watchId);
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && currentRiderLatLng) {
            sendLocationUpdate(
                currentRiderLatLng.lat,
                currentRiderLatLng.lng,
                null, null, null
            );
        }
    });

    startGpsWatch();
})();
</script>
</body>
</html>
