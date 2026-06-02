<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

include("../includes/auth.php");
include("../config/database.php");
check_auth();
require_role(['Admin', 'Sales']);

// session_start();

/* ─────────────────── Pagination setup ─────────────────── */
$default_per_page = 10;
$per_page = max(5, (int)($_GET['per_page'] ?? $default_per_page));
$page     = max(1, (int)($_GET['page'] ?? 1));

/* ---------- CONVERT RFQ TO ORDER ---------- */
if (isset($_POST['convertToOrder'])) {
    $rfq_id       = (int)$_POST['rfq_id'];
    $order_number = mysqli_real_escape_string($conn, $_POST['order_number']);

    if (!is_numeric($order_number)) {
        preg_match('/(\d+)$/', $order_number, $m);
        $order_number = isset($m[1]) ? (int)$m[1] : 0;
    }

    $exists = mysqli_query($conn, "SELECT id FROM orders WHERE rfq_id = $rfq_id");
    if (mysqli_num_rows($exists) > 0) {
        header("Location: rfq_view.php?id=$rfq_id&converted=1");
        exit;
    }

    $rfq = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM rfqs WHERE id = $rfq_id"));

    $year = date("Y");
    $lastOrder = mysqli_fetch_assoc(mysqli_query($conn, "SELECT customer_po_number FROM orders WHERE customer_po_number LIKE 'PO-$year-%' ORDER BY id DESC LIMIT 1"));
    $nextNum = ($lastOrder && preg_match('/PO-\d{4}-(\d+)$/', $lastOrder['customer_po_number'], $m)) ? (int)$m[1] + 1 : 1;
    $customer_po_number = "PO-$year-" . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

    $rfq_number  = mysqli_real_escape_string($conn, $rfq['rfq_number']);
    $quote_date  = mysqli_real_escape_string($conn, $rfq['quote_date'] ?? '');
    $validityStr = strtolower(trim($rfq['validity'] ?? ''));
    $daysLeft    = 0;

    if ($rfq['quote_date'] && $validityStr) {
        $quoteDate = new DateTime($rfq['quote_date']);
        preg_match('/(\d+)/', $validityStr, $numMatch);
        $num = isset($numMatch[1]) ? (int)$numMatch[1] : 0;

        if (strpos($validityStr, 'month') !== false && $num > 0) {
            $expiryDate = (clone $quoteDate)->add(new DateInterval("P{$num}M"));
        } elseif ($num > 0) {
            $expiryDate = (clone $quoteDate)->add(new DateInterval("P{$num}D"));
        } else {
            $expiryDate = null;
        }

        if (isset($expiryDate)) {
            $today = new DateTime();
            $daysLeft = (int)$today->diff($expiryDate)->days;
            if ($today > $expiryDate) $daysLeft = 0;
        }
    }

    $rfq_customer_name = mysqli_real_escape_string($conn, $rfq['customer_name'] ?? '');

    mysqli_query($conn, "INSERT INTO orders (rfq_id, order_number, rfq_title, rfq_number, customer_id, customer_name, salesPerson_id, billTo_id, buyer_id, shipTo_id, status, customer_po_number, quote_date, lead_time) VALUES ('$rfq_id','$order_number','{$rfq['rfq_title']}','$rfq_number','{$rfq['customer_id']}','$rfq_customer_name','{$rfq['salesPerson_id']}','{$rfq['billTo_id']}','{$rfq['buyer_id']}','{$rfq['shipTo_id']}','Open','$customer_po_number'," . ($quote_date ? "'$quote_date'" : "NULL") . ",'$daysLeft days')");

    $order_id = mysqli_insert_id($conn);

    $rfq_lines = mysqli_query($conn, "SELECT * FROM rfq_lines WHERE rfq_id = $rfq_id");
    while ($l = mysqli_fetch_assoc($rfq_lines)) {
        $ol_part   = mysqli_real_escape_string($conn, $l['part'] ?? '');
        $ol_mfg    = mysqli_real_escape_string($conn, $l['mfg'] ?? '');
        $ol_desc   = mysqli_real_escape_string($conn, $l['description'] ?? '');
        $ol_product = mysqli_real_escape_string($conn, $l['description'] ?? '');

        mysqli_query(
            $conn,
            "INSERT INTO order_lines
            (order_id, product, part_number,
             manufacturer, description,
             qty, unit_price, cost_price, status)
            VALUES
            ('$order_id','$ol_product',
             '$ol_part','$ol_mfg',
             '$ol_desc',
             '" . (float)($l['qty'] ?? 0) . "',
             '" . (float)($l['unit_price'] ?? 0) . "',
             '" . (float)($l['cost_price'] ?? 0) . "',
             'Pending')"
        );
    }

    mysqli_query($conn, "UPDATE rfqs SET status = 'Converted' WHERE id = $rfq_id");
    header("Location: rfqs.php?id=$order_id");
    exit;
}

/* ---------- CONVERT RFQ TO LOST ---------- */
if (isset($_POST['convertToLost'])) {
    $rfq_id       = (int)$_POST['rfq_id'];
    $lost_note    = mysqli_real_escape_string($conn, $_POST['lost_note']);
    $awarded_price = !empty($_POST['awarded_price']) ? (float)$_POST['awarded_price'] : NULL;
    $awarded_to    = mysqli_real_escape_string($conn, $_POST['awarded_to']);

    $rfq = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM rfqs WHERE id = $rfq_id"));
    if ($rfq['status'] === 'Lost') {
        header("Location: rfq_view.php?id=$rfq_id&lost=1");
        exit;
    }

    $our_price = isset($_POST['our_price']) ? (float)$_POST['our_price'] : 0;
    $result = mysqli_query($conn, "UPDATE rfqs SET
        status='Lost',
        our_price=$our_price,
        lost_note='$lost_note',
        awarded_price=" . ($awarded_price !== NULL ? $awarded_price : "NULL") . ",
        awarded_to='$awarded_to',
        lost_date=NOW()
        WHERE id=$rfq_id");

    if (!$result) die("SQL Error: " . mysqli_error($conn));
    header("Location: rfqs.php?success=1");
    exit;
}

/* ---------- DELETE ---------- */
if (isset($_POST['delete_rfq_id'])) {
    $id = (int)$_POST['delete_rfq_id'];
    mysqli_query($conn, "DELETE FROM `rfqs` WHERE `id`='$id'");
    header("Location: rfqs.php?deleted=1");
    exit;
}
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM `rfqs` WHERE `id`='$id'");
    header("Location: rfqs.php?deleted=1");
    exit;
}

/* ---------- AJAX STATUS UPDATE ---------- */
if (isset($_POST['update_status'])) {
    $rfq_id = (int)$_POST['rfq_id'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $valid_statuses = ['Ready for Review', 'Ready to Submit', 'Submitted'];

    if (in_array($status, $valid_statuses)) {
        $result = mysqli_query($conn, "UPDATE rfqs SET status = '$status' WHERE id = $rfq_id");
        echo json_encode($result ? ['success' => true, 'status' => $status] : ['success' => false, 'error' => 'Database update failed']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid status']);
    }
    exit;
}

/* ---------- TABLE HELPERS ---------- */
function formatRfqNumber($rfq_number, $created_at)
{
    if (preg_match('/^RFQ-\d{4}-/i', $rfq_number)) return $rfq_number;
    return 'RFQ-' . date('Y', strtotime($created_at)) . '-' . $rfq_number;
}

function getRfqsTable($conn, $search = "", $filter = "", $page = 1, $per_page = 20)
{
    $page     = max(1, (int)$page);
    $per_page = max(5, (int)$per_page);
    $offset   = ($page - 1) * $per_page;

    $whereClause = "WHERE r.status != 'Lost'";

    if (!empty($filter)) {
        if ($filter === 'expiring') {
            $whereClause .= " AND r.status NOT IN ('Lost','Converted') AND DATE_ADD(r.quote_date, INTERVAL CAST(r.validity AS UNSIGNED) MONTH) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        } elseif ($filter === 'total') {
            $whereClause = "WHERE r.status != 'Lost'";
        } else {
            $safeFilter = mysqli_real_escape_string($conn, $filter);
            $whereClause .= " AND r.status = '$safeFilter'";
        }
    }

    if (!empty($search)) {
        $s = mysqli_real_escape_string($conn, trim($search));
        $whereClause .= " AND (r.rfq_number LIKE '%$s%' OR r.rfq_title LIKE '%$s%' OR r.customer_name LIKE '%$s%')";
    }

    $count_sql = "
        SELECT COUNT(*) AS total
        FROM rfqs r
        JOIN customers c ON r.customer_id = c.id
        JOIN salesperson sp ON r.salesPerson_id = sp.id
        LEFT JOIN billto bt ON r.billTo_id = bt.id
        LEFT JOIN buyer b ON r.buyer_id = b.id
        LEFT JOIN shipto st ON r.shipTo_id = st.id
        $whereClause
    ";
    $total_row = mysqli_fetch_assoc(mysqli_query($conn, $count_sql));
    $total     = (int)($total_row['total'] ?? 0);
    $pages     = max(1, (int)ceil($total / $per_page));

    $rfqs = mysqli_query($conn, "
        SELECT r.*, COALESCE(r.customer_name, c.name) AS customer, sp.name AS salesPerson,
               bt.title AS billTo, b.name AS buyer, st.name AS shipTo, r.created_at
        FROM rfqs r
        JOIN customers c ON r.customer_id = c.id
        JOIN salesperson sp ON r.salesPerson_id = sp.id
        LEFT JOIN billto bt ON r.billTo_id = bt.id
        LEFT JOIN buyer b ON r.buyer_id = b.id
        LEFT JOIN shipto st ON r.shipTo_id = st.id
        $whereClause
        ORDER BY r.id DESC
        LIMIT $offset, $per_page
    ");

    $count = mysqli_num_rows($rfqs);
    ob_start();

    if ($count === 0): ?>


        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-journal-text"></i></div>
            <div class="empty-state-title">No RFQs Found</div>
            <div class="empty-state-sub">Try adjusting your search or filter criteria</div>
        </div>
    <?php else: ?>

        <table class="table rfqs-table mb-0">
            <thead>
                <tr>
                    <th>RFQ #</th>
                    <th class="d-none d-md-table-cell">Title</th>
                    <th>Customer</th>
                    <th class="d-none d-lg-table-cell">Sales Person</th>
                    <th class="d-none d-xl-table-cell">Bill To</th>
                    <th class="d-none d-lg-table-cell">Buyer</th>
                    <th class="d-none d-xl-table-cell">Ship To</th>
                    <th>Valid</th>
                    <th class="d-none d-md-table-cell">Created</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($r = mysqli_fetch_assoc($rfqs)):
                    $statusClass = 'bg-warning text-dark';
                    $statusText  = $r['status'];
                    if ($r['status'] == 'Ready to Submit') $statusClass = 'bg-info';
                    elseif ($r['status'] == 'Submitted')   $statusClass = 'bg-success';

                    $quoteDate   = new DateTime($r['quote_date']);
                    $validityStr = strtolower(trim($r['validity'] ?? ''));
                    preg_match('/(\d+)/', $validityStr, $numMatch);
                    $num = isset($numMatch[1]) ? (int)$numMatch[1] : 0;
                    $validityInterval = null;
                    if (strpos($validityStr, 'month') !== false && $num > 0) $validityInterval = new DateInterval("P{$num}M");
                    elseif (strpos($validityStr, 'day') !== false && $num > 0) $validityInterval = new DateInterval("P{$num}D");

                    $validityBadge = "<span class='validity-badge na'>N/A</span>";
                    if ($validityInterval) {
                        $expiryDate = (clone $quoteDate)->add($validityInterval);
                        $today      = new DateTime();
                        $daysLeft   = $today->diff($expiryDate)->days;
                        if ($today > $expiryDate)
                            $validityBadge = "<span class='validity-badge expired'><i class='bi bi-clock me-1'></i>Exp</span>";
                        elseif ($daysLeft <= 7)
                            $validityBadge = "<span class='validity-badge expiring'><i class='bi bi-exclamation-circle me-1'></i>{$daysLeft}d</span>";
                        else
                            $validityBadge = "<span class='validity-badge valid'><i class='bi bi-check-circle me-1'></i>{$daysLeft}d</span>";
                    }

                    $has_order   = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM orders WHERE rfq_id = {$r['id']}")) > 0;
                    $rfqFormatted = htmlspecialchars(formatRfqNumber($r['rfq_number'], $r['created_at']));
                    $rfqCustomer  = htmlspecialchars($r['customer'] ?? '—');
                    $rfqTitle     = htmlspecialchars($r['rfq_title'] ?? '—');
                    $rfqCreated   = date('M j, Y', strtotime($r['created_at']));
                ?>
                    <tr class="rfq-row status-row-<?= $r['id'] ?>">
                        <td class="fw-semibold text-nowrap">
                            <span class="ref-badge ref-badge-blue"><?= $rfqFormatted ?>
                            </span>
                        </td>
                        <td class="d-none d-md-table-cell rfq-title-cell text-truncate" title="<?= $rfqTitle ?>">
                            <span style="font-size:.83rem;"><?= $rfqTitle ?>
                            </span>
                        </td>
                        <td class="fw-semibold" style="font-size:.83rem;">
                            <span class="cell-truncate" title="<?= $rfqCustomer ?>"><?= $rfqCustomer ?>
                            </span>
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <div class="d-flex align-items-center gap-2">
                                <div class="mini-avatar"><?= strtoupper(substr($r['salesPerson'] ?? '?', 0, 1)) ?>
                                </div>
                                <span class="text-truncate" style="font-size:.8rem;max-width:110px;">
                                    <?= htmlspecialchars($r['salesPerson'] ?? '—') ?>
                                </span>
                            </div>
                        </td>
                        <td class="d-none d-xl-table-cell text-truncate text-muted" style="font-size:.8rem;max-width:100px;">
                            <?= htmlspecialchars($r['billTo'] ?? '—') ?>
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <?php if (!empty($r['buyer'])): ?>
                                <a href="rfq_edit.php?id=<?= $r['id'] ?>"
                                    title="<?= htmlspecialchars($r['buyer']) ?>"
                                    class="buyer-link badge bg-primary-subtle text-primary-emphasis fw-semibold text-decoration-none p-2 rounded-3 w-100 d-flex align-items-center justify-content-center gap-1">
                                    <span class="text-truncate" style="max-width:80px;" title="<?= htmlspecialchars($r['buyer']) ?>"><?= htmlspecialchars($r['buyer']) ?></span>
                                    <i class="bi bi-arrow-up-right flex-shrink-0 opacity-75"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-muted" style="font-size:.8rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-xl-table-cell text-truncate text-muted" style="font-size:.8rem;max-width:100px;">
                            <?= htmlspecialchars($r['shipTo'] ?? '—') ?>
                        </td>
                        <td><?= $validityBadge ?>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <span class="text-muted text-nowrap" style="font-size:.78rem;">
                                <i class="bi bi-calendar3 me-1" style="opacity:.5;font-size:.7rem;"></i>
                                <?= date("M j, H:i", strtotime($r['created_at'])) ?>
                            </span>
                        </td>
                        <td class="status-cell p-2">
                            <span class="status-display badge <?= $statusClass ?> px-2 py-1 fw-semibold editable-status"
                                data-status="<?= htmlspecialchars($statusText) ?>"
                                data-rfq-id="<?= $r['id'] ?>"
                                tabindex="0"
                                style="cursor:pointer;display:block;width:100%;font-size:0.72rem;">
                                <?= htmlspecialchars($statusText) ?>
                                <i class="bi bi-chevron-down ms-1 opacity-50" style="font-size:.6em;"></i>
                            </span>
                        </td>
                        <td class="text-center p-2">
                            <div class="rfq-action-dropdown" style="position:relative;display:inline-block;">
                                <button class="row-action-btn menu-btn action-toggle"
                                    data-rfq-id="<?= $r['id'] ?>" type="button" title="Actions">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="action-menu" id="actionMenu<?= $r['id'] ?>"
                                    style="display:none;position:fixed;z-index:9999;min-width:185px;
                                       background:var(--color-surface);border:1px solid var(--color-border);
                                       border-radius:12px;padding:4px;
                                       box-shadow:0 12px 32px rgba(15,23,42,.35);
                                       list-style:none;margin:0;">
                                    <li>
                                        <a class="action-item" href="rfq-pdf.php?id=<?= $r['id'] ?>">
                                            <span class="action-icon" style="background:#d1fae5;color:#059669;"><i class="bi bi-printer"></i></span>Print / PDF
                                        </a>
                                    </li>
                                    <li>
                                        <a class="action-item" href="rfq_edit.php?id=<?= $r['id'] ?>">
                                            <span class="action-icon" style="background:#dbeafe;color:#2563eb;"><i class="bi bi-pencil"></i></span>Edit RFQ
                                        </a>
                                    </li>
                                    <?php if (!$has_order && $r['status'] !== 'Lost'): ?>


                                        <li>
                                            <button class="action-item btn-convert-order w-100 text-start"
                                                data-rfq-id="<?= $r['id'] ?>" data-rfq-number="<?= $rfqFormatted ?>" type="button">
                                                <span class="action-icon" style="background:#e0f2fe;color:#0284c7;"><i class="bi bi-arrow-repeat"></i></span>Convert to Order
                                            </button>
                                        </li>
                                    <?php elseif ($has_order): ?>


                                        <li>
                                            <span class="action-item text-muted" style="cursor:default;">
                                                <span class="action-icon" style="background:#d1fae5;color:#059669;"><i class="bi bi-check-circle"></i></span>Order Created
                                            </span>
                                        </li>
                                    <?php endif; ?>


                                    <?php if ($r['status'] !== 'Lost'): ?>
                                        <li>
                                            <button class="action-item btn-mark-lost w-100 text-start"
                                                data-rfq-id="<?= htmlspecialchars($r['id']) ?>"
                                                data-rfq-number="<?= htmlspecialchars($rfqFormatted) ?>"
                                                data-total="<?= htmlspecialchars($r['total_amount'] ?? '') ?>" type="button">
                                                <span class="action-icon" style="background:#fef3c7;color:#d97706;"><i class="bi bi-x-circle"></i></span>Mark as Lost
                                            </button>
                                        </li>
                                    <?php endif; ?>


                                    <li>
                                        <hr style="margin:4px 8px;border-color:var(--color-border);">
                                    </li>
                                    <li>
                                        <button type="button" class="action-item action-danger btn-delete-rfq"
                                            data-rfq-id="<?= (int) $r['id'] ?>"
                                            data-rfq-number="<?= htmlspecialchars($rfqFormatted) ?>"
                                            data-rfq-customer="<?= htmlspecialchars($rfqCustomer) ?>"
                                            data-rfq-title="<?= htmlspecialchars($rfqTitle) ?>"
                                            data-rfq-status="<?= htmlspecialchars($statusText) ?>"
                                            data-rfq-created="<?= htmlspecialchars($rfqCreated) ?>">
                                            <span class="action-icon" style="background:#fee2e2;color:#dc2626;"><i class="bi bi-trash3"></i></span>Delete
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>


            </tbody>
        </table>

        <div class="table-footer-bar d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span class="text-muted" style="font-size:.78rem;">
                <i class="bi bi-list-ul me-1"></i>
                Showing <strong><?= $count ?>

                </strong> of <strong><?= $total ?>

                </strong> RFQ<?= $total !== 1 ? 's' : '' ?>


            </span>

            <?php if ($pages > 1): ?>


                <nav aria-label="RFQ pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="javascript:void(0)" data-page="<?= max(1, $page - 1) ?>">Prev</a>
                        </li>
                        <?php
                        $start = max(1, $page - 2);
                        $end   = min($pages, $page + 2);
                        for ($p = $start; $p <= $end; $p++): ?>


                            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                <a class="page-link" href="javascript:void(0)" data-page="<?= $p ?>"><?= $p ?>

                                </a>
                            </li>
                        <?php endfor; ?>


                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="javascript:void(0)" data-page="<?= min($pages, $page + 1) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>


        </div>
<?php endif;

    return ob_get_clean();
}

/* ---------- AJAX ENTRY POINT ---------- */
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $per_page = max(5, (int)($_GET['per_page'] ?? $default_per_page));
    echo getRfqsTable($conn, $_GET['rfq_search'] ?? "", $_GET['filter'] ?? "", $page, $per_page);
    exit;
}

/* ---------- NORMAL PAGE RENDER ---------- */
if (!file_exists("../templates/header.php")) die("Critical error: header template missing.");
include("../templates/header.php");
if (!file_exists("../templates/navbar.php")) die("Critical error: navbar template missing.");
include("../templates/navbar.php");
?>



<!-- include the CSS file from Part 2 -->

<div class="page-wrapper">

    <!-- ALERTS -->
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3" style="border-radius:10px;font-size:.875rem;">
            <i class="bi bi-check-circle-fill me-2 text-success"></i>RFQ deleted successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-3" style="border-radius:10px;font-size:.875rem;">
            <i class="bi bi-x-circle-fill me-2"></i>RFQ marked as Lost.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>


    <?php if (isset($_GET['converted'])): ?>


        <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm mb-3" style="border-radius:10px;font-size:.875rem;">
            <i class="bi bi-arrow-repeat me-2"></i>RFQ already converted to an Order.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>



    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-journal-check"></i>RFQs
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="page-search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" id="rfq_search"
                        value="<?= htmlspecialchars($_GET['rfq_search'] ?? '') ?>"
                        class="form-control page-search-input"
                        placeholder="Search RFQ #, title, customer…">
                </div>
                <a href="add-rfq.php" class="page-action-btn primary">
                    <i class="bi bi-plus-circle"></i>Create RFQ
                </a>
            </div>
        </div>
    </div>

    <!-- STAT CARDS -->
    <?php
    $cnt_total    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM rfqs WHERE status != 'Lost'"))['c'];
    $cnt_open     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM rfqs WHERE status='Ready for Review'"))['c'];
    $cnt_review   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM rfqs WHERE status='Ready to Submit'"))['c'];
    $cnt_approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM rfqs WHERE status='Submitted'"))['c'];
    $cnt_expiring = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM rfqs WHERE status NOT IN ('Lost','Converted') AND DATE_ADD(quote_date, INTERVAL CAST(validity AS UNSIGNED) MONTH) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"))['c'];
    $current_filter = $_GET['filter'] ?? '';
    ?>


    <div class="row g-2 g-md-3 mb-4">
        <div class="col-6 col-sm-4 col-md">
            <div class="rfq-stat-card <?= ($current_filter === 'total') ? 'active-filter' : '' ?>"
                data-filter="total" style="--card-color:#6366f1;">
                <div class="rfq-stat-inner">
                    <div>
                        <div class="rfq-stat-label">All RFQs</div>
                        <div class="rfq-stat-num" style="color:#6366f1;"><?= $cnt_total ?>

                        </div>
                    </div>
                    <div class="rfq-stat-icon" style="background:#ede9fe;color:#6366f1;">
                        <i class="bi bi-collection-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-sm-4 col-md">
            <div class="rfq-stat-card <?= ($current_filter === 'Ready for Review') ? 'active-filter' : '' ?>"
                data-filter="Ready for Review" style="--card-color:#f59e0b;">
                <div class="rfq-stat-inner">
                    <div>
                        <div class="rfq-stat-label">Ready for Review</div>
                        <div class="rfq-stat-num" style="color:#f59e0b;"><?= $cnt_open ?>

                        </div>
                    </div>
                    <div class="rfq-stat-icon" style="background:#fef3c7;color:#f59e0b;">
                        <i class="bi bi-circle-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-sm-4 col-md">
            <div class="rfq-stat-card <?= ($current_filter === 'Ready to Submit') ? 'active-filter' : '' ?>"
                data-filter="Ready to Submit" style="--card-color:#0ea5e9;">
                <div class="rfq-stat-inner">
                    <div>
                        <div class="rfq-stat-label">Ready to Submit</div>
                        <div class="rfq-stat-num" style="color:#0ea5e9;"><?= $cnt_review ?>

                        </div>
                    </div>
                    <div class="rfq-stat-icon" style="background:#e0f2fe;color:#0ea5e9;">
                        <i class="bi bi-eye-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-sm-4 col-md">
            <div class="rfq-stat-card <?= ($current_filter === 'Submitted') ? 'active-filter' : '' ?>"
                data-filter="Submitted" style="--card-color:#10b981;">
                <div class="rfq-stat-inner">
                    <div>
                        <div class="rfq-stat-label">Submitted</div>
                        <div class="rfq-stat-num" style="color:#10b981;"><?= $cnt_approved ?></div>
                    </div>
                    <div class="rfq-stat-icon" style="background:#d1fae5;color:#10b981;">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-sm-4 col-md">
            <div class="rfq-stat-card <?= ($current_filter === 'expiring') ? 'active-filter' : '' ?>"
                data-filter="expiring" style="--card-color:#ef4444;">
                <div class="rfq-stat-inner">
                    <div>
                        <div class="rfq-stat-label">Expiring</div>
                        <div class="rfq-stat-num" style="color:#ef4444;"><?= $cnt_expiring ?>

                        </div>
                    </div>
                    <div class="rfq-stat-icon" style="background:#fee2e2;color:#ef4444;">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="rfq-page-card card border-0">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2"
            style="background:var(--color-surface-2);border-bottom:1px solid var(--color-border);">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill"
                    style="background:var(--color-accent-bg);color:var(--color-accent-dark);font-size:.7rem;">
                    <i class="bi bi-funnel me-1"></i>List
                </span>
                <span style="font-size:.8rem;color:var(--color-text-secondary);">
                    paginate RFQs
                </span>
            </div>
            <div class="d-flex gap-2">
                <select id="perPage" class="form-select form-select-sm"
                    style="min-width:90px;font-size:.8rem;">
                    <option value="10" <?= $per_page == 10 ? 'selected' : '' ?>>10 / page</option>
                    <option value="20" <?= $per_page == 20 ? 'selected' : '' ?>>20 / page</option>
                    <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50 / page</option>
                </select>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="rfqTable" style="overflow-x:auto;overflow-y:visible;min-height:400px;">
                <?= getRfqsTable($conn, $_GET['rfq_search'] ?? '', $_GET['filter'] ?? '', $page, $per_page) ?>


            </div>
        </div>
    </div>

    <!-- Convert to Order Modal -->
    <div class="modal fade" id="sharedOrderModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form method="post" id="orderModalForm">
                    <div class="modal-header border-0 rounded-top-3"
                        style="background:linear-gradient(135deg,#0ea5e9,#0284c7);">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-white bg-opacity-20 p-2 rounded-circle">
                                <i class="bi bi-arrow-repeat fs-5 text-white"></i>
                            </div>
                            <div>
                                <h5 class="modal-title mb-0 fw-bold text-white">Convert to Order</h5>
                                <small class="text-white opacity-75" id="orderModalSubtitle">RFQ #—</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body px-4 py-3">
                        <input type="hidden" name="rfq_id" id="orderModalRfqId">
                        <label class="form-label fw-semibold small text-uppercase text-muted">
                            Order Number <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="bi bi-hash text-muted"></i>
                            </span>
                            <input type="text" name="order_number" id="orderModalInput"
                                class="form-control border-start-0 ps-0"
                                placeholder="e.g., ORD-<?= date('Y') ?>-001" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light rounded-bottom-3 px-4 py-3">
                        <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i>Cancel
                        </button>
                        <button type="submit" name="convertToOrder" class="btn btn-primary btn-sm px-4">
                            <i class="bi bi-check-circle me-2"></i>Convert to Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Mark as Lost Modal -->
    <div class="modal fade" id="sharedLostModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form method="post" id="lostModalForm">
                    <div class="modal-header border-0 rounded-top-3"
                        style="background:linear-gradient(135deg,#ef4444,#dc2626);">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-white bg-opacity-20 p-2 rounded-circle">
                                <i class="bi bi-x-circle fs-5 text-white"></i>
                            </div>
                            <div>
                                <h5 class="modal-title mb-0 fw-bold text-white">Mark as Lost</h5>
                                <small class="text-white opacity-75" id="lostModalSubtitle">RFQ #—</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body px-4 py-3">
                        <input type="hidden" name="rfq_id" id="lostModalRfqId">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Lost Date</label>
                            <input type="date" name="lost_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Reason <span class="text-danger">*</span></label>
                            <textarea name="lost_note" class="form-control form-control-sm" rows="2" placeholder="Why were we not selected?" required></textarea>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted">Our Price</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" name="our_price" id="lostModalOurPrice" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted">Awarded Price</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" name="awarded_price" class="form-control" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Awarded To</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-building"></i></span>
                                <input type="text" name="awarded_to" class="form-control" placeholder="Competitor name">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light rounded-bottom-3 px-4 py-3">
                        <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i>Cancel
                        </button>
                        <button type="submit" name="convertToLost" class="btn btn-danger btn-sm px-4">
                            <i class="bi bi-check-circle me-2"></i>Mark as Lost
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div><!-- /page-wrapper -->

<!-- DELETE RFQ MODAL -->
<div class="modal fade" id="deleteRfqModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="delete-modal-header">
                <div class="d-flex align-items-center justify-content-between" style="position:relative;z-index:1;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="delete-icon-wrap"><i class="bi bi-journal-x"></i></div>
                        <div>
                            <div class="delete-modal-title">Delete RFQ</div>
                            <div class="delete-modal-sub" id="deleteRfqModalSub">Confirm RFQ removal</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="delete-rfq-chip mb-3">
                    <div class="delete-rfq-avatar" id="deleteRfqAvatar">?</div>
                    <div style="flex:1;min-width:0;">
                        <div class="delete-rfq-code" id="deleteRfqNumber">—</div>
                        <div class="delete-rfq-meta" id="deleteRfqCustomer">—</div>
                        <div class="delete-rfq-meta" id="deleteRfqTitle">—</div>
                        <div class="chip-detail">
                            <div class="chip-detail-item">
                                <span class="chip-detail-label">Status</span>
                                <span class="chip-detail-value" id="deleteRfqStatus">—</span>
                            </div>
                            <div style="width:1px;height:28px;background:#fecaca;"></div>
                            <div class="chip-detail-item">
                                <span class="chip-detail-label">Created</span>
                                <span class="chip-detail-value" id="deleteRfqCreated">—</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="delete-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>This action is <strong>permanent and cannot be undone.</strong> The RFQ and all associated line items will be permanently removed.</div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 py-3" style="background:#f8fafc;border-radius:0 0 16px 16px;">
                <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancel</button>
                <a href="#" id="deleteRfqConfirmBtn" class="btn-delete-confirm"><i class="bi bi-trash-fill"></i> Yes, Delete RFQ</a>
            </div>
        </div>
    </div>
</div>

<script>
    /* JS: action menus, filters, pagination, status dropdown */

    function openDeleteModal(id, rfqNumber, customer, title, status, created) {
        document.getElementById('deleteRfqModalSub').textContent = 'Removing: ' + rfqNumber;
        document.getElementById('deleteRfqNumber').textContent = rfqNumber;
        document.getElementById('deleteRfqCustomer').textContent = customer;
        document.getElementById('deleteRfqTitle').textContent = title;
        document.getElementById('deleteRfqStatus').textContent = status;
        document.getElementById('deleteRfqCreated').textContent = created;
        document.getElementById('deleteRfqConfirmBtn').href = '?delete=' + id;
        document.getElementById('deleteRfqAvatar').textContent = rfqNumber ? rfqNumber.charAt(0).toUpperCase() : '?';
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteRfqModal'));
        modal.show();
    }

    let openMenu = null,
        openBtn = null;

    function positionMenu(btn, menu) {
        const r = btn.getBoundingClientRect();
        menu.style.top = (r.bottom + 4) + 'px';
        menu.style.right = (window.innerWidth - r.right) + 'px';
        menu.style.left = 'auto';
    }

    function closeOpenMenu() {
        if (openMenu) {
            openMenu.style.display = 'none';
            openBtn?.classList.remove('active');
            openMenu = null;
            openBtn = null;
        }
    }

    function initActionMenus() {
        document.querySelectorAll('.action-toggle').forEach(btn => {
            const fresh = btn.cloneNode(true);
            btn.replaceWith(fresh);
            fresh.addEventListener('click', function(e) {
                e.stopPropagation();
                const menu = document.getElementById('actionMenu' + this.dataset.rfqId);
                if (!menu) return;
                if (openMenu === menu) {
                    closeOpenMenu();
                    return;
                }
                closeOpenMenu();
                menu.style.display = 'block';
                positionMenu(this, menu);
                this.classList.add('active');
                openMenu = menu;
                openBtn = this;
            });
        });
    }

    window.addEventListener('scroll', () => {
        if (openMenu && openBtn) positionMenu(openBtn, openMenu);
        repositionStatusDropdown();
    }, true);
    window.addEventListener('resize', () => {
        if (openMenu && openBtn) positionMenu(openBtn, openMenu);
        repositionStatusDropdown();
    });
    document.addEventListener('click', e => {
        if (openMenu && !e.target.closest('.action-toggle') && !e.target.closest('.action-menu')) closeOpenMenu();
    });

    let searchTimer, activeFilter = "<?= htmlspecialchars($_GET['filter'] ?? '') ?>";

    function loadTable(search, filter, page = 1) {
        document.getElementById("rfqTable").innerHTML =
            '<div class="text-center py-5"><div class="spinner-border text-primary opacity-50" style="width:1.5rem;height:1.5rem;border-width:2px;"></div><p class="text-muted small mt-2">Loading...</p></div>';

        const perPage = document.getElementById('perPage')?.value || <?= (int)$per_page ?>

        ;
        let url = 'rfqs.php?ajax=1&page=' + page + '&per_page=' + perPage;
        if (search) url += '&rfq_search=' + encodeURIComponent(search);
        if (filter) url += '&filter=' + encodeURIComponent(filter);

        fetch(url).then(r => r.text()).then(html => {
            document.getElementById("rfqTable").innerHTML = html;
            initAll();
            let qs = [];
            if (search) qs.push('rfq_search=' + encodeURIComponent(search));
            if (filter) qs.push('filter=' + encodeURIComponent(filter));
            qs.push('page=' + page);
            qs.push('per_page=' + perPage);
            history.pushState(null, '', qs.length ? '?' + qs.join('&') : '?');
        });
    }

    function filterByCard(filter) {
        activeFilter = activeFilter === filter ? '' : filter;
        document.querySelectorAll('.rfq-stat-card')
            .forEach(c => c.classList.toggle('active-filter', c.dataset.filter === activeFilter));
        document.getElementById('rfq_search').value = '';
        loadTable('', activeFilter, 1);
    }

    function doSearch(query) {
        activeFilter = '';
        document.querySelectorAll('.rfq-stat-card').forEach(c => c.classList.remove('active-filter'));
        loadTable(query.trim(), '', 1);
    }

    document.querySelectorAll('.rfq-stat-card').forEach(card =>
        card.addEventListener('click', function() {
            filterByCard(this.dataset.filter);
        })
    );
    document.getElementById('rfq_search').addEventListener('input', function() {
        clearTimeout(searchTimer);
        const q = this.value.trim();
        searchTimer = setTimeout(() => doSearch(q), 350);
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.target.matches('#rfq_search')) {
            e.preventDefault();
            clearTimeout(searchTimer);
            doSearch(e.target.value);
        }
    });

    /* pagination click */
    document.addEventListener('click', function(e) {
        const pg = e.target.closest('.pagination .page-link');
        if (!pg || !pg.dataset.page) return;
        e.preventDefault();
        const page = parseInt(pg.dataset.page, 10) || 1;
        const search = document.getElementById('rfq_search').value;
        loadTable(search, activeFilter, page);
    });

    /* status dropdown core – keep same signature as before */
    let currentDropdown = null,
        currentRfqId = null,
        currentStatusEl = null;

    function initStatusDropdowns() {
        document.querySelectorAll('.editable-status').forEach(el => {
            const fresh = el.cloneNode(true);
            el.replaceWith(fresh);
            fresh.addEventListener('click', e => {
                e.stopPropagation();
                showStatusDropdown(fresh);
            });
            fresh.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    showStatusDropdown(fresh);
                }
            });
        });
    }

    function showStatusDropdown(el) {
        closeOpenMenu();
        if (currentDropdown) hideStatusDropdown();

        const rfqId = el.dataset.rfqId,
            cur = el.dataset.status;

        currentRfqId = rfqId;
        currentStatusEl = el;
        document.querySelector(`.status-row-${rfqId}`)?.classList.add('status-dropdown-open');

        const opts = [{
                status: 'Ready for Review',
                color: '#f59e0b'
            },
            {
                status: 'Ready to Submit',
                color: '#0ea5e9'
            },
            {
                status: 'Submitted',
                color: '#10b981'
            }
        ];

        const dd = document.createElement('div');
        dd.className = 'status-dropdown';
        dd.innerHTML = opts.map(o =>
            `<div class="status-dropdown-option ${cur === o.status ? 'active' : ''}" data-status="${o.status}">
            <span style="width:8px;height:8px;border-radius:50%;background:${o.color};display:inline-block;flex-shrink:0;"></span>
            ${o.status}
        </div>`
        ).join('');

        dd.style.position = 'fixed';
        dd.style.zIndex = '9999';
        dd.style.right = 'auto';

        document.body.appendChild(dd);
        currentDropdown = dd;
        repositionStatusDropdown();

        dd.querySelectorAll('.status-dropdown-option').forEach(o =>
            o.addEventListener('click', e => {
                e.stopPropagation();
                updateStatus(rfqId, o.dataset.status, el);
            })
        );
    }

    function repositionStatusDropdown() {
        if (!currentDropdown || !currentStatusEl) return;
        const rect = currentStatusEl.getBoundingClientRect();

        if (rect.bottom < 60 || rect.top > window.innerHeight) {
            hideStatusDropdown();
            return;
        }

        currentDropdown.style.top = (rect.bottom + 4) + 'px';
        currentDropdown.style.left = rect.left + 'px';
        currentDropdown.style.minWidth = Math.max(rect.width, 175) + 'px';
    }

    function hideStatusDropdown() {
        currentDropdown?.remove();
        currentDropdown = null;
        currentStatusEl = null;
        document.querySelector(`.status-row-${currentRfqId}`)?.classList.remove('status-dropdown-open');
        currentRfqId = null;
    }
    document.addEventListener('click', e => {
        if (currentDropdown && !e.target.closest('.status-display') && !e.target.closest('.status-dropdown')) {
            hideStatusDropdown();
        }
    });

    function updateStatus(rfqId, newStatus, el) {
        el.classList.add('status-updating');
        el.innerHTML = '<i class="bi bi-arrow-repeat"></i> Saving...';
        const fd = new FormData();
        fd.append('update_status', '1');
        fd.append('rfq_id', rfqId);
        fd.append('status', newStatus);

        fetch(window.location.href, {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                el.classList.remove('status-updating');
                if (data.success) {
                    el.dataset.status = newStatus;
                    const cls = newStatus === 'Ready for Review' ?
                        'bg-warning text-dark' :
                        newStatus === 'Ready to Submit' ?
                        'bg-info' :
                        'bg-success';
                    el.className = `status-display badge ${cls} px-2 py-1 fw-semibold editable-status`;
                    el.style = 'cursor:pointer;display:block;width:100%;font-size:0.72rem;';
                    el.innerHTML = `${newStatus} <i class="bi bi-chevron-down ms-1 opacity-50" style="font-size:.6em;"></i>`;
                    hideStatusDropdown();
                } else {
                    el.innerHTML = `${el.dataset.status} <i class="bi bi-chevron-down ms-1 opacity-50" style="font-size:.6em;"></i>`;
                    alert('Update failed: ' + (data.error || 'Unknown error'));
                }
            }).catch(() => {
                el.classList.remove('status-updating');
                el.innerHTML = `${el.dataset.status} <i class="bi bi-chevron-down ms-1 opacity-50" style="font-size:.6em;"></i>`;
            });
    }

    /* Convert / Lost / Delete modal triggers */
    document.addEventListener('click', function(e) {
        const orderBtn = e.target.closest('.btn-convert-order');
        if (orderBtn) {
            e.stopPropagation();
            closeOpenMenu();
            const {
                rfqId,
                rfqNumber
            } = orderBtn.dataset;
            document.getElementById('orderModalRfqId').value = rfqId;
            document.getElementById('orderModalSubtitle').textContent = 'RFQ #' + rfqNumber;
            document.getElementById('orderModalInput').value = 'ORD-<?= date('Y') ?>-';
            setTimeout(() => bootstrap.Modal.getOrCreateInstance(document.getElementById('sharedOrderModal')).show(), 150);
        }

        const lostBtn = e.target.closest('.btn-mark-lost');
        if (lostBtn) {
            e.stopPropagation();
            closeOpenMenu();
            const {
                rfqId,
                rfqNumber,
                total
            } = lostBtn.dataset;
            document.getElementById('lostModalRfqId').value = rfqId;
            document.getElementById('lostModalSubtitle').textContent = 'RFQ #' + rfqNumber;
            document.getElementById('lostModalOurPrice').value = total || '';
            document.querySelector('#lostModalForm textarea[name="lost_note"]').value = '';
            document.querySelector('#lostModalForm input[name="awarded_price"]').value = '';
            document.querySelector('#lostModalForm input[name="awarded_to"]').value = '';
            setTimeout(() => bootstrap.Modal.getOrCreateInstance(document.getElementById('sharedLostModal')).show(), 150);
        }

        const deleteBtn = e.target.closest('.btn-delete-rfq');
        if (deleteBtn) {
            e.stopPropagation();
            closeOpenMenu();
            const {
                rfqId,
                rfqNumber,
                rfqCustomer,
                rfqTitle,
                rfqStatus,
                rfqCreated
            } = deleteBtn.dataset;
            openDeleteModal(rfqId, rfqNumber, rfqCustomer, rfqTitle, rfqStatus, rfqCreated);
        }
    });

    /* filter dropdown + per-page change */
    document.getElementById('perPage').addEventListener('change', () => {
        const search = document.getElementById('rfq_search').value;
        loadTable(search, activeFilter, 1);
    });

    /* init */
    function initAll() {
        initActionMenus();
        initStatusDropdowns();
    }
    document.addEventListener('DOMContentLoaded', initAll);
</script>

<?php
if (file_exists("../templates/footer.php")) include("../templates/footer.php");
else echo '</body></html>';
?>
