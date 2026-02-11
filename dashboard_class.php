<?php
session_start();
require 'db.php'; // Make sure this connects to your database


if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'class'){
    header("Location: login.php?role=class");
    exit();
}

$class_name = $_SESSION['class_name'];
$owner_name = $_SESSION['owner_name'];

// Handle CSV upload
if(isset($_POST['upload'])){
    $file = $_FILES['csv_file']['tmp_name'];

    if($_FILES['csv_file']['size'] > 0){
        $handle = fopen($file, "r");

        // Skip header row
        fgetcsv($handle);

        while(($data = fgetcsv($handle, 1000, ",")) !== FALSE){
            $student_name = $data[0];
            $phone = $data[1];
            $star = $data[2];
            $strong = $data[3];
            $weak = $data[4];
            $work = $data[5];
            $suggest = $data[6];

            $stmt = $conn->prepare("INSERT INTO students (owner_name, class_name, student_name, phone_number, performance_star, strong_topics, weak_topics, topics_to_work_on, suggestions)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssisssss", $owner_name, $class_name, $student_name, $phone, $star, $strong, $weak, $work, $suggest);
            $stmt->execute();
        }

        fclose($handle);

        $upload_success = "✅ Students data uploaded successfully!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Class Dashboard</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">

<style>
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
    min-height: 100vh;
    padding: 40px;
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

  /* NEW: Brutalist Container */
  .dashboard-container {
    background: #fff;
    border: 2px solid #0a0a0a;
    border-radius: 12px;
    padding: 40px 50px;
    max-width: 700px;
    width: 100%; /* Changed from 90% */
    box-shadow: 8px 8px 0px #0a0a0a;
    
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

  /* NEW: Header Fonts */
  h2 {
    font-family: 'Space Mono', monospace;
    font-size: 28px;
    margin-bottom: 25px;
    color: #0a0a0a;
  }

  h3 {
    font-family: 'Space Mono', monospace;
    margin-top: 25px;
    margin-bottom: 12px;
    color: #0a0a0a;
  }
  
  p {
    color: #333;
    margin-bottom: 10px;
  }

  /* Remove underline from links */
  a {
    text-decoration: none;
  }
  
  /* Make all button-links full width */
  .link-btn {
    display: block;
    width: 100%;
    margin-top: 15px;
  }

  /* NEW: Brutalist Button */
  button {
    font-family: 'Manrope', sans-serif;
    font-weight: 700;
    padding: 12px 28px;
    width: 100%; /* Make buttons full-width */
    background: #0a0a0a;
    border: 2px solid #0a0a0a;
    border-radius: 30px; /* Pill shape */
    color: #fff;
    font-size: 15px;
    cursor: pointer;
    margin-top: 10px;
    transition: all 0.3s;
    box-shadow: 5px 5px 0px #98ff98; /* Neon green shadow */
  }

  button:hover {
    transform: translateY(-2px);
    box-shadow: 7px 7px 0px #98ff98;
  }

  /* NEW: Themed Logout Button */
  .logout-btn {
    background: #ff4e4e; /* Danger red */
    color: #0a0a0a; /* High contrast black text */
    font-weight: 700;
    box-shadow: 5px 5px 0px #0a0a0a; /* Black shadow */
  }

  .logout-btn:hover {
    background: #d43b3b;
    box-shadow: 7px 7px 0px #0a0a0a;
  }

  /* Themed upload message */
  .upload-message {
    color: green;
    margin-top: 20px;
    font-weight: 700;
    text-align: center;
  }
</style>
</head>
<body>

<div class="dashboard-container">

<h2>Welcome, <?php echo htmlspecialchars($owner_name); ?> (<?php echo htmlspecialchars($class_name); ?>)</h2>

<h3>Student Data Sync</h3>
<p>Data will automatically sync from Google Sheet every 24 hours.</p>

<!-- <a class="link-btn" href="auto_import_students.php"><button>Sync Now (Manual)</button></a> -->


<?php if(isset($upload_success)) echo "<p class='upload-message'>$upload_success</p>"; ?>

<a class="link-btn" href="view_students.php"><button>Show Student Data</button></a>

<br>
<a class="link-btn" href="logout.php"><button class="logout-btn">Logout</button></a>

</div>
</body>

</html>