<?php
/**
 * Status Migration Script
 * Updates old RFQ statuses to new ones:
 * Open → Ready for Review
 * Ready for review → Ready to Submit
 * Approved → Submitted
 */

include("../config/database.php");

echo "<h2>RFQ Status Migration</h2>";

// Ensure connection is good
if (!$conn) {
    die("<p style='color: red;'>Database connection failed</p>");
}

// Migration data
$migrations = [
    'Open' => 'Ready for Review',
    'Ready for review' => 'Ready to Submit',
    'Approved' => 'Submitted'
];

// Process each migration
foreach ($migrations as $oldStatus => $newStatus) {
    $oldStatus_escaped = mysqli_real_escape_string($conn, $oldStatus);
    $newStatus_escaped = mysqli_real_escape_string($conn, $newStatus);
    
    // Get count before update
    $count_result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM rfqs WHERE status = '$oldStatus_escaped'");
    $count_row = mysqli_fetch_assoc($count_result);
    $affected_before = $count_row['cnt'];
    
    // Perform update
    $update_result = mysqli_query($conn, "UPDATE rfqs SET status = '$newStatus_escaped' WHERE status = '$oldStatus_escaped'");
    
    if ($update_result) {
        $affected = mysqli_affected_rows($conn);
        echo "<p style='color: green;'><strong>✓ Success:</strong> '$oldStatus' → '$newStatus' | Updated <strong>$affected</strong> record(s)</p>";
    } else {
        echo "<p style='color: red;'><strong>✗ Error:</strong> Failed to update '$oldStatus' to '$newStatus' | Error: " . mysqli_error($conn) . "</p>";
    }
}

// Show final status summary
echo "<hr>";
echo "<h3>Current Status Distribution:</h3>";
$status_result = mysqli_query($conn, "SELECT status, COUNT(*) AS cnt FROM rfqs GROUP BY status ORDER BY cnt DESC");
echo "<table style='border-collapse: collapse; margin-top: 10px;'>";
echo "<tr style='border: 1px solid #ddd;'><th style='border: 1px solid #ddd; padding: 8px;'>Status</th><th style='border: 1px solid #ddd; padding: 8px;'>Count</th></tr>";
while ($row = mysqli_fetch_assoc($status_result)) {
    echo "<tr style='border: 1px solid #ddd;'><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($row['status']) . "</td><td style='border: 1px solid #ddd; padding: 8px;'>" . $row['cnt'] . "</td></tr>";
}
echo "</table>";

mysqli_close($conn);
echo "<p><br><a href='rfqs.php'>← Back to RFQs</a></p>";
?>
