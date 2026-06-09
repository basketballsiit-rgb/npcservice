<?php
/**
 * Temporary Admin Password Reset Script
 */

require_once __DIR__ . '/db.php';

try {
    $db = getDB();
    $newHash = password_hash('1234', PASSWORD_DEFAULT);
    
    // Check if the 'admin' user exists
    $stmt = $db->prepare("SELECT * FROM `admin_users` WHERE `username` = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin) {
        // Update password
        $updateStmt = $db->prepare("UPDATE `admin_users` SET `password` = ? WHERE `username` = 'admin'");
        $updateStmt->execute([$newHash]);
        echo "<h1>Reset Success</h1>";
        echo "<p>The password for user <strong>admin</strong> has been reset to <strong>1234</strong>.</p>";
    } else {
        // Insert new admin
        $insertStmt = $db->prepare("INSERT INTO `admin_users` (`username`, `password`) VALUES ('admin', ?)");
        $insertStmt->execute([$newHash]);
        echo "<h1>Reset Success</h1>";
        echo "<p>User <strong>admin</strong> did not exist, so it was created with password <strong>1234</strong>.</p>";
    }
    echo "<p style='color:red;'><strong>IMPORTANT:</strong> Please delete the file <code>reset_admin.php</code> from your server immediately for security reasons.</p>";
} catch (Exception $e) {
    echo "<h1>Error</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
