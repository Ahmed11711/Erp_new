<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->invoice_no ?? $invoice->invoice_number ?? 'Purchase' }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; margin: 0; padding: 24px; color: #222; background: #f6f7fb; }
        .sheet { max-width: 900px; margin: 0 auto; background: #fff; padding: 32px; border-radius: 8px; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        .toolbar { display: flex; justify-content: flex-end; margin-bottom: 16px; gap: 8px; }
        .toolbar button { padding: 10px 18px; border: none; border-radius: 6px; background: #2563eb; color: #fff; cursor: pointer; font-size: 14px; }
        .toolbar button:hover { background: #1d4ed8; }
        header.doc-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; border-bottom: 2px solid #e5e7eb; padding-bottom: 16px; margin-bottom: 20px; }
        .logo { max-height: 72px; max-width: 200px; object-fit: contain; }
        .company { font-size: 22px; font-weight: 700; margin: 0 0 4px; }
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 20px; font-size: 14px; }
        .meta-grid div span { color: #6b7280; display: block; font-size: 12px; }
        table.lines { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 8px; }
        table.lines th, table.lines td { border: 1px solid #e5e7eb; padding: 10px 8px; text-align: right; }
        table.lines th { background: #f3f4f6; font-weight: 600; }
        .totals { margin-top: 20px; width: 320px; margin-right: auto; font-size: 14px; }
        .totals tr td { padding: 6px 0; }
        .totals tr td:last-child { text-align: left; font-weight: 600; }
        .qr-placeholder { border: 1px dashed #cbd5e1; padding: 8px 12px; font-size: 11px; color: #64748b; border-radius: 4px; text-align: center; min-width: 120px; }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; border-radius: 0; max-width: none; padding: 18mm; }
        }
        @page { size: A4; margin: 14mm; }
    </style>
</head>
<body>
<div class="sheet">
    <div class="toolbar">
        <button type="button" onclick="window.print()">طباعة</button>
    </div>
    <header class="doc-head">
        <div>
            <div class="company">{{ $company }}</div>
            <div style="color:#6b7280;font-size:13px;">فاتورة مشتريات — Purchase Invoice</div>
        </div>
        <div style="text-align:left;">
            @if(file_exists(public_path('images/logo.png')))
                <img class="logo" src="{{ $logoUrl }}" alt="Logo">
            @endif
        </div>
    </header>

    <div class="meta-grid">
        <div>
            <span>رقم المستند الداخلي</span>
            <strong>{{ $invoice->invoice_no ?? $invoice->invoice_number ?? ('#'.$invoice->id) }}</strong>
        </div>
        <div>
            <span>رقم فاتورة المورد</span>
            <strong>{{ $invoice->external_invoice_no ?: '—' }}</strong>
        </div>
        <div>
            <span>التاريخ</span>
            <strong>{{ $invoice->receipt_date }}</strong>
        </div>
        <div>
            <span>المورد</span>
            <strong>{{ $invoice->supplier->supplier_name ?? '—' }}</strong>
        </div>
        <div>
            <span>مندوب الشحن</span>
            <strong>{{ $invoice->shippingCompany->name ?? '—' }}</strong>
        </div>
        <div>
            <span>نوع الفاتورة</span>
            <strong>{{ $invoice->invoice_type }}</strong>
        </div>
        <div>
            <span>حالة الطباعة</span>
            <strong>{{ $invoice->printable_status ?? 'draft' }}</strong>
        </div>
    </div>

    @if($invoice->notes)
        <p style="font-size:13px;color:#374151;"><strong>ملاحظات:</strong> {{ $invoice->notes }}</p>
    @endif

    <table class="lines">
        <thead>
        <tr>
            <th>#</th>
            <th>الصنف</th>
            <th>الوحدة</th>
            <th>الكمية</th>
            <th>سعر الوحدة</th>
            <th>الإجمالي</th>
        </tr>
        </thead>
        <tbody>
        @foreach($lines as $i => $line)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $line->product_name }}</td>
                <td>{{ $line->product_unit }}</td>
                <td>{{ number_format((float)$line->product_quantity, 3) }}</td>
                <td>{{ number_format((float)$line->product_price, 4) }}</td>
                <td>{{ number_format((float)$line->total, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>مجموع البنود</td><td>{{ number_format($subtotal, 2) }}</td></tr>
        <tr><td>الشحن / مصاريف إضافية</td><td>{{ number_format($shipping, 2) }}</td></tr>
        <tr><td>الضريبة ({{ number_format($tax_rate * 100, 2) }}٪)</td><td>{{ number_format($tax_amount, 2) }}</td></tr>
        <tr><td><strong>الإجمالي</strong></td><td><strong>{{ number_format($grand + $tax_amount, 2) }}</strong></td></tr>
    </table>

    <div style="margin-top:28px;display:flex;justify-content:space-between;align-items:center;">
        <div class="qr-placeholder">
            Barcode / QR (اختياري)<br>
            {{ $invoice->invoice_no ?? $invoice->invoice_number }}
        </div>
        <div style="font-size:12px;color:#94a3b8;">تم الإنشاء آلياً من نظام ERP</div>
    </div>
</div>
</body>
</html>
