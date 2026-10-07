<style>
    .pos-receipt {
        width: 100%;
        color: #000;
        background: #fff;
        font-size: 12px;
        line-height: 1.25;
    }
    .pos-receipt *, .pos-receipt *::before, .pos-receipt *::after { box-sizing: border-box; }
    .pos-receipt .flex > div { min-width: 0; max-width: 100%; }
    .pos-receipt .receipt-wrap, .pos-receipt .whitespace-nowrap {
        white-space: normal !important;
        overflow-wrap: anywhere;
    }
    .pos-receipt .text-xs\/4 { font-size: 12px; line-height: 1.25; }
    .pos-receipt table { width: 100%; table-layout: fixed; border-collapse: collapse; font-size: 12px; line-height: 1.25; }
    .pos-receipt td, .pos-receipt th { padding: 1px 2px; }
    .pos-receipt td { font-size: inherit !important; }
    .pos-receipt thead th, .pos-receipt .receipt-numbers td { white-space: nowrap; overflow-wrap: normal; }
    .pos-receipt th.w-20 { width: auto; }
    .pos-receipt h5 { font-size: 24px; line-height: 1.25; }
    .pos-receipt .mt-10 { margin-top: 2.5em; }
    .pos-receipt .uddds-slip { padding: 0.5em; }
    .pos-receipt .uddds-slip + .uddds-slip { border-top: 1px dashed #000; margin-top: 1em; }
    @media print {
        html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; }
        body { transform: none !important; }
        .min-h-screen { min-height: 0 !important; }
        .pos-print-page { width: 100%; max-width: none; margin: 0; padding: 0; }
        .pos-print-page .no-print { display: none !important; }
        .pos-receipt { width: 100%; max-width: none; margin: 0; }
        .pos-receipt .uddds-slip { break-before: auto; break-after: auto; break-inside: auto; }
        .pos-receipt tr { break-inside: avoid; }
        .pos-receipt thead, .pos-receipt h5 { break-after: avoid; }
    }
</style>
