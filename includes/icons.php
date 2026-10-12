<?php
function cpIcon(string $nome, string $classe = ''): string
{
    $paths = [
        'dashboard' =>
            '<rect x="3" y="3" width="8" height="6" rx="1.5"/><rect x="13" y="3" width="8" height="10" rx="1.5"/><rect x="3" y="11" width="8" height="10" rx="1.5"/><rect x="13" y="15" width="8" height="6" rx="1.5"/>',
        'financial-overview' =>
            '<path d="M3 17V7"/><path d="M3 17h18"/><path d="m6 14 4-4 3 2 5-6"/><path d="M16 6h2v2"/>',
        'bank-link' =>
            '<path d="m12 3 8 4H4l8-4Z"/><path d="M6 10v5M10 10v5"/><path d="M4 18h9"/><path d="M16 13h2a3 3 0 0 1 0 6h-2"/><path d="M16 19h-2a3 3 0 0 1 0-6h2"/>',
        'income' =>
            '<path d="M20 8V6a2 2 0 0 0-2-2H5a3 3 0 0 0 0 6h16v9a2 2 0 0 1-2 2H5a3 3 0 0 1-3-3V7"/><path d="m9 14-3 3-3-3"/><path d="M6 11v6"/>',
        'expense' =>
            '<path d="M20 8V6a2 2 0 0 0-2-2H5a3 3 0 0 0 0 6h16v9a2 2 0 0 1-2 2H9"/><path d="m3 14 3-3 3 3"/><path d="M6 11v7"/>',
        'transactions' =>
            '<path d="M4 7h15"/><path d="m15 3 4 4-4 4"/><path d="M20 17H5"/><path d="m9 13-4 4 4 4"/>',
        'budget-plan' =>
            '<path d="M4 5h16v14H4z"/><path d="M8 9h8"/><path d="M8 13h5"/><circle cx="17" cy="15" r="2"/>',
        'recurring' =>
            '<path d="M20 7h-9a5 5 0 0 0-5 5v1"/><path d="m17 4 3 3-3 3"/><path d="M4 17h9a5 5 0 0 0 5-5v-1"/><path d="m7 20-3-3 3-3"/>',
        'financial-health' =>
            '<path d="M20.8 5.8a5.2 5.2 0 0 0-7.4 0L12 7.2l-1.4-1.4a5.2 5.2 0 0 0-7.4 7.4L12 22l8.8-8.8a5.2 5.2 0 0 0 0-7.4Z"/><path d="M5.5 13h3l1.4-3 2.5 6 1.5-3H18"/>',
        'goal' =>
            '<path d="M5 21V4"/><path d="M5 5h11l-2 4 2 4H5"/><circle cx="5" cy="21" r="1"/>',
        'loan' =>
            '<path d="M6 3h9l4 4v14H6z"/><path d="M15 3v5h5"/><path d="M9 13h7M9 17h5"/><path d="M10 9h2"/>',
        'investments' =>
            '<path d="M5 20V10M12 20V4M19 20v-7"/><path d="M3 20h18"/><path d="m15 8 3-3 3 3"/>',
        'purchase-check' =>
            '<path d="M3 4h2l2.5 10h9.5l2-7H7"/><circle cx="9" cy="19" r="1"/><circle cx="17" cy="19" r="1"/><path d="m12 10 2 2 4-4"/>',
        'planning' =>
            '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/><path d="m8 15 2 2 5-5"/>',
        'reports' =>
            '<path d="M6 3h9l4 4v14H6z"/><path d="M15 3v5h5"/><path d="M9 17v-3M12 17v-6M15 17v-2"/>',
        // O brilho é o símbolo original do Copiloto e deve ser igual em todo o sistema.
        'copilot' =>
            '<path d="m12 3 1.6 4.4L18 9l-4.4 1.6L12 15l-1.6-4.4L6 9l4.4-1.6L12 3Z"/><path d="m19 15 .8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15Z"/>',
        'action-plan' =>
            '<path d="M5 4h14v16H5z"/><path d="m8 9 1.5 1.5L12 8M14 9h2M8 15h8"/>',
        'learning' =>
            '<path d="M3 5.5A3.5 3.5 0 0 1 6.5 2H12v18H6.5A3.5 3.5 0 0 0 3 23V5.5Z"/><path d="M21 5.5A3.5 3.5 0 0 0 17.5 2H12v18h5.5A3.5 3.5 0 0 1 21 23V5.5Z"/>',
        'business-overview' =>
            '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5h8v2M3 12h18"/><path d="M10 12v3h4v-3"/>',
        'performance' =>
            '<path d="M4 20V10M10 20V6M16 20v-4M22 20H2"/><path d="m4 7 5-4 5 4 6-5"/>',
        'cash-forecast' =>
            '<path d="M3 18h18"/><path d="M5 15c3-8 6 2 9-5 2-4 4-4 6-5"/><path d="M17 5h3v3"/>',
        'sales' =>
            '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h4"/><path d="m14 15 1.5 1.5L18 14"/>',
        'products' =>
            '<path d="m21 8-9 5-9-5 9-5 9 5Z"/><path d="m3 8 9 5 9-5v8l-9 5-9-5V8Z"/><path d="M12 13v8"/>',
        'employees' =>
            '<circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2"/><path d="M16 11a3 3 0 1 0 0-6M18 15a5 5 0 0 1 3 4v2"/>',
        'suppliers' =>
            '<path d="M3 6h11v10H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="19" r="2"/><circle cx="18" cy="19" r="2"/><path d="M6 10h5"/>',
        'costs' =>
            '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6M9 16h4"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'chevron-left' => '<path d="m15 18-6-6 6-6"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v6H5V6h6"/>',
        'send' => '<path d="m22 2-7 20-4-9-9-4 20-7Z"/><path d="M22 2 11 13"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/>',
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.7 2.7 0 1 1 4.5 2c-1.2.9-2 1.4-2 3M12 18h.01"/>',
        'grid' =>
            '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'arrow-up' => '<path d="M12 19V5"/><path d="m5 12 7-7 7 7"/>',
        'trending-up' => '<path d="m3 17 6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
        'trending-down' => '<path d="m3 7 6 6 4-4 8 8"/><path d="M15 17h6v-6"/>',
        'wallet' =>
            '<path d="M20 7V5a2 2 0 0 0-2-2H5a3 3 0 0 0 0 6h16v10a2 2 0 0 1-2 2H5a3 3 0 0 1-3-3V6"/><path d="M16 13h2"/>',
        'invest' =>
            '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/><path d="m15 7 3-3 3 3"/>',
        'forecast' => '<path d="M3 18 9 12l4 4 8-10"/><path d="M15 6h6v6"/>',
        'arrow-down' => '<path d="M12 5v14"/><path d="m19 12-7 7-7-7"/>',
        'arrow-down-left' => '<path d="M17 7 7 17"/><path d="M17 17H7V7"/>',
        'arrow-up-right' => '<path d="M7 17 17 7"/><path d="M7 7h10v10"/>',
        'arrow-left-right' =>
            '<path d="M8 3 4 7l4 4"/><path d="M4 7h16"/><path d="m16 21 4-4-4-4"/><path d="M20 17H4"/>',
        'upload' => '<path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M5 20h14"/>',
        'history' =>
            '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/>',
        'tag' => '<path d="M20 13 11 22l-9-9V4h9l9 9Z"/><circle cx="7" cy="9" r="1"/>',
        'user' => '<path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>',
        'logout' =>
            '<path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'pencil' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/>',
        'trash' =>
            '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/>',
        'archive' =>
            '<rect x="3" y="5" width="18" height="4" rx="1"/><path d="M5 9v11h14V9"/><path d="M10 13h4"/>',
        'shield' =>
            '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'gauge' =>
            '<path d="M4.93 19a10 10 0 1 1 14.14 0"/><path d="m12 12 4-4"/><path d="M12 12h.01"/>',
        'list-checks' =>
            '<path d="m3 7 2 2 4-4"/><path d="M11 7h10"/><path d="m3 17 2 2 4-4"/><path d="M11 17h10"/>',
        'graduation' =>
            '<path d="m2 10 10-5 10 5-10 5L2 10Z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/><path d="M22 10v6"/>',
        'swap' =>
            '<path d="m7 7 3-3 3 3"/><path d="M10 4v12"/><path d="m17 17-3 3-3-3"/><path d="M14 20V8"/>',
        'budget' => '<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/><path d="M12 8v8"/>',
        'repeat' =>
            '<path d="m17 1 4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="m7 23-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
        'target' =>
            '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="1"/>',
        'finance' =>
            '<path d="M3 7h18"/><path d="M5 7v12"/><path d="M19 7v12"/><path d="M3 19h18"/><path d="m12 3 9 4H3l9-4Z"/>',
        'cart' =>
            '<circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M3 4h2l2.5 11h10L20 8H7"/>',
        'calendar' =>
            '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'chart' => '<path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/>',
        'radar' =>
            '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><path d="M12 12 18 6"/>',
        'spark' =>
            '<path d="m12 3 1.6 4.4L18 9l-4.4 1.6L12 15l-1.6-4.4L6 9l4.4-1.6L12 3Z"/><path d="m19 15 .8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15Z"/>',
        'learn' =>
            '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4v15.5"/><path d="M4 4h13a3 3 0 0 1 3 3v10H6.5A2.5 2.5 0 0 0 4 19.5"/><path d="m10 8 4 2-4 2V8Z"/>',
        'briefcase' =>
            '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M3 12h18"/>',
        'sale' => '<path d="M3 12h18"/><path d="M12 3v18"/><path d="M7 7h10v10H7z"/>',
        'box' => '<path d="m21 8-9 5-9-5 9-5 9 5Z"/><path d="m3 8 9 5 9-5v8l-9 5-9-5V8Z"/>',
        'users' =>
            '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'truck' =>
            '<path d="M3 5h11v11H3z"/><path d="M14 9h4l3 3v4h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
        'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6M9 16h4"/>',
        'sun' =>
            '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>',
        'moon' => '<path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/>',
        'plane' => '<path d="m22 2-7 20-4-9-9-4 20-7Z"/><path d="M22 2 11 13"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
        'settings' =>
            '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .3 1.8l.1.1-2.8 2.8-.1-.1a1.65 1.65 0 0 0-1.8-.3 1.65 1.65 0 0 0-1 1.5V21h-4v-.2a1.65 1.65 0 0 0-1-1.5 1.65 1.65 0 0 0-1.8.3l-.1.1-2.8-2.8.1-.1A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.5-1H3v-4h.1a1.65 1.65 0 0 0 1.5-1 1.65 1.65 0 0 0-.3-1.8l-.1-.1 2.8-2.8.1.1a1.65 1.65 0 0 0 1.8.3 1.65 1.65 0 0 0 1-1.5V3h4v.2a1.65 1.65 0 0 0 1 1.5 1.65 1.65 0 0 0 1.8-.3l.1-.1 2.8 2.8-.1.1a1.65 1.65 0 0 0-.3 1.8 1.65 1.65 0 0 0 1.5 1H21v4h-.2a1.65 1.65 0 0 0-1.4 1Z"/>'
    ];

    $p = $paths[$nome] ?? $paths['help'];

    return '<svg class="cp-icon ' .
        htmlspecialchars($classe, ENT_QUOTES, 'UTF-8') .
        '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' .
        $p .
        '</svg>';
} ?>
