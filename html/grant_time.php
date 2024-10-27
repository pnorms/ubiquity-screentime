<?php
// Load configuration
$config = json_decode(file_get_contents('config.json'), true);

// Include UniFi API client
require_once './UniFi-API-client/src/Client.php';

// Connect to UniFi
$unifi_connection = new UniFi_API\Client(
    'api',
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

// Get MAC addresses from POST data
$macAddresses = isset($_POST['mac_addresses']) ? explode(',', $_POST['mac_addresses']) : [];
$user = isset($_POST['user']) ? $_POST['user'] : 'nooneisset';
$time = isset($_POST['time']) ? $_POST['time'] : 'notimeisset';
$results = [];

// Flag to track if any MAC address was unblocked successfully
$anySuccess = false;

// Iterate through MAC addresses and unblock each one
foreach ($macAddresses as $mac) {
    $mac = trim($mac);
    if ($mac) {
        $result = $unifi_connection->unblock_sta($mac);
        if ($result) {
            $anySuccess = true; // Mark as successful if any unblocking succeeds
        }
        $results[$mac] = $result; // Store the result for each MAC
    }
}

// Only update the user state if at least one MAC was successfully unblocked
if ($anySuccess) {
    // Check if the record already exists
    $stmt = $mysqli->prepare("SELECT * FROM user_state WHERE name = ?");
    $stmt->bind_param('s', $user);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        // Update the existing record
        $stmt = $mysqli->prepare("UPDATE user_state SET state = 'on', time = ?, last_updated = NOW() WHERE name = ?");
        $stmt->bind_param('is', $time, $user);
    } else {
        // Insert a new record
        $stmt = $mysqli->prepare("INSERT INTO user_state (name, state, time) VALUES (?, 'on', ?)");
        $stmt->bind_param('si', $user, $time);
    }
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
    <title>Grant Time Result</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <meta http-equiv="refresh" content="30;url=index.php">
</head>
<body class="bg-dark text-white">
    <div class="container text-center mt-5">
        <h2>Grant Time Results:</h2>
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
