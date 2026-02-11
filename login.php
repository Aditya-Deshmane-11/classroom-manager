<?php
session_start();
require 'db.php';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];  // ✅ coming from form

    $query = $conn->query("SELECT * FROM users WHERE email='$email' AND password='$password' AND role='$role'");

    if($query->num_rows > 0){
        $user = $query->fetch_assoc();

        $_SESSION['class_name'] = $user['class_name'];
        $_SESSION['owner_name'] = $user['owner_name'];
        $_SESSION['role'] = $role;
        $_SESSION['user_id'] = $user['id'];

        // ✅ Redirect based on role
        if($role === "class"){
            header("Location: dashboard_class.php");
        } else {
            header("Location: dashboard_company.php");
        }
        exit;
    } else {
        echo "Invalid login details";
        exit;
    }
}

$role = $_GET['role']; // coming from index.html
?>

<!DOCTYPE html>
<html>
<head>
<title>Login</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">

<style>
    /* REMOVED all old styles.
      REPLACED with the new "neo-brutalist" theme.
    */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        /* NEW: Theme Fonts */
        font-family: 'Manrope', -apple-system, sans-serif;
        
        /* NEW: Theme Colors & Background */
        background: #f5f5f0; /* Off-white/Cream */
        color: #0a0a0a; /* Black */
        
        /* Centering (from original code) */
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        position: relative;
    }

    /* NEW: Aesthetic Grid Background */
    body::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-image: 
            linear-gradient(to right, rgba(10, 10, 10, 0.05) 1px, transparent 1px),
            linear-gradient(to bottom, rgba(10, 10, 10, 0.05) 1px, transparent 1px);
        background-size: 30px 30px;
        z-index: -1;
    }

    /* NEW: Brutalist Form Container */
    form {
        background: #ffffff;
        padding: 30px 40px;
        border: 2px solid #0a0a0a;
        border-radius: 12px;
        box-shadow: 8px 8px 0px #0a0a0a;
        text-align: center;
        width: 320px;

        /* NEW: Animation */
        opacity: 0;
        transform: translateY(20px);
        animation: fadeIn 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    }

    @keyframes fadeIn {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* NEW: Header Font */
    h3 {
        font-family: 'Space Mono', monospace;
        margin-bottom: 20px;
        font-size: 22px;
        color: #0a0a0a;
    }

    /* NEW: Themed Input Fields */
    input[type="email"],
    input[type="password"] {
        font-family: 'Manrope', sans-serif;
        width: 100%;
        padding: 12px;
        margin: 10px 0;
        border: 2px solid #0a0a0a;
        border-radius: 12px;
        font-size: 15px;
        background: #f5f5f0;
    }
    
    /* Makes placeholder text clearer */
    ::placeholder {
        color: #555;
        opacity: 1;
    }

    /* NEW: Brutalist Button */
    button {
        font-family: 'Manrope', sans-serif;
        font-weight: 700;
        width: 100%;
        padding: 12px;
        background: #0a0a0a;
        border: 2px solid #0a0a0a;
        border-radius: 30px; /* Pill shape */
        color: white;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 10px; /* Added margin */
        box-shadow: 5px 5px 0px #98ff98; /* Neon green shadow */
    }

    button:hover {
        transform: translateY(-2px);
        box-shadow: 7px 7px 0px #98ff98;
    }
</style>

</head>
<body>

<form action="login.php" method="post">
  <h3><?= ucfirst($role) ?> Login</h3>

  <input type="email" name="email" placeholder="Email" required><br>
  <input type="password" name="password" placeholder="Password" required><br>

  <input type="hidden" name="role" value="<?= $role ?>">

  <button type="submit" name="login">Login</button>
</form>

</body>
</html>

