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
        // Selected lines
        $ids = json_decode($_POST['ids'], true);
        if (!is_array($ids) || !count($ids)) die("Invalid request.");
        $ids = array_map('intval', $ids);
        $where = "ol.id IN (" . implode(',', $ids) . ")";
    }
    elseif (!empty($_POST['order_id'])) {
        // Full order
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
            st.name AS shipto_name,
            st.address AS shipto_address,
            st.email AS shipto_email,
            st.phone AS shipto_phone,
            st.company AS shipto_company
        FROM order_lines ol
        LEFT JOIN orders o ON ol.order_id = o.id
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
            .order-info, .addresses-table td { border: 1px solid #d1d5db; padding: 10px; line-height: 1.5; }
            .addresses-table { margin: 14px 0 18px; }
            .items th { background: #1d4ed8; color: #fff; padding: 9px; text-align: left; }
            .items td { border: 1px solid #d1d5db; padding: 8px; vertical-align: top; }
            .items tbody tr:nth-child(even) td { background: #f8fafc; }
            .auth-section { margin-top: 28px; line-height: 1.5; }
            .auth-section img { width: 120px; height: auto; margin-top: 8px; }
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

        <div class="title">Packaging Slip</div>
        <div class="order-info">
            <strong>Purchase Order Number:</strong> #<?= htmlspecialchars($order['order_number']) ?><br>
            <strong>Date:</strong> <?= htmlspecialchars($order['quote_date']) ?>
        </div>

        <table class="addresses-table">
            <tr>
                <td width="50%">
                    <strong>Ship To:</strong><br>
                    <?= htmlspecialchars($order['shipto_company']) ?><br>
                    Name: <?= htmlspecialchars($order['shipto_name']) ?><br>
                    Email: <?= htmlspecialchars($order['shipto_email']) ?><br>
                    Phone: <?= htmlspecialchars($order['shipto_phone']) ?><br>
                    Address: <?= htmlspecialchars($order['shipto_address']) ?>
                </td>
                <td width="50%">
                    <strong>Ship By:</strong><br>
                    EVERSTONE TECHNOLOGY SYSTEMS INC.<br>
                    Email: sales@everstonetech.ca<br>
                    Phone: +1 619 918 5013
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $i => $line): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($line['description']) ?></td>
                        <td><?= htmlspecialchars($line['qty']) ?></td>
                        <td><?= htmlspecialchars($line['unit']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="auth-section">
            <strong>Authorized By: Sajid Ahmadzai</strong><br>
            CEO & Contract Manager<br>
            <img src="https://everstonetech.ca/assets/ceo-signature.png">
        </div>

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
    $dompdf->stream("Packaging-Slip.pdf", ["Attachment" => true]);
    exit;
}

die("Invalid request.");
