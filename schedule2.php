<?php
/*
========================================================
ESP-SWITCH7
schedule2.php
OWNER DEACTIVATION CONTROL
========================================================

Database table:
weekly_schedule

Additional fields:
owner_deactivated_1
owner_deactivated_2
owner_deactivated_3

RULE:
Owner deactivation has priority over period_active.

owner_deactivated = 1
        ↓
OWNER DEACTIVATED
        ↓
ESP output OFF

owner_deactivated = 0
        ↓
normal period_active is used
========================================================
*/

date_default_timezone_set("Asia/Kolkata");

/* =====================================================
   DATABASE CONNECTION
   ===================================================== */

$host     = getenv("DB_HOST");
$user     = getenv("DB_USER");
$password = getenv("DB_PASSWORD");
$database = getenv("DB_NAME");
$port     = intval(getenv("DB_PORT"));

if (!$host || !$user || !$database || !$port) {
    die("Database environment variables are missing.");
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
    $password,
    $database,
    $port,
    NULL,
    MYSQLI_CLIENT_SSL
)) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");


/* =====================================================
   CONTROLLER ID
   ===================================================== */

$controller_id = isset($_REQUEST['controller_id'])
    ? trim($_REQUEST['controller_id'])
    : 'ESP0001';


/* =====================================================
   HELPER FUNCTIONS
   ===================================================== */

function make_datetime($date, $time)
{
    if (empty($date) || empty($time)) {
        return NULL;
    }

    return $date . " " . $time . ":00";
}


function sql_datetime($conn, $value)
{
    if ($value === NULL || $value === '') {
        return "NULL";
    }

    return "'" . mysqli_real_escape_string($conn, $value) . "'";
}


/* =====================================================
   SAVE SCHEDULE
   ===================================================== */

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['save_schedule'])) {

    $id = intval($_POST['id']);

    $periods = array();

    for ($p = 1; $p <= 3; $p++) {

        $start_date = isset($_POST["start_date_$p"])
            ? trim($_POST["start_date_$p"])
            : "";

        $start_time = isset($_POST["start_time_$p"])
            ? trim($_POST["start_time_$p"])
            : "";

        $end_date = isset($_POST["end_date_$p"])
            ? trim($_POST["end_date_$p"])
            : "";

        $end_time = isset($_POST["end_time_$p"])
            ? trim($_POST["end_time_$p"])
            : "";

        $start_datetime = make_datetime(
            $start_date,
            $start_time
        );

        $end_datetime = make_datetime(
            $end_date,
            $end_time
        );


        /* ---------------------------------------------
           PERIOD ACTIVE
           --------------------------------------------- */

        $period_active = isset($_POST["period_active_$p"])
            ? intval($_POST["period_active_$p"])
            : 0;


        /* ---------------------------------------------
           OWNER DEACTIVATION

           IMPORTANT:
           This value is taken from the owner control.

           1 = Owner has deactivated period
           0 = Owner has activated period
           --------------------------------------------- */

        $owner_deactivated = isset($_POST["owner_deactivated_$p"])
            ? intval($_POST["owner_deactivated_$p"])
            : 0;


        /* ---------------------------------------------
           PINS
           --------------------------------------------- */

        $pins = "";

        if (isset($_POST["pins_$p"])
            && is_array($_POST["pins_$p"])) {

            $pin_array = array();

            foreach ($_POST["pins_$p"] as $pin) {

                $pin = trim($pin);

                if (preg_match('/^D[1-8]$/', $pin)) {
                    $pin_array[] = $pin;
                }
            }

            $pins = implode(",", $pin_array);
        }


        $periods[$p] = array(
            "start" => $start_datetime,
            "end" => $end_datetime,
            "pins" => $pins,
            "active" => $period_active,
            "owner_deactivated" => $owner_deactivated
        );
    }


    /* =================================================
       DATE/TIME VALIDATION
       ================================================= */

    $valid = true;


    for ($p = 1; $p <= 3; $p++) {

        $start = $periods[$p]["start"];
        $end   = $periods[$p]["end"];

        if ($start !== NULL && $end !== NULL) {

            if (strtotime($start) >= strtotime($end)) {

                $error =
                    "Period $p: Start date/time must be before end date/time.";

                $valid = false;
                break;
            }
        }
    }


    /* =================================================
       PERIOD ORDER VALIDATION
       ================================================= */

    if ($valid) {

        if (
            $periods[1]["end"] !== NULL &&
            $periods[2]["start"] !== NULL
        ) {

            if (
                strtotime($periods[1]["end"]) >
                strtotime($periods[2]["start"])
            ) {

                $error =
                    "Period 1 must finish before Period 2 starts.";

                $valid = false;
            }
        }
    }


    if ($valid) {

        if (
            $periods[2]["end"] !== NULL &&
            $periods[3]["start"] !== NULL
        ) {

            if (
                strtotime($periods[2]["end"]) >
                strtotime($periods[3]["start"])
            ) {

                $error =
                    "Period 2 must finish before Period 3 starts.";

                $valid = false;
            }
        }
    }


    /* =================================================
       UPDATE DATABASE
       ================================================= */

    if ($valid) {

        $controller_sql =
            mysqli_real_escape_string(
                $conn,
                $controller_id
            );


        $sql = "
        UPDATE weekly_schedule SET

            start_time_1 = " .
            sql_datetime(
                $conn,
                $periods[1]["start"]
            ) . ",

            end_time_1 = " .
            sql_datetime(
                $conn,
                $periods[1]["end"]
            ) . ",

            pins_output_1 = '" .
            mysqli_real_escape_string(
                $conn,
                $periods[1]["pins"]
            ) . "',

            period_active_1 = " .
            intval($periods[1]["active"]) . ",

            owner_deactivated_1 = " .
            intval($periods[1]["owner_deactivated"]) . ",


            start_time_2 = " .
            sql_datetime(
                $conn,
                $periods[2]["start"]
            ) . ",

            end_time_2 = " .
            sql_datetime(
                $conn,
                $periods[2]["end"]
            ) . ",

            pins_output_2 = '" .
            mysqli_real_escape_string(
                $conn,
                $periods[2]["pins"]
            ) . "',

            period_active_2 = " .
            intval($periods[2]["active"]) . ",

            owner_deactivated_2 = " .
            intval($periods[2]["owner_deactivated"]) . ",


            start_time_3 = " .
            sql_datetime(
                $conn,
                $periods[3]["start"]
            ) . ",

            end_time_3 = " .
            sql_datetime(
                $conn,
                $periods[3]["end"]
            ) . ",

            pins_output_3 = '" .
            mysqli_real_escape_string(
                $conn,
                $periods[3]["pins"]
            ) . "',

            period_active_3 = " .
            intval($periods[3]["active"]) . ",

            owner_deactivated_3 = " .
            intval($periods[3]["owner_deactivated"]) . "

        WHERE id = $id
        AND controller_id = '$controller_sql'
        ";


        if (mysqli_query($conn, $sql)) {

            $message =
                "Schedule saved successfully.";

        } else {

            $error =
                "Database error: " .
                mysqli_error($conn);
        }
    }
}


/* =====================================================
   READ ALL DAYS
   ===================================================== */

$controller_sql =
    mysqli_real_escape_string(
        $conn,
        $controller_id
    );


$sql = "
SELECT
    id,
    controller_id,
    day_week,

    start_time_1,
    end_time_1,
    pins_output_1,
    period_active_1,
    owner_deactivated_1,

    start_time_2,
    end_time_2,
    pins_output_2,
    period_active_2,
    owner_deactivated_2,

    start_time_3,
    end_time_3,
    pins_output_3,
    period_active_3,
    owner_deactivated_3

FROM weekly_schedule

WHERE controller_id = '$controller_sql'

ORDER BY FIELD(
    day_week,
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday'
)
";


$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Schedule query failed: " . mysqli_error($conn));
}


/* =====================================================
   FORMAT DATETIME FOR HTML
   ===================================================== */

function date_value($value)
{
    if (empty($value)) {
        return "";
    }

    return date("Y-m-d", strtotime($value));
}


function time_value($value)
{
    if (empty($value)) {
        return "";
    }

    return date("H:i", strtotime($value));
}


/* =====================================================
   HTML
   ===================================================== */
?>

<!DOCTYPE html>
<html lang="en">

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
    padding: 15px;
}

h1 {
    text-align: center;
}

.controller {
    text-align: center;
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 20px;
}

.message {
    background: #d4edda;
    color: #155724;
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 6px;
}

.error {
    background: #f8d7da;
    color: #721c24;
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 6px;
}

.day-box {
    background: white;
    margin-bottom: 25px;
    padding: 15px;
    border-radius: 10px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

.day-title {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 15px;
}

.period {
    border: 1px solid #ccc;
    padding: 15px;
    margin-bottom: 15px;
    border-radius: 8px;
}

.period-title {
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 10px;
}

label {
    font-weight: bold;
}

input[type="date"],
input[type="time"] {
    padding: 7px;
    margin: 4px;
}

.pin-area {
    margin-top: 12px;
}

.pin {
    display: inline-block;
    margin: 4px;
}

.pin input {
    display: none;
}

.pin span {
    display: inline-block;
    padding: 9px 13px;
    border: 2px solid #888;
    border-radius: 6px;
    background: #eee;
    cursor: pointer;
    font-weight: bold;
}

.pin input:checked + span {
    background: #28a745;
    color: white;
    border-color: #28a745;
}

.active-area {
    margin-top: 12px;
    padding: 10px;
    background: #eef6ff;
    border-radius: 6px;
}

.owner-area {
    margin-top: 12px;
    padding: 12px;
    background: #fff3cd;
    border: 1px solid #ffeeba;
    border-radius: 6px;
}

.owner-area label {
    color: #856404;
}

.save-button {
    background: #007bff;
    color: white;
    border: none;
    padding: 12px 25px;
    font-size: 16px;
    border-radius: 6px;
    cursor: pointer;
}

.save-button:hover {
    background: #0056b3;
}

.status-active {
    color: green;
    font-weight: bold;
}

.status-deactivated {
    color: red;
    font-weight: bold;
}

</style>

</head>

<body>

<h1>ESP-SWITCH7 WEEKLY SCHEDULE</h1>

<div class="controller">
Controller: <?php echo htmlspecialchars($controller_id); ?>
</div>


<?php if ($message != ""): ?>

<div class="message">
<?php echo htmlspecialchars($message); ?>
</div>

<?php endif; ?>


<?php if ($error != ""): ?>

<div class="error">
<?php echo htmlspecialchars($error); ?>
</div>

<?php endif; ?>


<?php

while ($row = mysqli_fetch_assoc($result)):

?>

<div class="day-box">

<div class="day-title">
<?php echo htmlspecialchars($row["day_week"]); ?>
</div>


<form method="POST">

<input type="hidden"
       name="save_schedule"
       value="1">

<input type="hidden"
       name="id"
       value="<?php echo intval($row["id"]); ?>">

<input type="hidden"
       name="controller_id"
       value="<?php echo htmlspecialchars($controller_id); ?>">


<?php for ($p = 1; $p <= 3; $p++): ?>

<?php

$start_field = "start_time_" . $p;
$end_field   = "end_time_" . $p;
$pins_field  = "pins_output_" . $p;
$active_field = "period_active_" . $p;
$owner_field = "owner_deactivated_" . $p;

$start_value = $row[$start_field];
$end_value   = $row[$end_field];

$pins_value =
    trim($row[$pins_field]);

$period_active =
    intval($row[$active_field]);

$owner_deactivated =
    intval($row[$owner_field]);

$selected_pins = array();

if ($pins_value != "") {

    $selected_pins =
        explode(",", $pins_value);
}

?>


<div class="period">

<div class="period-title">
Period <?php echo $p; ?>
</div>


<div>

<label>
Start:
</label>

<input type="date"
       name="start_date_<?php echo $p; ?>"
       value="<?php echo date_value($start_value); ?>">

<input type="time"
       name="start_time_<?php echo $p; ?>"
       value="<?php echo time_value($start_value); ?>">

</div>


<div>

<label>
End:
</label>

<input type="date"
       name="end_date_<?php echo $p; ?>"
       value="<?php echo date_value($end_value); ?>">

<input type="time"
       name="end_time_<?php echo $p; ?>"
       value="<?php echo time_value($end_value); ?>">

</div>


<div class="pin-area">

<strong>Select Outputs:</strong><br><br>

<?php for ($d = 1; $d <= 8; $d++): ?>

<?php

$pin_name = "D" . $d;

$checked =
    in_array(
        $pin_name,
        $selected_pins
    );

?>

<label class="pin">

<input type="checkbox"
       name="pins_<?php echo $p; ?>[]"
       value="<?php echo $pin_name; ?>"
       <?php echo $checked ? "checked" : ""; ?>>

<span>
<?php echo $pin_name; ?>
</span>

</label>

<?php endfor; ?>

</div>


<div class="active-area">

<input type="hidden"
       name="period_active_<?php echo $p; ?>"
       value="0">

<label>

<input type="checkbox"
       name="period_active_<?php echo $p; ?>"
       value="1"
       <?php echo ($period_active == 1) ? "checked" : ""; ?>>

User Period Active

</label>

<br>

<?php if ($period_active == 1): ?>

<span class="status-active">
Period is ACTIVE
</span>

<?php else: ?>

<span class="status-deactivated">
Period is DEACTIVATED
</span>

<?php endif; ?>

</div>


<div class="owner-area">

<input type="hidden"
       name="owner_deactivated_<?php echo $p; ?>"
       value="0">

<label>

<input type="checkbox"
       name="owner_deactivated_<?php echo $p; ?>"
       value="1"
       <?php echo ($owner_deactivated == 1) ? "checked" : ""; ?>>

OWNER DEACTIVATE THIS PERIOD

</label>

<br><br>

<?php if ($owner_deactivated == 1): ?>

<span class="status-deactivated">
OWNER DEACTIVATED
</span>

<?php else: ?>

<span class="status-active">
OWNER ACTIVATED
</span>

<?php endif; ?>

</div>


<?php if ($owner_deactivated == 1): ?>

<p class="status-deactivated">
<strong>
IMPORTANT: Owner has deactivated this period.
The ESP output will remain OFF even if the user
period is Active.
</strong>
</p>

<?php endif; ?>


</div>

<?php endfor; ?>


<button type="submit"
        class="save-button">

Save <?php echo htmlspecialchars($row["day_week"]); ?>

</button>


</form>

</div>

<?php endwhile; ?>


</body>
</html>

<?php

mysqli_close($conn);

?>
