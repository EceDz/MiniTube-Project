<?php
//20220702045 Ece Duzgec

// Authenticates existing users or creates new accounts in the USERS table.

header('Content-Type: application/json');

$conn = new mysqli("localhost", "root", "mysql", "ece_duzgec");

if ($conn->connect_error) {
    echo json_encode(['ok' => false, 'error' => 'Database connection failed.']);
    exit;
}

$action = $_POST['action'] ?? '';

/*Sign in */
if ($action === 'signin') {

    $uname = trim($_POST['username'] ?? '');
    $pass  = trim($_POST['password'] ?? '');

    if ($uname === '' || $pass === '') {
        echo json_encode(['ok' => false, 'error' => 'Please fill in all fields.']);
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT user_id, password, full_name FROM USERS WHERE username = ?"
    );
    $stmt->bind_param("s", $uname);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['ok' => false, 'error' => 'No account found with that username.']);
        exit;
    }

    $row         = $result->fetch_assoc();
    $user_id     = $row['user_id'];
    $stored_pass = $row['password'];
    $full_name   = $row['full_name'];

    // Passwords in the database are stored as plain text (consistent with seed data).
    // password_verify() fallback handles any old hashed rows that may still exist.
    if ($pass !== $stored_pass && !password_verify($pass, $stored_pass)) {
        echo json_encode(['ok' => false, 'error' => 'Incorrect password.']);
        exit;
    }

    echo json_encode(['ok' => true, 'user_id' => $user_id, 'full_name' => $full_name]);
    exit;
}

/* Sign up */
if ($action === 'signup') {

    $full_name = trim($_POST['full_name'] ?? '');
    $uname     = trim($_POST['username']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $country   = trim($_POST['country']   ?? '');
    $bio       = trim($_POST['bio']       ?? '');
    $pass      = trim($_POST['password']  ?? '');

    if ($full_name === '' || $uname === '' || $email === '' || $country === '' || $pass === '') {
        echo json_encode(['ok' => false, 'error' => 'Please fill in all required fields.']);
        exit;
    }

    if (!preg_match('/^[a-zA-Z0-9]+$/', $uname)) {
        echo json_encode(['ok' => false, 'error' => 'Username must contain only letters and numbers.']);
        exit;
    }

    if (strlen($pass) < 4) {
        echo json_encode(['ok' => false, 'error' => 'Password must be at least 4 characters.']);
        exit;
    }

    // Check if username is taken
    $stmt = $conn->prepare("SELECT user_id FROM USERS WHERE username = ?");
    $stmt->bind_param("s", $uname);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo json_encode(['ok' => false, 'error' => 'Username already taken. Please choose another.']);
        exit;
    }
    $stmt->close();

    // Check if email is taken
    $stmt = $conn->prepare("SELECT user_id FROM USERS WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo json_encode(['ok' => false, 'error' => 'An account with that email already exists.']);
        exit;
    }
    $stmt->close();

    // Store password as plain text 
    $img    = "https://picsum.photos/seed/" . $uname . "/150/150";
    $joined = date('Y-m-d');

    $stmt = $conn->prepare(
        "INSERT INTO USERS (username, password, user_image, full_name, email, country, joined_on, bio)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("ssssssss", $uname, $pass, $img, $full_name, $email, $country, $joined, $bio);

    if (!$stmt->execute()) {
        echo json_encode(['ok' => false, 'error' => 'Registration failed: ' . $stmt->error]);
        exit;
    }

    $new_id = $conn->insert_id;
    $stmt->close();

    // Assign 3 random channel subscriptions to the new user so their feed is not empty the first time they log in.
    // Pick channels that are not owned by this user as new users never own a channel.
    $sub_stmt = $conn->prepare(
        "INSERT IGNORE INTO SUBSCRIPTIONS (subscriber_id, channel_id, subscribed_at)
         SELECT ?, channel_id, NOW()
         FROM   CHANNELS
         ORDER BY RAND()
         LIMIT 3"
    );
    $sub_stmt->bind_param("i", $new_id);
    $sub_stmt->execute();
    $sub_stmt->close();

    echo json_encode(['ok' => true, 'user_id' => $new_id, 'full_name' => $full_name]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
?>
