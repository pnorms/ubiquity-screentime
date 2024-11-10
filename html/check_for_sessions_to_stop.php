<?php
// Load the JSON configuration
$config = json_decode(file_get_contents('config.json'), true);

// Connect to the MySQL database using mysqli
$mysqli = new mysqli($config['database']['host'], $config['database']['username'], $config['database']['password'], $config['database']['dbname'], $config['database'
]['port']);

// Check connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Get app server info
$app_host = $config['app']['host'];
$app_port = $config['app']['port'];

// Get current time
$current_time = new DateTime();

// Query to find active sessions
$query = "SELECT * FROM user_state WHERE state = 'on'";
$result = $mysqli->query($query);

// Initialize a variable to track if any sessions were stopped
$action_taken = false;

while ($row = $result->fetch_assoc()) {
    $name = $row['name'];
    $last_updated = new DateTime($row['last_updated']);
    $time_limit = $row['time'];

    // Calculate the time difference
    $interval = $current_time->diff($last_updated);
    $minutes_passed = ($interval->h * 60) + $interval->i;

    // Check if the session needs to be stopped
    if ($minutes_passed > $time_limit) {
        // Call your stop_by_name.php logic here
        // Assuming it's a function or you can call it directly

        // For example, using a curl request:
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, ("http://" . $app_host .":" . $app_port . "/stop_by_name.php"));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['user' => $name]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch) . "\n";
        } else {
                echo "Response from stop_by_name.php: $response\n";
        }

        curl_close($ch);

        // Log the action
        $action_taken = true;
        echo "Stopping $name at " . date('Y-m-d H:i:s') . "\n";
    }
}

// Close the database connection
$mysqli->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
</head>
<body>
    <p>
    <?php
    // Return a message based on whether any action was taken
    if (!$action_taken) {
        echo "No action needed at " . date('Y-m-d H:i:s') . "\n";
    } else {
        echo "Stopping some things at " . date('Y-m-d H:i:s') . "\n";
    }
    ?>
    </p>
</body>
</html>
