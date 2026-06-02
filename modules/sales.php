<?php
include("../config/database.php");
include("../includes/auth.php");
include("../templates/header.php");
include("../templates/navbar.php");
check_auth();
require_role(['Admin']);

$sales = mysqli_query($conn, "
    SELECT s.*, c.name AS customer 
    FROM sales s
    JOIN customers c ON s.customer_id = c.id
    ORDER BY s.id DESC
");
?>
<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3 px-2">
        <h4>Sales</h4>
        <a href="sales_add.php" class="btn btn-primary">New Sale</a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Sale #</th>
                            <th>Bill To</th>
                            <th>Customer</th>
                            <th>Delivery In</th>
                            <th>RFQ #</th>
                            <th>Title</th>
                            <th>Expiration</th>
                            <th>Catalogs</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($s = mysqli_fetch_assoc($sales)) { ?>
                            <tr onclick="window.location='sales_edit.php?id=<?= $s['id'] ?>'">
                                <td>
                                    <?= htmlspecialchars($s['code']) ?>
                                </td>
                                <td><?= htmlspecialchars($s['bill_to']) ?></td>
                                <td><?= htmlspecialchars($s['customer']) ?></td>
                                <td><?= htmlspecialchars($s['delivery_in']) ?></td>
                                <td><?= htmlspecialchars($s['rfq_id']) ?></td>
                                <td><?= htmlspecialchars($s['title']) ?></td>
                                <td><?= date("m/d/Y", strtotime($s['expiration'])) ?></td>
                                <td>
                                    <?php
                                    $lines = mysqli_query(
                                        $conn,
                                        "
                                        SELECT sl.catalog_file
                                        FROM sale_lines sl
                                        WHERE sl.sale_id = " . intval($s['id'])
                                    );
                                    $catalogs = [];
                                    while ($l = mysqli_fetch_assoc($lines)) {
                                        if (!empty($l['catalog_file'])) {
                                            $catalogs[] = $l['catalog_file'];
                                        }
                                    }
                                    if (count($catalogs) > 0) {
                                        foreach ($catalogs as $i => $file) {
                                            echo '<a href="../' . htmlspecialchars($file) . '" target="_blank" 
                                                    class="btn btn-sm btn-outline-secondary me-2"
                                                    title="View Catalog ' . ($i + 1) . '">
                                                    <i class="bi bi-file-earmark-pdf"></i>
                                                  </a>';
                                        }
                                    } else {
                                        echo '<span class="text-muted">—</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="sales_delete.php?id=<?= $s['id'] ?>"
                                        class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Delete this sale?')">Delete</a>

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