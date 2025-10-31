<?php
require_once __DIR__ . '/../../../config/invite_gate.php';
checkInviteAccess(); // This will redirect to beta_access.php if not verified
require_once __DIR__ . '/../../../config/config.php';

// Initialize database connection
try {
    $db = new PDO('sqlite:' . __DIR__ . '/../data/jailtrak.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Pagination settings
$perPage = 30;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $perPage;

// Get statistics
$stats = [
    'total' => $db->query("SELECT COUNT(*) FROM inmates")->fetchColumn(),
    'male' => $db->query("SELECT COUNT(*) FROM inmates WHERE sex = 'M'")->fetchColumn(),
    'female' => $db->query("SELECT COUNT(*) FROM inmates WHERE sex = 'F'")->fetchColumn(),
    'felonies' => $db->query("SELECT COUNT(DISTINCT inmate_id) FROM charges WHERE charge_type = 'Felony'")->fetchColumn(),
    'misdemeanors' => $db->query("SELECT COUNT(DISTINCT inmate_id) FROM charges WHERE charge_type = 'Misdemeanor'")->fetchColumn(),
    'last_update' => $db->query("SELECT MAX(scrape_time) FROM scrape_logs WHERE status = 'success'")->fetchColumn()
];

// Get crime type statistics
$crimeStatsQuery = "
    SELECT 
        charge_description,
        COUNT(*) as count,
        charge_type
    FROM charges
    GROUP BY charge_description
    ORDER BY count DESC
    LIMIT 15
";
$crimeStats = $db->query($crimeStatsQuery)->fetchAll(PDO::FETCH_ASSOC);

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build base query for counting
$countQuery = "
    SELECT COUNT(DISTINCT i.id)
    FROM inmates i
    LEFT JOIN charges c ON i.inmate_id = c.inmate_id
    WHERE 1=1
";

$params = [];

if ($search) {
    $countQuery .= " AND (i.name LIKE ? OR c.charge_description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Apply sex filters to count
if ($filter === 'male') {
    $countQuery .= " AND i.sex = 'M'";
} elseif ($filter === 'female') {
    $countQuery .= " AND i.sex = 'F'";
}

// Get total count for pagination
$stmt = $db->prepare($countQuery);
$stmt->execute($params);
$totalInmates = $stmt->fetchColumn();
$totalPages = ceil($totalInmates / $perPage);

// Build main query
$query = "
    SELECT 
        i.*,
        GROUP_CONCAT(c.charge_description, '; ') as charges,
        GROUP_CONCAT(DISTINCT c.charge_type) as charge_types
    FROM inmates i
    LEFT JOIN charges c ON i.inmate_id = c.inmate_id
    WHERE 1=1
";

$params = [];

if ($search) {
    $query .= " AND (i.name LIKE ? OR c.charge_description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " GROUP BY i.id";

// Apply filters after grouping
$havingClauses = [];
if ($filter === 'felony') {
    $havingClauses[] = "charge_types LIKE '%Felony%'";
} elseif ($filter === 'misdemeanor') {
    $havingClauses[] = "charge_types LIKE '%Misdemeanor%'";
} elseif ($filter === 'male') {
    $query = str_replace('WHERE 1=1', 'WHERE i.sex = "M"', $query);
} elseif ($filter === 'female') {
    $query = str_replace('WHERE 1=1', 'WHERE i.sex = "F"', $query);
}

if (!empty($havingClauses)) {
    $query .= " HAVING " . implode(' AND ', $havingClauses);
}

$query .= " ORDER BY i.booking_date DESC, i.booking_time DESC";
$query .= " LIMIT ? OFFSET ?";

$params[] = $perPage;
$params[] = $offset;

$stmt = $db->prepare($query);
$stmt->execute($params);
$inmates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate page range for display
$startRecord = $offset + 1;
$endRecord = min($offset + $perPage, $totalInmates);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clayton County Jail Dashboard - Dark Mode</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;8
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
            min-height: 100vh;
            padding: 20px;
            color: #e0e0e0;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
        }
        
        header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        h1 {
            color: #00d4ff;
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 0 0 20px rgba(0,212,255,0.5);
        }
        
        .subtitle {
            color: #a0a0a0;
            font-size: 1.1em;
        }
        
        .last-update {
            color: #808080;
            font-size: 0.9em;
            margin-top: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #1e1e3f 0%, #2a2a4a 100%);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,212,255,0.3);
            border-color: rgba(0,212,255,0.5);
        }
        
        .stat-card h3 {
            font-size: 0.9em;
            color: #a0a0a0;
            text-transform: uppercase;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }
        
        .stat-card .number {
            font-size: 2.5em;
            font-weight: bold;
            color: #00d4ff;
            text-shadow: 0 0 10px rgba(0,212,255,0.5);
        }
        
        .stat-card.male .number { 
            color: #4a9eff;
            text-shadow: 0 0 10px rgba(74,158,255,0.5);
        }
        .stat-card.female .number { 
            color: #ff4a9e;
            text-shadow: 0 0 10px rgba(255,74,158,0.5);
        }
        .stat-card.felony .number { 
            color: #ff4444;
            text-shadow: 0 0 10px rgba(255,68,68,0.5);
        }
        .stat-card.misdemeanor .number { 
            color: #ffaa00;
            text-shadow: 0 0 10px rgba(255,170,0,0.5);
        }
        
        /* Crime Statistics Section */
        .crime-stats-section {
            background: linear-gradient(135deg, #1e1e3f 0%, #2a2a4a 100%);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .crime-stats-section h2 {
            color: #00d4ff;
            font-size: 1.8em;
            margin-bottom: 25px;
            text-shadow: 0 0 15px rgba(0,212,255,0.5);
            text-align: center;
        }
        
        .crime-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .crime-stat-item {
            background: rgba(22,33,62,0.6);
            padding: 15px 20px;
            border-radius: 10px;
            border-left: 4px solid;
            transition: all 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .crime-stat-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(0,212,255,0.2);
        }
        
        .crime-stat-item.felony-item {
            border-color: #ff4444;
            background: rgba(255,68,68,0.1);
        }
        
        .crime-stat-item.misdemeanor-item {
            border-color: #ffaa00;
            background: rgba(255,170,0,0.1);
        }
        
        .crime-stat-item.unknown-item {
            border-color: #808080;
            background: rgba(128,128,128,0.1);
        }
        
        .crime-name {
            flex: 1;
            font-size: 0.95em;
            color: #e0e0e0;
            font-weight: 500;
        }
        
        .crime-count {
            font-size: 1.5em;
            font-weight: bold;
            margin-left: 15px;
            min-width: 50px;
            text-align: right;
        }
        
        .crime-stat-item.felony-item .crime-count {
            color: #ff6868;
            text-shadow: 0 0 10px rgba(255,68,68,0.5);
        }
        
        .crime-stat-item.misdemeanor-item .crime-count {
            color: #ffcc00;
            text-shadow: 0 0 10px rgba(255,170,0,0.5);
        }
        
        .crime-stat-item.unknown-item .crime-count {
            color: #a0a0a0;
        }
        
        .crime-bar {
            height: 6px;
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .crime-bar-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 1s ease;
            animation: fillBar 1.5s ease-out;
        }
        
        @keyframes fillBar {
            from { width: 0%; }
        }
        
        .crime-stat-item.felony-item .crime-bar-fill {
            background: linear-gradient(90deg, #ff4444 0%, #ff6868 100%);
            box-shadow: 0 0 10px rgba(255,68,68,0.5);
        }
        
        .crime-stat-item.misdemeanor-item .crime-bar-fill {
            background: linear-gradient(90deg, #ffaa00 0%, #ffcc00 100%);
            box-shadow: 0 0 10px rgba(255,170,0,0.5);
        }
        
        .crime-stat-item.unknown-item .crime-bar-fill {
            background: linear-gradient(90deg, #808080 0%, #a0a0a0 100%);
        }
        
        .crime-type-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
            margin-left: 10px;
            text-transform: uppercase;
        }
        
        .crime-type-badge.felony {
            background: rgba(255,68,68,0.3);
            color: #ff6868;
            border: 1px solid #ff4444;
        }
        
        .crime-type-badge.misdemeanor {
            background: rgba(255,170,0,0.3);
            color: #ffcc00;
            border: 1px solid #ffaa00;
        }
        
        .crime-type-badge.unknown {
            background: rgba(128,128,128,0.3);
            color: #a0a0a0;
            border: 1px solid #808080;
        }
        
        .controls {
            background: linear-gradient(135deg, #1e1e3f 0%, #2a2a4a 100%);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 20px;
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            font-size: 1em;
            transition: border-color 0.3s, box-shadow 0.3s;
            background: #16213e;
            color: #e0e0e0;
        }
        
        .search-box input::placeholder {
            color: #808080;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 15px rgba(0,212,255,0.3);
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 10px 20px;
            border: none;
            background: #2a2a4a;
            color: #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.95em;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .filter-btn:hover {
            background: #3a3a5a;
            border-color: #00d4ff;
            box-shadow: 0 0 10px rgba(0,212,255,0.3);
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%);
            color: #0f0f23;
            border-color: #00d4ff;
            box-shadow: 0 0 15px rgba(0,212,255,0.5);
        }
        
        .refresh-btn {
            padding: 10px 25px;
            background: linear-gradient(135deg, #00ff88 0%, #00cc6a 100%);
            color: #0f0f23;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 0 10px rgba(0,255,136,0.3);
        }
        
        .refresh-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px rgba(0,255,136,0.5);
        }
        
        .roulette-btn {
            padding: 12px 30px;
            background: linear-gradient(135deg, #ff6b00 0%, #ff4444 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 1.1em;
            transition: all 0.3s;
            box-shadow: 0 0 15px rgba(255,107,0,0.4);
            animation: pulse 2s infinite;
        }
        
        .roulette-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 25px rgba(255,107,0,0.6);
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.85; }
        }
        
        .pagination-info {
            background: linear-gradient(135deg, #1e1e3f 0%, #2a2a4a 100%);
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.1);
            color: #a0a0a0;
        }
        
        .pagination-info strong {
            color: #00d4ff;
        }
        
        .table-container {
            background: linear-gradient(135deg, #1e1e3f 0%, #2a2a4a 100%);
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            overflow-x: auto;
            overflow-y: visible;
            border: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 30px;
        }
        
        table {
            width: 100%;
            min-width: 1200px;
            border-collapse: collapse;
        }
        
        thead {
            background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%);
            color: #0f0f23;
        }
        
        th {
            padding: 15px 10px;
            text-align: left;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 1px;
            white-space: nowrap;
        }
        
        td {
            padding: 15px 10px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            vertical-align: top;
            color: #e0e0e0;
        }
        
        tbody tr {
            transition: background 0.2s;
        }
        
        tbody tr:hover {
            background: rgba(0,212,255,0.1);
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            margin-right: 5px;
            margin-bottom: 5px;
            white-space: nowrap;
        }
        
        .badge-felony {
            background: rgba(255,68,68,0.3);
            color: #ff6868;
            border: 1px solid #ff4444;
        }
        
        .badge-misdemeanor {
            background: rgba(255,170,0,0.3);
            color: #ffcc00;
            border: 1px solid #ffaa00;
        }
        
        .badge-unknown {
            background: rgba(128,128,128,0.3);
            color: #a0a0a0;
            border: 1px solid #808080;
        }
        
        .badge-ready {
            background: rgba(0,255,136,0.3);
            color: #00ff88;
            border: 1px solid #00cc6a;
        }
        
        .badge-not-ready {
            background: rgba(255,68,68,0.3);
            color: #ff6868;
            border: 1px solid #ff4444;
        }
        
        .charges-cell {
            max-width: 250px;
            font-size: 0.9em;
            line-height: 1.6;
        }
        
        .bond-cell {
            min-width: 180px;
            font-size: 0.9em;
        }
        
        .status-in-jail {
            color: #ff6868;
            font-weight: 600;
            text-shadow: 0 0 5px rgba(255,68,68,0.5);
        }
        
        .status-released {
            color: #00ff88;
            font-weight: 600;
            text-shadow: 0 0 5px rgba(0,255,136,0.5);
        }
        
        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            padding: 30px 20px;
        }
        
        .pagination a,
        .pagination span {
            padding: 10px 18px;
            background: #2a2a4a;
            color: #e0e0e0;
            border-radius: 8px;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .pagination a:hover {
            background: #3a3a5a;
            border-color: #00d4ff;
            box-shadow: 0 0 10px rgba(0,212,255,0.3);
            transform: translateY(-2px);
        }
        
        .pagination .current {
            background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%);
            color: #0f0f23;
            border-color: #00d4ff;
            box-shadow: 0 0 15px rgba(0,212,255,0.5);
        }
        
        .pagination .disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .pagination .page-jump {
            padding: 10px 20px;
            background: #16213e;
            color: #e0e0e0;
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            font-size: 1em;
            width: 80px;
            text-align: center;
        }
        
        .pagination .page-jump:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 10px rgba(0,212,255,0.3);
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #808080;
        }
        
        .no-results svg {
            width: 100px;
            height: 100px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.85);
            animation: fadeIn 0.3s;
            overflow-y: auto;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { 
                transform: translateY(-50px);
                opacity: 0;
            }
            to { 
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-content {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            margin: 5% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 700px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            animation: slideIn 0.4s;
            overflow: hidden;
            border: 1px solid rgba(0,212,255,0.3);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #ff6b00 0%, #ff4444 100%);
            color: white;
            padding: 30px;
            position: relative;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 2em;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .modal-header .roulette-icon {
            font-size: 1.5em;
            animation: spin 2s linear;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .close {
            position: absolute;
            top: 20px;
            right: 25px;
            color: white;
            font-size: 35px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .close:hover {
            transform: scale(1.2);
        }
        
        .modal-body {
            padding: 30px;
            color: #e0e0e0;
        }
        
        .inmate-info {
            background: rgba(42,42,74,0.5);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .inmate-info h3 {
            color: #00d4ff;
            margin-bottom: 20px;
            font-size: 1.5em;
            border-bottom: 3px solid #00d4ff;
            padding-bottom: 10px;
            text-shadow: 0 0 10px rgba(0,212,255,0.5);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-weight: 600;
            color: #a0a0a0;
            font-size: 0.85em;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 1.1em;
            color: #e0e0e0;
            font-weight: 500;
        }
        
        .bond-amount {
            background: linear-gradient(135deg, #00ff88 0%, #00cc6a 100%);
            color: #0f0f23;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,255,136,0.3);
        }
        
        .bond-amount .label {
            font-size: 0.9em;
            opacity: 0.9;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .bond-amount .amount {
            font-size: 2.5em;
            font-weight: bold;
        }
        
        .charges-list {
            margin-top: 15px;
        }
        
        .charges-list .info-label {
            margin-bottom: 10px;
        }
        
        .bond-instructions {
            background: rgba(255,107,0,0.2);
            border-left: 4px solid #ff6b00;
            padding: 20px;
            border-radius: 10px;
        }
        
        .bond-instructions h4 {
            color: #ff9944;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        
        .bond-instructions ol {
            padding-left: 20px;
            line-height: 1.8;
            color: #e0e0e0;
        }
        
        .bond-instructions li {
            margin-bottom: 10px;
        }
        
        .contact-info {
            background: rgba(0,212,255,0.2);
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            border: 1px solid rgba(0,212,255,0.3);
        }
        
        .contact-info h4 {
            color: #00d4ff;
            margin-bottom: 15px;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            font-size: 1.05em;
            color: #e0e0e0;
        }
        
        .contact-item strong {
            color: #00d4ff;
        }
        
        .contact-item a {
            color: #00d4ff;
            text-decoration: none;
        }
        
        .contact-item a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            h1 {
                font-size: 1.8em;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .crime-stats-grid {
                grid-template-columns: 1fr;
            }
            
            .controls {
                flex-direction: column;
            }
            
            .search-box {
                width: 100%;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
            
            table {
                font-size: 0.85em;
            }
            
            th, td {
                padding: 8px 5px;
            }
            
            .pagination {
                gap: 5px;
            }
            
            .pagination a,
            .pagination span {
                padding: 8px 12px;
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🏛️ JailTraq Beta - Live Inmate Statistics</h1>
            <p class="subtitle">Real-time inmate tracking and analytics - For Clayton County Jail</p>
            <?php if ($stats['last_update']): ?>
                <p class="last-update">Last updated: <?= date('F j, Y g:i A', strtotime($stats['last_update'])) ?></p>
            <?php endif; ?>
        </header>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Inmates</h3>
                <div class="number"><?= $stats['total'] ?></div>
            </div>
            
            <div class="stat-card male">
                <h3>Male</h3>
                <div class="number"><?= $stats['male'] ?></div>
            </div>
            
            <div class="stat-card female">
                <h3>Female</h3>
                <div class="number"><?= $stats['female'] ?></div>
            </div>
            
            <div class="stat-card felony">
                <h3>Felonies</h3>
                <div class="number"><?= $stats['felonies'] ?></div>
            </div>
            
            <div class="stat-card misdemeanor">
                <h3>Misdemeanors</h3>
                <div class="number"><?= $stats['misdemeanors'] ?></div>
            </div>
        </div>
        
        <!-- Crime Statistics Section -->
        <div class="crime-stats-section">
            <h2>📊 Top Crime Categories</h2>
            <div class="crime-stats-grid">
                <?php 
                $maxCount = !empty($crimeStats) ? $crimeStats[0]['count'] : 1;
                foreach ($crimeStats as $crime): 
                    $percentage = ($crime['count'] / $maxCount) * 100;
                    $type = strtolower($crime['charge_type']);
                    $itemClass = $type === 'felony' ? 'felony-item' : 
                                ($type === 'misdemeanor' ? 'misdemeanor-item' : 'unknown-item');
                ?>
                <div class="crime-stat-item <?= $itemClass ?>">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center;">
                            <span class="crime-name"><?= htmlspecialchars($crime['charge_description']) ?></span>
                            <span class="crime-type-badge <?= $type ?>"><?= ucfirst($type) ?></span>
                        </div>
                        <div class="crime-bar">
                            <div class="crime-bar-fill" style="width: <?= $percentage ?>%;"></div>
                        </div>
                    </div>
                    <div class="crime-count"><?= $crime['count'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="controls">
            <div class="search-box">
                <form method="GET" style="margin: 0;">
                    <input type="text" name="search" placeholder="🔍 Search by name or charge..." value="<?= htmlspecialchars($search) ?>">
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                </form>
            </div>
            
            <div class="filter-tabs">
                <a href="?filter=all&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">All</a>
                <a href="?filter=felony&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'felony' ? 'active' : '' ?>">Felonies</a>
                <a href="?filter=misdemeanor&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'misdemeanor' ? 'active' : '' ?>">Misdemeanors</a>
                <a href="?filter=male&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'male' ? 'active' : '' ?>">Male</a>
                <a href="?filter=female&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'female' ? 'active' : '' ?>">Female</a>
            </div>
            
            <button class="roulette-btn" onclick="bondRoulette()">🎰 Bond Roulette!</button>
            <button class="refresh-btn" onclick="window.location.reload()">♻️ Refresh</button>
        </div>
        
        <?php if ($totalInmates > 0): ?>
        <div class="pagination-info">
            Showing <strong><?= $startRecord ?></strong> to <strong><?= $endRecord ?></strong> of <strong><?= $totalInmates ?></strong> inmates
            (Page <strong><?= $currentPage ?></strong> of <strong><?= $totalPages ?></strong>)
        </div>
        <?php endif; ?>
        
        <div class="table-container">
            <?php if (count($inmates) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 8%;">Docket #</th>
                            <th style="width: 15%;">Name</th>
                            <th style="width: 5%;">Age</th>
                            <th style="width: 12%;">Intake Date/Time</th>
                            <th style="width: 10%;">Status</th>
                            <th style="width: 25%;">Charges</th>
                            <th style="width: 10%;">Type</th>
                            <th style="width: 15%;">Bond Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inmates as $inmate): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($inmate['inmate_id']) ?></strong></td>
                                <td><strong><?= htmlspecialchars($inmate['name']) ?></strong></td>
                                <td><?= htmlspecialchars($inmate['age']) ?></td>
                                <td>
                                    <?= htmlspecialchars($inmate['booking_date']) ?>
                                    <?php if ($inmate['booking_time']): ?>
                                        <br><small><?= htmlspecialchars($inmate['booking_time']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($inmate['in_jail']): ?>
                                        <span class="status-in-jail">🔒 IN JAIL</span>
                                    <?php else: ?>
                                        <span class="status-released">✓ Released</span>
                                    <?php endif; ?>
                                </td>
                                <td class="charges-cell">
                                    <?php 
                                    if ($inmate['charges']) {
                                        $charges = explode('; ', $inmate['charges']);
                                        foreach ($charges as $charge):
                                    ?>
                                        <div style="margin-bottom: 5px;"><?= htmlspecialchars(trim($charge)) ?></div>
                                    <?php 
                                        endforeach;
                                    } else {
                                        echo 'No charges listed';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($inmate['charge_types']) {
                                        $types = array_unique(explode(',', $inmate['charge_types']));
                                        foreach ($types as $type):
                                            $badgeClass = strtolower(trim($type)) === 'felony' ? 'badge-felony' : 
                                                         (strtolower(trim($type)) === 'misdemeanor' ? 'badge-misdemeanor' : 'badge-unknown');
                                    ?>
                                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars(trim($type)) ?></span>
                                    <?php 
                                        endforeach;
                                    }
                                    ?>
                                </td>
                                <td class="bond-cell">
                                    <?php 
                                    $bondInfo = htmlspecialchars($inmate['bond_amount']);
                                    if (strpos($bondInfo, 'READY') !== false) {
                                        if (strpos($bondInfo, 'NOT READY') !== false) {
                                            echo '<span class="badge badge-not-ready">NOT READY</span><br>';
                                        } else {
                                            echo '<span class="badge badge-ready">READY</span><br>';
                                        }
                                        $bondInfo = str_replace(['NOT READY |', 'READY |', 'NOT READY', 'READY'], '', $bondInfo);
                                    }
                                    echo trim($bondInfo) ?: 'N/A';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-results">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h2>No inmates found</h2>
                    <p>Try adjusting your search or filter criteria</p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
            // Build query string for pagination
            $queryParams = ['filter' => $filter];
            if ($search) {
                $queryParams['search'] = $search;
            }
            
            // First and Previous
            if ($currentPage > 1): ?>
                <a href="?<?= http_build_query(array_merge($queryParams, ['page' => 1])) ?>">« First</a>
                <a href="?<?= http_build_query(array_merge($queryParams, ['page' => $currentPage - 1])) ?>">‹ Prev</a>
            <?php else: ?>
                <span class="disabled">« First</span>
                <span class="disabled">‹ Prev</span>
            <?php endif; ?>
            
            <?php
            // Page numbers
            $startPage = max(1, $currentPage - 2);
            $endPage = min($totalPages, $currentPage + 2);
            
            for ($i = $startPage; $i <= $endPage; $i++): 
                if ($i == $currentPage): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?<?= http_build_query(array_merge($queryParams, ['page' => $i])) ?>"><?= $i ?></a>
                <?php endif;
            endfor;
            ?>
            
            <?php
            // Next and Last
            if ($currentPage < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($queryParams, ['page' => $currentPage + 1])) ?>">Next ›</a>
                <a href="?<?= http_build_query(array_merge($queryParams, ['page' => $totalPages])) ?>">Last »</a>
            <?php else: ?>
                <span class="disabled">Next ›</span>
                <span class="disabled">Last »</span>
            <?php endif; ?>
            
            <!-- Page Jump -->
            <form method="GET" style="display: inline; margin-left: 10px;">
                <?php foreach ($queryParams as $key => $value): ?>
                    <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                <?php endforeach; ?>
                <input type="number" name="page" class="page-jump" min="1" max="<?= $totalPages ?>" placeholder="Go to" title="Jump to page">
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- Bond Roulette Modal -->
    <div id="bondModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><span class="roulette-icon">🎰</span> Bond Roulette Result</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script>
        // Store ALL inmates data for roulette (not just current page)
        <?php
        // Get all inmates for roulette
        $allInmatesQuery = "
            SELECT 
                i.*,
                GROUP_CONCAT(c.charge_description, '; ') as charges,
                GROUP_CONCAT(DISTINCT c.charge_type) as charge_types
            FROM inmates i
            LEFT JOIN charges c ON i.inmate_id = c.inmate_id
            GROUP BY i.id
            ORDER BY RANDOM()
            LIMIT 100
        ";
        $allInmates = $db->query($allInmatesQuery)->fetchAll(PDO::FETCH_ASSOC);
        ?>
        const inmatesData = <?= json_encode($allInmates) ?>;

        function bondRoulette() {
            if (inmatesData.length === 0) {
                alert('No inmates available for roulette!');
                return;
            }

            // Get random inmate
            const randomIndex = Math.floor(Math.random() * inmatesData.length);
            const inmate = inmatesData[randomIndex];

            // Build modal content
            const modalContent = `
                <div class="inmate-info">
                    <h3>👤 Inmate Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Docket Number</span>
                            <span class="info-value">${escapeHtml(inmate.inmate_id)}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Full Name</span>
                            <span class="info-value">${escapeHtml(inmate.name)}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Booking Date</span>
                            <span class="info-value">${escapeHtml(inmate.booking_date)}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Booking Time</span>
                            <span class="info-value">${escapeHtml(inmate.booking_time || 'N/A')}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Age</span>
                            <span class="info-value">${escapeHtml(inmate.age)}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status</span>
                            <span class="info-value">${inmate.in_jail ? '🔒 IN JAIL' : '✓ Released'}</span>
                        </div>
                    </div>
                    
                    <div class="bond-amount">
                        <div class="label">💰 BOND AMOUNT</div>
                        <div class="amount">${escapeHtml(inmate.bond_amount || 'Not Set')}</div>
                    </div>
                    
                    <div class="charges-list">
                        <span class="info-label">⚖️ Charges</span>
                        <div class="charges-cell">
                            ${inmate.charges ? inmate.charges.split('; ').map(charge => {
                                const type = inmate.charge_types || '';
                                const badgeClass = type.toLowerCase().includes('felony') ? 'badge-felony' : 
                                                   (type.toLowerCase().includes('misdemeanor') ? 'badge-misdemeanor' : 'badge-unknown');
                                return `<span class="badge ${badgeClass}">${escapeHtml(charge)}</span>`;
                            }).join('') : 'No charges listed'}
                        </div>
                    </div>
                </div>

                <div class="bond-instructions">
                    <h4>📋 How to Post Bond in Clayton County</h4>
                    <ol>
                        <li><strong>Contact a Licensed Bail Bondsman:</strong> You'll typically need to pay 10-15% of the total bond amount to a bondsman.</li>
                        <li><strong>Provide Required Information:</strong> Bring the inmate's full name, docket number (${escapeHtml(inmate.inmate_id)}), charges, and bond amount.</li>
                        <li><strong>Sign the Agreement:</strong> You (the indemnitor) will sign a contract agreeing to ensure the defendant appears in court.</li>
                        <li><strong>Pay the Premium:</strong> Pay the non-refundable fee (typically 10-15% of bond amount) plus any additional fees.</li>
                        <li><strong>Wait for Release:</strong> Processing can take 2-8 hours depending on jail capacity.</li>
                    </ol>
                </div>

                <div class="contact-info">
                    <h4>📞 Clayton County Jail Contact Information</h4>
                    <div class="contact-item">
                        <strong>Address:</strong> 9157 Tara Blvd, Jonesboro, GA 30236
                    </div>
                    <div class="contact-item">
                        <strong>Main Phone:</strong> (770) 477-4479
                    </div>
                    <div class="contact-item">
                        <strong>Inmate Info:</strong> (770) 477-3747
                    </div>
                    <div class="contact-item">
                        <strong>Visiting Hours:</strong> Sat-Sun 9:00 AM - 5:00 PM
                    </div>
                    <div class="contact-item">
                        <strong>Website:</strong> <a href="https://weba.claytoncountyga.gov/sjiserver/htdocs/index.shtml" target="_blank">Clayton County Sheriff Inmate Search</a>
                    </div>
                </div>
            `;

            document.getElementById('modalBody').innerHTML = modalContent;
            document.getElementById('bondModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('bondModal').style.display = 'none';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('bondModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>