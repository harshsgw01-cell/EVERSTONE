<?php
include("../config/database.php");
include("../includes/auth.php");
include("../templates/header.php");
include("../templates/navbar.php");
check_auth();
require_role(['Admin']);

$id = intval($_GET['id']);

$sale = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT s.*, c.name as customer_name 
    FROM sales s 
    JOIN customers c ON s.customer_id = c.id 
    WHERE s.id='$id'
"));

if (isset($_POST['update_sale'])) {
    $delivery_in = mysqli_real_escape_string($conn, $_POST['delivery_in']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $expiration = $_POST['expiration'];

    mysqli_query($conn, "UPDATE sales SET delivery_in='$delivery_in', title='$title', expiration='$expiration' WHERE id='$id'");
    header("Location: sales_edit.php?id=$id");
    exit;
}

if (isset($_POST['add_line'])) {
    $product_name = trim($_POST['product']);
    $qty = intval($_POST['qty']);
    $unit_price = floatval($_POST['unit_price']);
    $cost_price = ($_POST['cost_price'] !== '') ? floatval($_POST['cost_price']) : $unit_price;
    $catalog_file = null;

    if (!empty($_FILES['catalog_file']['name'])) {
        $upload_dir = "../uploads/catalogs/";
        if (!is_dir($upload_dir))
            mkdir($upload_dir, 0777, true);

        $file_name = time() . "_" . basename($_FILES['catalog_file']['name']);
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['catalog_file']['tmp_name'], $target_file)) {
            $catalog_file = "uploads/catalogs/" . $file_name;
        }
    } elseif (!empty($_POST['existing_catalog'])) {
        $catalog_file = $_POST['existing_catalog'];
    }

    $product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM products WHERE name='$product_name' LIMIT 1"));
    if (!$product) {
        echo "<script>alert('Invalid product. Please select from list.'); window.location.href='sales_edit.php?id=$id';</script>";
        exit;
    }

    mysqli_query($conn, "INSERT INTO sale_lines (sale_id, product_id, qty, unit_price, cost_price, catalog_file) 
                         VALUES ('$id','{$product['id']}','$qty','$unit_price','$cost_price','$catalog_file')");
    header("Location: sales_edit.php?id=$id");
    exit;
}

if (isset($_GET['delete_line'])) {
    $line_id = intval($_GET['delete_line']);
    mysqli_query($conn, "DELETE FROM sale_lines WHERE id='$line_id'");
    header("Location: sales_edit.php?id=$id");
    exit;
}

$lines = mysqli_query($conn, "
    SELECT sl.*, p.name AS product_name 
    FROM sale_lines sl
    LEFT JOIN products p ON sl.product_id = p.id
    WHERE sl.sale_id='$id'
");

if (isset($_POST['send_message'])) {
    $from = $_SESSION['useremail'];
    $to = mysqli_real_escape_string($conn, $_POST['to']);
    $msg = mysqli_real_escape_string($conn, $_POST['message']);

    mysqli_query($conn, "INSERT INTO messages (sale_id, from_email, to_email, message) 
                         VALUES ('$id', '$from', '$to', '$msg')");

    $subject = "Message regarding Sale #$id - " . htmlspecialchars($sale['title']);
    $headers = "From: {$from}\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h3 style='color:#333;'>New message regarding Sale #$id</h3>
            <p><strong>From:</strong> {$from}</p>
            <p><strong>Message:</strong></p>
            <div style='padding:10px; border-left:3px solid #4CAF50; background:#f9f9f9;'>
                " . nl2br($msg) . "
            </div>
            <p style='margin-top:20px;'>
                You can view the full sale details here:<br>
                <a href='https://everstonetech.ca/modules/sales_edit.php?id=$id'>
                    View Sale #$id
                </a>
            </p>
            <hr>
            <p style='font-size:12px;color:#888;'>This message was sent via Everstone Messaging System.</p>
        </body>
        </html>
    ";

    $sent = @mail($to, $subject, $body, $headers);
    if (!$sent) {
        echo "<script>alert('Message saved, but email could not be sent.');</script>";
    }

    header("Location: sales_edit.php?id=$id");
    exit;
}


$messages = mysqli_query($conn, "
    SELECT * FROM messages 
    WHERE sale_id='$id' 
    ORDER BY created_at ASC
");
?>

<div class="container mt-4">
    <h4><i class="bi bi-arrow-left-circle" title="Back" onclick="history.go(-1); return false;"></i> Edit Sale
        #<?= $sale['id'] ?>
    </h4>

    <form method="post" class="card shadow-sm border-0 p-3 mb-3">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Bill To</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($sale['bill_to']) ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Customer</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($sale['customer_name']) ?>"
                    readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Delivery In</label>
                <input type="text" name="delivery_in" value="<?= htmlspecialchars($sale['delivery_in']) ?>"
                    class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Quotation Expiration</label>
                <input type="date" name="expiration" value="<?= $sale['expiration'] ?>" class="form-control">
            </div>
            <div class="col-md-12">
                <label class="form-label">Quotation Title</label>
                <input type="text" name="title" value="<?= htmlspecialchars($sale['title']) ?>" class="form-control">
            </div>
        </div>
        <div class="mt-3">
            <button class="btn btn-primary" name="update_sale">Update</button>
        </div>
    </form>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <h5 class="card-title mb-3">Order Line</h5>

            <form method="post" enctype="multipart/form-data" class="row g-2 mb-3">
                <div class="col-md-3 position-relative">
                    <input type="text" name="product" id="productSearch" class="form-control" placeholder="Product"
                        autocomplete="off" required>
                    <div id="productDropdown" class="list-group position-absolute w-100"
                        style="z-index:1000; display:none;"></div>
                </div>
                <div class="col-md-2">
                    <input type="number" min="1" value="1" name="qty" class="form-control" placeholder="Qty" required>
                </div>
                <div class="col-md-2">
                    <input type="number" min="0" step="0.01" name="unit_price" class="form-control"
                        placeholder="Unit Price" required>
                </div>
                <div class="col-md-2">
                    <input type="number" min="0" step="0.01" name="cost_price" class="form-control"
                        placeholder="Cost Price">
                </div>
                <div class="col-md-3">
                    <input type="file" name="catalog_file" class="form-control mb-1" accept="application/pdf">
                    <input type="hidden" name="existing_catalog" id="existingCatalog">
                    <a href="#" target="_blank" id="catalogLink" class="btn btn-sm btn-outline-secondary w-100"
                        style="display:none;">View Catalog</a>
                </div>
                <div class="col-12 mt-2">
                    <button class="btn btn-outline-success" name="add_line">Add Line</button>
                </div>
            </form>

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Cost Price</th>
                        <th>Catalog</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($line = mysqli_fetch_assoc($lines)) { ?>
                        <tr>
                            <td><?= htmlspecialchars($line['product_name']) ?></td>
                            <td><?= $line['qty'] ?></td>
                            <td>$<?= number_format($line['unit_price'], 2) ?></td>
                            <td>$<?= number_format($line['cost_price'], 2) ?></td>
                            <td>
                                <?php if ($line['catalog_file']) { ?>
                                    <a href="../<?= htmlspecialchars($line['catalog_file']) ?>" target="_blank"
                                        class="btn btn-sm btn-outline-secondary">View PDF</a>
                                <?php } else { ?>
                                    <span class="text-muted">No File</span>
                                <?php } ?>
                            </td>
                            <td>
                                <a href="?id=<?= $id ?>&delete_line=<?= $line['id'] ?>"
                                    class="btn btn-outline-danger btn-sm"
                                    onclick="return confirm('Delete this line?')">Delete</a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <h5 class="card-title mb-3">Messages</h5>

            <div class="border rounded p-3 mb-3" style="max-height: 300px; overflow-y:auto; background:#f9f9f9;">
                <?php while ($msg = mysqli_fetch_assoc($messages)) {
                    $isMe = ($msg['from_email'] == $_SESSION['useremail']); ?>

                    <div class="d-flex mb-2 <?= $isMe ? 'justify-content-end' : 'justify-content-start' ?>">
                        <div class="p-2 rounded 
                        <?= $isMe ? 'bg-primary text-white' : 'bg-light border' ?>" style="max-width:70%;">
                            <div class="small fw-bold"><?= htmlspecialchars($msg['from_email']) ?></div>
                            <div><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                            <div class="small text-muted"><?= date("M d, H:i", strtotime($msg['created_at'])) ?></div>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <form method="post" class="row g-2">
                <div class="col-12">
                    <input type="text" name="to" class="form-control" placeholder="To (email)" required>
                </div>
                <div class="col-12">
                    <textarea name="message" class="form-control" placeholder="Type your message..."
                        required></textarea>
                </div>
                <div class="col-12 text-end">
                    <button class="btn btn-outline-success" name="send_message">Send</button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const productInput = document.getElementById("productSearch");
        const dropdown = document.getElementById("productDropdown");
        const modal = document.getElementById("newProductModal");
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
                                    document.querySelector("input[name='unit_price']").value = p.sales_price || "";
                                    document.querySelector("input[name='cost_price']").value = p.cost_price || "";
                                    const existingCatalogInput = document.getElementById("existingCatalog");
                                    const catalogLink = document.getElementById("catalogLink");

                                    if (p.catalog_file) {
                                        existingCatalogInput.value = p.catalog_file;
                                        catalogLink.href = "../" + p.catalog_file;
                                        catalogLink.style.display = "block";
                                    } else {
                                        existingCatalogInput.value = "";
                                        catalogLink.style.display = "none";
                                    }
                                    dropdown.style.display = "none";
                                });
                                dropdown.appendChild(option);
                            });
                        } else {
                            let addOption = document.createElement("a");
                            addOption.href = "#";
                            addOption.className = "list-group-item list-group-item-action text-primary";
                            addOption.textContent = "- Add Product -";
                            addOption.addEventListener("click", function (e) {
                                e.preventDefault();
                                dropdown.style.display = "none";
                                new bootstrap.Modal(modal).show();
                            });
                            dropdown.appendChild(addOption);
                        }
                        dropdown.style.display = "block";
                    });
            }, 300);
        });

        document.getElementById("newProductForm").addEventListener("submit", function (e) {
            e.preventDefault();
            let formData = new FormData(this);
            fetch("save-product.php", { method: "POST", body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        productInput.value = data.name;
                        bootstrap.Modal.getInstance(modal).hide();
                        this.reset();
                    } else {
                        alert("Error saving product");
                    }
                });
        });
    });
</script>

<?php include("add-product-model.php"); ?>
<?php include("../templates/footer.php"); ?>