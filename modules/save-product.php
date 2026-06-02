<?php
include("../config/database.php");
include("../includes/auth.php");
check_auth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$name         = trim($_POST['name']         ?? '');
$code         = trim($_POST['code']         ?? '');
$part_number  = trim($_POST['part_number']  ?? '');
$manufacturer = trim($_POST['manufacturer'] ?? '');
$description  = trim($_POST['description']  ?? '');
$sales_price  = (float)($_POST['sales_price'] ?? 0);
$cost_price   = (float)($_POST['cost_price']  ?? 0);

/* ── Validation ── */
if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Product name is required.']);
    exit;
}
if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Product code is required.']);
    exit;
}

/* ── Duplicate code check ── */
$code_escaped = mysqli_real_escape_string($conn, $code);
$check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM products WHERE code = '$code_escaped' LIMIT 1"));
if ($check) {
    echo json_encode(['success' => false, 'message' => 'A product with this code already exists.']);
    exit;
}

/* ── Catalog file upload ── */
$catalog_file = null;

if (!empty($_FILES['catalog_file']['name']) && $_FILES['catalog_file']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $file_type     = mime_content_type($_FILES['catalog_file']['tmp_name']);

    if (!in_array($file_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Only PDF or Word documents are allowed.']);
        exit;
    }

    $upload_dir = '../uploads/catalogs/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $safe_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['catalog_file']['name']));
    $dest          = $upload_dir . $safe_filename;

    if (!move_uploaded_file($_FILES['catalog_file']['tmp_name'], $dest)) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload catalog file.']);
        exit;
    }

    $catalog_file = 'uploads/catalogs/' . $safe_filename;
}

/* ── Insert product ── */
$name_esc         = mysqli_real_escape_string($conn, $name);
$part_esc         = mysqli_real_escape_string($conn, $part_number);
$mfg_esc          = mysqli_real_escape_string($conn, $manufacturer);
$desc_esc         = mysqli_real_escape_string($conn, $description);
$catalog_esc      = $catalog_file ? "'" . mysqli_real_escape_string($conn, $catalog_file) . "'" : "NULL";

$insert = mysqli_query($conn, "
    INSERT INTO products (code, name, part_number, manufacturer, description, sales_price, cost_price, catalog_file)
    VALUES ('$code_escaped', '$name_esc', '$part_esc', '$mfg_esc', '$desc_esc', '$sales_price', '$cost_price', $catalog_esc)
");

if (!$insert) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

echo json_encode([
    'success'      => true,
    'id'           => mysqli_insert_id($conn),
    'name'         => $name,
    'code'         => $code,
    'part_number'  => $part_number,
    'manufacturer' => $manufacturer,
    'description'  => $description,
    'sales_price'  => $sales_price,
    'cost_price'   => $cost_price,
    'catalog_file' => $catalog_file,
]);