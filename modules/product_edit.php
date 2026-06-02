<?php
// include("../config/database.php");
include("../includes/auth.php");
include("../templates/header.php");
include("../templates/navbar.php");
check_auth();
require_role(['Admin']);

$id = intval($_GET['id']);
$product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM products WHERE id=$id"));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $sales_price = $_POST['sales_price'] ?? 0;
    $cost_price = $_POST['cost_price'] ?? 0;
    $catalog_file = $product['catalog_file'];

    if (isset($_FILES['catalog_file']) && $_FILES['catalog_file']['error'] == 0) {
        $uploadDir = "../uploads/catalogs/";
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0777, true);

        $fileName = time() . "_" . basename($_FILES['catalog_file']['name']);
        $filePath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['catalog_file']['tmp_name'], $filePath)) {
            $catalog_file = "uploads/catalogs/" . $fileName;
        }
    }

    mysqli_query($conn, "UPDATE products 
                         SET name='$name', sales_price='$sales_price', cost_price='$cost_price', catalog_file=" . ($catalog_file ? "'$catalog_file'" : "NULL") . " 
                         WHERE id=$id");

    header("Location: products.php");
    exit;
}
?>

<div class="container mt-4">
    <h4><i class="bi bi-arrow-left-circle" title="Back" onclick="history.go(-1); return false;"></i> Edit
        Product</h4>
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input class="form-control" name="name" value="<?= htmlspecialchars($product['name']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Sales Price</label>
                    <input type="number" step="0.01" class="form-control" name="sales_price"
                        value="<?= $product['sales_price'] ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Cost Price</label>
                    <input type="number" step="0.01" class="form-control" name="cost_price"
                        value="<?= $product['cost_price'] ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Catalog (PDF)</label><br>
                    <?php if ($product['catalog_file']) { ?>
                        <a href="../<?= $product['catalog_file'] ?>" target="_blank"
                            class="btn btn-sm btn-secondary mb-2">View Current</a><br>
                    <?php } ?>
                    <input type="file" name="catalog_file" class="form-control" accept="application/pdf">
                </div>
                <button class="btn btn-success">Save Changes</button>
            </form>
        </div>
    </div>
</div>

<?php include("../templates/footer.php"); ?>