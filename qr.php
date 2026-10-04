<?php

// ----------------------------------------
// qr.php
// ESP-SWITCH7
// ----------------------------------------

$base_url = "https://esp-switch9-remote.onrender.com";

$display_url = $base_url . "/display_schedule.php";

$qr_url =
    "https://api.qrserver.com/v1/create-qr-code/"
    . "?size=300x300"
    . "&data="
    . urlencode($display_url);

?>

<!DOCTYPE html>

<html>

<head>

    <meta charset="UTF-8">

    <title>ESP-SWITCH7 QR Code</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            text-align: center;
            background: #f2f2f2;
            margin-top: 40px;
        }

        .box {
            width: 420px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #aaa;
        }

        h1 {
            color: #333;
        }

        img {
            width: 300px;
            height: 300px;
            margin: 20px;
        }

        .url {
            background: #eeeeee;
            padding: 12px;
            word-break: break-all;
            border-radius: 5px;
        }

        a {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

    </style>

</head>

<body>

<div class="box">

    <h1>ESP-SWITCH7</h1>

    <h2>Schedule QR Code</h2>

    <img
        src="<?php echo htmlspecialchars($qr_url); ?>"
        alt="ESP-SWITCH7 QR Code"
    >

    <p>
        Scan this QR code to open the schedule display.
    </p>

    <div class="url">

        <?php echo htmlspecialchars($display_url); ?>

    </div>

    <a href="<?php echo htmlspecialchars($display_url); ?>">
        Open Schedule
    </a>

</div>

</body>

</html>
