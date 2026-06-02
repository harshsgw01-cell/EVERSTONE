<?php
include("../config/database.php");
include("../includes/auth.php");
check_auth();
require_role(['Admin']);

$id = intval($_GET['id']);
mysqli_query($conn, "DELETE FROM sales WHERE id='$id'");
header("Location: sales.php");
exit;
