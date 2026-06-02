<?php
// include("../config/database.php");
include("../includes/auth.php");
include("../templates/header.php");
include("../templates/navbar.php");
check_auth();
require_role(['Admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bill_to = "Default Company";
    $customer_id = intval($_POST['customer_id']);
    $delivery_in = mysqli_real_escape_string($conn, $_POST['delivery_in']);
    $rfq_id = intval($_POST['rfq_id']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $expiration = $_POST['expiration'];

    mysqli_query(
        $conn,
        "INSERT INTO sales (bill_to, customer_id, delivery_in, rfq_id, title, expiration) 
         VALUES ('$bill_to','$customer_id','$delivery_in','$rfq_id','$title','$expiration')"
    );
    $sale_id = mysqli_insert_id($conn);

    if (isset($_POST['product']) && is_array($_POST['product'])) {
        foreach ($_POST['product'] as $i => $product) {
            $product = trim($product);
            if ($product === "")
                continue;

            $qty = intval($_POST['qty'][$i]);
            $unit_price = floatval($_POST['unit_price'][$i]);
            $cost_price = $_POST['cost_price'][$i] !== "" ? floatval($_POST['cost_price'][$i]) : $unit_price;

            $check = mysqli_fetch_assoc(
                mysqli_query(
                    $conn,
                    "SELECT id, catalog_file FROM products 
                     WHERE name='" . mysqli_real_escape_string($conn, $product) . "' 
                     LIMIT 1"
                )
            );
            if (!$check)
                continue;

            $product_id = $check['id'];
            $catalog_file = "";

            if (!empty($check['catalog_file'])) {
                $catalog_file = $check['catalog_file'];
            }

            if (!empty($_FILES['catalog']['name'][$i])) {
                $targetDir = "../uploads/catalogs/";
                if (!is_dir($targetDir))
                    mkdir($targetDir, 0777, true);

                $fileTmp = $_FILES['catalog']['tmp_name'][$i];
                $fileName = time() . "_" . basename($_FILES['catalog']['name'][$i]);
                $targetFile = $targetDir . $fileName;

                if (is_uploaded_file($fileTmp) && move_uploaded_file($fileTmp, $targetFile)) {
                    $catalog_file = "uploads/catalogs/" . $fileName;
                }
            }

            if (empty($catalog_file) && !empty($_POST['existing_catalog'][$i])) {
                $catalog_file = $_POST['existing_catalog'][$i];
            }

            mysqli_query(
                $conn,
                "INSERT INTO sale_lines (sale_id, product_id, qty, unit_price, cost_price, catalog_file) 
                 VALUES ('$sale_id','$product_id','$qty','$unit_price','$cost_price','$catalog_file')"
            );
        }
    }

    header("Location: sales_edit.php?id=$sale_id");
    exit;
}

$customers = mysqli_query($conn, "SELECT * FROM customers ORDER BY name ASC");
$rfqs = mysqli_query($conn, "SELECT * FROM rfqs ORDER BY id DESC");
?>

<div class="container mt-4">
    <h4><i class="bi bi-arrow-left-circle" title="Back" onclick="history.go(-1); return false;"></i> New Sale</h4>
    <form method="post" enctype="multipart/form-data" class="card shadow-sm border-0 p-3 mb-3">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Bill To</label>
                <input type="text" class="form-control" value="Default Company" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Customer</label>
                <select name="customer_id" class="form-control" required>
                    <option value="">Select Customer</option>
                    <?php while ($c = mysqli_fetch_assoc($customers)) { ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php } ?>
                    <option value="add_new">- Add Customer -</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">RFQ #</label>
                <select name="rfq_id" class="form-control">
                    <option value="">Select RFQ</option>
                    <?php while ($r = mysqli_fetch_assoc($rfqs)) { ?>
                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['code']) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">RFQ Title</label>
                <input type="text" name="title" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Delivery In</label>
                <input type="text" name="delivery_in" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Quotation Expiration</label>
                <input type="date" name="expiration" class="form-control" required>
            </div>
        </div>

        <div class="card shadow-sm border-0 mt-3">
            <div class="card-body">
                <h5 class="card-title mb-3">Order Line</h5>
                <div id="lines"></div>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addLine()">+ Add Product</button>
            </div>
        </div>

        <div class="mt-3">
            <button class="btn btn-primary">Save Sale</button>
            <button title="Cancel" onclick="history.go(-1); return false;"
                class="btn btn-outline-secondary">Cancel</button>
        </div>
    </form>
</div>

<script>
    function initProductAutocomplete(line) {
        const productInput = line.querySelector(".productSearch");
        const dropdown = line.querySelector(".productDropdown");
        const catalogInput = line.querySelector(".catalogInput");
        const catalogPreview = line.querySelector(".catalogPreview");
        let timeout = null;

        productInput.addEventListener("input", function () {
            clearTimeout(timeout);
            let term = this.value;
            if (term.length < 2) { dropdown.style.display = "none"; return; }
            timeout = setTimeout(() => {
                fetch("search-products.php?term=" + encodeURIComponent(term))
                    .then(res => res.json())
                    .then(data => {
                        dropdown.innerHTML = "";
                        if (data.length > 0) {
                            data.forEach(p => {
                                let option = document.createElement("a");
                                option.href = "#";
                                option.className = "list-group-item list-group-item-action";
                                option.textContent = p.name;
                                option.addEventListener("click", function (e) {
                                    e.preventDefault();
                                    productInput.value = p.name;
                                    line.querySelector(".unit_price").value = p.sales_price || "";
                                    line.querySelector(".cost_price").value = p.cost_price || "";

                                    if (p.catalog_file) {
                                        catalogInput.value = "";
                                        catalogPreview.innerHTML = `<a href="../${p.catalog_file}" target="_blank" class="btn btn-sm btn-outline-secondary">View Catalog</a>`;
                                        let hidden = line.querySelector(".existingCatalog");
                                        if (!hidden) {
                                            hidden = document.createElement("input");
                                            hidden.type = "hidden";
                                            hidden.name = "existing_catalog[]";
                                            hidden.className = "existingCatalog";
                                            line.appendChild(hidden);
                                        }
                                        hidden.value = p.catalog_file;
                                    } else {
                                        catalogPreview.innerHTML = `<span class="text-muted">No Catalog</span>`;
                                    }
                                    dropdown.style.display = "none";
                                });
                                dropdown.appendChild(option);
                            });
                        }
                        dropdown.style.display = "block";
                    });
            }, 300);
        });
    }

    function addLine() {
        const div = document.createElement("div");
        div.className = "row g-2 mb-2 line";
        div.innerHTML = `
        <div class="col-md-3 position-relative">
            <input type="text" name="product[]" class="form-control productSearch" placeholder="Product" autocomplete="off" required>
            <div class="list-group position-absolute w-100 productDropdown" style="z-index:1000; display:none;"></div>
        </div>
        <div class="col"><input type="number" min="0" value="1" class="form-control" name="qty[]" required></div>
        <div class="col"><input type="number" min="0" step="0.01" class="form-control unit_price" name="unit_price[]" placeholder="Unit Price" required></div>
        <div class="col"><input type="number" min="0" step="0.01" class="form-control cost_price" name="cost_price[]" placeholder="Cost Price"></div>
        <div class="col">
            <input type="file" name="catalog[]" class="form-control catalogInput" accept="application/pdf">
            <div class="catalogPreview mt-1"></div>
        </div>
        <div class="col-auto"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.line').remove()">x</button></div>
    `;
        document.getElementById("lines").appendChild(div);
        initProductAutocomplete(div);
    }
</script>

<?php include("add-product-model.php"); ?>
<?php include("add-customer-model.php"); ?>
<?php include("../templates/footer.php"); ?>