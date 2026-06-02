<?php
include("../config/database.php");
include("../includes/auth.php");
check_auth();
require_role(['Admin']);

/* DELETE */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM vendors WHERE id=$id");
    header("Location: vendors.php");
    exit;
}

/* SEARCH */
$search = trim($_GET['search'] ?? '');
$where = "";

if ($search !== '') {
    $safe = mysqli_real_escape_string($conn, $search);
    $where = "WHERE name LIKE '%$safe%' 
              OR email LIKE '%$safe%' 
              OR phone LIKE '%$safe%'";
}

$vendors = mysqli_query($conn, "
    SELECT * FROM vendors
    $where
    ORDER BY id DESC
");

include("../templates/header.php");
include("../templates/navbar.php");
?>

<div class="container-fluid mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Vendors</h4>

        <div class="d-flex gap-2">
            <form method="get" class="d-flex gap-2">
                <input type="text"
                       name="search"
                       value="<?= htmlspecialchars($search) ?>"
                       class="form-control form-control-sm"
                       placeholder="Search vendor...">
                <button class="btn btn-outline-secondary btn-sm">Search</button>
            </form>

            <a href="vendor_add.php" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i> New Vendor
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Created</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($v = mysqli_fetch_assoc($vendors)) { ?>
                            <tr>
                                <td><?= htmlspecialchars($v['name']) ?></td>
                                <td><?= htmlspecialchars($v['email']) ?></td>
                                <td><?= htmlspecialchars($v['phone']) ?></td>
                                <td><?= htmlspecialchars($v['address']) ?></td>
                                <td><?= date("m/d/Y", strtotime($v['created_at'])) ?></td>

                                <td class="text-center">
                                    <a href="?delete=<?= $v['id'] ?>"
                                       onclick="return confirm('Delete this vendor?');"
                                       class="btn btn-sm btn-outline-danger">
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
