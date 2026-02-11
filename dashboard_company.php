<?php
session_start();
require 'db.php';


if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'company') {
    header("Location: login.php?role=company");
    exit();
}

$owner_name = $_SESSION['owner_name'] ?? 'Unknown';
$message = "";

// Handle CSV upload
if (isset($_POST['upload'])) {
    $file = $_FILES['csv_file']['tmp_name'];

    if ($_FILES['csv_file']['size'] > 0) {
        $handle = fopen($file, "r");
        fgetcsv($handle); // Skip header row

        $inserted = 0;
        $skipped = 0;

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Skip empty rows
            if (empty(array_filter($data))) continue;

            list(
                $Interview_Date,
                $Student_Name,
                $Student_ID,
                $WhatsApp_Number,
                $owner_name_csv,
                $Course_Name,
                $Interview_Attended,
                $AI_Interviewer_Name,
                $Interview_Duration_mins,
                $Total_Questions_Asked,
                $Questions_Attempted,
                $Correct_Answers_Percentage,
                $Communication_Score,
                $Confidence_Score,
                $Technical_Knowledge_Score,
                $Overall_Score,
                $Strong_Topics,
                $Weak_Topics,
                $Topics_to_Work_On,
                $Extra_Suggestions,
                $Ranking_in_Batch,
                $Interview_Summary,
                $Improvement_Since_Last_Percentage
            ) = array_pad($data, 23, null);

            // 🧩 Convert date formats like dd-mm-yyyy or dd/mm/yyyy → yyyy-mm-dd
            $Interview_Date = str_replace('/', '-', trim($Interview_Date));
            if (!empty($Interview_Date)) {
                $timestamp = strtotime($Interview_Date);
                if ($timestamp) {
                    $Interview_Date = date("Y-m-d", $timestamp);
                } else {
                    $Interview_Date = null; // Invalid date
                }
            }

            // Default owner if missing in CSV
            $owner_name_csv = $owner_name_csv ?: $owner_name;

            // ✅ Prevent duplicate entries for same Student_ID + Date + Owner
            $check = $conn->prepare("SELECT * FROM students WHERE Student_ID=? AND Interview_Date=? AND owner_name=?");
            $check->bind_param("sss", $Student_ID, $Interview_Date, $owner_name_csv);
            $check->execute();
            $exists = $check->get_result()->num_rows;
            $check->close();

            if ($exists) {
                $skipped++;
                continue;
            }

            // ✅ Insert Data
            $stmt = $conn->prepare("
                INSERT INTO students (
                    Interview_Date, Student_Name, Student_ID, WhatsApp_Number, owner_name,
                    Course_Name, Interview_Attended, AI_Interviewer_Name, Interview_Duration_mins,
                    Total_Questions_Asked, Questions_Attempted, Correct_Answers_Percentage,
                    Communication_Score, Confidence_Score, Technical_Knowledge_Score, Overall_Score,
                    Strong_Topics, Weak_Topics, Topics_to_Work_On, Extra_Suggestions,
                    Ranking_in_Batch, Interview_Summary, Improvement_Since_Last_Percentage
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");

            $stmt->bind_param(
                "ssssssssiiidddddssssisd",
                $Interview_Date, $Student_Name, $Student_ID, $WhatsApp_Number, $owner_name_csv,
                $Course_Name, $Interview_Attended, $AI_Interviewer_Name, $Interview_Duration_mins,
                $Total_Questions_Asked, $Questions_Attempted, $Correct_Answers_Percentage,
                $Communication_Score, $Confidence_Score, $Technical_Knowledge_Score, $Overall_Score,
                $Strong_Topics, $Weak_Topics, $Topics_to_Work_On, $Extra_Suggestions,
                $Ranking_in_Batch, $Interview_Summary, $Improvement_Since_Last_Percentage
            );

            if ($stmt->execute()) $inserted++;
        }

        fclose($handle);
        $message = "✅ Upload complete — Inserted: $inserted | Skipped (duplicates): $skipped";
    } else {
        $message = "⚠️ Please upload a valid CSV file.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Company Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: 'Manrope', sans-serif;
        background: #f5f5f0;
        color: #0a0a0a;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
    }

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

    .dashboard {
        background: white;
        padding: 40px;
        border: 2px solid #000;
        border-radius: 12px;
        width: 90%;
        max-width: 600px;
        box-shadow: 8px 8px 0px #000;
        text-align: center;
        animation: fadeIn 0.8s ease forwards;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    h2, h3 {
        font-family: 'Space Mono', monospace;
        margin-bottom: 20px;
    }

    form {
        display: flex;
        flex-direction: column;
        gap: 15px;
        margin-bottom: 15px;
    }

    input[type="file"] {
        border: 2px dashed #0a0a0a;
        border-radius: 12px;
        padding: 10px;
        cursor: pointer;
        background: #fafafa;
    }

    input[type="file"]::file-selector-button {
        background: #0a0a0a;
        color: #fff;
        border: none;
        border-radius: 20px;
        padding: 8px 16px;
        cursor: pointer;
    }

    input[type="submit"], .btn {
        background: #98ff98;
        color: #0a0a0a;
        border: 2px solid #0a0a0a;
        padding: 12px 28px;
        border-radius: 30px;
        cursor: pointer;
        font-weight: bold;
        box-shadow: 5px 5px 0px #000;
        transition: all 0.2s;
    }

    input[type="submit"]:hover, .btn:hover {
        transform: translateY(-2px);
        box-shadow: 7px 7px 0px #000;
    }

    .logout-btn {
        background: #ff4e4e;
        color: white;
        border: 2px solid #000;
    }

    .message {
        margin-top: 20px;
        font-weight: bold;
        color: green;
    }
</style>
</head>
<body>
<div class="dashboard">
    <h2>Welcome, <?= htmlspecialchars($owner_name) ?></h2>
    <h3>Upload Interview Data (CSV)</h3>

    <form method="post" enctype="multipart/form-data">
        <input type="file" name="csv_file" accept=".csv" required>
        <input type="submit" name="upload" value="Upload CSV">
    </form>

    <?php if ($message): ?>
        <p class="message"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <a href="view_students_company.php"><button class="btn">View Uploaded Data</button></a>
    <a href="logout.php"><button class="btn logout-btn">Logout</button></a>
</div>
</body>
</html>
