<?php
//20220702045 Ece Duzgec

//Drops, recreates and seeds the database with all six tables.
set_time_limit(0);
$servername = "localhost";
$username = "root";
$password = "mysql";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Drop existing database and recreate fresh
$conn->query("DROP DATABASE IF EXISTS ece_duzgec");

// Create database
$sql = "CREATE DATABASE ece_duzgec";

if ($conn->query($sql) === FALSE) {
    die("Error creating database: " . mysqli_connect_error());
}

// Select database
mysqli_select_db($conn, 'ece_duzgec');

// Create tables
$sql = "CREATE TABLE USERS (
    user_id    INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50) NOT NULL UNIQUE,
    password   VARCHAR(300) NOT NULL,
    user_image VARCHAR(500),
    full_name  VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL UNIQUE,
    country    VARCHAR(200) NOT NULL,
    joined_on  DATE NOT NULL,
    bio        TEXT
) ENGINE=InnoDB" ;

if ($conn->query($sql) === FALSE) {
    die("Error creating USERS table: " . mysqli_connect_error());
}

$sql = "CREATE TABLE CHANNELS (
    channel_id    INT AUTO_INCREMENT PRIMARY KEY,
    owner_id      INT NOT NULL UNIQUE,
    channel_image VARCHAR(500),
    name          VARCHAR(150) NOT NULL,
    description   TEXT,
    created_on    DATE NOT NULL,
    category      VARCHAR(100) NOT NULL,
    FOREIGN KEY (owner_id) REFERENCES USERS(user_id) ON DELETE CASCADE
) ENGINE=InnoDB";

if ($conn->query($sql) === FALSE) {
    die("Error creating CHANNELS table: " . mysqli_connect_error());
}

$sql = "CREATE TABLE VIDEOS (
    video_id         INT AUTO_INCREMENT PRIMARY KEY,
    channel_id       INT NOT NULL,
    title            VARCHAR(300) NOT NULL,
    description      TEXT,
    url              VARCHAR(500) NOT NULL,
    duration_seconds INT NOT NULL DEFAULT 0,
    uploaded_at      DATETIME NOT NULL,
    view_count       INT NOT NULL DEFAULT 0,
    like_count       INT NOT NULL DEFAULT 0,
    FOREIGN KEY (channel_id) REFERENCES CHANNELS(channel_id) ON DELETE CASCADE
) ENGINE=InnoDB";

if ($conn->query($sql) === FALSE) {
    die("Error creating VIDEOS table: " . mysqli_connect_error());
}

$sql = "CREATE TABLE SUBSCRIPTIONS (
    subscription_id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id   INT NOT NULL,
    channel_id      INT NOT NULL,
    subscribed_at   DATETIME NOT NULL,
    UNIQUE KEY uq_sub (subscriber_id, channel_id),
    FOREIGN KEY (subscriber_id) REFERENCES USERS(user_id) ON DELETE CASCADE,
    FOREIGN KEY (channel_id) REFERENCES CHANNELS(channel_id) ON DELETE CASCADE
) ENGINE=InnoDB";

if ($conn->query($sql) === FALSE) {
    die("Error creating SUBSCRIPTIONS table: " . mysqli_connect_error());
}

$sql = "CREATE TABLE COMMENTS (
    comment_id        INT AUTO_INCREMENT PRIMARY KEY,
    video_id          INT NOT NULL,
    user_id           INT NOT NULL,
    parent_comment_id INT DEFAULT NULL,
    body              TEXT NOT NULL,
    posted_at         DATETIME NOT NULL,
    FOREIGN KEY (video_id) REFERENCES VIDEOS(video_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES USERS(user_id) ON DELETE CASCADE,
    FOREIGN KEY (parent_comment_id) REFERENCES COMMENTS(comment_id) ON DELETE CASCADE
) ENGINE=InnoDB";

if ($conn->query($sql) === FALSE) {
    die("Error creating COMMENTS table: " . mysqli_connect_error());
}

$sql = "CREATE TABLE LIKES (
    like_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    video_id   INT NOT NULL,
    liked_at   DATETIME NOT NULL,
    UNIQUE KEY uq_like (user_id, video_id),
    FOREIGN KEY (user_id)  REFERENCES USERS(user_id)  ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES VIDEOS(video_id) ON DELETE CASCADE
) ENGINE=InnoDB ";

if ($conn->query($sql) === FALSE) {
    die("Error creating LIKES table: " . mysqli_connect_error());
}

// Fill tables
$seed_file = __DIR__ . '/seed.sql';

if (!file_exists($seed_file)) {
    die("seed.sql not found. Run generate_data.php first.");
}

$sql = file_get_contents($seed_file);

// Disable mysqli exceptions so next_result() never throws a fatal error
mysqli_report(MYSQLI_REPORT_OFF);

// Strip FOREIGN_KEY_CHECKS from seed.sql
$sql = preg_replace('/^\s*SET\s+FOREIGN_KEY_CHECKS\s*=\s*\d+\s*;\s*$/im', '', $sql);

$conn->query("SET FOREIGN_KEY_CHECKS=0");

if ($conn->multi_query($sql) === false) {
    die("Error starting seed load: " . $conn->error);
}

do {
    if ($res = $conn->store_result()) {
        $res->free();
    }
    if ($conn->errno) {
        die("Error filling tables: " . $conn->error);
    }
} while ($conn->more_results() && $conn->next_result());

$conn->query("SET FOREIGN_KEY_CHECKS=1");
$conn->close();

echo "SUCCESS: Database 'ece_duzgec' created, tables set up and data loaded.";
?>