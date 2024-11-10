<?php
// Load the JSON configuration
$config = json_decode(file_get_contents('config.json'), true);

// Include UniFi API client
if (!$config["testing"]) {
    require_once './UniFi-API-client/src/Client.php';
} else {
    require_once './UniFi-API-client/src/Client.Testing.php';
}

// Connect to the MySQL database using mysqli
$mysqli = new mysqli($config['database']['host'], $config['database']['username'], $config['database']['password'], $config['database']['dbname'], $config['database']['port']);

// Check connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check for the device pattern
$user = isset($_GET['user']) ? $_GET['user'] : null;
$timeLimit = $config['timelimits']['per-day']['minutes'];

// Initialize variables
$totalTimeUsed = 0;
$remainingTime = $timeLimit;
$sessionInProgress = false;
$sessionStartTime = null;
$sessionStopTime = null;
$sessionTime = null;

if ($user) {
    // Get last session time
    $stmt = $mysqli->prepare("SELECT session_time_minutes FROM time_used WHERE name = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('s', $user);
    $stmt->execute();
    $stmt->bind_result($lastTimeUsed);
    $stmt->fetch();
    $stmt->close();

    // Get total time used in the last 24 hours
    $stmt = $mysqli->prepare("SELECT SUM(session_time_minutes) AS total_time FROM time_used WHERE name = ? AND started >= NOW() - INTERVAL 1 DAY");
    $stmt->bind_param('s', $user);
    $stmt->execute();
    $stmt->bind_result($totalTimeUsed);
    $stmt->fetch();
    $stmt->close();

    // Calculate remaining time
    $remainingTime = $timeLimit - $totalTimeUsed;

    // Check if there is an active session in user_state
    $stmt = $mysqli->prepare("SELECT time, last_updated FROM user_state WHERE name = ? AND state = 'on'");
    $stmt->bind_param('s', $user);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($sessionTime, $sessionStartTime);
        $stmt->fetch();
        $sessionInProgress = true;
    }
    $stmt->close();

    // Check how long ago an active session ended
    $stmt = $mysqli->prepare("SELECT last_updated FROM user_state WHERE name = ? AND state = 'off'");
    $stmt->bind_param('s', $user);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($sessionStopTime);
        $stmt->fetch();
        $sessionInProgress = false;
    }
    $stmt->close();

    // Calculate remaining session time
    $remainingSessionTime = null;
    if ($sessionInProgress) {
        $elapsedTime = (new DateTime())->diff(new DateTime($sessionStartTime));
        $elapsedMinutes = $elapsedTime->h * 60 + $elapsedTime->i; // Total elapsed minutes
        $remainingSessionTime = $sessionTime - $elapsedMinutes;
    }

    // Calculate remaining wait time, always wait at least 20 minutes
    $remainingWaitTime = -1;
    if (!$sessionInProgress && $sessionStopTime) {
        $elapsedWaitTime = (new DateTime())->diff(new DateTime($sessionStopTime));
        $elapsedWaitMinutes = $elapsedWaitTime->h * 60 + $elapsedWaitTime->i; // Total elapsed wait minutes
        $remainingWaitTime = $lastTimeUsed - $elapsedWaitMinutes;
    }

    // If there is no time left or there is wait time, save the query to unify (since there are limits)
    if ($remainingWaitTime < 0 && $remainingTime > 0 && !$sessionInProgress) {
        // Connect to UniFi
        $unifi_connection = new UniFi_API\Client($config['unifi']['username'], $config['unifi']['password'], $config['unifi']['url'], $config['unifi']['site'], $config['unifi']['version'], false);
        $login = $unifi_connection->login();

        // List all devices
        $devices = $unifi_connection->stat_allusers();

        // Filter devices that match the pattern
        $matchedDevices = [];
        foreach ($devices as $device) {
            if (isset($device->name) && preg_match('/^' . preg_quote($user, '/') . ".*s\s-\s(?!(Alexa|iPhone|Fire\sStick|TV)\b).+/i", $device->name)) {
                $matchedDevices[] = $device;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device List</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-white">
    <div class="container text-center mt-5">
        <a href="index.php" class="btn btn-secondary mb-4">Back</a>

        <?php if ($user): ?>
            <?php if ($sessionInProgress): ?>
                <p class="alert alert-warning">A session is currently in progress. Time left: <?php echo max(0, $remainingSessionTime); ?> minutes.</p>
            <?php elseif ($totalTimeUsed >= $timeLimit): ?>
                <p class="alert alert-danger">Out of time for the day.</p>
            <?php elseif ($remainingWaitTime >= 1): ?>
                <p class="alert alert-danger">You need a break (<?php echo $remainingWaitTime; ?> minutes), please wait a bit before requesting more time.</p>
            <?php else: ?>
                <p class="alert alert-success">You have <?php echo $remainingTime; ?> minutes left today.</p>
            <?php endif; ?>

            <?php if (!empty($matchedDevices)): ?>
                <h2>Found Devices:</h2>
                <ul class="list-group">
                    <?php foreach ($matchedDevices as $device): ?>
                        <li class="list-group-item bg-secondary text-white">
                            <?php echo htmlspecialchars($device->name); ?> (MAC: <?php echo htmlspecialchars($device->mac); ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($sessionInProgress): ?>
                    <form action="stop_time.php" method="post" class="mt-4">
                        <input type="hidden" name="mac_addresses" value="<?php echo htmlspecialchars(implode(',', array_column($matchedDevices, 'mac'))); ?>">
                        <input type="hidden" name="user" value="<?php echo $user; ?>">
                        <button type="submit" class="btn btn-danger">Stop The Clock</button>
                    </form>
                <?php endif; ?>

                <?php if (!$sessionInProgress && $totalTimeUsed < $timeLimit): ?>
                    <form action="grant_time.php" method="post" class="mt-4">
                        <input type="hidden" name="mac_addresses" value="<?php echo htmlspecialchars(implode(',', array_column($matchedDevices, 'mac'))); ?>">
                        <input type="hidden" name="time" value="15">
                        <input type="hidden" name="user" value="<?php echo $user; ?>">
                        <button type="submit" class="btn btn-success">Grant 15 Minutes</button>
                    </form>
                    <form action="grant_time.php" method="post" class="mt-4">
                        <input type="hidden" name="mac_addresses" value="<?php echo htmlspecialchars(implode(',', array_column($matchedDevices, 'mac'))); ?>">
                        <input type="hidden" name="time" value="30">
                        <input type="hidden" name="user" value="<?php echo $user; ?>">
                        <button type="submit" class="btn btn-success">Grant 30 Minutes</button>
                    </form>
                    <form action="grant_time.php" method="post" class="mt-4">
                        <input type="hidden" name="mac_addresses" value="<?php echo htmlspecialchars(implode(',', array_column($matchedDevices, 'mac'))); ?>">
                        <input type="hidden" name="time" value="45">
                        <input type="hidden" name="user" value="<?php echo $user; ?>">
                        <button type="submit" class="btn btn-success">Grant 45 Minutes</button>
                    </form>
                <?php endif; ?>
            <?php elseif ($user && $sessionInProgress): ?>
                    <form action="stop_by_name.php" method="post" class="mt-4">
                        <input type="hidden" name="user" value="<?php echo $user; ?>">
                        <button type="submit" class="btn btn-danger">Stop The Clock</button>
                    </form>
            <?php elseif ($user && ($remainingWaitTime > 0 || $remainingTime < 0 || $sessionInProgress)): ?>
                <br>
            <?php elseif ($user): ?>
                <p>No devices matched the user "<?php echo htmlspecialchars($user); ?>".</p>
            <?php endif; ?>
        <?php endif; ?>
        </br></br></br>
    </div>
</body>
</html>

