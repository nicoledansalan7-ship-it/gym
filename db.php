<?php

$conn = mysqli_connect("localhost", "root", "", "vigor_gym", 3306);

if (!$conn) {
    die("Database connection failed.");
}

mysqli_set_charset($conn, "utf8mb4");