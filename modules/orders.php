<?php
include("../includes/auth.php");
include("../templates/header.php");
include("../templates/navbar.php");
check_auth();

$orders = mysqli_query($conn, "
    SELECT o.*, 
           c.name AS customer,
           sp.name AS salesPerson,
           bt.title AS billTo,
           b.name AS buyer,
           st.name AS shipTo
    FROM orders o
    JOIN customers c ON o.customer_id=c.id
    LEFT JOIN salesperson sp ON o.salesPerson_id=sp.id
    LEFT JOIN billto bt ON o.billTo_id=bt.id
    LEFT JOIN buyer b ON o.buyer_id=b.id
    LEFT JOIN shipto st ON o.shipTo_id=st.id
    ORDER BY o.id DESC
");

if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    mysqli_query($conn, "DELETE FROM orders WHERE id='$id'");
    echo "<script>window.location.href = 'orders.php';</script>";
    exit;
}
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3 px-2">
        <h4>Orders</h4>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Order #</th>
                            <th>Title</th>
                            <th>Customer</th>
                            <th>Sales Person</th>
                            <th>Bill To</th>
                            <th>Buyer</th>
                            <th>Ship To</th>
                            <th>Validity</th>
                            <th>Creation Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($o = mysqli_fetch_assoc($orders)) { ?>
                            <tr onclick="window.location='order.php?id=<?= $o['id'] ?>'">
                                <td><?= htmlspecialchars($o['order_number']) ?></td>
                                <td><?= htmlspecialchars($o['rfq_title']) ?></td>
                                <td><?= htmlspecialchars($o['customer']) ?></td>
                                <td><?= htmlspecialchars($o['salesPerson']) ?></td>
                                <td><?= htmlspecialchars($o['billTo']) ?></td>
                                <td><?= htmlspecialchars($o['buyer']) ?></td>
                                <td><?= htmlspecialchars($o['shipTo']) ?></td>
                                <td>
                                    <?php
                                    $quoteDate = new DateTime($o['quote_date']);

                                    $validityStr = strtolower(trim($o['validity']));
                                    $validityInterval = null;

                                    if (strpos($validityStr, 'month') !== false) {
                                        $months = (int) filter_var($validityStr, FILTER_SANITIZE_NUMBER_INT);
                                        $validityInterval = new DateInterval("P{$months}M");
                                    } elseif (strpos($validityStr, 'day') !== false) {
                                        $days = (int) filter_var($validityStr, FILTER_SANITIZE_NUMBER_INT);
                                        $validityInterval = new DateInterval("P{$days}D");
                                    }

                                    if ($validityInterval) {
                                        $expiryDate = (clone $quoteDate)->add($validityInterval);
                                        $today = new DateTime();
                                        $daysLeft = $today->diff($expiryDate)->days;

                                        if ($today > $expiryDate) {
                                            echo "<span class='text-danger'>Expired</span>";
                                        } else {
                                            echo $daysLeft . " days left";
                                        }
                                    } else {
                                        echo "N/A";
                                    }
                                    ?>
                                </td>
                                <td><?= date("m/d/Y", strtotime($o['created_at'])) ?></td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($o['rfq_status']) ?></span></td>
                                <td onclick="event.stopPropagation();">
                                    <a href="?delete=<?= $o['id'] ?>" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Delete this Order?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include("../templates/footer.php"); ?>