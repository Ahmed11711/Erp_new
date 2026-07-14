<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->invoice_no ?? $invoice->invoice_number ?? 'Purchase Invoice' }}</title>
    <style>
        :root {
            --brand: #7b2869;
            --brand-light: #f8f1f5;
            --border: #e5d5df;
            --text: #222;
            --muted: #666;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }

        html, body {
            margin: 0;
            font-family: Arial, Tahoma, sans-serif;
            color: var(--text);
            background: #ececf0;
        }

        p { margin: 0; }

        .toolbar {
            display: flex;
            justify-content: flex-end;
            padding: 16px 24px 0;
        }

        .toolbar button {
            padding: 10px 22px;
            border: none;
            border-radius: 10px;
            background: var(--brand);
            color: #fff;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .invoice-page {
            max-width: 900px;
            margin: 0 auto 24px;
            padding: 2.5rem 3rem 2rem;
            background: #fff;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.08);
        }

        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }

        .doc-badge {
            padding: 10px 34px;
            border-radius: 10px;
            background: var(--brand) !important;
            color: #fff !important;
            font-size: 1.15rem;
            font-weight: 700;
        }

        .doc-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1.05rem;
            font-weight: 700;
        }

        .doc-date {
            font-size: 0.95rem;
            color: var(--muted);
            font-weight: 500;
        }

        .logo-wrap {
            text-align: center;
            margin: 2rem 0 1.75rem;
        }

        .logo-wrap img {
            width: 140px;
            max-width: 100%;
            height: auto;
            object-fit: contain;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem 1.25rem;
            margin-bottom: 1.25rem;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .meta-item label {
            font-size: 0.82rem;
            color: var(--muted);
            font-weight: 600;
        }

        .meta-value {
            border: 2px solid var(--brand);
            border-radius: 8px;
            padding: 7px 12px;
            font-size: 0.92rem;
            background: #fff;
            min-height: 36px;
            display: flex;
            align-items: center;
        }

        .notes-box {
            margin-bottom: 1rem;
            padding: 10px 14px;
            border: 1px dashed #d8b8c8;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #444;
            background: var(--brand-light);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
            margin-top: 0.5rem;
        }

        .table th,
        .table td {
            border: 1px solid #dee2e6;
            padding: 0.55rem 0.45rem;
            font-size: 0.82rem;
        }

        .table th {
            background: var(--brand) !important;
            color: #fff !important;
            font-weight: 600;
        }

        .table tbody tr:nth-child(even) {
            background: #fafafa;
        }

        .table-empty td {
            padding: 1.25rem;
            color: var(--muted);
            font-style: italic;
        }

        .totals-section {
            margin-top: 1.75rem;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem;
            max-width: 620px;
            margin-right: auto;
            margin-left: 0;
        }

        .total-box {
            font-size: 0.95rem;
            border: 2px solid var(--brand);
            padding: 11px 14px;
            border-radius: 10px;
            text-align: center;
            background: #fff;
        }

        .total-box--grand {
            grid-column: 1 / -1;
            background: var(--brand) !important;
            color: #fff !important;
            font-weight: 700;
            font-size: 1.05rem;
        }

        .invoice-footer {
            margin-top: 2.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
            text-align: center;
        }

        .footer-note p {
            font-size: 0.82rem;
            color: var(--muted);
            line-height: 1.6;
        }

        .connect {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 0.65rem;
        }

        .connect p {
            font-size: 0.8rem;
            color: #555;
        }

        @media (max-width: 720px) {
            .invoice-page { padding: 1.5rem; }
            .meta-grid { grid-template-columns: 1fr; }
            .totals-section { grid-template-columns: 1fr; max-width: none; }
            .header-row { flex-direction: column; align-items: stretch; text-align: center; }
            .doc-meta { justify-content: center; }
        }

        @media print {
            @page { size: A4; margin: 12mm; }

            html, body { background: #fff; }

            .toolbar { display: none; }

            .invoice-page {
                max-width: none;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            .doc-badge,
            .table th,
            .total-box--grand {
                background: var(--brand) !important;
                color: #fff !important;
            }

            .totals-section,
            .invoice-footer,
            .table {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <button type="button" onclick="window.print()">طباعة</button>
</div>

<div class="invoice-page">
    <header class="header-row">
        <p class="doc-badge">فاتورة مشتريات</p>
        <div class="doc-meta">
            <p>{{ $invoice->invoice_no ?? $invoice->invoice_number ?? ('#'.$invoice->id) }}</p>
            <p class="doc-date">{{ $invoice->receipt_date }}</p>
        </div>
    </header>

    @if($logoUrl)
        <div class="logo-wrap">
            <img src="{{ $logoUrl }}" alt="Logo">
        </div>
    @endif

    <section class="meta-grid">
        <div class="meta-item">
            <label>المورد</label>
            <div class="meta-value">{{ $invoice->supplier->supplier_name ?? '—' }}</div>
        </div>
        <div class="meta-item">
            <label>مندوب الشحن</label>
            <div class="meta-value">{{ $invoice->shippingCompany->name ?? '—' }}</div>
        </div>
        <div class="meta-item">
            <label>رقم فاتورة المورد</label>
            <div class="meta-value">{{ $invoice->external_invoice_no ?: '—' }}</div>
        </div>
        <div class="meta-item">
            <label>نوع الفاتورة</label>
            <div class="meta-value">{{ $invoice->invoice_type }}</div>
        </div>
    </section>

    @if($invoice->notes)
        <div class="notes-box"><strong>ملاحظات:</strong> {{ $invoice->notes }}</div>
    @endif

    <table class="table">
        <thead>
        <tr>
            <th>#</th>
            <th style="width: 38%;">الصنف</th>
            <th>الوحدة</th>
            <th>الكمية</th>
            <th>سعر الوحدة</th>
            <th>الإجمالي</th>
        </tr>
        </thead>
        <tbody>
        @forelse($lines as $i => $line)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $line->product_name }}</td>
                <td>{{ $line->product_unit }}</td>
                <td>{{ number_format((float) $line->product_quantity, 3) }}</td>
                <td>{{ number_format((float) $line->product_price, 4) }}</td>
                <td>{{ number_format((float) $line->total, 2) }}</td>
            </tr>
        @empty
            <tr class="table-empty">
                <td colspan="6">لا توجد بنود</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <section class="totals-section">
        <p class="total-box">مجموع البنود: {{ number_format($subtotal, 2) }}</p>
        <p class="total-box">الشحن / مصاريف إضافية: {{ number_format($shipping, 2) }}</p>
        <p class="total-box">الضريبة ({{ number_format($tax_rate * 100, 2) }}٪): {{ number_format($tax_amount, 2) }}</p>
        <p class="total-box total-box--grand">الإجمالي: {{ number_format($grand + $tax_amount, 2) }}</p>
    </section>

    <footer class="invoice-footer">
        <div class="footer-note">
            <p>شكراً لتعاملكم معنا.</p>
            <p>تم إنشاء المستند آلياً من نظام ERP</p>
        </div>
        <div class="connect">
            <p>info@magalis-egypt.com</p>
            <p>+201118127345</p>
        </div>
    </footer>
</div>
</body>
</html>
