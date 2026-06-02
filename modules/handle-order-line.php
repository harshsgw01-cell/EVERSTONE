<?php
include("../includes/auth.php");
include("../config/database.php");
check_auth();
require_role(['Admin']);

if (!isset($_GET['ol_id'])) { header("Location: orders.php"); exit; }

$id       = (int)$_GET['ol_id'];
$i        = (int)($_GET['i'] ?? 0);
$lineInfo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM order_lines WHERE id=$id"));
if (!$lineInfo) { die("Order line not found"); }
$order_id = (int)$lineInfo['order_id'];

/* ---------- STATUS UPDATE (drag-and-drop POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ol_id'], $_POST['status']) && count($_POST) === 2) {
    header('Content-Type: application/json');
    $ol_id   = (int)$_POST['ol_id'];
    $status  = mysqli_real_escape_string($conn, $_POST['status']);
    $allowed = ['Open','In Progress','In Review','To be Tested','Delayed','Cancelled','Closed','On Hold','Completed'];
    if (!in_array($status, $allowed)) { http_response_code(400); echo json_encode(["error"=>"Invalid status"]); exit; }
    mysqli_query($conn, "UPDATE order_lines SET status='$status' WHERE id=$ol_id AND order_id=$order_id");
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS pending FROM order_lines WHERE order_id=$order_id AND status!='Completed'"));
    mysqli_query($conn, "UPDATE orders SET status='" . ($row['pending']==0 ? 'Completed' : 'Pending') . "' WHERE id=$order_id");
    echo json_encode(["success"=>true, "ol_id"=>$ol_id, "status"=>$status]);
    exit;
}

$lines    = mysqli_query($conn, "SELECT * FROM order_lines WHERE order_id=$order_id ORDER BY id ASC");
$statuses = ['Open','In Progress','In Review','To be Tested','Delayed','Cancelled','Closed','On Hold','Completed'];

/* ---------- PROFIT CALC ---------- */
$qty           = (float)($lineInfo['qty'] ?? 0);
$unit_price    = (float)($lineInfo['unit_price'] ?? 0);
$cost_price    = (float)($lineInfo['cost_price'] ?? 0);
$shipping_cost = (float)($lineInfo['shipping_cost'] ?? 0);
$delivered     = (float)($lineInfo['delivered'] ?? 0);
$revenue       = $qty * $unit_price;
$total_cost    = ($qty * $cost_price) + $shipping_cost;
$profit        = $revenue - $total_cost;
$remaining     = max(0, $qty - $delivered);
$delivery_pct  = $qty > 0 ? round(($delivered / $qty) * 100) : 0;

$statusColors = [
    'Open'         => '#3b82f6',
    'In Progress'  => '#f59e0b',
    'In Review'    => '#8b5cf6',
    'To be Tested' => '#06b6d4',
    'Delayed'      => '#f97316',
    'Cancelled'    => '#ef4444',
    'Closed'       => '#6b7280',
    'On Hold'      => '#64748b',
    'Completed'    => '#10b981',
];

$currentStatus = $lineInfo['status'] ?? 'Open';
$currentColor  = $statusColors[$currentStatus] ?? '#6b7280';

include("../templates/header.php");
include("../templates/navbar.php");
?>
<div class="page-wrapper">

    <!-- ══════════ PAGE HEADER ══════════ -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-list-check"></i>
                    <?= htmlspecialchars($lineInfo['product'] ?? $lineInfo['description'] ?? 'Order Line') ?>
                </div>
                <div class="page-header-sub">
                    <?php if (!empty($lineInfo['part_number'] ?? $lineInfo['part'])): ?>
                        Part: <?= htmlspecialchars($lineInfo['part_number'] ?? $lineInfo['part']) ?>
                        &nbsp;&bull;&nbsp;
                    <?php endif; ?>
                    <?php if (!empty($lineInfo['manufacturer'])): ?>
                        <?= htmlspecialchars($lineInfo['manufacturer']) ?> &nbsp;&bull;&nbsp;
                    <?php endif; ?>
                    Order #<?= $order_id ?>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="line-status-badge"
                    style="background:<?= $currentColor ?>22;color:<?= $currentColor ?>;border-color:<?= $currentColor ?>44;">
                    <i class="bi bi-circle-fill" style="font-size:.45rem;"></i>
                    <?= htmlspecialchars($currentStatus) ?>
                </span>
                <div class="line-num-badge">
                    <i class="bi bi-hash"></i> Line <?= $i ?>
                </div>
                <button type="button" class="back-btn" onclick="history.go(-1);">
                    <i class="bi bi-arrow-left"></i>Back
                </button>
            </div>
        </div>
    </div>

    <!-- ══════════ STAT CARDS ══════════ -->
    <div class="stat-strip">
        <div class="stat-card">
            <div class="stat-val stat-blue"><?= number_format($qty, 0) ?></div>
            <div class="stat-lbl">Ordered</div>
        </div>
        <div class="stat-card">
            <div class="stat-val stat-green"><?= number_format($delivered, 0) ?></div>
            <div class="stat-lbl">Delivered</div>
        </div>
        <div class="stat-card">
            <div class="stat-val <?= $remaining > 0 ? 'stat-red' : 'stat-green' ?>">
                <?= number_format($remaining, 0) ?>
            </div>
            <div class="stat-lbl">Remaining</div>
        </div>
        <div class="stat-card">
            <div class="stat-val stat-dark">$<?= number_format($revenue, 2) ?></div>
            <div class="stat-lbl">Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-val stat-muted">$<?= number_format($total_cost, 2) ?></div>
            <div class="stat-lbl">Total Cost</div>
        </div>
        <div class="stat-card">
            <div class="stat-val <?= $profit >= 0 ? 'profit-pos' : 'profit-neg' ?>">
                <?= $profit >= 0 ? '+' : '' ?>$<?= number_format($profit, 2) ?>
            </div>
            <div class="stat-lbl">Profit / Loss</div>
        </div>
    </div>

    <!-- ══════════ DELIVERY PROGRESS ══════════ -->
    <div class="delivery-card">
        <div class="d-flex justify-content-between align-items-center">
            <span class="delivery-label">Delivery Progress</span>
            <span style="font-size:.78rem;color:#64748b;font-weight:600;">
                <?= $delivery_pct ?>% &mdash; <?= number_format($delivered,0) ?> of <?= number_format($qty,0) ?> delivered
            </span>
        </div>
        <div class="delivery-bar">
            <div class="delivery-fill" style="width:<?= $delivery_pct ?>%"></div>
        </div>
        <?php if (!empty($lineInfo['tracking_number'])): ?>
        <div class="mt-2">
            <?php if (!empty($lineInfo['tracking_url'])): ?>
                <a href="<?= htmlspecialchars($lineInfo['tracking_url']) ?>" target="_blank" class="tracking-pill">
                    <i class="bi bi-truck-front-fill"></i>
                    Track: <?= htmlspecialchars($lineInfo['tracking_number']) ?>
                    <i class="bi bi-box-arrow-up-right" style="font-size:.65rem;"></i>
                </a>
            <?php else: ?>
                <span class="tracking-plain">
                    <i class="bi bi-truck"></i>
                    <?= htmlspecialchars($lineInfo['tracking_number']) ?>
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ══════════ TABS CARD ══════════ -->
    <div class="tabs-card">

        <!-- Tab nav -->
        <div class="tabs-nav">
            <button class="tab-btn active" data-tab="tab-orderline">
                <i class="bi bi-list-ul"></i> Order Line
            </button>
            <button class="tab-btn" data-tab="tab-sourcing">
                <i class="bi bi-box-seam"></i> Sourcing &amp; Shipping
            </button>
            <button class="tab-btn" data-tab="tab-comments">
                <i class="bi bi-chat-left-text"></i> Comments
            </button>
            <button class="tab-btn" data-tab="tab-documents">
                <i class="bi bi-paperclip"></i> Documents
            </button>
            <button class="tab-btn" data-tab="tab-order">
                <i class="bi bi-bag-check"></i> Order
            </button>
            <button class="tab-btn" data-tab="tab-kanban">
                <i class="bi bi-kanban"></i> Status Board
            </button>
        </div>

        <div class="tab-content">

            <!-- ── ORDER LINE TAB ── -->
            <div class="tab-pane active" id="tab-orderline">
                <form id="orderLineForm">
                    <input type="hidden" name="ol_id" value="<?= $lineInfo['id'] ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Part Number</label>
                            <input type="text" name="part_number" class="form-control form-input"
                                   value="<?= htmlspecialchars($lineInfo['part_number'] ?? $lineInfo['part'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Manufacturer</label>
                            <input type="text" name="manufacturer" class="form-control form-input"
                                   value="<?= htmlspecialchars($lineInfo['manufacturer'] ?? $lineInfo['mfg'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" class="form-control form-input"
                                   value="<?= htmlspecialchars($lineInfo['description'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Qty</label>
                            <input type="number" name="qty" class="form-control form-input" id="lf_qty"
                                   value="<?= htmlspecialchars($lineInfo['qty']) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Delivered</label>
                            <input type="number" step="0.01" name="delivered" class="form-control form-input"
                                   value="<?= htmlspecialchars($lineInfo['delivered'] ?? 0) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Invoiced</label>
                            <input type="number" step="0.01" name="invoiced" class="form-control form-input"
                                   value="<?= htmlspecialchars($lineInfo['invoiced'] ?? 0) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Unit Price ($)</label>
                            <input type="number" step="0.01" name="unit_price" class="form-control form-input" id="lf_unit_price"
                                   value="<?= htmlspecialchars($lineInfo['unit_price']) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Cost Price ($)</label>
                            <input type="number" step="0.01" name="cost_price" class="form-control form-input" id="lf_cost_price"
                                   value="<?= htmlspecialchars($lineInfo['cost_price'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control form-input">
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?= $s ?>" <?= $lineInfo['status']===$s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Note to Buyer</label>
                            <textarea name="note_to_buyer" class="form-control form-input" rows="2"
                                      placeholder="Prints on invoice and packing slip"><?= htmlspecialchars($lineInfo['note_to_buyer'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Live P&L bar -->
                    <div class="pnl-bar">
                        <span style="font-size:.65rem;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.08em;font-weight:700;">Live P&amp;L</span>
                        <div class="pnl-sep"></div>
                        <div class="pnl-item">
                            <span class="pnl-label">Revenue</span>
                            <span class="pnl-value" id="pv_revenue" style="color:#fcd34d;">-</span>
                        </div>
                        <div class="pnl-sep"></div>
                        <div class="pnl-item">
                            <span class="pnl-label">Cost</span>
                            <span class="pnl-value" id="pv_cost" style="color:#94a3b8;">-</span>
                        </div>
                        <div class="pnl-sep"></div>
                        <div class="pnl-item">
                            <span class="pnl-label">Profit</span>
                            <span class="pnl-value" id="pv_profit">-</span>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="action-btn save">
                            <i class="bi bi-check-circle-fill"></i> Update Line
                        </button>
                    </div>
                </form>
            </div>

            <!-- ── SOURCING & SHIPPING TAB ── -->
            <div class="tab-pane" id="tab-sourcing">
                <form id="sourcingForm">
                    <input type="hidden" name="ol_id" value="<?= $lineInfo['id'] ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Fulfillment Method</label>
                            <select name="fulfillment_method" class="form-control form-input">
                                <option value="Stock"              <?= ($lineInfo['fulfillment_method']??'')==='Stock'              ? 'selected':'' ?>>Stock — Ship from our warehouse</option>
                                <option value="Drop Ship"          <?= ($lineInfo['fulfillment_method']??'')==='Drop Ship'          ? 'selected':'' ?>>Drop Ship — Manufacturer to Customer</option>
                                <option value="Warehouse Delivery" <?= ($lineInfo['fulfillment_method']??'')==='Warehouse Delivery' ? 'selected':'' ?>>Warehouse Delivery — Mfg → Us → Customer</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Supplier / Manufacturer Name</label>
                            <input type="text" name="supplier_name" class="form-control form-input"
                                   value="<?= htmlspecialchars($lineInfo['supplier_name'] ?? '') ?>"
                                   placeholder="Where did we source from?">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Our PO # to Supplier</label>
                            <input type="text" name="supplier_po_number" class="form-control form-input"
                                   value="<?= htmlspecialchars($lineInfo['supplier_po_number'] ?? '') ?>"
                                   placeholder="PO we placed with supplier">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Shipping Cost ($)</label>
                            <input type="number" step="0.01" name="shipping_cost" class="form-control form-input"
                                   value="<?= htmlspecialchars($lineInfo['shipping_cost'] ?? '0.00') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tracking Number</label>
                            <input type="text" name="tracking_number" id="trackingInput" class="form-control form-input"
                                   value="<?= htmlspecialchars($lineInfo['tracking_number'] ?? '') ?>"
                                   placeholder="Carrier tracking number">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Tracking URL</label>
                            <div class="url-input-group">
                                <input type="url" name="tracking_url" id="trackingUrl" class="form-control form-input"
                                       value="<?= htmlspecialchars($lineInfo['tracking_url'] ?? '') ?>"
                                       placeholder="https://www.fedex.com/track?…">
                                <?php if (!empty($lineInfo['tracking_url'])): ?>
                                    <a href="<?= htmlspecialchars($lineInfo['tracking_url']) ?>" target="_blank" class="url-open-btn">
                                        <i class="bi bi-box-arrow-up-right"></i> Track
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Tracking preview -->
                    <div id="trackingPreview" class="tracking-preview-bar"
                         style="<?= empty($lineInfo['tracking_number']) ? 'display:none' : '' ?>">
                        <i class="bi bi-truck-front-fill" style="font-size:1.1rem;"></i>
                        <div>
                            <strong>Tracking:</strong>
                            <span id="trackingDisplay"><?= htmlspecialchars($lineInfo['tracking_number'] ?? '') ?></span>
                            <?php if (!empty($lineInfo['tracking_url'])): ?>
                                &mdash;
                                <a href="<?= htmlspecialchars($lineInfo['tracking_url']) ?>" target="_blank"
                                   style="color:#1d4ed8;font-weight:600;">
                                    Click to track <i class="bi bi-box-arrow-up-right" style="font-size:.7rem;"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="action-btn save-green">
                            <i class="bi bi-floppy-fill"></i> Save Sourcing Info
                        </button>
                    </div>
                </form>
            </div>

            <!-- ── COMMENTS TAB ── -->
            <div class="tab-pane" id="tab-comments">
                <div class="section-label"><i class="bi bi-plus-circle me-1"></i> Add Comment</div>
                <form id="commentForm" enctype="multipart/form-data">
                    <input type="hidden" name="order_line_id" value="<?= $lineInfo['id'] ?>">
                    <div id="editor" style="height:130px;border-radius:9px;background:#fff;"></div>
                    <input type="hidden" name="comment" id="commentInput">
                    <div class="mt-3">
                        <label class="form-label">Attach Receipt / File <span style="font-weight:400;text-transform:none;color:#94a3b8;">(optional)</span></label>
                        <input type="file" name="attachment" class="form-control form-input" style="height:auto;padding:6px 10px;"
                               accept=".pdf,.jpg,.jpeg,.png,.xlsx,.docx">
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="action-btn save-blue">
                            <i class="bi bi-send-fill"></i> Post Comment
                        </button>
                    </div>
                </form>
                <hr style="border-color:#f1f5f9;margin:20px 0;">
                <div class="section-label"><i class="bi bi-chat-left-text me-1"></i> Comments</div>
                <div id="commentsList"></div>
            </div>

            <!-- ── DOCUMENTS TAB ── -->
            <div class="tab-pane" id="tab-documents">
                <div class="section-label"><i class="bi bi-upload me-1"></i> Upload Document</div>
                <form id="documentForm" enctype="multipart/form-data">
                    <input type="hidden" name="order_line_id" value="<?= $lineInfo['id'] ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">File</label>
                            <input type="file" name="document" class="form-control form-input" style="height:auto;padding:6px 10px;"
                                   accept=".pdf,.jpg,.jpeg,.png,.xlsx,.docx">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Label / Description</label>
                            <input type="text" name="doc_label" class="form-control form-input"
                                   placeholder="Optional label">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="action-btn save-green">
                            <i class="bi bi-upload"></i> Upload
                        </button>
                    </div>
                </form>
                <hr style="border-color:#f1f5f9;margin:20px 0;">
                <div class="section-label"><i class="bi bi-folder2-open me-1"></i> Documents</div>
                <div id="documentsList"></div>
            </div>

            <!-- ── ORDER TAB ── -->
            <div class="tab-pane" id="tab-order">
                <?php $orderInfo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM orders WHERE id=$order_id")); ?>
                <form id="orderForm">
                    <input type="hidden" name="order_id" value="<?= $orderInfo['id'] ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Order Number</label>
                            <input type="text" name="order_number" id="order_number" class="form-control form-input"
                                   value="<?= htmlspecialchars($orderInfo['order_number'] ?? '') ?>">
                            <div id="orderNumberMsg" style="font-size:.72rem;color:#dc2626;margin-top:4px;"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Customer PO Number</label>
                            <input type="text" name="customer_po_number" class="form-control form-input"
                                   value="<?= htmlspecialchars($orderInfo['customer_po_number'] ?? '') ?>"
                                   placeholder="PO # customer sent us">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Shipping / Freight Terms</label>
                            <input type="text" name="shipping" class="form-control form-input"
                                   value="<?= htmlspecialchars($orderInfo['shipping'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Note to Buyer</label>
                            <textarea name="note_to_buyer" class="form-control form-input" rows="3"
                                      placeholder="Prints on invoice and packing slip"><?= htmlspecialchars($orderInfo['note_to_buyer'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="action-btn save-green">
                            <i class="bi bi-floppy-fill"></i> Update Order
                        </button>
                    </div>
                </form>
            </div>

            <!-- ── KANBAN STATUS BOARD ── -->
            <div class="tab-pane" id="tab-kanban">
                <div class="kanban-board">
                    <?php foreach ($statuses as $status): ?>
                        <div class="kanban-col" data-status="<?= $status ?>">
                            <span class="kanban-col-label"
                                  style="background:<?= $statusColors[$status] ?? '#999' ?>">
                                <?= htmlspecialchars($status) ?>
                            </span>
                            <div class="kanban-items" id="kb-<?= strtolower(str_replace(' ','-',$status)) ?>">
                                <?php
                                mysqli_data_seek($lines, 0);
                                while ($line = mysqli_fetch_assoc($lines)):
                                    if ($line['status'] === $status):
                                ?>
                                    <div class="kanban-item" draggable="true" data-id="<?= $line['id'] ?>">
                                        <div class="kanban-item-title">
                                            <?= htmlspecialchars($line['product'] ?? $line['description'] ?? 'Line '.$i) ?>
                                        </div>
                                        <?php if (!empty($line['part_number'] ?? $line['part'])): ?>
                                            <div class="kanban-item-meta">Part: <?= htmlspecialchars($line['part_number'] ?? $line['part']) ?></div>
                                        <?php endif; ?>
                                        <div class="kanban-item-meta" style="margin-top:4px;">
                                            Qty: <?= $line['qty'] ?> &bull; Delivered: <?= $line['delivered'] ?? 0 ?>
                                        </div>
                                    </div>
                                <?php endif; endwhile; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div><!-- /tab-content -->
    </div><!-- /tabs-card -->

</div><!-- /page-wrapper -->

<!-- Loader overlay -->
<div id="loaderOverlay">
    <div style="background:#fff;border-radius:14px;padding:28px 36px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <div class="spinner-border" style="color:#d97706;width:2.5rem;height:2.5rem;">
            <span class="visually-hidden">Loading…</span>
        </div>
        <div style="margin-top:10px;font-size:.82rem;color:#64748b;font-weight:600;">Updating status…</div>
    </div>
</div>

<script>
/* ── Custom tab switching ── */
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        document.getElementById(this.dataset.tab).classList.add('active');
    });
});

/* ── Quill editor ── */
var quill = new Quill('#editor', { theme: 'snow' });
let currentLineId = <?= $id ?>;

/* ── Load comments & documents ── */
function loadLineData() {
    fetch(`get-line-data.php?ol_id=${currentLineId}`)
        .then(r => r.json())
        .then(data => {
            const comments = data.comments || [];
            document.getElementById('commentsList').innerHTML = comments.length === 0
                ? '<p style="color:#94a3b8;font-size:.82rem;">No comments yet.</p>'
                : comments.map(c => `
                    <div class="comment-item">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                            <div>
                                <div style="font-size:.78rem;font-weight:700;color:#0f172a;margin-bottom:4px;">
                                    <i class="bi bi-person-circle me-1" style="color:#94a3b8;"></i>${c.username || 'Unknown'}
                                </div>
                                <div style="font-size:.82rem;color:#475569;">${c.comment}</div>
                                ${c.attachment ? `<div style="margin-top:6px;"><a href="../${c.attachment}" target="_blank" style="font-size:.75rem;color:#0369a1;font-weight:600;"><i class="bi bi-paperclip me-1"></i>Attachment</a></div>` : ''}
                            </div>
                            <button class="delete-btn" onclick="deleteComment(${c.id})"><i class="bi bi-trash"></i></button>
                        </div>
                        <div class="comment-meta"><i class="bi bi-clock me-1"></i>${c.created_at}</div>
                    </div>`).join('');

            const docs = data.documents || [];
            document.getElementById('documentsList').innerHTML = docs.length === 0
                ? '<p style="color:#94a3b8;font-size:.82rem;">No documents uploaded.</p>'
                : docs.map((d, i) => `
                    <div class="doc-item">
                        <div>
                            <a href="../${d.file_path}" target="_blank"
                               style="font-size:.82rem;font-weight:700;color:#0369a1;text-decoration:none;">
                                <i class="bi bi-file-earmark me-1"></i>${d.doc_label || 'Document ' + (i+1)}
                            </a>
                            <span style="font-size:.72rem;color:#94a3b8;margin-left:8px;">${d.uploaded_at}</span>
                        </div>
                        <button class="delete-btn" onclick="deleteDocument(${d.id})"><i class="bi bi-trash"></i></button>
                    </div>`).join('');
        });
}
loadLineData();

/* ── Live P&L ── */
function updateProfitPreview() {
    const qty       = parseFloat(document.getElementById('lf_qty')?.value)        || 0;
    const unitPrice = parseFloat(document.getElementById('lf_unit_price')?.value) || 0;
    const costPrice = parseFloat(document.getElementById('lf_cost_price')?.value) || 0;
    const revenue   = qty * unitPrice;
    const cost      = qty * costPrice;
    const profit    = revenue - cost;
    document.getElementById('pv_revenue').textContent = '$' + revenue.toFixed(2);
    document.getElementById('pv_cost').textContent    = '$' + cost.toFixed(2);
    const pEl = document.getElementById('pv_profit');
    pEl.textContent = (profit >= 0 ? '+' : '') + '$' + profit.toFixed(2);
    pEl.style.color = profit >= 0 ? '#4ade80' : '#f87171';
}
['lf_qty','lf_unit_price','lf_cost_price'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', updateProfitPreview);
});
updateProfitPreview();

/* ── Tracking preview ── */
document.getElementById('trackingInput')?.addEventListener('input', function () {
    const preview = document.getElementById('trackingPreview');
    const display = document.getElementById('trackingDisplay');
    if (this.value.trim()) { preview.style.display = 'flex'; display.textContent = this.value.trim(); }
    else { preview.style.display = 'none'; }
});

/* ── Form submissions ── */
document.getElementById('commentForm').addEventListener('submit', function (e) {
    e.preventDefault();
    document.getElementById('commentInput').value = quill.root.innerHTML;
    fetch('save-orderline-comments.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(d => { if (d.success) { loadLineData(); quill.setContents([]); this.reset(); } else alert(d.error || 'Failed'); });
});

document.getElementById('documentForm').addEventListener('submit', function (e) {
    e.preventDefault();
    fetch('upload-orderline-documents.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(d => { if (d.success) { loadLineData(); this.reset(); } else alert(d.error || 'Upload failed'); });
});

document.getElementById('orderLineForm').addEventListener('submit', function (e) {
    e.preventDefault();
    fetch('update-order-line.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(d => { if (d.success) { alert('Line updated!'); location.reload(); } else alert('Error: ' + (d.error || 'Failed')); });
});

document.getElementById('sourcingForm').addEventListener('submit', function (e) {
    e.preventDefault();
    fetch('update-order-line.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(d => { if (d.success) { alert('Sourcing info saved!'); location.reload(); } else alert('Error: ' + (d.error || 'Failed')); });
});

document.getElementById('orderForm').addEventListener('submit', function (e) {
    e.preventDefault();
    fetch('update-order.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(d => { if (d.success) alert('Order updated!'); else alert('Error: ' + (d.error || 'Failed')); });
});

function deleteComment(id) {
    if (!confirm('Delete this comment?')) return;
    fetch('delete-orderline-comment.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`id=${id}` })
        .then(r => r.json()).then(d => { if (d.success) loadLineData(); });
}
function deleteDocument(id) {
    if (!confirm('Delete this document?')) return;
    fetch('delete-orderline-document.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`id=${id}` })
        .then(r => r.json()).then(d => { if (d.success) loadLineData(); });
}

/* ── Kanban drag-drop ── */
document.querySelectorAll('.kanban-item').forEach(item => {
    item.addEventListener('dragstart', e => {
        e.dataTransfer.setData('id', item.dataset.id);
        setTimeout(() => item.classList.add('dragging'), 0);
    });
    item.addEventListener('dragend', () => item.classList.remove('dragging'));
});
document.querySelectorAll('.kanban-items').forEach(container => {
    container.addEventListener('dragover', e => e.preventDefault());
    container.addEventListener('drop', e => {
        e.preventDefault();
        const id   = e.dataTransfer.getData('id');
        const card = document.querySelector(`.kanban-item[data-id='${id}']`);
        container.appendChild(card);
        const newStatus = container.parentElement.getAttribute('data-status');
        document.getElementById('loaderOverlay').style.display = 'flex';
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ol_id=${id}&status=${encodeURIComponent(newStatus)}`
        })
        .then(r => r.json())
        .then(d => { if (!d.success) alert('Error: ' + (d.error||'Failed')); else setTimeout(() => location.reload(), 600); })
        .finally(() => { document.getElementById('loaderOverlay').style.display = 'none'; });
    });
});

/* ── Order number duplicate check ── */
document.getElementById('order_number')?.addEventListener('blur', function () {
    const orderId = document.querySelector('[name="order_id"]').value;
    const val = this.value.trim();
    if (!val) return;
    fetch(`check-order-number.php?order_id=${orderId}&order_number=${encodeURIComponent(val)}`)
        .then(r => r.json())
        .then(d => {
            document.getElementById('orderNumberMsg').textContent = d.valid ? '' : (d.msg || '');
            this.classList.toggle('is-invalid', !d.valid);
        });
});
</script>

<?php include("../templates/footer.php"); ?>
