<?php
require_once __DIR__ . '/../../../config/config.php';

echo "Setting up Probation Officer Alert System...\n\n";

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../data/jailtrak.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Reading schema...\n";
    $schema = file_get_contents(__DIR__ . '/probation_schema.sql');
    
    echo "Creating tables...\n";
    $db->exec($schema);
    
    echo "✓ Tables created successfully!\n\n";
    
    // Check if any officers exist
    $count = $db->query("SELECT COUNT(*) FROM probation_officers")->fetchColumn();
    
    if ($count == 0) {
        echo "No officers registered yet.\n";
        echo "Visit probation_register.php to create your first account.\n\n";
    } else {
        echo "Found $count registered officer(s).\n\n";
    }
    
    echo "Setup complete!\n\n";
    echo "Next steps:\n";
    echo "1. Register an account at: probation_register.php\n";
    echo "2. Login at: probation_login.php\n";
    echo "3. Add inmates to your watchlist\n";
    echo "4. Setup cron job for alerts: */15 * * * * php " . __DIR__ . "/alert_checker.php\n\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>