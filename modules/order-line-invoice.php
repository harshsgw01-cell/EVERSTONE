<?php
require '../vendor/autoload.php';
include("../includes/auth.php");
include("../config/database.php");
check_auth();
require_role(['Admin']);

use Dompdf\Dompdf;
use Dompdf\Options;

/* ---------- ACCEPT BOTH FULL ORDER & SELECTED LINES ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!empty($_POST['ids'])) {
        $ids = json_decode($_POST['ids'], true);
        if (!is_array($ids) || !count($ids)) die("Invalid request.");
        $ids = array_map('intval', $ids);
        $where = "ol.id IN (" . implode(',', $ids) . ")";
    }
    elseif (!empty($_POST['order_id'])) {
        $order_id = (int)$_POST['order_id'];
        $where = "ol.order_id = $order_id";
    }
    else {
        die("Invalid request.");
    }

    $query = "
        SELECT 
            ol.*, 
            o.order_number,
            o.quote_date,
            o.lead_time,
            c.name AS customer_name,
            c.address AS customer_address,
            bt.title AS billto_title,
            bt.address AS billto_address,
            st.name AS shipto_name,
            st.company AS shipto_company,
            st.address AS shipto_address
        FROM order_lines ol
        LEFT JOIN orders o ON ol.order_id = o.id
        LEFT JOIN customers c ON o.customer_id = c.id
        LEFT JOIN billto bt ON o.billTo_id = bt.id
        LEFT JOIN shipto st ON o.shipTo_id = st.id
        WHERE $where
        ORDER BY ol.id
    ";

    $result = mysqli_query($conn, $query);
    if (!$result || mysqli_num_rows($result) == 0) {
        die("No lines found.");
    }

    $lines = [];
    $order = null;
    while ($row = mysqli_fetch_assoc($result)) {
        $lines[] = $row;
        $order = $row;
    }

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);

    $subtotal = 0;
    foreach ($lines as $l) {
        $subtotal += ($l['qty'] * $l['unit_price']);
    }

    ob_start();
    ?>
    <html>
    <head>
        <style>
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #1f2937; }
            table { width: 100%; border-collapse: collapse; }
            .header-table { margin-bottom: 18px; border-bottom: 2px solid #1d4ed8; }
            .header-left { width: 35%; padding-bottom: 12px; }
            .header-left img { width: 120px; height: auto; }
            .header-right { text-align: right; line-height: 1.5; padding-bottom: 12px; }
            .title { margin: 18px 0; font-size: 24px; font-weight: 700; color: #1d4ed8; text-align: center; text-transform: uppercase; }
            .addresses-table { margin: 14px 0 18px; }
            .addresses-table td { border: 1px solid #d1d5db; padding: 10px; line-height: 1.5; vertical-align: top; }
            .items th { background: #1d4ed8; color: #fff; padding: 9px; text-align: left; }
            .items td { border: 1px solid #d1d5db; padding: 8px; vertical-align: top; }
            .items tbody tr:nth-child(even) td { background: #f8fafc; }
            .totals { width: 42%; margin-top: 18px; margin-left: auto; }
            .totals td { border: 1px solid #d1d5db; padding: 8px; }
            .totals .label { text-align: right; font-weight: 700; background: #f8fafc; }
            .footer { margin-top: 28px; padding-top: 10px; border-top: 1px solid #d1d5db; font-size: 10px; color: #6b7280; text-align: center; }
        </style>
    </head>

    <body>
        <table class="header-table">
            <tr>
                <td class="header-left">
                    <img src="https://everstonetech.ca/assets/Everstone.png">
                </td>
                <td class="header-right">
                    <strong>EVERSTONE TECHNOLOGY SYSTEMS INC.</strong><br>
                    sales@everstonetech.ca<br>
                    +1 619 918 5013<br>
                    everstonetech.ca
                </td>
            </tr>
        </table>

        <div class="title">Invoice</div>

        <table class="addresses-table">
            <tr>
                <td width="50%">
                    <strong>Bill To:</strong><br>
                    <?= htmlspecialchars($order['billto_title'] ?? '') ?><br>
                    <?= nl2br(htmlspecialchars($order['billto_address'] ?? '')) ?>
                </td>
                <td width="50%">
                    <strong>Ship To:</strong><br>
                    <?= htmlspecialchars($order['shipto_company']) ?><br>
                    <?= htmlspecialchars($order['shipto_name']) ?><br>
                    <?= nl2br(htmlspecialchars($order['shipto_address'] ?? '')) ?>
                </td>
            </tr>
        </table>

        <p>
            <strong>Invoice #:</strong> <?= htmlspecialchars($order['order_number']) ?><br>
            <strong>Date:</strong> <?= htmlspecialchars($order['quote_date']) ?><br>
            <strong>Lead Time:</strong> <?= htmlspecialchars($order['lead_time']) ?> days
        </p>

        <table class="items">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $i => $line): 
                    $amount = $line['qty'] * $line['unit_price'];
                ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($line['description']) ?></td>
                        <td><?= htmlspecialchars($line['qty']) ?></td>
                        <td>$<?= number_format($line['unit_price'], 2) ?></td>
                        <td>$<?= number_format($amount, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td class="label" width="80%">Subtotal:</td>
                <td>$<?= number_format($subtotal, 2) ?></td>
            </tr>
            <tr>
                <td class="label">Tax:</td>
                <td>$0.00</td>
            </tr>
            <tr>
                <td class="label">Total:</td>
                <td><strong>$<?= number_format($subtotal, 2) ?></strong></td>
            </tr>
        </table>

        <div class="footer">
            This document was generated by <a href="https://everstonetech.ca/">Everstone</a>
        </div>
    </body>
    </html>
    <?php

    $html = ob_get_clean();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream("Invoice.pdf", ["Attachment" => true]);
    exit;
}

die("Invalid request.");
