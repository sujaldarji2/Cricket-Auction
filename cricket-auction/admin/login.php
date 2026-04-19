<?php
session_start();
include '../includes/db-config.php';

// Redirect to admin if already logged in
if(isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    if(empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        // Using MD5 for simplicity
        $md5_password = md5($password);
        
        // Use prepared statement to prevent SQL injection
        $query = "SELECT * FROM admin WHERE username = ? AND password = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ss", $username, $md5_password);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if(mysqli_num_rows($result) == 1) {
            $admin = mysqli_fetch_assoc($result);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_id'] = $admin['id'];
            
            // Redirect to dashboard
            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid username or password!";
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cricket Auction - Admin Login</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
            padding: 50px 40px;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .cricket-icon {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .cricket-icon i {
            font-size: 70px;
            color: #667eea;
            background: #f0f3ff;
            padding: 20px;
            border-radius: 50%;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.2);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-header h1 {
            color: #2c3e50;
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .login-header p {
            color: #7f8c8d;
            font-size: 15px;
            letter-spacing: 0.5px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #34495e;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            background: #fafafa;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .form-group input:hover {
            border-color: #b0b0b0;
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .error-message {
            background: #fee;
            color: #e74c3c;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            text-align: center;
            border: 1px solid #fcc;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .error-message i {
            font-size: 18px;
        }
        
        .info-box {
            background: #f0f7ff;
            border: 1px solid #b8daff;
            border-radius: 12px;
            padding: 15px;
            margin-top: 25px;
            text-align: center;
        }
        
        .info-box p {
            color: #004085;
            font-size: 14px;
            margin: 5px 0;
        }
        
        .info-box strong {
            color: #0056b3;
            font-weight: 700;
        }
        
        .test-links {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
            font-size: 13px;
        }
        
        .test-links a {
            color: #667eea;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 20px;
            background: #f0f3ff;
            transition: all 0.3s;
        }
        
        .test-links a:hover {
            background: #667eea;
            color: white;
        }
        
        .input-icon {
            position: relative;
        }
        
        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #95a5a6;
            font-size: 16px;
        }
        
        .input-icon input {
            padding-left: 45px;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="cricket-icon">
            <i class="fas fa-baseball-ball"></i>
        </div>
        
        <div class="login-header">
            <h1>Welcome Back!</h1>
            <p>Sign in to access admin panel</p>
        </div>
        
        <?php if($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user"></i> Username
                </label>
                <div class="input-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" id="username" name="username" required 
                           placeholder="Enter your username" value="admin" autocomplete="off">
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i> Password
                </label>
                <div class="input-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter your password" value="admin123">
                </div>
            </div>
            
            <button type="submit" class="btn-login" id="loginBtn">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>
        
        <div class="info-box">
            <p><i class="fas fa-info-circle"></i> Default Credentials</p>
            <p><strong>Username:</strong> admin | <strong>Password:</strong> admin123</p>
        </div>
        
        <div class="test-links">
            <a href="test_db.php" target="_blank"><i class="fas fa-database"></i> Test DB</a>
            <a href="setup_admin.php" target="_blank"><i class="fas fa-tools"></i> Setup</a>
        </div>
    </div>

    <script>
    // Add loading state to button
    document.getElementById('loginForm').addEventListener('submit', function() {
        const btn = document.getElementById('loginBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
        btn.disabled = true;
    });
    </script>
</body>
</html>