<?php
/*
=========================================================
 ESP-SWITCH7
 display_schedule.php
 Render + TiDB Cloud version
 Timezone: Asia/Kolkata
=========================================================
*/

// -------------------------------------------------------
// TIMEZONE
// -------------------------------------------------------
date_default_timezone_set("Asia/Kolkata");


// -------------------------------------------------------
// DATABASE CREDENTIALS FROM RENDER ENVIRONMENT VARIABLES
// -------------------------------------------------------
$db_host = getenv("DB_HOST");
$db_user = getenv("DB_USER");
$db_pass = getenv("DB_PASSWORD");
$db_name = getenv("DB_NAME");
$db_port = getenv("DB_PORT");


// -------------------------------------------------------
// CHECK ENVIRONMENT VARIABLES
// -------------------------------------------------------
if (
    !$db_host ||
    !$db_user ||
    !$db_pass ||
    !$db_name ||
    !$db_port
) {
    die("Database environment variables are missing.");
}


// -------------------------------------------------------
// CONNECT TO TiDB CLOUD USING SSL
// -------------------------------------------------------
$conn = mysqli_init();

if (!$conn) {
    die("MySQL initialization failed.");
}

mysqli_ssl_set(
    $conn,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL
);

if (!mysqli_real_connect(
    $conn,
    $db_host,
    $db_user,
    $db_pass,
    $db_name,
    (int)$db_port,
    NULL,
    MYSQLI_CLIENT_SSL
)) {
    die("Database connection failed: " . mysqli_connect_error());
}


// -------------------------------------------------------
// CONTROLLER ID
// -------------------------------------------------------
$controller_id = isset($_GET["controller_id"])
    ? trim($_GET["controller_id"])
    : "ESP0001";


// -------------------------------------------------------
// CURRENT INDIA DATE / TIME
// -------------------------------------------------------
$current_datetime = date("Y-m-d H:i:s");
$current_date     = date("Y-m-d");
$current_time     = date("H:i:s");
$current_day      = date("l");


// -------------------------------------------------------
// DAY NAME
// Monday = 1 ... Sunday = 7
// -------------------------------------------------------
$day_number = date("N");


// -------------------------------------------------------
// FIND TODAY'S SCHEDULE
// -------------------------------------------------------
$sql = "
    SELECT *
    FROM weekly_schedule
    WHERE controller_id = ?
      AND day_week = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("SQL prepare failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt,
    "si",
    $controller_id,
    $day_number
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$schedule = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


// -------------------------------------------------------
// FUNCTION TO CONVERT PINS STRING INTO ARRAY
// -------------------------------------------------------
function getPins($pins)
{
    $output = array();

    if ($pins === null || trim($pins) === "") {
        return $output;
    }

    $parts = explode(",", $pins);

    foreach ($parts as $pin) {

        $pin = trim($pin);

        if ($pin !== "") {
            $output[] = $pin;
        }
    }

    return $output;
}


// -------------------------------------------------------
// FUNCTION TO CHECK WHETHER A PERIOD IS ACTIVE NOW
// -------------------------------------------------------
function periodIsRunning(
    $start_date,
    $start_time,
    $end_date,
    $end_time
) {
    if (
        empty($start_date) ||
        empty($start_time) ||
        empty($end_date) ||
        empty($end_time)
    ) {
        return false;
    }

    $now = time();

    $start = strtotime(
        $start_date . " " . $start_time
    );

    $end = strtotime(
        $end_date . " " . $end_time
    );

    if ($start === false || $end === false) {
        return false;
    }

    return ($now >= $start && $now <= $end);
}


// -------------------------------------------------------
// PERIOD INFORMATION
// -------------------------------------------------------
$periods = array();

for ($i = 1; $i <= 3; $i++) {

    $start_field = "start_time_" . $i;
    $end_field   = "end_time_" . $i;
    $pins_field  = "pins_output_" . $i;
    $active_field = "period_active_" . $i;

    $start_value = isset($schedule[$start_field])
        ? $schedule[$start_field]
        : "";

    $end_value = isset($schedule[$end_field])
        ? $schedule[$end_field]
        : "";

    $pins_value = isset($schedule[$pins_field])
        ? $schedule[$pins_field]
        : "";

    $period_active = isset($schedule[$active_field])
        ? (int)$schedule[$active_field]
        : 0;


    // ---------------------------------------------------
    // Default values
    // ---------------------------------------------------
    $status = "DEACTIVATED";
    $running = false;
    $pins = getPins($pins_value);


    // ---------------------------------------------------
    // PERIOD ACTIVE FLAG
    // ---------------------------------------------------
    if ($period_active == 1) {

        /*
         * start_time_X / end_time_X may be DATETIME
         * values such as:
         *
         * 2026-09-30 09:32:00
         * 2026-10-02 18:00:00
         */

        $start_timestamp = strtotime($start_value);
        $end_timestamp   = strtotime($end_value);

        if (
            $start_timestamp !== false &&
            $end_timestamp !== false
        ) {

            $now_timestamp = time();

            if ($now_timestamp < $start_timestamp) {

                $status = "NOT STARTED";

            } elseif ($now_timestamp > $end_timestamp) {

                $status = "EXPIRED";

            } else {

                $status = "ACTIVE";
                $running = true;
            }
        }
    }


    // ---------------------------------------------------
    // IF DEACTIVATED, OUTPUT MUST BE OFF
    // ---------------------------------------------------
    if ($period_active != 1) {

        $status = "DEACTIVATED";
        $running = false;
        $pins = array();
    }


    $periods[$i] = array(
        "start"   => $start_value,
        "end"     => $end_value,
        "pins"    => $pins,
        "active"  => $period_active,
        "status"  => $status,
        "running" => $running
    );
}


// -------------------------------------------------------
// DETERMINE CURRENT OUTPUT PINS
// -------------------------------------------------------
$current_pins = array();

for ($i = 1; $i <= 3; $i++) {

    if ($periods[$i]["running"] === true) {

        foreach ($periods[$i]["pins"] as $pin) {

            if (!in_array($pin, $current_pins)) {
                $current_pins[] = $pin;
            }
        }
    }
}


// -------------------------------------------------------
// SORT PINS
// -------------------------------------------------------
sort($current_pins);


// -------------------------------------------------------
// FUNCTION FOR PIN STATUS
// -------------------------------------------------------
function pinIsOn($pin, $current_pins)
{
    return in_array($pin, $current_pins);
}

?>
<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>ESP-SWITCH7 Schedule</title>

<style>

body {
    font-family: Arial, sans-serif;
    background: #f2f2f2;
    margin: 0;
    padding: 20px;
}

.container {
    max-width: 1000px;
    margin: auto;
    background: white;
    padding: 20px;
    border-radius: 10px;
}

h1 {
    text-align: center;
    margin-bottom: 5px;
}

.header-info {
    text-align: center;
    margin-bottom: 20px;
    font-size: 18px;
}

.controller {
    font-weight: bold;
    font-size: 20px;
}

.day {
    font-weight: bold;
    font-size: 20px;
}

.time {
    font-size: 18px;
    margin-top: 5px;
}

.period {
    border: 1px solid #ccc;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    background: #fafafa;
}

.period-title {
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 10px;
}

.date-time {
    font-size: 16px;
    margin-bottom: 10px;
}

.status {
    display: inline-block;
    padding: 8px 14px;
    border-radius: 5px;
    font-weight: bold;
    margin-bottom: 12px;
}

.status-active {
    background: #198754;
    color: white;
}

.status-notstarted {
    background: #ffc107;
    color: black;
}

.status-expired {
    background: #dc3545;
    color: white;
}

.status-deactivated {
    background: #6c757d;
    color: white;
}

.pins {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.pin {
    width: 55px;
    height: 45px;
    border-radius: 6px;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: bold;
    border: 2px solid #333;

    background: #eeeeee;
    color: #333;
}

.pin-on {
    background: #198754;
    color: white;
    border-color: #198754;
}

.pin-off {
    background: #eeeeee;
    color: #333;
}

.current-output {
    margin-top: 25px;
    padding: 15px;
    border-radius: 8px;
    background: #f8f9fa;
    border: 1px solid #ccc;
}

.current-output h2 {
    margin-top: 0;
}

.no-schedule {
    text-align: center;
    padding: 30px;
    font-size: 20px;
    color: #666;
}

.refresh {
    text-align: center;
    margin-top: 20px;
}

.refresh button {
    padding: 10px 20px;
    font-size: 16px;
    cursor: pointer;
}

</style>

</head>

<body>

<div class="container">

<h1>ESP-SWITCH7</h1>

<div class="header-info">

<div class="controller">
Controller: <?php echo htmlspecialchars($controller_id); ?>
</div>

<div class="day">
Today: <?php echo htmlspecialchars($current_day); ?>
</div>

<div class="time">
India Time:
<strong>
<?php echo htmlspecialchars($current_datetime); ?>
</strong>
</div>

</div>


<?php if (!$schedule): ?>

<div class="no-schedule">

No schedule found for
<strong><?php echo htmlspecialchars($current_day); ?></strong>.

</div>

<?php else: ?>


<!-- ===============================================
     PERIOD 1
================================================ -->

<div class="period">

<div class="period-title">
Period 1
</div>

<div class="date-time">

<strong>Start:</strong>
<?php echo htmlspecialchars($periods[1]["start"]); ?>

<br>

<strong>End:</strong>
<?php echo htmlspecialchars($periods[1]["end"]); ?>

</div>


<?php

$status_class = "status-deactivated";

if ($periods[1]["status"] == "ACTIVE") {
    $status_class = "status-active";
}
elseif ($periods[1]["status"] == "NOT STARTED") {
    $status_class = "status-notstarted";
}
elseif ($periods[1]["status"] == "EXPIRED") {
    $status_class = "status-expired";
}

?>

<div class="status <?php echo $status_class; ?>">

<?php echo htmlspecialchars($periods[1]["status"]); ?>

</div>


<div>
<strong>Outputs:</strong>
</div>

<div class="pins">

<?php for ($p = 1; $p <= 8; $p++): ?>

<?php
$pin_name = "D" . $p;
$is_on = pinIsOn(
    $pin_name,
    $periods[1]["running"]
        ? $periods[1]["pins"]
        : array()
);
?>

<div class="pin <?php echo $is_on ? 'pin-on' : 'pin-off'; ?>">

<?php echo $pin_name; ?>

</div>

<?php endfor; ?>

</div>

</div>



<!-- ===============================================
     PERIOD 2
================================================ -->

<div class="period">

<div class="period-title">
Period 2
</div>

<div class="date-time">

<strong>Start:</strong>
<?php echo htmlspecialchars($periods[2]["start"]); ?>

<br>

<strong>End:</strong>
<?php echo htmlspecialchars($periods[2]["end"]); ?>

</div>


<?php

$status_class = "status-deactivated";

if ($periods[2]["status"] == "ACTIVE") {
    $status_class = "status-active";
}
elseif ($periods[2]["status"] == "NOT STARTED") {
    $status_class = "status-notstarted";
}
elseif ($periods[2]["status"] == "EXPIRED") {
    $status_class = "status-expired";
}

?>

<div class="status <?php echo $status_class; ?>">

<?php echo htmlspecialchars($periods[2]["status"]); ?>

</div>


<div>
<strong>Outputs:</strong>
</div>

<div class="pins">

<?php for ($p = 1; $p <= 8; $p++): ?>

<?php
$pin_name = "D" . $p;

$is_on = pinIsOn(
    $pin_name,
    $periods[2]["running"]
        ? $periods[2]["pins"]
        : array()
);
?>

<div class="pin <?php echo $is_on ? 'pin-on' : 'pin-off'; ?>">

<?php echo $pin_name; ?>

</div>

<?php endfor; ?>

</div>

</div>



<!-- ===============================================
     PERIOD 3
================================================ -->

<div class="period">

<div class="period-title">
Period 3
</div>

<div class="date-time">

<strong>Start:</strong>
<?php echo htmlspecialchars($periods[3]["start"]); ?>

<br>

<strong>End:</strong>
<?php echo htmlspecialchars($periods[3]["end"]); ?>

</div>


<?php

$status_class = "status-deactivated";

if ($periods[3]["status"] == "ACTIVE") {
    $status_class = "status-active";
}
elseif ($periods[3]["status"] == "NOT STARTED") {
    $status_class = "status-notstarted";
}
elseif ($periods[3]["status"] == "EXPIRED") {
    $status_class = "status-expired";
}

?>

<div class="status <?php echo $status_class; ?>">

<?php echo htmlspecialchars($periods[3]["status"]); ?>

</div>


<div>
<strong>Outputs:</strong>
</div>

<div class="pins">

<?php for ($p = 1; $p <= 8; $p++): ?>

<?php
$pin_name = "D" . $p;

$is_on = pinIsOn(
    $pin_name,
    $periods[3]["running"]
        ? $periods[3]["pins"]
        : array()
);
?>

<div class="pin <?php echo $is_on ? 'pin-on' : 'pin-off'; ?>">

<?php echo $pin_name; ?>

</div>

<?php endfor; ?>

</div>

</div>



<!-- ===============================================
     CURRENT OUTPUT
================================================ -->

<div class="current-output">

<h2>Current Output</h2>

<div class="pins">

<?php for ($p = 1; $p <= 8; $p++): ?>

<?php
$pin_name = "D" . $p;
$is_on = pinIsOn($pin_name, $current_pins);
?>

<div class="pin <?php echo $is_on ? 'pin-on' : 'pin-off'; ?>">

<?php echo $pin_name; ?>

</div>

<?php endfor; ?>

</div>

</div>

<?php endif; ?>


<div class="refresh">

<button onclick="location.reload();">
Refresh
</button>

</div>

</div>

</body>

</html>

<?php

// -------------------------------------------------------
// CLOSE DATABASE
// -------------------------------------------------------
mysqli_close($conn);

?>
