<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Track Order &middot; #{{ $order->order_number }} &middot; Motobook</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin="" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.lucide) {
                window.lucide.createIcons({
                    attrs: { 'stroke-width': 1.75, class: 'inline-block shrink-0' },
                });
            }
        });
    </script>

    <style>
        :root {
            --surface: #F8FAFC;
            --card: #FFFFFF;
            --border: #E2E8F0;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-muted: #94A3B8;
            --accent: #0891B2;
            --accent-hover: #0E7490;
            --accent-soft: #ECFEFF;
            --success: #059669;
            --success-soft: #ECFDF5;
            --warning: #D97706;
            --warning-soft: #FFFBEB;
            --danger: #DC2626;
            --danger-soft: #FEF2F2;
            --info: #2563EB;
            --info-soft: #EFF6FF;
        }

        * { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0;
            min-height: 100dvh;
            background: var(--surface);
            color: var(--text-primary);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            font-size: 14px;
            -webkit-font-smoothing: antialiased;
        }

        .page {
            display: grid;
            grid-template-columns: 1fr;
            grid-template-rows: auto 1fr auto;
            min-height: 100dvh;
            max-width: 1200px;
            margin: 0 auto;
        }
        @media (min-width: 1024px) {
            .page {
                grid-template-columns: 1fr 380px;
                grid-template-rows: auto 1fr;
                height: 100dvh;
            }
        }

        .topbar {
            grid-column: 1 / -1;
            padding: 14px 20px;
            background: var(--card);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
            justify-content: space-between;
            padding-top: calc(14px + env(safe-area-inset-top));
        }
        .brand {
            display: flex; align-items: center; gap: 10px;
        }
        .brand-mark {
            width: 38px; height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, #0891B2, #06B6D4);
            display: grid; place-items: center;
            color: white;
            box-shadow: 0 1px 3px rgba(8,145,178,0.3);
        }
        .brand-title { font-size: 15px; font-weight: 700; letter-spacing: -0.01em; }
        .brand-sub { font-size: 11px; color: var(--text-muted); font-weight: 500; }

        .top-meta { display: flex; align-items: center; gap: 10px; }
        .live-pill {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 6px 12px; border-radius: 9999px;
            background: var(--success-soft);
            color: var(--success);
            font-size: 11px; font-weight: 700;
            letter-spacing: 0.04em; text-transform: uppercase;
            border: 1px solid rgba(5, 150, 105, 0.2);
        }
        .live-dot {
            width: 7px; height: 7px; border-radius: 50%;
            background: currentColor;
            animation: livePulse 1.5s ease-in-out infinite;
        }
        @keyframes livePulse {
            0%, 100% { opacity: 1; box-shadow: 0 0 0 0 currentColor; }
            50% { opacity: 0.55; box-shadow: 0 0 0 5px transparent; }
        }

        .map-wrap {
            position: relative;
            min-height: 55vh;
            order: 1;
        }
        @media (min-width: 1024px) {
            .map-wrap { min-height: 0; order: 0; }
        }
        #map {
            position: absolute;
            inset: 0;
            background: #E6EEF5;
        }

        .map-legend {
            position: absolute;
            top: 12px; left: 12px;
            z-index: 450;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 12px;
            display: flex; flex-direction: column; gap: 7px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        }
        .legend-item {
            display: flex; align-items: center; gap: 8px;
            font-size: 11px; font-weight: 600; color: var(--text-secondary);
        }
        .legend-swatch {
            width: 12px; height: 12px; border-radius: 50%;
            flex-shrink: 0;
            box-shadow: 0 0 0 2px white, 0 1px 2px rgba(0,0,0,0.15);
        }

        .detail-panel {
            background: var(--card);
            border-top: 1px solid var(--border);
            order: 2;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: env(safe-area-inset-bottom);
        }
        @media (min-width: 1024px) {
            .detail-panel {
                border-top: none;
                border-left: 1px solid var(--border);
                order: 1;
            }
        }

        .order-header {
            padding: 20px 20px 16px;
            border-bottom: 1px solid var(--border);
        }
        .order-ref-row {
            display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
            margin-bottom: 10px;
        }
        .order-ref {
            font-size: 20px; font-weight: 800; letter-spacing: -0.02em; color: var(--text-primary);
        }
        .status-chip {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px;
            border-radius: 9999px;
            font-size: 11px; font-weight: 700;
            letter-spacing: 0.02em; text-transform: uppercase;
            flex-shrink: 0;
        }
        .status-chip.status-default { background: var(--info-soft); color: var(--info); }
        .status-chip.status-success { background: var(--success-soft); color: var(--success); }
        .status-chip.status-warning { background: var(--warning-soft); color: var(--warning); }
        .status-chip.status-danger  { background: var(--danger-soft);  color: var(--danger);  }

        .summary-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 14px;
        }
        .stat {
            padding: 10px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            text-align: center;
        }
        .stat-label {
            font-size: 10px; font-weight: 700;
            letter-spacing: 0.1em; text-transform: uppercase;
            color: var(--text-muted); margin-bottom: 4px;
        }
        .stat-value {
            font-size: 15px; font-weight: 700;
            color: var(--text-primary);
            font-variant-numeric: tabular-nums;
        }
        .stat-value.accent { color: var(--accent-hover); }

        .timeline {
            padding: 18px 20px 8px;
            border-bottom: 1px solid var(--border);
        }
        .timeline-title {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 14px;
            display: flex; align-items: center; gap: 8px;
        }
        .tl-steps {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 0;
        }
        .tl-step {
            display: grid;
            grid-template-columns: 28px 1fr auto;
            gap: 12px;
            padding: 9px 0;
            position: relative;
            align-items: flex-start;
        }
        .tl-step + .tl-step::before {
            content: "";
            position: absolute;
            left: 13px;
            top: -5px;
            width: 2px;
            height: 14px;
            background: var(--border);
        }
        .tl-step.done + .tl-step::before { background: var(--success); }
        .tl-step.current + .tl-step::before { background: linear-gradient(to bottom, var(--accent), var(--border)); }
        .tl-bullet {
            width: 28px; height: 28px;
            border-radius: 50%;
            display: grid; place-items: center;
            flex-shrink: 0;
            margin-top: 2px;
            background: white;
            border: 2px solid var(--border);
            color: var(--text-muted);
            transition: all 200ms ease;
        }
        .tl-step.done .tl-bullet {
            border-color: var(--success);
            background: var(--success);
            color: white;
        }
        .tl-step.current .tl-bullet {
            border-color: var(--accent);
            background: var(--accent-soft);
            color: var(--accent-hover);
            box-shadow: 0 0 0 4px rgba(8, 145, 178, 0.1);
        }
        .tl-content { min-width: 0; }
        .tl-title {
            font-size: 13px; font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 2px;
            line-height: 1.3;
        }
        .tl-step.done .tl-title,
        .tl-step.current .tl-title { color: var(--text-primary); }
        .tl-desc {
            font-size: 11px; color: var(--text-muted);
        }
        .tl-time {
            font-size: 11px; font-weight: 600;
            color: var(--text-muted);
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            padding-top: 6px;
        }
        .tl-step.current .tl-time { color: var(--accent-hover); }
        .tl-step.done .tl-time { color: var(--success); }

        .stop-card {
            margin: 14px 20px;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: var(--surface);
        }
        .stop-card + .stop-card { margin-top: 10px; }
        .stop-head {
            display: flex; align-items: flex-start; gap: 12px;
            margin-bottom: 10px;
        }
        .stop-icon {
            width: 38px; height: 38px;
            border-radius: 10px;
            display: grid; place-items: center;
            flex-shrink: 0;
        }
        .stop-icon.merchant { background: #FFF7ED; color: #C2410C; }
        .stop-icon.customer { background: #F5F3FF; color: #6D28D9; }

        .stop-body { flex: 1; min-width: 0; }
        .stop-label {
            font-size: 10px; font-weight: 700;
            letter-spacing: 0.1em; text-transform: uppercase;
            color: var(--text-muted); margin-bottom: 3px;
        }
        .stop-addr {
            font-size: 13px; font-weight: 600;
            color: var(--text-primary);
            line-height: 1.4;
        }
        .stop-coord {
            font-size: 11px;
            color: var(--text-muted);
            font-variant-numeric: tabular-nums;
            margin-top: 4px;
        }

        .rider-card {
            margin: 0 20px 16px;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: var(--card);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .rider-avatar {
            width: 46px; height: 46px;
            border-radius: 12px;
            background: linear-gradient(135deg, #0891B2, #06B6D4);
            display: grid; place-items: center;
            color: white; font-weight: 800; font-size: 16px;
            letter-spacing: 0.01em;
            box-shadow: 0 2px 6px rgba(8,145,178,0.25);
        }
        .rider-info { flex: 1; min-width: 0; }
        .rider-name { font-size: 14px; font-weight: 700; color: var(--text-primary); margin-bottom: 2px; }
        .rider-role { font-size: 11px; font-weight: 600; color: var(--accent-hover); letter-spacing: 0.03em; text-transform: uppercase; }
        .rider-contact {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 12px;
            border-radius: 10px;
            background: var(--accent-soft);
            color: var(--accent-hover);
            font-size: 12px; font-weight: 600;
            border: 1px solid rgba(8, 145, 178, 0.2);
            cursor: pointer;
            transition: all 150ms;
        }
        .rider-contact:hover { background: var(--accent); color: white; }

        .footer-note {
            padding: 14px 20px;
            text-align: center;
            font-size: 11px;
            color: var(--text-muted);
            border-top: 1px solid var(--border);
        }

        .leaflet-container {
            font-family: 'Inter', sans-serif;
            background: #E6EEF5;
        }
        .leaflet-control-attribution {
            background: rgba(255,255,255,0.85) !important;
            color: var(--text-muted) !important;
            font-size: 10px !important;
        }
        .leaflet-control-zoom a {
            background: var(--card) !important;
            border: 1px solid var(--border) !important;
            color: var(--text-primary) !important;
            border-radius: 8px !important;
            margin-bottom: 4px !important;
            width: 32px !important;
            height: 32px !important;
            line-height: 30px !important;
        }
        .leaflet-control-zoom-in { border-radius: 8px !important; }
        .leaflet-control-zoom-out { border-radius: 8px !important; }

        .custom-marker { background: transparent; border: none; }
        .marker-icon {
            position: relative;
            display: grid;
            place-items: center;
        }
        .rider-pulse {
            position: absolute;
            width: 28px; height: 28px;
            border-radius: 50%;
            background: rgba(8, 145, 178, 0.3);
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            animation: riderPulse 1.8s ease-out infinite;
            pointer-events: none;
            z-index: -1;
        }
        @keyframes riderPulse {
            0%   { transform: translate(-50%, -50%) scale(0.8); opacity: 0.9; }
            100% { transform: translate(-50%, -50%) scale(2); opacity: 0; }
        }
        .marker-pin { filter: drop-shadow(0 2px 5px rgba(0,0,0,0.25)); }
        .marker-label {
            position: absolute;
            bottom: -18px;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 5px;
            color: white;
            letter-spacing: 0.02em;
        }

        .route-line-base {
            stroke: rgba(8, 145, 178, 0.22);
            stroke-width: 10;
            stroke-linecap: round;
            fill: none;
        }
        .route-line-top {
            stroke: #0891B2;
            stroke-width: 4;
            stroke-linecap: round;
            stroke-dasharray: 6 6;
            fill: none;
            animation: dash 22s linear infinite;
        }
        @keyframes dash {
            to { stroke-dashoffset: -1000; }
        }

        .spinner {
            width: 18px; height: 18px;
            border: 2px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .poll-error {
            display: none;
            padding: 10px 14px;
            margin: 12px 20px 0;
            border-radius: 10px;
            background: var(--danger-soft);
            color: var(--danger);
            border: 1px solid rgba(220, 38, 38, 0.2);
            font-size: 12px;
            font-weight: 600;
            align-items: center;
            gap: 8px;
        }
        .poll-error.visible { display: inline-flex; }
    </style>
</head>
<body>
<div class="page">
    <header class="topbar">
        <div class="brand">
            <div class="brand-mark">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     data-lucide="bike" aria-hidden="true"></svg>
            </div>
            <div>
                <div class="brand-title">Motobook</div>
                <div class="brand-sub">Delivery Tracking</div>
            </div>
        </div>
        <div class="top-meta">
            <div class="live-pill" id="livePill" role="status" aria-live="polite">
                <span class="live-dot"></span>
                <span id="liveText">LIVE</span>
            </div>
        </div>
    </header>

    <div class="map-wrap">
        <div id="map" aria-label="Delivery tracking map"></div>
        <div class="map-legend" aria-label="Map legend">
            <div class="legend-item"><span class="legend-swatch" style="background:#0891B2;"></span> Rider (Live)</div>
            <div class="legend-item"><span class="legend-swatch" style="background:#C2410C;"></span> Merchant / Pickup</div>
            <div class="legend-item"><span class="legend-swatch" style="background:#6D28D9;"></span> Your Location</div>
        </div>
    </div>

    <aside class="detail-panel">
        <div class="order-header">
            <div class="order-ref-row">
                <div class="order-ref">#{{ $order->order_number }}</div>
                <div class="status-chip status-default" id="statusChip" data-status="{{ $order->status }}">
                    <span id="statusText">{{ $order->status }}</span>
                </div>
            </div>
            <div style="display:flex; gap:12px; align-items:center; font-size:12px; color:var(--text-secondary);">
                <span style="display:inline-flex; align-items:center; gap:6px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
                         data-lucide="calendar-clock" aria-hidden="true"></svg>
                    <span id="placedAtText">Placed {{ $order->created_at->format('M j, g:i A') }}</span>
                </span>
            </div>

            <div class="summary-stats">
                <div class="stat">
                    <div class="stat-label">Distance</div>
                    <div class="stat-value accent" id="distanceStat">-- m</div>
                </div>
                <div class="stat">
                    <div class="stat-label">ETA</div>
                    <div class="stat-value" id="etaStat">-- min</div>
                </div>
                <div class="stat">
                    <div class="stat-label">Payment</div>
                    <div class="stat-value" id="paymentStat">{{ strtoupper($order->payment_method) }}</div>
                </div>
            </div>
        </div>

        <div class="poll-error" id="pollError" role="alert">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <span id="pollErrorMsg">Connection interrupted — retrying...</span>
        </div>

        <div class="timeline">
            <div class="timeline-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     data-lucide="route" aria-hidden="true"></svg>
                Delivery Progress
            </div>
            <div class="tl-steps" id="timelineSteps">
            </div>
        </div>

        @if($order->pickup_address)
        <div class="stop-card" id="pickupCard">
            <div class="stop-head">
                <div class="stop-icon merchant">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         data-lucide="store" aria-hidden="true"></svg>
                </div>
                <div class="stop-body">
                    <div class="stop-label">Pickup from Merchant</div>
                    <div class="stop-addr" id="pickupAddr">{{ $order->pickup_address }}</div>
                    @if($order->merchant_lat && $order->merchant_lng)
                    <div class="stop-coord" id="pickupCoord">{{ number_format((float)$order->merchant_lat, 6) }}, {{ number_format((float)$order->merchant_lng, 6) }}</div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        <div class="stop-card">
            <div class="stop-head">
                <div class="stop-icon customer">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         data-lucide="map-pin" aria-hidden="true"></svg>
                </div>
                <div class="stop-body">
                    <div class="stop-label">Drop-off at Your Location</div>
                    <div class="stop-addr">{{ $order->dropoff_address }}</div>
                    <div class="stop-coord">{{ number_format((float)$order->customer_lat, 6) }}, {{ number_format((float)$order->customer_lng, 6) }}</div>
                </div>
            </div>
        </div>

        <div class="rider-card" id="riderCard" style="display:none;">
            <div class="rider-avatar" id="riderAvatar">R</div>
            <div class="rider-info">
                <div class="rider-name" id="riderName">Assigning Rider...</div>
                <div class="rider-role">Motobook Rider</div>
            </div>
            <button class="rider-contact" type="button" id="riderContactBtn" aria-label="Call rider">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                </svg>
                <span>Call</span>
            </button>
        </div>

        <div class="footer-note">
            Map updates every 3 seconds when rider is in motion &middot; Powered by OpenStreetMap
        </div>
    </aside>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>

<script>
(function () {
    'use strict';

    const ORDER_ID = @json($order->id);
    const API_BASE_URL = @json(url('/api'));
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

    const INIT = {
        status:       @json($order->status),
        customerLat:  {{ $order->customer_lat }},
        customerLng:  {{ $order->customer_lng }},
        merchantLat:  {{ $order->merchant_lat ?? 'null' }},
        merchantLng:  {{ $order->merchant_lng ?? 'null' }},
        riderLat:     {{ $order->rider_lat ?? 'null' }},
        riderLng:     {{ $order->rider_lng ?? 'null' }},
        createdAt:    @json($order->created_at->toIso8601String()),
        acceptedAt:   @json($order->accepted_at?->toIso8601String()),
        pickedUpAt:   @json($order->picked_up_at?->toIso8601String()),
        deliveredAt:  @json($order->delivered_at?->toIso8601String()),
        totalAmount:  {{ (float)$order->total_amount }},
        deliveryFee:  {{ (float)$order->delivery_fee }},
        paymentMethod:@json($order->payment_method),
        paymentStatus:@json($order->payment_status),
        dropoffAddr:  @json($order->dropoff_address),
        pickupAddr:   @json($order->pickup_address),
    };

    const POLL_INTERVAL_MOVING_MS = 3000;
    const POLL_INTERVAL_IDLE_MS   = 8000;
    const RECONNECT_BASE_MS       = 2000;
    const MAX_RECONNECT_MS        = 30000;

    let map = null;
    let customerMarker = null;
    let merchantMarker = null;
    let riderMarker = null;
    let routeLayer = null;
    let bounds = null;
    let pollTimer = null;
    let consecutiveErrors = 0;
    let lastRiderPos = null;
    let lastUpdateAt = null;
    let currentStatus = INIT.status;

    const statusChip = document.getElementById('statusChip');
    const statusText = document.getElementById('statusText');
    const distanceStat = document.getElementById('distanceStat');
    const etaStat = document.getElementById('etaStat');
    const paymentStat = document.getElementById('paymentStat');
    const timelineSteps = document.getElementById('timelineSteps');
    const livePill = document.getElementById('livePill');
    const liveText = document.getElementById('liveText');
    const pollError = document.getElementById('pollError');
    const pollErrorMsg = document.getElementById('pollErrorMsg');
    const riderCard = document.getElementById('riderCard');
    const riderName = document.getElementById('riderName');
    const riderAvatar = document.getElementById('riderAvatar');

    function pinSvg(color) {
        return `
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="40" viewBox="0 0 32 40" fill="none" class="marker-pin">
            <path d="M16 0C7.163 0 0 7.163 0 16c0 12 16 24 16 24s16-12 16-24C32 7.163 24.837 0 16 0z" fill="${color}"/>
            <circle cx="16" cy="16" r="7" fill="white"/>
        </svg>`;
    }
    function riderPinSvg() {
        return `
        <div class="marker-icon">
            <div class="rider-pulse"></div>
            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 36 36" fill="none" class="marker-pin">
                <circle cx="18" cy="18" r="16" fill="#0891B2" stroke="#FFFFFF" stroke-width="3"/>
                <path d="M23 23h-3.7l-1.15-3.5h-4.3l-1.15 3.5H13M16.5 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM22 27a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM18 27a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"
                      stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
            </svg>
        </div>`;
    }

    function statusClassFor(status) {
        const s = (status || '').toUpperCase();
        if (s.includes('COMPLETE') || s.includes('PAID') || s.includes('DELIVERED')) return 'status-success';
        if (s.includes('CANCEL') || s.includes('FAIL') || s.includes('REJECT')) return 'status-danger';
        if (s.includes('PENDING') || s.includes('OFFER') || s.includes('WAITING') || s.includes('VERIFY')) return 'status-warning';
        return 'status-default';
    }

    function formatTime(iso) {
        if (!iso) return '—';
        try {
            const d = new Date(iso);
            return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        } catch (e) { return '—'; }
    }

    function formatDistance(meters) {
        if (meters == null || isNaN(meters)) return '-- m';
        if (meters >= 1000) return (meters / 1000).toFixed(1) + ' km';
        return Math.round(meters) + ' m';
    }
    function formatEta(meters) {
        if (meters == null || isNaN(meters)) return '-- min';
        const avgKmh = 22;
        const hours = (meters / 1000) / avgKmh;
        const mins = Math.max(1, Math.round(hours * 60));
        return mins + ' min';
    }
    function haversine(lat1, lng1, lat2, lng2) {
        const R = 6371000;
        const toRad = Math.PI / 180;
        const dLat = (lat2 - lat1) * toRad;
        const dLng = (lng2 - lng1) * toRad;
        const a = Math.sin(dLat/2)**2 + Math.cos(lat1*toRad)*Math.cos(lat2*toRad)*Math.sin(dLng/2)**2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    }

    function renderTimeline(data) {
        const accepted  = data.accepted_at || INIT.acceptedAt;
        const pickedUp  = data.picked_up_at || INIT.pickedUpAt;
        const delivered = data.delivered_at || INIT.deliveredAt;
        const status = (data.status || currentStatus).toUpperCase();

        const isPendingOrOffer    = ['PENDING', 'OFFER_RECEIVED'].includes(status);
        const isAccepted          = ['ACCEPTED', 'NAVIGATING_TO_PICKUP', 'ARRIVED_AT_PICKUP'].includes(status);
        const isPicked            = ['ORDER_VERIFIED', 'NAVIGATING_TO_DROP_OFF', 'ARRIVED_AT_DROP_OFF', 'PROOF_SUBMITTED'].includes(status);
        const isDone              = ['COMPLETED'].includes(status);
        const isCancelled         = ['CANCELLED'].includes(status);

        const steps = [
            {
                key: 'placed',
                title: 'Order Placed',
                desc: 'Motobook received your request',
                time: INIT.createdAt,
                state: (isPendingOrOffer && !isCancelled) ? 'current' : 'done',
                icon: 'shopping-bag',
            },
            {
                key: 'accepted',
                title: 'Rider Assigned',
                desc: accepted ? 'Rider is heading to pickup' : 'Looking for an available rider',
                time: accepted,
                state: isCancelled ? 'pending' : (isAccepted || isPicked || isDone ? 'done' : (isPendingOrOffer ? 'pending' : 'done')),
                icon: 'bike',
                currentOverride: isAccepted && !isPicked,
            },
            {
                key: 'picked',
                title: 'Package Picked Up',
                desc: pickedUp ? 'Package is on the way to you' : 'Awaiting pickup from merchant',
                time: pickedUp,
                state: isCancelled ? 'pending' : (isDone ? 'done' : (isPicked ? 'done' : (isAccepted ? 'pending' : 'pending'))),
                icon: 'package-check',
                currentOverride: isPicked && !isDone,
            },
            {
                key: 'delivered',
                title: isCancelled ? 'Order Cancelled' : 'Delivered',
                desc: isCancelled ? 'This order has been cancelled' : (delivered ? 'Handed to customer' : 'Out for delivery'),
                time: delivered,
                state: isCancelled ? 'current' : (isDone ? 'done' : 'pending'),
                icon: isCancelled ? 'x-circle' : 'handshake',
                currentOverride: isDone,
            },
        ];

        let html = '';
        steps.forEach(step => {
            let state = step.state;
            if (step.currentOverride) state = 'current';
            const timeStr = formatTime(step.time);
            const iconMap = {
                'shopping-bag':  '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
                'bike':          '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 16H9m10 0h3v-3.15a1 1 0 0 0-.84-.99L16 11l-2.7-3.6a1 1 0 0 0-.8-.4H5.24a2 2 0 0 0-1.8 1.1l-.8 1.63A6 6 0 0 0 2 12.42V16h2"/><circle cx="6.5" cy="16.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/></svg>',
                'package-check': '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/><path d="m9 15 2 2 4-4"/></svg>',
                'handshake':     '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m11 17 2 2a1 1 0 1 0 3-3"/><path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4"/><path d="m21 3 1 11h-2"/><path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3"/><path d="M3 4h8"/></svg>',
                'x-circle':      '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>',
                'check':         '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
                'dot':           '<span style="width:6px;height:6px;border-radius:50%;background:currentColor;"></span>',
            };
            const bullet = (state === 'done') ? iconMap['check'] : iconMap[step.icon] || iconMap['dot'];
            html += `
            <div class="tl-step ${state}" data-step="${step.key}">
                <div class="tl-bullet">${bullet}</div>
                <div class="tl-content">
                    <div class="tl-title">${step.title}</div>
                    <div class="tl-desc">${step.desc}</div>
                </div>
                <div class="tl-time">${timeStr}</div>
            </div>`;
        });
        timelineSteps.innerHTML = html;
    }

    function updateStatusUI(status) {
        currentStatus = status;
        const s = (status || '').toUpperCase();
        statusText.textContent = s.replace(/_/g, ' ');
        statusChip.classList.remove('status-default', 'status-success', 'status-warning', 'status-danger');
        statusChip.classList.add(statusClassFor(s));
    }

    function updateRiderCard(rider) {
        if (!rider) {
            riderCard.style.display = 'none';
            return;
        }
        riderCard.style.display = 'flex';
        riderName.textContent = rider.name;
        riderAvatar.textContent = (rider.name || 'R').trim().charAt(0).toUpperCase();
    }

    function updateRouteLayer(fromLat, fromLng, toLat, toLng) {
        if (routeLayer) {
            map.removeLayer(routeLayer);
            routeLayer = null;
        }
        const latlngs = [[fromLat, fromLng], [toLat, toLng]];
        routeLayer = L.layerGroup().addTo(map);
        const baseLine = L.polyline(latlngs, {
            color: '#0891B2',
            opacity: 0.22,
            weight: 10,
            lineCap: 'round',
            interactive: false,
        }).addTo(routeLayer);

        fetch(
            `https://router.project-osrm.org/route/v1/driving/${fromLng},${fromLat};${toLng},${toLat}?overview=full&geometries=geojson&steps=false`
        ).then(r => r.ok ? r.json() : null).then(data => {
            if (!data || !data.routes || !data.routes[0]) return;
            const coords = data.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);
            baseLine.setLatLngs(coords);
            try {
                const geo = data.routes[0].geometry;
                const dash = L.geoJSON(geo, {
                    style: {
                        color: '#0891B2',
                        weight: 4,
                        opacity: 0.95,
                        dashArray: '6, 6',
                        lineCap: 'round',
                        interactive: false,
                    },
                    interactive: false,
                }).addTo(routeLayer);
            } catch (e) {}
        }).catch(() => {});
    }

    function initMap() {
        const centerLat = INIT.riderLat ?? INIT.customerLat;
        const centerLng = INIT.riderLng ?? INIT.customerLng;

        map = L.map('map', {
            zoomControl: true,
            attributionControl: true,
            preferCanvas: true,
        }).setView([centerLat, centerLng], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        bounds = L.latLngBounds();

        customerMarker = L.marker([INIT.customerLat, INIT.customerLng], {
            icon: L.divIcon({
                className: 'custom-marker',
                html: pinSvg('#6D28D9') + '<div class="marker-label" style="background:#6D28D9;">YOU</div>',
                iconSize: [32, 56],
                iconAnchor: [16, 40],
            }),
        }).addTo(map).bindPopup(`<strong>Your Location</strong><br>${escapeHtml(INIT.dropoffAddr)}`);
        bounds.extend([INIT.customerLat, INIT.customerLng]);

        if (INIT.merchantLat != null && INIT.merchantLng != null) {
            merchantMarker = L.marker([INIT.merchantLat, INIT.merchantLng], {
                icon: L.divIcon({
                    className: 'custom-marker',
                    html: pinSvg('#C2410C') + '<div class="marker-label" style="background:#C2410C;">PICKUP</div>',
                    iconSize: [32, 56],
                    iconAnchor: [16, 40],
                }),
            }).addTo(map).bindPopup(`<strong>Merchant Pickup</strong><br>${escapeHtml(INIT.pickupAddr || '')}`);
            bounds.extend([INIT.merchantLat, INIT.merchantLng]);
        }

        if (INIT.riderLat != null && INIT.riderLng != null) {
            riderMarker = L.marker([INIT.riderLat, INIT.riderLng], {
                icon: L.divIcon({
                    className: 'custom-marker',
                    html: riderPinSvg(),
                    iconSize: [36, 36],
                    iconAnchor: [18, 18],
                }),
                zIndexOffset: 1000,
            }).addTo(map);
            lastRiderPos = { lat: INIT.riderLat, lng: INIT.riderLng };
            bounds.extend([INIT.riderLat, INIT.riderLng]);

            const destLat = shouldShowMerchantAsDestination(INIT.status) && INIT.merchantLat != null
                ? INIT.merchantLat : INIT.customerLat;
            const destLng = shouldShowMerchantAsDestination(INIT.status) && INIT.merchantLng != null
                ? INIT.merchantLng : INIT.customerLng;
            updateRouteLayer(INIT.riderLat, INIT.riderLng, destLat, destLng);
            const dist = haversine(INIT.riderLat, INIT.riderLng, destLat, destLng);
            distanceStat.textContent = formatDistance(dist);
            etaStat.textContent = formatEta(dist);
        }

        if (bounds.isValid()) map.fitBounds(bounds.pad(0.35), { maxZoom: 15, animate: false });

        setTimeout(() => { map.invalidateSize(); }, 250);
    }

    function shouldShowMerchantAsDestination(status) {
        const s = (status || '').toUpperCase();
        return ['ACCEPTED', 'NAVIGATING_TO_PICKUP', 'ARRIVED_AT_PICKUP', 'OFFER_RECEIVED'].includes(s);
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    async function pollOnce() {
        try {
            const res = await fetch(`${API_BASE_URL}/orders/${ORDER_ID}/tracking`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                },
                credentials: 'include',
            });

            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }
            const json = await res.json();
            if (!json.success) throw new Error('API returned error');

            consecutiveErrors = 0;
            pollError.classList.remove('visible');
            livePill.style.background = '';
            liveText.textContent = 'LIVE';

            applyTrackingData(json.data);
            scheduleNextPoll(json.data);
        } catch (err) {
            consecutiveErrors++;
            const backoff = Math.min(MAX_RECONNECT_MS, RECONNECT_BASE_MS * Math.pow(1.5, consecutiveErrors - 1));
            pollError.classList.add('visible');
            pollErrorMsg.textContent = consecutiveErrors >= 3
                ? `Connection lost — retrying in ${Math.round(backoff/1000)}s`
                : 'Connection interrupted — retrying...';
            livePill.style.background = 'var(--warning-soft)';
            livePill.style.color = 'var(--warning)';
            liveText.textContent = 'RECONNECTING';
            pollTimer = setTimeout(pollOnce, backoff);
        }
    }

    function scheduleNextPoll(data) {
        let interval = POLL_INTERVAL_IDLE_MS;
        if (data && data.locations && data.locations.rider && lastRiderPos) {
            const dt = Math.max(1, ((Date.now() - (lastUpdateAt ? new Date(lastUpdateAt).getTime() : 0)) / 1000));
            const d = haversine(
                lastRiderPos.lat, lastRiderPos.lng,
                parseFloat(data.locations.rider.lat), parseFloat(data.locations.rider.lng)
            );
            const ms = (d / dt) * 3.6;
            if (ms > 15) interval = POLL_INTERVAL_MOVING_MS;
        }
        pollTimer = setTimeout(pollOnce, interval);
    }

    function applyTrackingData(data) {
        const order = data.order || {};
        const locations = data.locations || {};
        const distances = data.distances || {};

        updateStatusUI(order.status);
        paymentStat.textContent = String(order.payment_method || '').toUpperCase();

        lastUpdateAt = order.updated_at;
        INIT.acceptedAt = order.accepted_at || INIT.acceptedAt;
        INIT.pickedUpAt = order.picked_up_at || INIT.pickedUpAt;
        INIT.deliveredAt = order.delivered_at || INIT.deliveredAt;

        renderTimeline(order);
        updateRiderCard(data.rider);

        if (locations.rider) {
            const rLat = parseFloat(locations.rider.lat);
            const rLng = parseFloat(locations.rider.lng);
            if (!riderMarker) {
                riderMarker = L.marker([rLat, rLng], {
                    icon: L.divIcon({
                        className: 'custom-marker',
                        html: riderPinSvg(),
                        iconSize: [36, 36],
                        iconAnchor: [18, 18],
                    }),
                    zIndexOffset: 1000,
                }).addTo(map);
            } else {
                riderMarker.setLatLng([rLat, rLng]);
            }

            const toMerchantFirst = shouldShowMerchantAsDestination(order.status) && locations.merchant;
            const destLat = toMerchantFirst ? parseFloat(locations.merchant.lat) : parseFloat(locations.customer.lat);
            const destLng = toMerchantFirst ? parseFloat(locations.merchant.lng) : parseFloat(locations.customer.lng);
            const distMeters = distances.rider_to_customer_meters;
            const customerDistanceRaw = distMeters != null ? distMeters
                : haversine(rLat, rLng, parseFloat(locations.customer.lat), parseFloat(locations.customer.lng));

            distanceStat.textContent = formatDistance(customerDistanceRaw);
            etaStat.textContent = formatEta(customerDistanceRaw);

            if (!lastRiderPos || Math.abs(lastRiderPos.lat - rLat) > 0.00005 || Math.abs(lastRiderPos.lng - rLng) > 0.00005) {
                updateRouteLayer(rLat, rLng, destLat, destLng);
                lastRiderPos = { lat: rLat, lng: rLng };
                try { map.panTo([rLat, rLng], { animate: true, duration: 1.2 }); } catch (e) {}
            } else {
                lastRiderPos = { lat: rLat, lng: rLng };
            }
        }
    }

    window.addEventListener('resize', () => { if (map) map.invalidateSize(); });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            if (pollTimer) clearTimeout(pollTimer);
            pollOnce();
        }
    });

    window.addEventListener('beforeunload', () => {
        if (pollTimer) clearTimeout(pollTimer);
    });

    updateStatusUI(INIT.status);
    renderTimeline({ status: INIT.status });
    initMap();
    pollOnce();
})();
</script>
</body>
</html>
