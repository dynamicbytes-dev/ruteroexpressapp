<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title ?? 'Reporte Oficial GAZ' }}</title>
    <style>
        @page {
            margin: 28px 28px 38px 28px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1a202c;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #6c11b0;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .logo-gaz {
            font-size: 32px;
            font-weight: 900;
            color: #1b0730;
            margin: 0;
            line-height: 1;
            letter-spacing: -1px;
        }
        .logo-gaz span {
            color: #00b8d4;
        }
        .empresa-sub {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #718096;
            margin-top: 2px;
            font-weight: bold;
        }
        .doc-title-box {
            text-align: right;
        }
        .doc-title {
            font-size: 16px;
            font-weight: bold;
            color: #1b0730;
            margin: 0;
            text-transform: uppercase;
        }
        .doc-subtitle {
            font-size: 10px;
            color: #4a5568;
            margin-top: 3px;
        }
        .doc-meta {
            font-size: 9px;
            color: #718096;
            margin-top: 4px;
        }
        .metrics-grid {
            width: 100%;
            margin-bottom: 16px;
            border-spacing: 6px;
            border-collapse: separate;
        }
        .metric-cell {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 12px;
            text-align: center;
        }
        .metric-label {
            font-size: 8.5px;
            text-transform: uppercase;
            color: #718096;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .metric-val {
            font-size: 14px;
            font-weight: bold;
            color: #1b0730;
            margin-top: 2px;
        }
        .table-data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 9.5px;
        }
        .table-data th {
            background-color: #1b0730;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding: 6px 8px;
            text-transform: uppercase;
            font-size: 8px;
            letter-spacing: 0.5px;
        }
        .table-data td {
            padding: 5.5px 8px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: middle;
        }
        .table-data tr:nth-child(even) td {
            background-color: #fbfcfe;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-green { background-color: #def7ec; color: #03543f; }
        .badge-yellow { background-color: #fef08a; color: #713f12; }
        .badge-red { background-color: #fde8e8; color: #9b1c1c; }
        .badge-blue { background-color: #e1effe; color: #1e429f; }
        .badge-purple { background-color: #f3e8ff; color: #6b21a8; }
        .footer {
            position: fixed;
            bottom: 0px;
            left: 0px;
            right: 0px;
            height: 22px;
            border-top: 1px solid #e2e8f0;
            font-size: 8px;
            color: #a0aec0;
            line-height: 20px;
        }
        .footer-left {
            float: left;
        }
        .footer-right {
            float: right;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: 'Courier New', Courier, monospace; }
        .text-muted { color: #718096; }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #1b0730;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 14px;
            margin-bottom: 6px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 3px;
        }
    </style>
</head>
<body>
    {{-- Membrete corporativo GAZ --}}
    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 40%; vertical-align: top;">
                <div class="logo-gaz">g<span>a</span>z</div>
                <div class="empresa-sub">Sistema de Movilidad y Rutas</div>
                <div style="font-size: 8.5px; color: #718096; margin-top: 3px;">
                    Plataforma Oficial de Gestión Operativa y Auditoría
                </div>
            </td>
            <td class="doc-title-box" style="width: 60%; vertical-align: top;">
                <h1 class="doc-title">{{ $title ?? 'Reporte Oficial' }}</h1>
                @if (!empty($subtitle))
                    <div class="doc-subtitle">{{ $subtitle }}</div>
                @endif
                <div class="doc-meta">
                    <strong>Fecha de emisión:</strong> {{ now()->translatedFormat('d/m/Y H:i:s') }}<br>
                    <strong>Generado por:</strong> {{ auth()->user()->name ?? 'Auditor del Sistema' }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Contenido del reporte --}}
    <div class="content">
        @yield('content')
    </div>

    {{-- Pie de página fijo --}}
    <div class="footer">
        <div class="footer-left">
            GAZ · Documento oficial emitido bajo firma del sistema. Válido para conciliación y auditoría interna.
        </div>
        <div class="footer-right">
            Ref: {{ strtoupper(substr(md5(now()->toIso8601String()), 0, 10)) }}
        </div>
    </div>
</body>
</html>
