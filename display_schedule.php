<?php
/*
===========================================================
 ESP-SWITCH9 REMOTE
 display_schedule.php

 Render + TiDB Cloud
 Database : esp_switch7

 Purpose:
 - Display weekly schedule
 - Show current India date/time
 - Show today's three periods
 - Show D1-D8 outputs
 - Show ACTIVE / DEACTIVATED
 - Deactivated period = all outputs OFF
===========================================================
*/

date_default_timezone_set("Asia/Kolkata");


/* =========================================================
   DATABASE CONNECTION
   ========================================================= */

$host = getenv("DB_HOST");
$user = getenv("DB_USER");
$pass = getenv("DB_PASS");
$db   = getenv("DB_NAME");
$port = getenv("DB_PORT");

if (!$port) {
    $port = 4000;
}

$conn = mysqli_init();

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
    $host,
    $user,
    $pass,
    $db,
    $port,
    NULL,
    MYSQLI_CLIENT_SSL
)) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");


/* =========================================================
   CONTROLLER ID
   ========================================================= */

$controller_id = "";

if (isset($_GET["controller_id"])) {
    $controller_id = trim($_GET["controller_id"]);
}

if ($controller_id == "") {
    die("Controller ID is required.");
}


/* =========================================================
   HELPER
   ========================================================= */

function h($value)
{
    return htmlspecialchars($value ?? "", ENT_QUOTES, "UTF-8");
}


/* =========================================================
   FORMAT DATE/TIME
   ========================================================= */

function formatDateTime($value)
{
    if ($value == "" || $value == null) {
        return "";
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date("d-m-Y H:i", $timestamp);
}


/* =========================================================
   GET CURRENT INDIA DATE/TIME
   ========================================================= */

$current_datetime = date("d-m-Y H:i:s");

$current_day = date("l");


/* =========================================================
   DAY SELECTION
   ========================================================= */

$days = array(
    "Monday",
    "Tuesday",
    "Wednesday",
    "Thursday",
    "Friday",
    "Saturday",
    "Sunday"
);

$selected_day = $_GET["day"] ?? $current_day;

if (!in_array($selected_day, $days)) {
    $selected_day = $current_day;
}


/* =========================================================
   DEFAULT DATA
   ========================================================= */

$data = array(

    "start_time_1" => "",
    "end_time_1" => "",
    "pins_output_1" => "",
    "period_active_1" => 0,

    "start_time_2" => "",
    "end_time_2" => "",
    "pins_output_2" => "",
    "period_active_2" => 0,

    "start_time_3" => "",
    "end_time_3" => "",
    "pins_output_3" => "",
    "period_active_3" => 0
);


/* =========================================================
   READ SCHEDULE
   ========================================================= */

$sql = "
    SELECT

        start_time_1,
        end_time_1,
        pins_output_1,
        period_active_1,

        start_time_2,
        end_time_2,
        pins_output_2,
        period_active_2,

        start_time_3,
        end_time_3,
        pins_output_3,
        period_active_3

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
    "ss",
    $controller_id,
    $selected_day
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {

    foreach ($data as $key => $value) {

        if (isset($row[$key])) {
            $data[$key] = $row[$key];
        }
    }
}


/* =========================================================
   FUNCTION TO DISPLAY PINS
   ========================================================= */

function displayPins($pin_string, $active)
{
    $all_pins = array(
        "D1",
        "D2",
        "D3",
        "D4",
        "D5",
        "D6",
        "D7",
        "D8"
    );

    $selected = array();

    if ($pin_string != "") {
        $selected = explode(",", $pin_string);
    }

    /*
    ---------------------------------------------------------
    DEACTIVATED
    ---------------------------------------------------------
    */

    if ($active != 1) {

        foreach ($all_pins as $pin) {

            echo '<div class="pin off">';
            echo h($pin);
            echo '<span>OFF</span>';
            echo '</div>';
        }

        return;
    }


    /*
    ---------------------------------------------------------
    ACTIVE
    ---------------------------------------------------------
    */

    foreach ($all_pins as $pin) {

        if (in_array($pin, $selected)) {

            echo '<div class="pin on">';
            echo h($pin);
            echo '<span>ON</span>';
            echo '</div>';

        } else {

            echo '<div class="pin off">';
            echo h($pin);
            echo '<span>OFF</span>';
            echo '</div>';
        }
    }
}


/* =========================================================
   CHECK WHETHER SCHEDULE EXISTS
   ========================================================= */

$schedule_exists = false;

foreach ($data as $key => $value) {

    if (
        strpos($key, "start_time") !== false ||
        strpos($key, "end_time") !== false ||
        strpos($key, "pins_output") !== false
    ) {

        if ($value != "") {
            $schedule_exists = true;
            break;
        }
    }
}

?>
<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
ESP-SWITCH9 Schedule Display
</title>


<style>

/* =========================================================
   PAGE
========================================================= */

body {

    font-family: Arial, sans-serif;

    background: #f2f2f2;

    margin: 0;

    padding: 15px;
}


.container {

    max-width: 1000px;

    margin: auto;

    background: white;

    padding: 20px;

    border-radius: 10px;

    box-shadow: 0 0 10px #ccc;
}


/* =========================================================
   HEADINGS
========================================================= */

h2 {

    text-align: center;

    margin-top: 0;

    margin-bottom: 8px;
}


.controller {

    text-align: center;

    font-size: 20px;

    font-weight: bold;

    margin-bottom: 8px;
}


.datetime {

    text-align: center;

    font-size: 16px;

    margin-bottom: 18px;
}


/* =========================================================
   DAYS
========================================================= */

.days {

    display: flex;

    flex-wrap: wrap;

    justify-content: center;

    gap: 6px;

    margin-bottom: 20px;
}


.days a {

    text-decoration: none;

    padding: 10px 13px;

    background: #ddd;

    color: black;

    border-radius: 5px;

    font-weight: bold;
}


.days a.selected {

    background: #222;

    color: white;
}


/* =========================================================
   DAY TITLE
========================================================= */

.day-title {

    text-align: center;

    font-size: 22px;

    font-weight: bold;

    margin-bottom: 20px;
}


/* =========================================================
   PERIOD
========================================================= */

.period {

    border: 2px solid #ccc;

    border-radius: 8px;

    padding: 15px;

    margin-bottom: 18px;
}


.period-title {

    display: flex;

    justify-content: space-between;

    align-items: center;

    flex-wrap: wrap;

    margin-bottom: 12px;
}


.period-title h3 {

    margin: 0;

    font-size: 20px;
}


/* =========================================================
   STATUS
========================================================= */

.status {

    padding: 7px 12px;

    border-radius: 5px;

    font-weight: bold;
}


.status.active {

    background: #d8f5d8;

    color: #087508;
}


.status.deactivated {

    background: #ffdcdc;

    color: #a00000;
}


/* =========================================================
   DATE/TIME
========================================================= */

.datetime-row {

    display: flex;

    flex-wrap: wrap;

    gap: 10px;

    margin-bottom: 15px;
}


.time-box {

    flex: 1;

    min-width: 230px;

    background: #f5f5f5;

    padding: 10px;

    border-radius: 5px;
}


.time-label {

    font-size: 13px;

    font-weight: bold;

    display: block;

    margin-bottom: 4px;
}


.time-value {

    font-size: 16px;

    font-weight: bold;
}


/* =========================================================
   OUTPUTS
========================================================= */

.output-title {

    font-weight: bold;

    margin-bottom: 8px;
}


.pin-grid {

    display: flex;

    flex-wrap: wrap;

    gap: 8px;
}


.pin {

    min-width: 55px;

    padding: 9px 8px;

    border-radius: 5px;

    border: 2px solid #aaa;

    text-align: center;

    font-weight: bold;
}


.pin span {

    display: block;

    font-size: 11px;

    margin-top: 3px;
}


.pin.on {

    background: #d8f5d8;

    border-color: #188018;

    color: #087508;
}


.pin.off {

    background: #eeeeee;

    border-color: #999;

    color: #555;
}


/* =========================================================
   NO SCHEDULE
========================================================= */

.no-schedule {

    text-align: center;

    padding: 25px;

    background: #fff7d6;

    border-radius: 6px;

    font-weight: bold;
}


/* =========================================================
   NOTE
========================================================= */

.note {

    margin-top: 20px;

    padding: 12px;

    background: #fff7d6;

    border-radius: 5px;

    font-size: 14px;

    line-height: 1.5;
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 600px) {

    body {
        padding: 8px;
    }

    .container {
        padding: 12px;
    }

    .period {
        padding: 12px;
    }

    .period-title h3 {
        margin-bottom: 8px;
    }

}

</style>

</head>


<body>


<div class="container">


<h2>
ESP-SWITCH9 WEEKLY SCHEDULE
</h2>


<div class="controller">

Controller:
<?php echo h($controller_id); ?>

</div>


<div class="datetime">

India Time:
<b id="clock">
<?php echo h($current_datetime); ?>
</b>

</div>


<!-- =====================================================
     DAY BUTTONS
====================================================== -->

<div class="days">

<?php foreach ($days as $day) { ?>

<a
href="?controller_id=<?php echo urlencode($controller_id); ?>&day=<?php echo urlencode($day); ?>"
class="<?php
echo ($selected_day == $day) ? "selected" : "";
?>"
>

<?php echo h($day); ?>

</a>

<?php } ?>

</div>


<div class="day-title">

<?php echo h($selected_day); ?>

</div>


<?php if (!$schedule_exists) { ?>


<div class="no-schedule">

No schedule has been saved for
<?php echo h($selected_day); ?>.

</div>


<?php } else { ?>


<!-- =====================================================
     PERIOD 1
====================================================== -->

<div class="period">


<div class="period-title">

<h3>
Period 1
</h3>


<?php if ($data["period_active_1"] == 1) { ?>

<div class="status active">
ACTIVE
</div>

<?php } else { ?>

<div class="status deactivated">
DEACTIVATED
</div>

<?php } ?>

</div>


<div class="datetime-row">


<div class="time-box">

<span class="time-label">
START
</span>

<div class="time-value">

<?php

if ($data["start_time_1"] != "") {

    echo h(
        formatDateTime($data["start_time_1"])
    );

} else {

    echo "--";

}

?>

</div>

</div>


<div class="time-box">

<span class="time-label">
END
</span>

<div class="time-value">

<?php

if ($data["end_time_1"] != "") {

    echo h(
        formatDateTime($data["end_time_1"])
    );

} else {

    echo "--";

}

?>

</div>

</div>


</div>


<div class="output-title">
D1-D8 OUTPUTS
</div>


<div class="pin-grid">

<?php

displayPins(
    $data["pins_output_1"],
    $data["period_active_1"]
);

?>

</div>


</div>


<!-- =====================================================
     PERIOD 2
====================================================== -->

<div class="period">


<div class="period-title">

<h3>
Period 2
</h3>


<?php if ($data["period_active_2"] == 1) { ?>

<div class="status active">
ACTIVE
</div>

<?php } else { ?>

<div class="status deactivated">
DEACTIVATED
</div>

<?php } ?>

</div>


<div class="datetime-row">


<div class="time-box">

<span class="time-label">
START
</span>

<div class="time-value">

<?php

if ($data["start_time_2"] != "") {

    echo h(
        formatDateTime($data["start_time_2"])
    );

} else {

    echo "--";

}

?>

</div>

</div>


<div class="time-box">

<span class="time-label">
END
</span>

<div class="time-value">

<?php

if ($data["end_time_2"] != "") {

    echo h(
        formatDateTime($data["end_time_2"])
    );

} else {

    echo "--";

}

?>

</div>

</div>


</div>


<div class="output-title">
D1-D8 OUTPUTS
</div>


<div class="pin-grid">

<?php

displayPins(
    $data["pins_output_2"],
    $data["period_active_2"]
);

?>

</div>


</div>


<!-- =====================================================
     PERIOD 3
====================================================== -->

<div class="period">


<div class="period-title">

<h3>
Period 3
</h3>


<?php if ($data["period_active_3"] == 1) { ?>

<div class="status active">
ACTIVE
</div>

<?php } else { ?>

<div class="status deactivated">
DEACTIVATED
</div>

<?php } ?>

</div>


<div class="datetime-row">


<div class="time-box">

<span class="time-label">
START
</span>

<div class="time-value">

<?php

if ($data["start_time_3"] != "") {

    echo h(
        formatDateTime($data["start_time_3"])
    );

} else {

    echo "--";

}

?>

</div>

</div>


<div class="time-box">

<span class="time-label">
END
</span>

<div class="time-value">

<?php

if ($data["end_time_3"] != "") {

    echo h(
        formatDateTime($data["end_time_3"])
    );

} else {

    echo "--";

}

?>

</div>

</div>


</div>


<div class="output-title">
D1-D8 OUTPUTS
</div>


<div class="pin-grid">

<?php

displayPins(
    $data["pins_output_3"],
    $data["period_active_3"]
);

?>

</div>


</div>


<?php } ?>


<!-- =====================================================
     INFORMATION
====================================================== -->

<div class="note">

<b>Schedule information:</b><br><br>

• Green <b>ON</b> means that the pin is selected for
the active period.<br>

• Grey <b>OFF</b> means that the pin output is OFF.<br>

• A <b>DEACTIVATED</b> period always gives all D1-D8
outputs OFF to the ESP.<br>

• Deactivating a period does <b>not</b> delete its
saved date, time or pin selections.<br>

• The displayed time is India Standard Time (IST).

</div>


</div>


<!-- =====================================================
     LIVE CLOCK
====================================================== -->

<script>

function updateClock()
{
    const now = new Date();

    const options = {
        timeZone: "Asia/Kolkata",
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
        hour12: false
    };

    let text =
        new Intl.DateTimeFormat(
            "en-GB",
            options
        ).format(now);

    document.getElementById("clock").textContent =
        text;
}

updateClock();

setInterval(updateClock, 1000);

</script>


</body>

</html>

