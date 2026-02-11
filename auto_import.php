<?php
// ==========================================
//  AUTO IMPORT EVERY 15 MINUTES
// ==========================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --------------------
// PREVENT MULTIPLE RUNS WITHIN 15 MINUTES
// --------------------
$lastRunFile = __DIR__ . "/last_run.txt";

if (file_exists($lastRunFile)) {
    $lastRun = intval(file_get_contents($lastRunFile));
    $now = time();

    if ($now - $lastRun < 900) { // 900 sec = 15 min
        exit("Already ran less than 15 mins ago.");
    }
}

file_put_contents($lastRunFile, time());

// --------------------
// DOWNLOAD CSV FROM GOOGLE DRIVE (PUBLIC LINK)
// --------------------
$FILE_ID = "12t-LgM0qwa0a7YjVCSkBGJmYFZgGrkAc"; // Replace with your CSV file ID
$sheetURL = "https://drive.google.com/uc?export=download&id=" . $FILE_ID;

$csvFile = __DIR__ . "/students.csv";

// Download CSV using cURL
$ch = curl_init($sheetURL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$csvData = curl_exec($ch);
$curlErr = curl_error($ch);
curl_close($ch);

if (!$csvData) {
    exit("Failed to fetch CSV from Google Drive. Error: $curlErr");
}

file_put_contents($csvFile, $csvData);

// --------------------
// IMPORT CSV TO DATABASE
// --------------------
include "db.php"; // Make sure $conn is your mysqli connection

if (($handle = fopen($csvFile, "r")) !== FALSE) {

    $header = fgetcsv($handle); // Skip header

    while (($row = fgetcsv($handle, 5000, ",")) !== FALSE) {
        if(count($row) < 1) continue; // Skip empty lines

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
        ) = $row;

        // Insert with duplicate check on Student_ID
        $stmt = $conn->prepare("
            INSERT INTO students (
                Student_ID, Interview_Date, Student_Name, WhatsApp_Number,
                owner_name, Course_Name, Interview_Attended,
                AI_Interviewer_Name, Interview_Duration_mins,
                Total_Questions_Asked, Questions_Attempted,
                Correct_Answers_Percentage, Communication_Score,
                Confidence_Score, Technical_Knowledge_Score,
                Overall_Score, Strong_Topics, Weak_Topics,
                Topics_to_Work_On, Extra_Suggestions, Ranking_in_Batch,
                Interview_Summary, Improvement_Since_Last_Percentage
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                Interview_Date=VALUES(Interview_Date),
                Student_Name=VALUES(Student_Name),
                WhatsApp_Number=VALUES(WhatsApp_Number),
                owner_name=VALUES(owner_name),
                Course_Name=VALUES(Course_Name),
                Interview_Attended=VALUES(Interview_Attended),
                AI_Interviewer_Name=VALUES(AI_Interviewer_Name),
                Interview_Duration_mins=VALUES(Interview_Duration_mins),
                Total_Questions_Asked=VALUES(Total_Questions_Asked),
                Questions_Attempted=VALUES(Questions_Attempted),
                Correct_Answers_Percentage=VALUES(Correct_Answers_Percentage),
                Communication_Score=VALUES(Communication_Score),
                Confidence_Score=VALUES(Confidence_Score),
                Technical_Knowledge_Score=VALUES(Technical_Knowledge_Score),
                Overall_Score=VALUES(Overall_Score),
                Strong_Topics=VALUES(Strong_Topics),
                Weak_Topics=VALUES(Weak_Topics),
                Topics_to_Work_On=VALUES(Topics_to_Work_On),
                Extra_Suggestions=VALUES(Extra_Suggestions),
                Ranking_in_Batch=VALUES(Ranking_in_Batch),
                Interview_Summary=VALUES(Interview_Summary),
                Improvement_Since_Last_Percentage=VALUES(Improvement_Since_Last_Percentage)
        ");

        $stmt->bind_param(
            "sssssssssssssssssssssss",
            $Student_ID,
            $Interview_Date,
            $Student_Name,
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
        );

        $stmt->execute();
    }

    fclose($handle);
}

echo "CSV import complete!";
?>
