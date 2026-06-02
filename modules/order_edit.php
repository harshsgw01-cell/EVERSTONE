<?php
include("../includes/auth.php");
include("../templates/header.php");
include("../templates/navbar.php");
check_auth();
require_role(['Admin']);

if (!isset($_GET['id'])) {
    header("Location: orders.php");
    exit;
}

$id = (int) $_GET['id'];
$order = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM orders WHERE id=$id"));
if (!$order) {
    echo "<div class='alert alert-danger'>Order not found.</div>";
    include("../templates/footer.php");
    exit;
}

$customers = mysqli_query($conn, "SELECT id,name FROM customers");
$rfqs = mysqli_query($conn, "SELECT id FROM rfqs");
$lines = mysqli_query($conn, "SELECT * FROM order_lines WHERE order_id=$id");

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $customer_id = $_POST['customer_id'];
    $rfq_id = $_POST['rfq_id'] ?: "NULL";
    $status = $_POST['status'];

    mysqli_query($conn, "UPDATE orders SET customer_id='$customer_id', rfq_id=$rfq_id, status='$status' WHERE id=$id");

    mysqli_query($conn, "DELETE FROM order_lines WHERE order_id=$id");

    $products = $_POST['product'];
    $qtys = $_POST['qty'];
    $unit_prices = $_POST['unit_price'];
    $cost_prices = $_POST['cost_price'];

    $total_cost = 0;
    $total_price = 0;

    foreach ($products as $i => $product) {
        $product = trim($product);
        if ($product == "")
            continue;

        $qty = (int) $qtys[$i];
        $unit = (float) $unit_prices[$i];
        $cost = $cost_prices[$i] === "" ? $unit : (float) $cost_prices[$i];

        mysqli_query($conn, "INSERT INTO order_lines (order_id, product, qty, unit_price, cost_price) 
                    VALUES ('$id','$product','$qty','$unit','$cost')");

        $total_cost += $qty * $cost;
        $total_price += $qty * $unit;
    }

    $profit = $total_price - $total_cost;
    $margin = $total_price > 0 ? ($profit / $total_price) * 100 : 0;
    mysqli_query($conn, "UPDATE orders 
                SET total_cost='$total_cost', total_price='$total_price', profit='$profit', margin='$margin' 
                WHERE id=$id");

    header("Location: orders.php");
    exit;
}
?>

<div class="container mt-4">
    <h4><i class="bi bi-arrow-left-circle" title="Back" onclick="history.go(-1); return false;"></i> Edit Order
        #<?= $order['code'] ?></h4>
    <div class="card">
        <div class="card-body">
            <form method="post">
                <div class="mb-3">
                    <label>Customer</label>
                    <select class="form-control" name="customer_id" required>
                        <?php while ($c = mysqli_fetch_assoc($customers)) { ?>
                            <option value="<?= $c['id'] ?>" <?= $c['id'] == $order['customer_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Linked RFQ (optional)</label>
                    <select class="form-control" name="rfq_id">
                        <option value="">None</option>
                        <?php
                        mysqli_data_seek($rfqs, 0);
                        while ($r = mysqli_fetch_assoc($rfqs)) { ?>
                            <option value="<?= $r['id'] ?>" <?= $order['rfq_id'] == $r['id'] ? 'selected' : '' ?>>
                                RFQ #<?= $r['id'] ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Status</label>
                    <select class="form-control" name="status">
                        <option <?= $order['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option <?= $order['status'] == 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option <?= $order['status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>

                <h6>Order Lines</h6>
                <div id="lines">
                    <?php while ($line = mysqli_fetch_assoc($lines)) { ?>
                        <div class="row g-2 mb-2 line">
                            <div class="col-md-3 position-relative">
                                <input type="text" name="product[]" class="form-control productSearch" placeholder="Product"
                                    autocomplete="off" value="<?= htmlspecialchars($line['product']) ?>" required>
                                <div class="list-group position-absolute w-100 productDropdown"
                                    style="z-index:1000; display:none;"></div>
                            </div>
                            <div class="col">
                                <input type="number" min="0" value="<?= $line['qty'] ?>" class="form-control" name="qty[]"
                                    required>
                            </div>
                            <div class="col">
                                <input type="number" min="0" step="0.01" class="form-control unit_price" name="unit_price[]"
                                    value="<?= $line['unit_price'] ?>" required>
                            </div>
                            <div class="col">
                                <input type="number" min="0" step="0.01" class="form-control cost_price" name="cost_price[]"
                                    value="<?= $line['cost_price'] ?>">
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <button type="button" class="btn btn-sm btn-secondary" onclick="addLine()">+ Add Line</button>

                <br><br>
                <button class="btn btn-primary">Update Order</button>
                <button title="Cancel" onclick="history.go(-1); return false;" class="btn btn-secondary">Cancel</button>
            </form>
        </div>
    </div>
</div>

<?php include("add-product-model.php"); ?>

<script>
    let activeProductInput = null;

    function initProductAutocomplete(line) {
        const productInput = line.querySelector(".productSearch");
        const dropdown = line.querySelector(".productDropdown");
        const modal = document.getElementById("newProductModal");

        let timeout = null;

        productInput.addEventListener("input", function () {
            clearTimeout(timeout);
            let term = this.value;
            if (term.length < 2) {
                dropdown.style.display = "none";
                return;
            }
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
                                    dropdown.style.display = "none";
                                });
                                dropdown.appendChild(option);
                            });

                            let addOption = document.createElement("a");
                            addOption.href = "#";
                            addOption.className = "list-group-item list-group-item-action text-primary";
                            addOption.textContent = "- Add Product -";
                            addOption.addEventListener("click", function (e) {
                                e.preventDefault();
                                dropdown.style.display = "none";
                                activeProductInput = productInput;
                                new bootstrap.Modal(modal).show();
                            });
                            dropdown.appendChild(addOption);
                        } else {
                            let addOption = document.createElement("a");
                            addOption.href = "#";
                            addOption.className = "list-group-item list-group-item-action text-primary";
                            addOption.textContent = "- Add Product -";
                            addOption.addEventListener("click", function (e) {
                                e.preventDefault();
                                dropdown.style.display = "none";
                                activeProductInput = productInput;
                                new bootstrap.Modal(modal).show();
                            });
                            dropdown.appendChild(addOption);
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
            <div class="col-auto"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.line').remove()">x</button></div>
        `;
        document.getElementById("lines").appendChild(div);
        initProductAutocomplete(div);
    }

    document.querySelectorAll(".line").forEach(initProductAutocomplete);

    document.getElementById("newProductForm").addEventListener("submit", function (e) {
        e.preventDefault();
        let formData = new FormData(this);
        fetch("save-product.php", { method: "POST", body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (activeProductInput) {
                        activeProductInput.value = data.name;
                        activeProductInput.closest(".line").querySelector(".unit_price").value = data.sales_price || "";
                        activeProductInput.closest(".line").querySelector(".cost_price").value = data.cost_price || "";
                    }
                    bootstrap.Modal.getInstance(document.getElementById("newProductModal")).hide();
                    this.reset();
                    activeProductInput = null;
                } else {
                    alert("Error saving product");
                }
            });
    });
</script>

<?php include("../templates/footer.php"); ?>