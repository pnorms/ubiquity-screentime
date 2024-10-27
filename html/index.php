<?php
// Read config.json
$config = json_decode(file_get_contents('config.json'), true);
$users = $config['users'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Page</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="bg-dark text-white">
    <div class="container text-center mt-5">
        <div class="row">
            <?php foreach ($users as $user): ?>
                <div class="col">
                    <a href="list_devices.php?user=<?php echo htmlspecialchars($user); ?>" class="square">
                        <?php echo htmlspecialchars($user); ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

