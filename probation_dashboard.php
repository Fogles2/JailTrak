<?php
session_start();
require_once __DIR__ . '/../../../config/invite_gate.php';
checkInviteAccess(); 
require_once __DIR__ . '/../../../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['officer_id'])) {
    header('Location: probation_login.php');
    exit;
}

// Initialize database connection
try {
    $db = new PDO('sqlite:' . __DIR__ . '/../data/jailtrak.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get officer info
$officerId = $_SESSION['officer_id'];
$stmt = $db->prepare("SELECT * FROM probation_officers WHERE id = ?");
$stmt->execute([$officerId]);
$officer = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle watchlist actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_watch') {
            $inmateName = trim($_POST['inmate_name']);
            $notes = trim($_POST['notes']);
            
            $stmt = $db->prepare("
                INSERT INTO watchlist (officer_id, inmate_name, notes, created_at)
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$officerId, $inmateName, $notes]);
            
            $success = "Added '{$inmateName}' to your watchlist";
        } elseif ($_POST['action'] === 'remove_watch') {
            $watchId = $_POST['watch_id'];
            $stmt = $db->prepare("DELETE FROM watchlist WHERE id = ? AND officer_id = ?");
            $stmt->execute([$watchId, $officerId]);
            
            $success = "Removed from watchlist";
        }
    }
}

// Get officer's watchlist
$stmt = $db->prepare("
    SELECT * FROM watchlist 
    WHERE officer_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$officerId]);
$watchlist = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent probation violations
$violationQuery = "
    SELECT 
        i.*,
        c.charge_description,
        c.charge_type,
        w.id as watchlist_id,
        w.notes as watch_notes
    FROM inmates i
    INNER JOIN charges c ON i.inmate_id = c.inmate_id
    LEFT JOIN watchlist w ON UPPER(i.name) = UPPER(w.inmate_name) AND w.officer_id = ?
    WHERE c.charge_description LIKE '%PROBATION%' OR c.charge_description LIKE '%VIOLATION%'
    ORDER BY i.booking_date DESC, i.booking_time DESC
    LIMIT 50
";
$stmt = $db->prepare($violationQuery);
$stmt->execute([$officerId]);
$violations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get alerts for this officer
$stmt = $db->prepare("
    SELECT * FROM probation_alerts 
    WHERE officer_id = ? AND read_status = 0
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$officerId]);
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count unread alerts
$stmt = $db->prepare("SELECT COUNT(*) FROM probation_alerts WHERE officer_id = ? AND read_status = 0");
$stmt->execute([$officerId]);
$unreadCount = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Probation Officer Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0a0a1a 0%, #1a1a3e 50%, #2a2a5e 100%);
            min-height: 100vh;
            padding: 20px;
            color: #e0e0e0;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
        }
        
        header {
            background: linear-gradient(135deg, #1a1a3e 0%, #2a2a5e 100%);
            padding: 25px 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            margin-bottom: 30px;
            border: 1px solid rgba(100,149,237,0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left h1 {
            color: #6495ed;
            font-size: 2em;
            margin-bottom: 5px;
            text-shadow: 0 0 20px rgba(100,149,237,0.5);
        }
        
        .header-left .subtitle {
            color: #a0a0a0;
            font-size: 0.9em;
        }
        
        .header-right {
            text-align: right;
        }
        
        .officer-info {
            color: #e0e0e0;
            margin-bottom: 10px;
        }
        
        .officer-info strong {
            color: #6495ed;
        }
        
        .logout-btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, #ff4444 0%, #cc0000 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px rgba(255,68,68,0.5);
        }
        
        .alert-badge {
            background: #ff4444;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
            margin-left: 10px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        .card {
            background: linear-gradient(135deg, #1e1e3f 0%, #2a2a4a 100%);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .card h2 {
            color: #6495ed;
            font-size: 1.5em;
            margin-bottom: 20px;
            text-shadow: 0 0 15px rgba(100,149,237,0.5);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-mini {
            background: rgba(100,149,237,0.1);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid rgba(100,149,237,0.3);
        }
        
        .stat-mini .label {
            font-size: 0.85em;
            color: #a0a0a0;
            margin-bottom: 8px;
        }
        
        .stat-mini .value {
            font-size: 2em;
            font-weight: bold;
            color: #6495ed;
            text-shadow: 0 0 10px rgba(100,149,237,0.5);
        }
        
        .add-watch-form {
            background: rgba(22,33,62,0.6);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #a0a0a0;
            font-weight: 600;
            font-size: 0.9em;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            background: #16213e;
            color: #e0e0e0;
            font-size: 1em;
            transition: all 0.3s;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #6495ed;
            box-shadow: 0 0 15px rgba(100,149,237,0.3);
        }
        
        .btn-primary {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #6495ed 0%, #4169e1 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1em;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(100,149,237,0.5);
        }
        
        .watchlist-item {
            background: rgba(22,33,62,0.6);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #6495ed;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }
        
        .watchlist-item:hover {
            background: rgba(100,149,237,0.15);
            transform: translateX(5px);
        }
        
        .watchlist-info {
            flex: 1;
        }
        
        .watchlist-name {
            font-weight: bold;
            color: #e0e0e0;
            font-size: 1.1em;
            margin-bottom: 5px;
        }
        
        .watchlist-notes {
            color: #a0a0a0;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        
        .watchlist-date {
            color: #808080;
            font-size: 0.8em;
        }
        
        .btn-remove {
            padding: 8px 15px;
            background: #ff4444;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s;
        }
        
        .btn-remove:hover {
            background: #cc0000;
            box-shadow: 0 0 15px rgba(255,68,68,0.5);
        }
        
        .violation-item {
            background: rgba(22,33,62,0.6);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #ff6b00;
            transition: all 0.3s;
        }
        
        .violation-item:hover {
            background: rgba(255,107,0,0.15);
            transform: translateX(5px);
        }
        
        .violation-item.on-watchlist {
            border-left-color: #ff4444;
            background: rgba(255,68,68,0.1);
        }
        
        .violation-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .violation-name {
            font-size: 1.3em;
            font-weight: bold;
            color: #e0e0e0;
        }
        
        .violation-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .violation-badge.watchlist {
            background: rgba(255,68,68,0.3);
            color: #ff6868;
            border: 1px solid #ff4444;
        }
        
        .violation-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .violation-detail {
            display: flex;
            flex-direction: column;
        }
        
        .violation-detail .label {
            font-size: 0.85em;
            color: #a0a0a0;
            margin-bottom: 5px;
        }
        
        .violation-detail .value {
            color: #e0e0e0;
            font-weight: 500;
        }
        
        .violation-charge {
            background: rgba(255,107,0,0.2);
            padding: 12px;
            border-radius: 8px;
            margin-top: 10px;
            border-left: 3px solid #ff6b00;
        }
        
        .alert-item {
            background: rgba(255,68,68,0.15);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #ff4444;
        }
        
        .alert-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .alert-title {
            font-weight: bold;
            color: #ff6868;
            font-size: 1.05em;
        }
        
        .alert-time {
            color: #808080;
            font-size: 0.85em;
        }
        
        .alert-message {
            color: #e0e0e0;
            font-size: 0.95em;
            line-height: 1.5;
        }
        
        .success-message {
            background: rgba(0,255,136,0.2);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #00ff88;
            color: #00ff88;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #808080;
        }
        
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .violation-details {
                grid-template-columns: 1fr;
            }
            
            .stats-mini {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-left">
                <h1>👮 Probation Officer Dashboard</h1>
                <p class="subtitle">Real-time probation violation monitoring & alerts</p>
            </div>
            <div class="header-right">
                <div class="officer-info">
                    <strong>Officer:</strong> <?= htmlspecialchars($officer['name']) ?>
                    <?php if ($unreadCount > 0): ?>
                        <span class="alert-badge"><?= $unreadCount ?> New Alert<?= $unreadCount > 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                </div>
                <div class="officer-info">
                    <strong>Email:</strong> <?= htmlspecialchars($officer['email']) ?>
                </div>
                <a href="probation_logout.php" class="logout-btn">Logout</a>
            </div>
        </header>
        
        <?php if (isset($success)): ?>
            <div class="success-message">
                ✓ <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <div class="dashboard-grid">
            <div class="main-content">
                <!-- Recent Probation Violations -->
                <div class="card">
                    <h2>⚠️ Recent Probation Violations</h2>
                    
                    <div class="stats-mini">
                        <div class="stat-mini">
                            <div class="label">Total Violations</div>
                            <div class="value"><?= count($violations) ?></div>
                        </div>
                        <div class="stat-mini">
                            <div class="label">On Watchlist</div>
                            <div class="value"><?= count(array_filter($violations, fn($v) => !empty($v['watchlist_id']))) ?></div>
                        </div>
                        <div class="stat-mini">
                            <div class="label">Watching</div>
                            <div class="value"><?= count($watchlist) ?></div>
                        </div>
                    </div>
                    
                    <?php if (empty($violations)): ?>
                        <div class="empty-state">
                            <p>No recent probation violations found</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($violations as $violation): ?>
                            <div class="violation-item <?= !empty($violation['watchlist_id']) ? 'on-watchlist' : '' ?>">
                                <div class="violation-header">
                                    <div class="violation-name"><?= htmlspecialchars($violation['name']) ?></div>
                                    <?php if (!empty($violation['watchlist_id'])): ?>
                                        <span class="violation-badge watchlist">🔔 ON YOUR WATCHLIST</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="violation-details">
                                    <div class="violation-detail">
                                        <span class="label">Docket Number</span>
                                        <span class="value"><?= htmlspecialchars($violation['inmate_id']) ?></span>
                                    </div>
                                    <div class="violation-detail">
                                        <span class="label">Age</span>
                                        <span class="value"><?= htmlspecialchars($violation['age']) ?></span>
                                    </div>
                                    <div class="violation-detail">
                                        <span class="label">Booking Date</span>
                                        <span class="value"><?= htmlspecialchars($violation['booking_date']) ?></span>
                                    </div>
                                    <div class="violation-detail">
                                        <span class="label">Booking Time</span>
                                        <span class="value"><?= htmlspecialchars($violation['booking_time']) ?></span>
                                    </div>
                                    <div class="violation-detail">
                                        <span class="label">Bond</span>
                                        <span class="value"><?= htmlspecialchars($violation['bond_amount']) ?: 'N/A' ?></span>
                                    </div>
                                    <div class="violation-detail">
                                        <span class="label">Status</span>
                                        <span class="value">
                                            <?= $violation['in_jail'] ? '🔒 IN JAIL' : '✓ Released' ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="violation-charge">
                                    <strong>Charge:</strong> <?= htmlspecialchars($violation['charge_description']) ?>
                                </div>
                                
                                <?php if (!empty($violation['watch_notes'])): ?>
                                    <div style="margin-top: 10px; padding: 10px; background: rgba(100,149,237,0.15); border-radius: 8px;">
                                        <strong>Your Notes:</strong> <?= htmlspecialchars($violation['watch_notes']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="sidebar">
                <!-- Add to Watchlist -->
                <div class="card">
                    <h2>➕ Add to Watchlist</h2>
                    <form method="POST" class="add-watch-form">
                        <input type="hidden" name="action" value="add_watch">
                        
                        <div class="form-group">
                            <label>Inmate Name *</label>
                            <input type="text" name="inmate_name" placeholder="LAST FIRST MIDDLE" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Notes (Optional)</label>
                            <textarea name="notes" placeholder="Add any notes about this individual..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn-primary">Add to Watchlist</button>
                    </form>
                </div>
                
                <!-- My Watchlist -->
                <div class="card">
                    <h2>👁️ My Watchlist (<?= count($watchlist) ?>)</h2>
                    
                    <?php if (empty($watchlist)): ?>
                        <div class="empty-state">
                            <p>Your watchlist is empty</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($watchlist as $watch): ?>
                            <div class="watchlist-item">
                                <div class="watchlist-info">
                                    <div class="watchlist-name"><?= htmlspecialchars($watch['inmate_name']) ?></div>
                                    <?php if (!empty($watch['notes'])): ?>
                                        <div class="watchlist-notes"><?= htmlspecialchars($watch['notes']) ?></div>
                                    <?php endif; ?>
                                    <div class="watchlist-date">Added: <?= date('M j, Y', strtotime($watch['created_at'])) ?></div>
                                </div>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="remove_watch">
                                    <input type="hidden" name="watch_id" value="<?= $watch['id'] ?>">
                                    <button type="submit" class="btn-remove" onclick="return confirm('Remove from watchlist?')">Remove</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Alerts -->
                <div class="card">
                    <h2>🔔 Recent Alerts</h2>
                    
                    <?php if (empty($alerts)): ?>
                        <div class="empty-state">
                            <p>No recent alerts</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($alerts as $alert): ?>
                            <div class="alert-item">
                                <div class="alert-header">
                                    <span class="alert-title"><?= htmlspecialchars($alert['alert_title']) ?></span>
                                    <span class="alert-time"><?= date('M j, g:i A', strtotime($alert['created_at'])) ?></span>
                                </div>
                                <div class="alert-message"><?= htmlspecialchars($alert['alert_message']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>