<?php
session_start();
require_once __DIR__ . '/../../../config/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $badge_number = trim($_POST['badge_number']);
    $department = trim($_POST['department']);
    
    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($badge_number)) {
        $error = 'All fields are required';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } else {
        try {
            $db = new PDO('sqlite:' . __DIR__ . '/../data/jailtrak.db');
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM probation_officers WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered';
            } else {
                // Hash password and create account
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("
                    INSERT INTO probation_officers (name, email, password_hash, badge_number, department, email_notifications, created_at)
                    VALUES (?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([$name, $email, $passwordHash, $badge_number, $department]);
                
                $success = 'Account created successfully! You can now login.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Probation Officer</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .register-container {
            background: linear-gradient(135deg, #1e1e3f 0%, #2a2a4a 100%);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            max-width: 500px;
            width: 100%;
            border: 1px solid rgba(100,149,237,0.3);
        }
        
        h1 {
            color: #6495ed;
            font-size: 2em;
            margin-bottom: 10px;
            text-align: center;
            text-shadow: 0 0 20px rgba(100,149,237,0.5);
        }
        
        .subtitle {
            color: #a0a0a0;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #a0a0a0;
            font-weight: 600;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            background: #16213e;
            color: #e0e0e0;
            font-size: 1em;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #6495ed;
            box-shadow: 0 0 15px rgba(100,149,237,0.3);
        }
        
        .btn-register {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #6495ed 0%, #4169e1 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.1em;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(100,149,237,0.5);
        }
        
        .error {
            background: rgba(255,68,68,0.2);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #ff4444;
            color: #ff6868;
        }
        
        .success {
            background: rgba(0,255,136,0.2);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #00ff88;
            color: #00ff88;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #a0a0a0;
        }
        
        .login-link a {
            color: #6495ed;
            text-decoration: none;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h1>👮 Register Account</h1>
        <p class="subtitle">Probation Officer Alert System</p>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">
                <?= htmlspecialchars($success) ?>
                <br><a href="probation_login.php" style="color: #00ff88;">Click here to login</a>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name" required placeholder="John Doe" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="email" required placeholder="officer@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label>Badge Number *</label>
                <input type="text" name="badge_number" required placeholder="12345" value="<?= htmlspecialchars($_POST['badge_number'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label>Department</label>
                <input type="text" name="department" placeholder="Clayton County Probation" value="<?= htmlspecialchars($_POST['department'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label>Password * (min 8 characters)</label>
                <input type="password" name="password" required placeholder="Enter password">
            </div>
            
            <div class="form-group">
                <label>Confirm Password *</label>
                <input type="password" name="confirm_password" required placeholder="Re-enter password">
            </div>
            
            <button type="submit" class="btn-register">Create Account</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="probation_login.php">Login here</a>
        </div>
    </div>
</body>
</html>