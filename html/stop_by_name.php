<?php
// Load configuration
$config = json_decode(file_get_contents('config.json'), true);

// Include UniFi API client
require_once './UniFi-API-client/src/Client.php';

// Connect to UniFi
$unifi_connection = new UniFi_API\Client(
    $config['unifi']['username'],
    $config['unifi']['password'],
    $config['unifi']['url'],
    $config['unifi']['site'],
    $config['unifi']['version'],
    false
);
$login = $unifi_connection->login();

// Connect to the MySQL database using mysqli
$mysqli = new mysqli(
    $config['database']['host'],
    $config['database']['username'],
    $config['database']['password'],
    $config['database']['dbname'],
    $config['database']['port']
);

// Check connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Get user from POST data
$user = isset($_POST['user']) ? $_POST['user'] : 'nooneisset';
$results = [];

// List all devices
$devices = $unifi_connection->stat_allusers();
$macAddresses = [];

// Filter devices that match the user
foreach ($devices as $device) {
    if (isset($device->name) && preg_match('/^' . preg_quote($user, '/') . ".*s\s-\s(?!(Alexa|iPhone|Fire\sStick|TV)\b).+/i", $device->name)) {
        $macAddresses[] = $device->mac;
    }
}

// Check if any devices were found for the user
if (empty($macAddresses)) {
    die("No devices found for user: " . htmlspecialchars($user));
}

// Flag to track if any MAC address was blocked successfully
$anySuccess = false;

// Iterate through MAC addresses and block each one
foreach ($macAddresses as $mac) {
    $mac = trim($mac);
    if ($mac) {
        $result = $unifi_connection->block_sta($mac);
        if ($result) {
            $anySuccess = true; // Mark as successful if any blocking succeeds
        }
        $results[$mac] = $result; // Store the result for each MAC
    }
}

// Only update the user state if at least one MAC was successfully blocked
if ($anySuccess) {
    // Add session time
    $stmt = $mysqli->prepare("SELECT * FROM user_state WHERE name = ?");
    $stmt->bind_param('s', $user);
    $stmt->execute();
    $res = $stmt->get_result();
    $userState = $res->fetch_assoc();

    if ($userState) {
        // Calculate the session time
        $started = $userState['last_updated']; // Assuming last_updated is the starting time
        $stopped = date('Y-m-d H:i:s'); // Current time
        $session_time_minutes = (strtotime($stopped) - strtotime($started)) / 60;

        // Insert the entry into time_used table
        $stmt = $mysqli->prepare("INSERT INTO time_used (name, session_time_minutes, started, stopped) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('siss', $user, $session_time_minutes, $started, $stopped);
        $stmt->execute();
        $stmt->close();
    }

    // Update user_state
    $stmt = $mysqli->prepare("UPDATE user_state SET state = 'off', time = -1, last_updated = NOW() WHERE name = ?");
    $stmt->bind_param('s', $user);
    $stmt->execute();
    $stmt->close();
}

// Close the database connection
$mysqli->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stop Time Result</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <meta http-equiv="refresh" content="30;url=index.php">
</head>
<body class="bg-dark text-white">
    <div class="container text-center mt-5">
        <h2>Stop Time Results:</h2>
        <ul class="list-group">
            <?php foreach ($results as $mac => $success): ?>
                <li class="list-group-item bg-secondary text-white">
                    MAC: <?php echo htmlspecialchars($mac); ?> -
                    <?php echo $success ? 'Success' : 'Failed'; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <a href="index.php" class="btn btn-secondary mt-4">Back</a>
    </div>
</body>
</html>

