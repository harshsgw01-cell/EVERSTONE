<?php
session_start();
include("../config/database.php");
include("../includes/auth.php");
check_auth();
require_role(['Admin', 'Sales', 'Account']);

if (!isset($_GET['id'])) {
    header("Location: invoices.php");
    exit;
}

$id = (int) $_GET['id'];

$id = (int) $_GET['id'];
 
// ✅ FIXED: was  JOIN customers c ON o.customer_id = c.id
//           now  JOIN customers c ON i.customer_id = c.id
//           so the invoice's own customer is always shown,
//           not the order's customer (which may differ).
$invoice_q = mysqli_query($conn, "
    SELECT i.*,
           o.code AS order_code,
           o.created_at AS order_date,
           c.name AS customer,
           c.email,
           c.phone,
           c.address
    FROM invoices i
    JOIN orders o ON i.order_id = o.id
    JOIN customers c ON i.customer_id = c.id
    WHERE i.id = $id
");
$invoice = mysqli_fetch_assoc($invoice_q);
 
if (!$invoice) {
    include("../templates/header.php");
    echo "<div class='container mt-5'><div class='alert alert-danger'>Invoice not found.</div></div>";
    include("../templates/footer.php");
    exit;
}
 
$order_lines_result = mysqli_query($conn,
    "SELECT * FROM order_lines WHERE order_id=" . (int)$invoice['order_id']
);
 
$ol      = mysqli_query($conn,
    "SELECT SUM(qty * unit_price) AS total_amount FROM order_lines WHERE order_id=" . (int)$invoice['order_id']
);
$tot      = mysqli_fetch_assoc($ol);
$ol_total = floatval($tot['total_amount'] ?? 0);

$body_class = 'invoice-view-page';
include("../templates/header.php");
?>
<!DOCTYPE html>

<div class="inv-doc" id="invoiceArea">

  <!-- ════════════════════════════════════
       PAGE 1 — INVOICE
  ════════════════════════════════════ -->

  <!-- TOP BAR -->
  <div class="p1-top">
    <div class="p1-top-l">
      <img src="../assets/Everstone.png" alt="EVERSTONE TECHNOLOGY SYSTEMS INC.">
      <div class="top-co-name">EVERSTONE TECHNOLOGY SYSTEMS INC. </div>
      <div class="top-co-addr">13455 94a Ave #104 Surrey, BC V3V 1M9 Canada</div>
    </div>
    <div class="p1-top-r">
      <div class="top-inv-label">Invoice</div>
      <div class="top-inv-number"><?= htmlspecialchars($invoice['code']) ?></div>
      <span class="top-inv-status"><?= htmlspecialchars($invoice['status'] ?? 'Draft') ?></span>
    </div>
  </div>

  <!-- META STRIP -->
  <div class="p1-meta">
    <div class="p1-meta-cell">
      <div class="mc-lbl">Order Number</div>
      <div class="mc-val accent"><?= htmlspecialchars($invoice['order_code']) ?></div>
    </div>
    <div class="p1-meta-cell">
      <div class="mc-lbl">Invoice Date</div>
      <div class="mc-val"><?= date("M j, Y", strtotime($invoice['created_at'])) ?></div>
    </div>
    <div class="p1-meta-cell">
      <div class="mc-lbl">Due Date</div>
      <div class="mc-val">
        <?= !empty($invoice['due_date']) ? date("M j, Y", strtotime($invoice['due_date'])) : '—' ?>
      </div>
    </div>
    <div class="p1-meta-cell">
      <div class="mc-lbl">Payment Status</div>
      <div class="mc-val <?= !empty($invoice['paid_date']) ? 'paid' : 'pending' ?>">
        <?= !empty($invoice['paid_date']) ? '✓ Paid ' . date("M j, Y", strtotime($invoice['paid_date'])) : 'Pending' ?>
      </div>
    </div>
  </div>

  <!-- ADDRESSES -->
  <div class="p1-addr">
    <div class="addr-col">
      <span class="addr-badge">Bill To</span>
      <div class="addr-name"><?= htmlspecialchars($invoice['customer']) ?></div>
      <div class="addr-line"><i class="bi bi-envelope"></i><?= htmlspecialchars($invoice['email']) ?></div>
      <div class="addr-line"><i class="bi bi-telephone"></i><?= htmlspecialchars($invoice['phone']) ?></div>
      <div class="addr-line"><i class="bi bi-geo-alt"></i><?= nl2br(htmlspecialchars($invoice['address'] ?? '')) ?></div>
    </div>
    <div class="addr-col right">
      <span class="addr-badge ship">Ship To</span>
      <div class="addr-name">Pascaru, Ms. Anamaria</div>
      <div class="addr-line">Brussels, BE (NCIA HQ – Ship-To)</div>
      <div class="addr-line">New NATO HQ – Industrial Infrastructure Building</div>
      <div class="addr-line">Reception Service, Rue Arthur Maes 1</div>
      <div class="addr-line">1130 Brussels, Belgium</div>
    </div>
  </div>

  <!-- LINE ITEMS -->
  <div class="p1-items">
    <table class="items-tbl">
      <thead>
        <tr>
          <th style="width:42px;">#</th>
          <th>Product / Description</th>
          <th class="tc" style="width:75px;">Qty</th>
          <th class="tr" style="width:130px;">Unit Price</th>
          <th class="tr" style="width:130px;">Sub Total</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $i = 1;
        while ($line = mysqli_fetch_assoc($order_lines_result)):
          $sub = floatval($line['qty']) * floatval($line['unit_price']);
        ?>
        <tr>
          <td style="color:var(--sub); font-size:12px;"><?= $i++ ?></td>
          <td>
            <div class="prod-name"><?= htmlspecialchars($line['product']) ?></div>
          </td>
          <td class="tc"><span class="qty-badge"><?= (int)$line['qty'] ?></span></td>
          <td class="tr">$ <?= number_format($line['unit_price'], 2) ?></td>
          <td class="tr" style="font-weight:600;">$ <?= number_format($sub, 2) ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- TOTALS -->
  <div class="p1-totals">
    <div class="p1-totals-l">
      <div class="payment-note">
        <div class="pn-title">Payment Information</div>
        <div class="pn-row"><strong>Terms:</strong> Net 30 Days</div>
        <div class="pn-row"><strong>Remit To:</strong> EVERSTONE TECHNOLOGY SYSTEMS INC. </div>
        <div class="pn-row"><strong>Email:</strong> sales@everstonetech.ca</div>
        <div class="pn-row"><strong>Phone:</strong> 236-953-7860</div>
      </div>
    </div>
    <div class="p1-totals-r">
      <div class="tot-box" style="min-height: 132px;">
        <div class="tot-row">
          <span class="tot-l">Subtotal</span>
          <span class="tot-r">€ <?= number_format($ol_total, 2) ?></span>
        </div>
        <div class="tot-row">
          <span class="tot-l">Tax (0%)</span>
          <span class="tot-r">€ 0.00</span>
        </div>
        <div class="tot-row grand">
          <span class="tot-l">Total Amount Due</span>
          <span class="tot-r">€ <?= number_format($ol_total, 2) ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- PAGE 1 FOOTER -->
  <div class="p1-foot">
    <div class="p1-foot-l">
      <span class="hi">EVERSTONE TECHNOLOGY SYSTEMS INC. </span>
      <span class="sep">·</span>
      <span class="light">13455 94a Ave #104 Surrey, BC V3V 1M9 Canada</span>
      <span class="sep">·</span>
      <span class="hi">sales@everstonetech.ca</span>
      <span class="sep">·</span>
      <span class="light">236-953-7860</span>
    </div>
    <div class="p1-foot-r">
      <span class="page-num">Page 1 of 2</span>
    </div>
  </div>

  <!-- ════════════════════════════════════
       PAGE 2 — COMPANY PROFILE
  ════════════════════════════════════ -->
  <div class="p2 pg-break">

    <!-- Full-width banner -->
    <img src="../assets/pdf-head-img.jpg" alt="Everstone Banner" class="p2-banner">

    <!-- Company Header -->
    <div class="p2-hdr">
      <div class="p2-hdr-l">
        <img src="../assets/Everstone.png" alt="Everstone">
        <div class="p2-co-name">EVERSTONE TECHNOLOGY SYSTEMS INC. </div>
        <div class="p2-co-tag">Technology Systems &amp; Procurement Solutions &nbsp;·&nbsp; Government &amp; Corporate Clients</div>
      </div>
    </div>

    <!-- Authorized By + Business POC -->
    <div class="p2-poc">
      <div class="p2-poc-l">
        <div class="auth-name">Emma William</div>
        <div class="auth-sub">Signed and Authorized By</div>
        <div class="auth-signid">Sign ID &nbsp;#&nbsp; 847093072321</div>
      </div>
      <div class="p2-poc-r">
        <div class="poc-card">
          <div class="poc-img-td">
            <img src="../assets/emma-wiliam.png" alt="Emma William">
          </div>
          <div class="poc-info-td">
            <div class="poc-title">Emma William</div>
            <div class="poc-role">Procurement Manager</div>
            <div class="poc-l"><i class="bi bi-envelope"></i> sales@everstonetech.ca</div>
            <div class="poc-l"><i class="bi bi-telephone"></i> 236-953-7860</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Contact Icons -->
    <div class="p2-contacts">
      <div class="p2-ct">
        <div class="ct-icon-wrap"><i class="bi bi-telephone-fill"></i></div>
        <div class="ct-label">Phone</div>
        <div class="ct-value">236-953-7860</div>
      </div>
      <div class="p2-ct">
        <div class="ct-icon-wrap"><i class="bi bi-envelope-fill"></i></div>
        <div class="ct-label">Email</div>
        <div class="ct-value">sales@everstonetech.ca</div>
      </div>
      <div class="p2-ct">
        <div class="ct-icon-wrap"><i class="bi bi-globe2"></i></div>
        <div class="ct-label">Website</div>
        <div class="ct-value">everstonetech.ca</div>
      </div>
      <div class="p2-ct" style="width:40%;">
        <div class="ct-icon-wrap"><i class="bi bi-geo-alt-fill"></i></div>
        <div class="ct-label">Address</div>
        <div class="ct-value">13455 94a Ave #104 Surrey, BC V3V 1M9 Canada</div>
      </div>
    </div>

    <!-- Registration Numbers -->
    <div class="p2-reg">
      <div class="reg-cell"><span class="reg-lbl">UEI</span><span class="reg-val">KQM1HHYHLHR5</span></div>
      <div class="reg-cell"><span class="reg-lbl">NCAGE</span><span class="reg-val">9EAX7</span></div>
      <div class="reg-cell"><span class="reg-lbl">DUNS</span><span class="reg-val">12-346-8707</span></div>
      <div class="reg-cell"><span class="reg-lbl">JCCS</span><span class="reg-val">133245</span></div>
      <div class="reg-cell"><span class="reg-lbl">CA License #</span><span class="reg-val">202253016523</span></div>
      <div class="reg-cell"><span class="reg-lbl">EIN</span><span class="reg-val">92-2840560</span></div>
    </div>

    <!-- Introduction -->
    <div class="p2-intro">
      <div class="intro-head">
        <div class="ih-l"><h2>Introduction</h2></div>
        <div class="ih-r"><img src="../assets/Everstone.png" alt="Everstone"></div>
      </div>
      <p class="intro-lead">
        Everstone provides technology systems, procurement, and operational support across a broad range of industries.
      </p>
      <p class="intro-body">
        Everstone brings practical experience in delivering reliable technology, procurement, and logistical
        support. The company aims to provide tailored solutions to enhance operational efficiency and
        adaptability for its clients. Dedicated to quality and precision, Everstone meets the needs of
        government and corporate clients through innovative and reliable solutions.
      </p>
    </div>

    <!-- Page 2 Footer -->
    <div class="p2-foot">
      <div class="p2-foot-l">
        <span class="hi">EVERSTONE TECHNOLOGY SYSTEMS INC. </span>
        <span class="sep">·</span>
        <span class="light">13455 94a Ave #104 Surrey, BC V3V 1M9 Canada</span>
        <span class="sep">·</span>
        <span class="hi">sales@everstonetech.ca</span>
        <span class="sep">·</span>
        <span class="light">everstonetech.ca</span>
      </div>
      <div class="p2-foot-r">
        <span class="pg">Page 2 of 2</span>
      </div>
    </div>

  </div>
  <!-- ACTION BAR -->
  <div class="action-bar no-print">
    <div class="action-bar-inner">
      <button onclick="history.go(-1)" class="btn btn-outline-secondary btn-sm me-2">
        <i class="bi bi-arrow-left me-1"></i> Back
      </button>
      <button onclick="window.print()" class="btn btn-sm" style="background:var(--navy); color:#fff; padding: 6px 18px; border:none; border-radius:3px; font-size:13px; cursor:pointer;">
        <i class="bi bi-printer-fill me-1"></i> Print / Save as PDF
      </button>
    </div>
  </div>

</div>


<?php include("../templates/footer.php"); ?>
