@php $biz = settings(); @endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>@yield('doc_title') @yield('doc_number')</title>
    <style>
        @page { margin: 28px 34px 60px 34px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2a37; line-height: 1.4; }
        h1, h2, h3, h4 { margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .muted { color: #6b7686; }
        .bold { font-weight: bold; }
        .accent { color: #0e9f8e; }
        .primary { color: #1b4f8a; }

        .header td { vertical-align: top; }
        .biz-name { font-size: 17px; font-weight: bold; color: #1b4f8a; }
        .doc-title { font-size: 20px; font-weight: bold; color: #1b4f8a; text-transform: uppercase; letter-spacing: 1px; }
        .doc-number { font-size: 11px; font-weight: bold; margin-top: 2px; }
        .band { height: 4px; background: #1b4f8a; margin: 10px 0 0; }
        .band-accent { height: 2px; background: #0e9f8e; margin: 0 0 14px; }

        .box { border: 1px solid #e3e8ef; border-radius: 6px; padding: 8px 10px; }
        .box-title { font-size: 8px; text-transform: uppercase; letter-spacing: .8px; color: #6b7686; font-weight: bold; margin-bottom: 3px; }
        .meta td { padding: 2px 0; }
        .meta td.label { color: #6b7686; width: 45%; }

        table.items { margin-top: 14px; }
        table.items th { background: #1b4f8a; color: #fff; font-size: 8.5px; text-transform: uppercase; letter-spacing: .4px; padding: 6px 6px; text-align: left; }
        table.items th.text-right { text-align: right; }
        table.items th.text-center { text-align: center; }
        table.items td { padding: 6px; border-bottom: 1px solid #e8ecf2; vertical-align: top; }
        table.items td.num, table.items th.num { text-align: right; white-space: nowrap; }
        table.items tr:nth-child(even) td { background: #f7f9fc; }
        table.items .desc { font-size: 8.5px; color: #6b7686; }

        table.totals td { padding: 4px 6px; }
        table.totals tr.grand td { font-size: 12px; font-weight: bold; color: #fff; background: #1b4f8a; }
        table.totals tr.due td { font-weight: bold; color: #b91c1c; }

        .status { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-partial { background: #fef3c7; color: #92400e; }
        .status-due { background: #fee2e2; color: #991b1b; }

        .notes { margin-top: 16px; font-size: 9px; }
        .signature td { padding-top: 34px; }
        .signature .line { border-top: 1px solid #9aa4b2; padding-top: 4px; width: 85%; font-size: 9px; color: #6b7686; }

        .footer { position: fixed; bottom: -40px; left: 0; right: 0; text-align: center; font-size: 8px; color: #6b7686; border-top: 1px solid #e3e8ef; padding-top: 6px; }
    </style>
    @stack('styles')
</head>
<body>
<div class="footer">
    {{ $biz->invoice_footer ?: 'Thank you for your business.' }}
    &middot; {{ $biz->business_name }}@if ($biz->phone) &middot; {{ $biz->phone }}@endif @if ($biz->email) &middot; {{ $biz->email }}@endif
</div>

<table class="header">
    <tr>
        <td style="width:60%">
            @if ($biz->logo_path)
                <img src="{{ $biz->logo_path }}" style="max-height:60px; max-width:180px; margin-bottom:6px"><br>
            @endif
            <div class="biz-name">{{ $biz->business_name }}</div>
            <div class="muted">
                @if ($biz->address){{ $biz->address }}@if ($biz->city), {{ $biz->city }}@endif<br>@endif
                @if ($biz->phone)Tel: {{ $biz->phone }}@endif @if ($biz->email) &middot; {{ $biz->email }}@endif<br>
                @if ($biz->tax_number){{ $biz->tax_label ?: 'Tax' }} / PIN: {{ $biz->tax_number }}@endif
            </div>
        </td>
        <td style="width:40%" class="text-right">
            <div class="doc-title">@yield('doc_title')</div>
            <div class="doc-number">@yield('doc_number')</div>
            @yield('doc_badge')
        </td>
    </tr>
</table>
<div class="band"></div>
<div class="band-accent"></div>

@yield('content')

</body>
</html>
