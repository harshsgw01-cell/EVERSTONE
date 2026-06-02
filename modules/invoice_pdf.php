<?php
session_start();
include("../config/database.php");
include("../includes/auth.php");
check_auth();
require_role(['Admin']);

if (!isset($_GET['id'])) {
    header("Location: invoices.php");
    exit;
}

$id = (int) $_GET['id'];

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
    JOIN customers c ON o.customer_id = c.id
    WHERE i.id = $id
");

$invoice = mysqli_fetch_assoc($invoice_q);

if (!$invoice) {
    include("../templates/header.php");
    echo "<div class='container mt-5'><div class='alert alert-danger'>Invoice not found.</div></div>";
    include("../templates/footer.php");
    exit;
}

$order_lines = mysqli_query(
    $conn,
    "SELECT * FROM order_lines WHERE order_id=" . (int)$invoice['order_id']
);

include("../templates/header.php");
// include("../templates/navbar.php");
?>

<section class="bg-white">
    <div class="w-100">
        <img src="../assets/pdf-head-img.jpg" class="img-fluid w-100" alt="PDF Head">
    </div>

    <div class="bg-secondary pb-2">
        <div class="d-flex justify-between">
            <div class="w-100 ms-5">
                <h1 class="pdf-head-name mt-5">Emma Willam</h1>
                <h3 class="text-white ms-3">Signed and Authorized By</h3>
                <h5 class="text-white ms-3">Sign Id # 847093072321</h5>
                <h1 class="pdf-header-w"><span class="span-blue">Women</span> <span class="span-red">Owned</span> Small
                    <span class="span-red">Business</span>
                </h1>
            </div>
            <img src="../assets/Everstone.png" class="img-fluid w-50" alt="PDF Logo">
        </div>

        <h1 class="pdf-head-title text-white ms-4">US Forces</br>Tactical Supply</h1>

        <div class="bg-white pb-2 mx-4 mb-2">
            <div class="d-flex border mt-4 p-3 align-items-center">
                <div class="w-50">
                    <div class="mb-2 d-flex align-items-center">
                        <i class="bi bi-telephone-fill text-danger me-2 border border-danger rounded-3 p-2"></i>
                        <span>236-953-7860</span>
                    </div>
                    <div class="mb-2 d-flex align-items-center">
                        <i class="bi bi-envelope-fill text-danger me-2 border border-danger rounded-3 p-2"></i>
                        <span>sales@everstonetech.ca</span>
                    </div>
                    <div class="mb-2 d-flex align-items-center">
                        <i class="bi bi-globe2 text-danger me-2 border border-danger rounded-3 p-2"></i>
                        <span>everstonetech.ca</span>
                    </div>
                    <div class="mb-2 d-flex align-items-center">
                        <i class="bi bi-geo-alt-fill text-danger me-2 border border-danger rounded-3 p-2"></i>
                        <span>13455 94a Ave #104 Surrey, BC V3V 1M9 Canada</span>
                    </div>
                </div>

                <div class="text-center w-25">
                    <img src="../assets/emma-wiliam.png" alt="Business POC" class="img-fluid rounded shadow-sm">
                </div>

                <div class="w-50 ps-3">
                    <h5 class="fw-bold">Business POC</h5>
                    <p class="mb-1"><strong>Name:</strong> Emma William</p>
                    <p class="mb-1"><strong>Title:</strong> Procurement Manager</p>
                    <p class="mb-1"><strong>Email:</strong> sales@everstonetech.ca</p>
                    <p class="mb-0"><strong>Cell:</strong> 236-953-7860</p>
                </div>

            </div>
            <div class="mt-3 text-center ">
                <strong class="span-red me-3"><span class="span-blue">UEI:</span> KQM1HHYHLHR5</strong>
                <strong class="span-red me-3"><span class="span-blue">NCAGE:</span> 9EAX7</strong>
                <strong class="span-red me-3"><span class="span-blue">DUNS:</span> 12-346-8707</strong>
                <strong class="span-red me-3"><span class="span-blue">JCCS:</span> 133245</strong>
                <strong class="span-red me-3"><span class="span-blue">CA License #:</span> 202253016523</strong>
                <strong class="span-red"><span class="span-blue">EIN:</span> 92-2840560</strong>
            </div>
        </div>
    </div>

    <div class="d-flex m-3 mt-5">
        <img src="../assets/Everstone.png" class="img-fluid" width="120px" style="height:100px;" alt="PDF Logo">
        <div>
            <p class="mt-1">EVERSTONE TECHNOLOGY SYSTEMS INC. </p>
            <p>13455 94a Ave #104</p>
            <p>Surrey, BC V3V 1M9 Canada</p>
            <p>United States</p>
        </div>
    </div>

    <div class="d-flex justify-content-end m-3 mb-4">
        <div>
            <p>Pascaru, Ms. Anamaria</p>
            <p>Brussels, BE (NCIA HQ - Ship-To) New NATO HQ</p>
            <p>-Industrial Infrastructure Building - Reception</p>
            <p>Service Rue Arthur Maes 1, 1130 BRUSSELS,</p>
            <p>Belgium BRUSSELS 1130 Belgium</p>
        </div>
    </div>

    <div class="m-3 mt-5" id="invoiceArea">
        <div>
            <div class="d-flex justify-content-between mb-4">
                <div>
                    <h4 class="fw-bold">Invoice <?= htmlspecialchars($invoice['code']) ?></h4>
                    <p class="mb-1">Order #: <?= htmlspecialchars($invoice['order_code']) ?></p>
                    <p class="mb-1">Date: <?= date("m/d/Y", strtotime($invoice['created_at'])) ?></p>
                    <span class="badge bg-info"><?= htmlspecialchars($invoice['status']) ?></span>
                </div>
                <div class="text-end">
                    <h5 class="fw-bold">Your Company</h5>
                    <p class="mb-1">123 Business St</p>
                    <p class="mb-1">City, Country</p>
                    <p>Email: info@company.com</p>
                </div>
            </div>

            <div class="mb-4">
                <h6 class="fw-bold">Bill To:</h6>
                <p class="mb-1">Customer: <?= htmlspecialchars($invoice['customer']) ?></p>
                <p class="mb-1">Email: <?= htmlspecialchars($invoice['email']) ?> |
                    Phone: <?= htmlspecialchars($invoice['phone']) ?>
                </p>
                <p>Address: <?= nl2br(htmlspecialchars($invoice['address'])) ?></p>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Sub Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 1;
                        while ($line = mysqli_fetch_assoc($order_lines)) { ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($line['product']) ?></td>
                                <td><?= $line['qty'] ?></td>
                                <td>$ <?= number_format($line['unit_price'], 2) ?></td>
                                <td>$ <?= number_format($line['qty'] * $line['unit_price'], 2) ?></td>
                            </tr>
                        <?php }

                        $ol = mysqli_query(
                            $conn,
                            "SELECT SUM(qty * unit_price) AS total_amount 
                             FROM order_lines 
                             WHERE order_id=" . (int)$invoice['order_id']
                        );
                        $tot = mysqli_fetch_assoc($ol);
                        $ol_total = $tot['total_amount'];
                        ?>
                        <tr class="table-light">
                            <th></th>
                            <th></th>
                            <th>Due Date</th>
                            <th>Paid Date</th>
                            <th>Total</th>
                        </tr>
                        <tr>
                            <td></td>
                            <td></td>
                            <td><strong><?= htmlspecialchars($invoice['due_date']) ?></strong></td>
                            <td><strong><?= htmlspecialchars($invoice['paid_date']) ?></strong></td>
                            <td><strong>$ <?= number_format($ol_total, 2) ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex m-3 mt-5">
        <img src="../assets/Everstone.png" class="img-fluid" width="120px" style="height:100px;" alt="PDF Logo">
        <div>
            <p class="mt-1">EVERSTONE TECHNOLOGY SYSTEMS INC. </p>
            <p>13455 94a Ave #104</p>
            <p>Surrey, BC V3V 1M9 Canada</p>
            <p>United States</p>
        </div>
    </div>

    <div class="m-3 mt-5 p-2 bg-light">
        <div>
            <p>EVERSTONE TECHNOLOGY SYSTEMS INC. </p>
            <p>13455 94a Ave #104</p>
            <p>Surrey, BC V3V 1M9 Canada</p>
            <p>United States</p>
        </div>
    </div>

    <hr />

    <div class="m-3 d-flex justify-content-between align-items-center">
        <h2>INTRODUCTION</h2>
        <img src="../assets/Everstone.png" class="img-fluid" width="120px" style="height:100px;" alt="PDF Logo">
    </div>

    <hr />

    <div class="m-3 mt-5">
        <h1 class="span-blue" style="font-size:50px">
            Everstone provides technology systems, procurement, and operational support across a broad range of industries.
        </h1>
        <h3 class="mt-2 letter-spacing-2">
            Everstone brings practical experience in delivering reliable technology, procurement, and logistical
            support. The company aims to provide tailored solutions to enhance operational efficiency and adaptability
            for its clients. The company is dedicated to quality and precision, meeting the needs of government and
            corporate clients through innovative and reliable solutions.
        </h3>
    </div>

    <div class="container mt-5 d-flex justify-content-end">
        <button onclick="window.print()" class="btn btn-outline-secondary mb-5 me-2">Print Invoice</button>
        <p onclick="history.go(-1); return false;" title="Back" class="btn btn-secondary mb-5">Back</p>
    </div>
</section>

<?php include("../templates/footer.php"); ?>
