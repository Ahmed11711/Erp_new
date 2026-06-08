<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $doc->transaction_no }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; margin: 0; padding: 24px; color: #222; background: #f6f7fb; }
        .sheet { max-width: 900px; margin: 0 auto; background: #fff; padding: 32px; border-radius: 8px; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        .toolbar { display: flex; justify-content: flex-end; margin-bottom: 16px; }
        .toolbar button { padding: 10px 18px; border: none; border-radius: 6px; background: #2563eb; color: #fff; cursor: pointer; font-size: 14px; }
        header.doc-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; border-bottom: 2px solid #e5e7eb; padding-bottom: 16px; margin-bottom: 20px; }
        .logo { max-height: 72px; max-width: 200px; object-fit: contain; }
        .company { font-size: 22px; font-weight: 700; margin: 0 0 4px; }
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 20px; font-size: 14px; }
        .meta-grid div span { color: #6b7280; display: block; font-size: 12px; }
        table.lines { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 8px; }
        table.lines th, table.lines td { border: 1px solid #e5e7eb; padding: 10px 8px; text-align: right; }
        table.lines th { background: #f3f4f6; font-weight: 600; }
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
            <div style="color:#6b7280;font-size:13px;">مستند مخزون — {{ $doc->type->name ?? '' }}</div>
        </div>
        <div style="text-align:left;">
            @if(file_exists(public_path('images/logo.png')))
                <img class="logo" src="{{ $logoUrl }}" alt="Logo">
            @endif
        </div>
    </header>

    <div class="meta-grid">
        <div>
            <span>رقم المستند</span>
            <strong>{{ $doc->transaction_no }}</strong>
        </div>
        <div>
            <span>النوع</span>
            <strong>{{ $doc->type->name ?? '—' }} ({{ $doc->type->code ?? '' }})</strong>
        </div>
        <div>
            <span>التاريخ</span>
            <strong>{{ $doc->document_date?->format('Y-m-d') }}</strong>
        </div>
        <div>
            <span>المخزن</span>
            <strong>{{ $doc->warehouse->name ?? '—' }}</strong>
        </div>
    </div>

    @if($doc->notes)
        <p style="font-size:13px;color:#374151;"><strong>ملاحظات:</strong> {{ $doc->notes }}</p>
    @endif

    <table class="lines">
        <thead>
        <tr>
            <th>#</th>
            <th>الصنف</th>
            <th>الكمية</th>
            <th>السعر</th>
            <th>الإجمالي</th>
            <th>وجهة / اتجاه</th>
        </tr>
        </thead>
        <tbody>
        @foreach($doc->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->product->category_name ?? $item->product_id }}</td>
                <td>{{ number_format((float)$item->qty, 3) }}</td>
                <td>{{ number_format((float)$item->price, 4) }}</td>
                <td>{{ number_format((float)$item->total, 2) }}</td>
                <td>
                    @if($item->to_product_id)
                        إلى: {{ $item->toProduct->category_name ?? $item->to_product_id }}
                    @elseif($item->qty_direction)
                        {{ $item->qty_direction }}
                    @else
                        —
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div style="margin-top:28px;display:flex;justify-content:space-between;align-items:center;">
        <div class="qr-placeholder">
            Barcode / QR (اختياري)<br>
            {{ $doc->transaction_no }}
        </div>
        <div style="font-size:12px;color:#94a3b8;">مستند مخزون — نظام ERP</div>
    </div>
</div>
</body>
</html>
