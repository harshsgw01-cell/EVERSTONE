<?php

/**
 * MODERN PDF STYLING SYSTEM
 * 
 * A completely new PDF styling approach with:
 * - Minimalist editorial design
 * - Warm earth tone color palette (terracotta, cream, charcoal)
 * - Grid-based modular layout
 * - Typography-focused design
 * - Clean geometric elements
 */

function get_modern_pdf_css(): string
{
    return '
/* ═══════════════════════════════════════════════════════════════════════════
   MODERN PDF STYLING SYSTEM
   ═══════════════════════════════════════════════════════════════════════════ */

@page {
    size: A4;
    margin: 0;
    padding: 0;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 0;
    font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
    font-size: 11px;
    line-height: 1.6;
    color: #2d2d2d;
    background: #faf9f7;
}

/* ─────────────────────────────────────────────────────────────────────────────
   PAGE CONTAINER
   ───────────────────────────────────────────────────────────────────────────── */

.pdf-page {
    width: 100%;
    min-height: 297mm;
    background: #ffffff;
    position: relative;
}

/* ─────────────────────────────────────────────────────────────────────────────
   COLOR PALETTE
   ───────────────────────────────────────────────────────────────────────────── */

:root {
    --color-primary: #c44536;      /* Terracotta */
    --color-secondary: #2d3436;    /* Charcoal */
    --color-accent: #e8b4a8;       /* Soft coral */
    --color-cream: #faf9f7;        /* Cream background */
    --color-sand: #f5f0eb;         /* Sand */
    --color-border: #e8e4df;       /* Light border */
    --color-text: #2d2d2d;         /* Dark text */
    --color-muted: #6b6b6b;        /* Muted text */
    --color-white: #ffffff;
}

/* ─────────────────────────────────────────────────────────────────────────────
   TYPOGRAPHY
   ───────────────────────────────────────────────────────────────────────────── */

.font-display {
    font-family: "Georgia", "Times New Roman", serif;
    font-weight: 400;
    letter-spacing: -0.02em;
}

.font-sans {
    font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
}

.text-h1 {
    font-size: 32px;
    font-weight: 300;
    letter-spacing: -0.03em;
    color: var(--color-secondary);
    margin: 0 0 8px 0;
}

.text-h2 {
    font-size: 18px;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--color-primary);
    margin: 0 0 16px 0;
}

.text-h3 {
    font-size: 14px;
    font-weight: 600;
    color: var(--color-secondary);
    margin: 0 0 8px 0;
}

.text-label {
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: var(--color-muted);
    margin: 0 0 4px 0;
}

.text-body {
    font-size: 11px;
    line-height: 1.7;
    color: var(--color-text);
}

.text-small {
    font-size: 10px;
    color: var(--color-muted);
}

/* ─────────────────────────────────────────────────────────────────────────────
   LAYOUT GRID
   ───────────────────────────────────────────────────────────────────────────── */

.grid-container {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 24px;
    padding: 40px;
}

.col-3 { grid-column: span 3; }
.col-4 { grid-column: span 4; }
.col-5 { grid-column: span 5; }
.col-6 { grid-column: span 6; }
.col-7 { grid-column: span 7; }
.col-8 { grid-column: span 8; }
.col-9 { grid-column: span 9; }
.col-12 { grid-column: span 12; }

/* ─────────────────────────────────────────────────────────────────────────────
   HEADER SECTION
   ───────────────────────────────────────────────────────────────────────────── */

.doc-header {
    padding: 48px 40px 32px;
    border-bottom: 3px solid var(--color-primary);
    background: linear-gradient(180deg, var(--color-cream) 0%, var(--color-white) 100%);
}

.header-grid {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 32px;
    align-items: start;
}

.company-branding {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.company-name {
    font-family: "Georgia", serif;
    font-size: 24px;
    font-weight: 400;
    letter-spacing: -0.02em;
    color: var(--color-secondary);
    margin: 0;
}

.company-tagline {
    font-size: 10px;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--color-primary);
    margin: 0;
}

.doc-badge {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
}

.doc-type {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--color-muted);
}

.doc-number {
    font-family: "Georgia", serif;
    font-size: 36px;
    font-weight: 400;
    color: var(--color-primary);
    line-height: 1;
}

/* ─────────────────────────────────────────────────────────────────────────────
   INFO CARDS
   ───────────────────────────────────────────────────────────────────────────── */

.info-card {
    background: var(--color-sand);
    border: 1px solid var(--color-border);
    border-radius: 4px;
    padding: 20px;
    position: relative;
}

.info-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--color-primary);
    border-radius: 4px 0 0 4px;
}

.info-card h6 {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: var(--color-primary);
    margin: 0 0 12px 0;
}

.info-card p {
    font-size: 10px;
    line-height: 1.6;
    color: var(--color-text);
    margin: 0 0 6px 0;
}

.info-card p:last-child {
    margin: 0;
}

.info-card strong {
    font-weight: 600;
    color: var(--color-secondary);
}

/* ─────────────────────────────────────────────────────────────────────────────
   DATA TABLES
   ───────────────────────────────────────────────────────────────────────────── */

.modern-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.modern-table thead {
    background: var(--color-secondary);
}

.modern-table th {
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--color-white);
    padding: 12px 16px;
    text-align: left;
    border: none;
}

.modern-table td {
    font-size: 10px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--color-border);
    color: var(--color-text);
    vertical-align: top;
}

.modern-table tbody tr:nth-child(even) {
    background: var(--color-sand);
}

.modern-table tbody tr:hover {
    background: var(--color-accent);
}

.modern-table .text-right {
    text-align: right;
}

.modern-table .text-center {
    text-align: center;
}

/* ─────────────────────────────────────────────────────────────────────────────
   TOTALS SECTION
   ───────────────────────────────────────────────────────────────────────────── */

.totals-section {
    display: flex;
    justify-content: flex-end;
    margin-top: 24px;
}

.totals-card {
    width: 280px;
    background: var(--color-secondary);
    color: var(--color-white);
    border-radius: 4px;
    padding: 20px;
}

.totals-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.totals-row:last-child {
    border-bottom: none;
    padding-top: 12px;
    margin-top: 4px;
    border-top: 2px solid var(--color-primary);
}

.totals-label {
    font-size: 10px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.7);
}

.totals-value {
    font-family: "Georgia", serif;
    font-size: 16px;
    font-weight: 400;
}

.totals-row:last-child .totals-label {
    font-weight: 600;
    color: var(--color-white);
}

.totals-row:last-child .totals-value {
    font-size: 20px;
    font-weight: 400;
    color: var(--color-accent);
}

/* ─────────────────────────────────────────────────────────────────────────────
   SECTIONS
   ───────────────────────────────────────────────────────────────────────────── */

.section {
    padding: 32px 40px;
    border-bottom: 1px solid var(--color-border);
}

.section:last-child {
    border-bottom: none;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.section-header::before {
    content: "";
    width: 32px;
    height: 2px;
    background: var(--color-primary);
}

.section-title {
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--color-secondary);
    margin: 0;
}

/* ─────────────────────────────────────────────────────────────────────────────
   SIGNATURE BLOCK
   ───────────────────────────────────────────────────────────────────────────── */

.signature-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 32px;
    margin-top: 24px;
}

.signature-box {
    border: 1px solid var(--color-border);
    border-radius: 4px;
    padding: 24px;
    background: var(--color-sand);
}

.signature-box h6 {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: var(--color-primary);
    margin: 0 0 16px 0;
}

.signature-line {
    border-bottom: 1px solid var(--color-muted);
    margin: 24px 0 8px 0;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.signature-line img {
    max-height: 40px;
}

.signature-name {
    font-size: 11px;
    font-weight: 600;
    color: var(--color-secondary);
    margin: 0 0 2px 0;
}

.signature-title {
    font-size: 9px;
    color: var(--color-muted);
    margin: 0;
}

/* ─────────────────────────────────────────────────────────────────────────────
   FOOTER
   ───────────────────────────────────────────────────────────────────────────── */

.doc-footer {
    padding: 24px 40px;
    background: var(--color-secondary);
    color: var(--color-white);
    text-align: center;
}

.footer-content {
    font-size: 9px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.6);
}

.footer-content span {
    color: var(--color-accent);
}

/* ─────────────────────────────────────────────────────────────────────────────
   UTILITY CLASSES
   ───────────────────────────────────────────────────────────────────────────── */

.mt-1 { margin-top: 8px; }
.mt-2 { margin-top: 16px; }
.mt-3 { margin-top: 24px; }
.mt-4 { margin-top: 32px; }
.mt-5 { margin-top: 40px; }

.mb-1 { margin-bottom: 8px; }
.mb-2 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 24px; }
.mb-4 { margin-bottom: 32px; }

.text-primary { color: var(--color-primary); }
.text-secondary { color: var(--color-secondary); }
.text-muted { color: var(--color-muted); }

.font-serif { font-family: "Georgia", serif; }
.font-weight-light { font-weight: 300; }
.font-weight-normal { font-weight: 400; }
.font-weight-semibold { font-weight: 600; }

.letter-spacing-wide { letter-spacing: 0.1em; }
.letter-spacing-wider { letter-spacing: 0.15em; }
.letter-spacing-widest { letter-spacing: 0.2em; }

.uppercase { text-transform: uppercase; }

.border-top { border-top: 1px solid var(--color-border); }
.border-bottom { border-bottom: 1px solid var(--color-border); }

.bg-sand { background: var(--color-sand); }
.bg-cream { background: var(--color-cream); }

/* ─────────────────────────────────────────────────────────────────────────────
   STATUS BADGES
   ───────────────────────────────────────────────────────────────────────────── */

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    border-radius: 2px;
}

.status-badge.paid {
    background: #27ae60;
    color: white;
}

.status-badge.pending {
    background: #f39c12;
    color: white;
}

.status-badge.cancelled {
    background: #c0392b;
    color: white;
}

.status-badge.draft {
    background: #95a5a6;
    color: white;
}
';
}

/**
 * Get modern PDF header HTML
 */
function get_modern_pdf_header(array $data): string
{
    $docType = $data['doc_type'] ?? 'DOCUMENT';
    $docNumber = $data['doc_number'] ?? '';
    $companyName = $data['company_name'] ?? 'EVERSTONE TECHNOLOGY SYSTEMS INC.';
    
    return '
    <header class="doc-header">
        <div class="header-grid">
            <div class="company-branding">
                <h1 class="company-name">' . htmlspecialchars($companyName) . '</h1>
                <p class="company-tagline">Technology Systems & Procurement</p>
            </div>
            <div class="doc-badge">
                <span class="doc-type">' . htmlspecialchars($docType) . '</span>
                <span class="doc-number">' . htmlspecialchars($docNumber) . '</span>
            </div>
        </div>
    </header>
    ';
}

/**
 * Get modern PDF info card HTML
 */
function get_modern_info_card(string $title, array $info): string
{
    $html = '<div class="info-card">';
    $html .= '<h6>' . htmlspecialchars($title) . '</h6>';
    
    foreach ($info as $label => $value) {
        if (!empty($value)) {
            $html .= '<p><strong>' . htmlspecialchars($label) . ':</strong> ' . htmlspecialchars($value) . '</p>';
        }
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Get modern PDF table HTML
 */
function get_modern_table(array $headers, array $rows, array $footers = []): string
{
    $html = '<table class="modern-table">';
    
    // Headers
    $html .= '<thead><tr>';
    foreach ($headers as $header) {
        $align = $header['align'] ?? 'left';
        $html .= '<th class="text-' . $align . '">' . htmlspecialchars($header['text']) . '</th>';
    }
    $html .= '</tr></thead>';
    
    // Rows
    $html .= '<tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $align = $cell['align'] ?? 'left';
            $html .= '<td class="text-' . $align . '">' . ($cell['html'] ?? htmlspecialchars($cell['text'] ?? '')) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody>';
    
    // Footers (totals)
    if (!empty($footers)) {
        $html .= '<tfoot>';
        foreach ($footers as $footer) {
            $html .= '<tr style="background: var(--color-secondary);">';
            foreach ($footer as $cell) {
                $align = $cell['align'] ?? 'left';
                $color = $cell['color'] ?? 'white';
                $html .= '<td class="text-' . $align . '" style="color: ' . $color . '; font-weight: 600;">' . ($cell['html'] ?? htmlspecialchars($cell['text'] ?? '')) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tfoot>';
    }
    
    $html .= '</table>';
    return $html;
}
