<?php
session_start();
require_once __DIR__ . '/../../../config/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    try {
        $db = new PDO('sqlite:' . __DIR__ . '/../data/jailtrak.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $db->prepare("SELECT * FROM probation_officers WHERE email = ?");
        $stmt->execute([$email]);
        $officer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($officer && password_verify($password, $officer['password_hash'])) {
            $_SESSION['officer_id'] = $officer['id'];
            $_SESSION['officer_name'] = $officer['name'];
            header('Location: probation_dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Probation Officer Login</title>
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
        
        .login-container {
            background: linear-gradient(135deg, #1e1e3f 0%, #2a2a4a 100%);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            max-width: 450px;
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
            padding: 15px;
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
        
        .btn-login {
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
        
        .btn-login:hover {
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
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #a0a0a0;
        }
        
        .register-link a {
            color: #6495ed;
            text-decoration: none;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>👮 Probation Officer</h1>
        <p class="subtitle">Inmate Alert System</p>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required placeholder="officer@example.com">
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Enter your password">
            </div>
            
            <button type="submit" class="btn-login">Login</button>
        </form>
        
        <div class="register-link">
            Don't have an account? <a href="probation_register.php">Register here</a>
        </div>
    </div>
</body>
</html>