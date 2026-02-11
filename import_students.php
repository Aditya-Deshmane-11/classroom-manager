<?php
session_start();
include "config.php"; // DB connection

if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'class'){
  header("Location: index.html");
  exit();
}

$class_owner = $_SESSION['user']['name'];

if(isset($_FILES['file']['name'])){
    $file = $_FILES['file']['tmp_name'];
    $handle = fopen($file, "r");

    // Skip header row (optional)
    fgetcsv($handle);

    while(($data = fgetcsv($handle, 1000, ",")) !== FALSE){
        $student_name = $data[0];
        $phone_number = $data[1];
        $performance_star = $data[2];
        $strong_topics = $data[3];
        $weak_topics = $data[4];
        $topics_to_work_on = $data[5];
        $suggestions = $data[6];

        $sql = "INSERT INTO students(class_owner, student_name, phone_number, performance_star, strong_topics, weak_topics, topics_to_work_on, suggestions) 
        VALUES('$class_owner','$student_name','$phone_number','$performance_star','$strong_topics','$weak_topics','$topics_to_work_on','$suggestions')";

        mysqli_query($conn, $sql);
    }
    fclose($handle);
    echo "Upload Successful! <a href='dashboard_class.php'>Go Back</a>";
}
?>
