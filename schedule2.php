
<?php
/*
===========================================================
 ESP-SWITCH9 REMOTE
 schedule2.php

 Render + TiDB Cloud
 Database : esp_switch7

 Purpose:
 - Create / edit weekly schedule
 - Monday to Sunday
 - 3 periods per day
 - Date + time
 - D1-D8 output selection
 - Activate / Deactivate each period
 - Period order validation
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

if (isset($_POST["controller_id"])) {
    $controller_id = trim($_POST["controller_id"]);
}

if ($controller_id == "") {
    die("Controller ID is required.");
}


/* =========================================================
   DAYS
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


/* =========================================================
   HELPER FUNCTION
   ========================================================= */

function h($value)
{
    return htmlspecialchars($value ?? "", ENT_QUOTES, "UTF-8");
}


/* =========================================================
   SAVE SCHEDULE
   ========================================================= */

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" &&
    isset($_POST["save_schedule"])) {

    $day_week = $_POST["day_week"] ?? "";

    if (!in_array($day_week, $days)) {
        $error = "Invalid day selected.";
    } else {

        /*
        -------------------------------------------------------
        PERIOD 1
        -------------------------------------------------------
        */

        $start_time_1 = trim($_POST["start_time_1"] ?? "");
        $end_time_1   = trim($_POST["end_time_1"] ?? "");

        $pins_output_1 = "";

        if (isset($_POST["pins_output_1"])) {
            $pins_output_1 = implode(",", $_POST["pins_output_1"]);
        }

        $period_active_1 =
            isset($_POST["period_active_1"]) ? 1 : 0;


        /*
        -------------------------------------------------------
        PERIOD 2
        -------------------------------------------------------
        */

        $start_time_2 = trim($_POST["start_time_2"] ?? "");
        $end_time_2   = trim($_POST["end_time_2"] ?? "");

        $pins_output_2 = "";

        if (isset($_POST["pins_output_2"])) {
            $pins_output_2 = implode(",", $_POST["pins_output_2"]);
        }

        $period_active_2 =
            isset($_POST["period_active_2"]) ? 1 : 0;


        /*
        -------------------------------------------------------
        PERIOD 3
        -------------------------------------------------------
        */

        $start_time_3 = trim($_POST["start_time_3"] ?? "");
        $end_time_3   = trim($_POST["end_time_3"] ?? "");

        $pins_output_3 = "";

        if (isset($_POST["pins_output_3"])) {
            $pins_output_3 = implode(",", $_POST["pins_output_3"]);
        }

        $period_active_3 =
            isset($_POST["period_active_3"]) ? 1 : 0;


        /*
        -------------------------------------------------------
        VALIDATE DATE/TIME
        -------------------------------------------------------
        */

        $valid = true;

        /*
        Period 1
        */

        if ($start_time_1 != "" && $end_time_1 != "") {

            if (strtotime($start_time_1) === false ||
                strtotime($end_time_1) === false) {

                $error = "Invalid Period 1 date/time.";
                $valid = false;
            }

            if ($valid &&
                strtotime($start_time_1) >= strtotime($end_time_1)) {

                $error =
                    "Period 1: Start date/time must be before End date/time.";

                $valid = false;
            }
        }


        /*
        Period 2
        */

        if ($valid &&
            $start_time_2 != "" &&
            $end_time_2 != "") {

            if (strtotime($start_time_2) === false ||
                strtotime($end_time_2) === false) {

                $error = "Invalid Period 2 date/time.";
                $valid = false;
            }

            if ($valid &&
                strtotime($start_time_2) >= strtotime($end_time_2)) {

                $error =
                    "Period 2: Start date/time must be before End date/time.";

                $valid = false;
            }
        }


        /*
        Period 3
        */

        if ($valid &&
            $start_time_3 != "" &&
            $end_time_3 != "") {

            if (strtotime($start_time_3) === false ||
                strtotime($end_time_3) === false) {

                $error = "Invalid Period 3 date/time.";
                $valid = false;
            }

            if ($valid &&
                strtotime($start_time_3) >= strtotime($end_time_3)) {

                $error =
                    "Period 3: Start date/time must be before End date/time.";

                $valid = false;
            }
        }


        /*
        -------------------------------------------------------
        PERIOD ORDER
        -------------------------------------------------------
        */

        if ($valid &&
            $start_time_1 != "" &&
            $start_time_2 != "") {

            if (strtotime($start_time_2) <=
                strtotime($start_time_1)) {

                $error =
                    "Period 2 must start after Period 1.";

                $valid = false;
            }
        }


        if ($valid &&
            $start_time_2 != "" &&
            $start_time_3 != "") {

            if (strtotime($start_time_3) <=
                strtotime($start_time_2)) {

                $error =
                    "Period 3 must start after Period 2.";

                $valid = false;
            }
        }


        /*
        -------------------------------------------------------
        SAVE TO DATABASE
        -------------------------------------------------------
        */

        if ($valid) {

            /*
            Check whether this controller/day already exists
            */

            $check_sql = "
                SELECT id
                FROM weekly_schedule
                WHERE controller_id = ?
                AND day_week = ?
                LIMIT 1
            ";

            $check_stmt = mysqli_prepare($conn, $check_sql);

            mysqli_stmt_bind_param(
                $check_stmt,
                "ss",
                $controller_id,
                $day_week
            );

            mysqli_stmt_execute($check_stmt);

            $check_result = mysqli_stmt_get_result($check_stmt);

            if (mysqli_num_rows($check_result) > 0) {

                /*
                ------------------------------------------------
                UPDATE
                ------------------------------------------------
                */

                $row = mysqli_fetch_assoc($check_result);

                $id = $row["id"];

                $update_sql = "
                    UPDATE weekly_schedule
                    SET
                        start_time_1 = ?,
                        end_time_1 = ?,
                        pins_output_1 = ?,

                        start_time_2 = ?,
                        end_time_2 = ?,
                        pins_output_2 = ?,

                        start_time_3 = ?,
                        end_time_3 = ?,
                        pins_output_3 = ?,

                        period_active_1 = ?,
                        period_active_2 = ?,
                        period_active_3 = ?

                    WHERE id = ?
                    AND controller_id = ?
                    AND day_week = ?
                ";

                $stmt = mysqli_prepare($conn, $update_sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "sssssssssiiisss",
                    $start_time_1,
                    $end_time_1,
                    $pins_output_1,

                    $start_time_2,
                    $end_time_2,
                    $pins_output_2,

                    $start_time_3,
                    $end_time_3,
                    $pins_output_3,

                    $period_active_1,
                    $period_active_2,
                    $period_active_3,

                    $id,
                    $controller_id,
                    $day_week
                );

                if (mysqli_stmt_execute($stmt)) {

                    $message =
                        $day_week .
                        " schedule updated successfully.";

                } else {

                    $error =
                        "Update failed: " .
                        mysqli_error($conn);
                }

            } else {

                /*
                ------------------------------------------------
                INSERT
                ------------------------------------------------
                */

                $insert_sql = "
                    INSERT INTO weekly_schedule
                    (
                        controller_id,
                        day_week,

                        start_time_1,
                        end_time_1,
                        pins_output_1,

                        start_time_2,
                        end_time_2,
                        pins_output_2,

                        start_time_3,
                        end_time_3,
                        pins_output_3,

                        period_active_1,
                        period_active_2,
                        period_active_3
                    )
                    VALUES
                    (
                        ?,
                        ?,

                        ?,
                        ?,
                        ?,

                        ?,
                        ?,
                        ?,

                        ?,
                        ?,
                        ?,

                        ?,
                        ?,
                        ?
                    )
                ";

                $stmt = mysqli_prepare($conn, $insert_sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssssssssssiii",
                    $controller_id,
                    $day_week,

                    $start_time_1,
                    $end_time_1,
                    $pins_output_1,

                    $start_time_2,
                    $end_time_2,
                    $pins_output_2,

                    $start_time_3,
                    $end_time_3,
                    $pins_output_3,

                    $period_active_1,
                    $period_active_2,
                    $period_active_3
                );

                if (mysqli_stmt_execute($stmt)) {

                    $message =
                        $day_week .
                        " schedule saved successfully.";

                } else {

                    $error =
                        "Insert failed: " .
                        mysqli_error($conn);
                }
            }
        }
    }
}


/* =========================================================
   LOAD SELECTED DAY
   ========================================================= */

$selected_day = $_GET["day"] ?? "Monday";

if (!in_array($selected_day, $days)) {
    $selected_day = "Monday";
}


/* =========================================================
   GET EXISTING DATA
   ========================================================= */

$data = array(
    "start_time_1" => "",
    "end_time_1" => "",
    "pins_output_1" => "",

    "start_time_2" => "",
    "end_time_2" => "",
    "pins_output_2" => "",

    "start_time_3" => "",
    "end_time_3" => "",
    "pins_output_3" => "",

    "period_active_1" => 0,
    "period_active_2" => 0,
    "period_active_3" => 0
);


$sql = "
    SELECT
        start_time_1,
        end_time_1,
        pins_output_1,

        start_time_2,
        end_time_2,
        pins_output_2,

        start_time_3,
        end_time_3,
        pins_output_3,

        period_active_1,
        period_active_2,
        period_active_3

    FROM weekly_schedule

    WHERE controller_id = ?
    AND day_week = ?

    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

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
   PIN ARRAYS
   ========================================================= */

$pins1 = array();

$pins2 = array();

$pins3 = array();

if ($data["pins_output_1"] != "") {
    $pins1 = explode(",", $data["pins_output_1"]);
}

if ($data["pins_output_2"] != "") {
    $pins2 = explode(",", $data["pins_output_2"]);
}

if ($data["pins_output_3"] != "") {
    $pins3 = explode(",", $data["pins_output_3"]);
}

?>
<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
ESP-SWITCH9 Schedule
</title>

<style>

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

h2 {
    text-align: center;
    margin-top: 0;
}

.controller {
    text-align: center;
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 15px;
}

.days {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 6px;
    margin-bottom: 20px;
}

.days a {
    text-decoration: none;
    padding: 10px 14px;
    background: #ddd;
    color: black;
    border-radius: 5px;
    font-weight: bold;
}

.days a.selected {
    background: #222;
    color: white;
}

.message {
    background: #d9f7d9;
    color: #146014;
    padding: 12px;
    border-radius: 5px;
    margin-bottom: 15px;
    text-align: center;
    font-weight: bold;
}

.error {
    background: #ffdede;
    color: #a00000;
    padding: 12px;
    border-radius: 5px;
    margin-bottom: 15px;
    text-align: center;
    font-weight: bold;
}

.period {
    border: 2px solid #ccc;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.period h3 {
    margin-top: 0;
}

.date-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.date-box {
    flex: 1;
    min-width: 250px;
}

.date-box label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
}

input[type="datetime-local"] {
    width: 100%;
    box-sizing: border-box;
    padding: 9px;
    font-size: 15px;
}

.pins {
    margin-top: 15px;
}

.pin-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.pin {
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #777;
    border-radius: 5px;
    padding: 8px 12px;
    background: white;
    cursor: pointer;
}

.pin input {
    margin-right: 5px;
}

.active-box {
    margin-top: 15px;
    padding: 10px;
    background: #f0f0f0;
    border-radius: 5px;
}

.active-box label {
    font-weight: bold;
}

button {
    width: 100%;
    padding: 14px;
    font-size: 18px;
    font-weight: bold;
    background: #222;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

button:hover {
    background: #444;
}

.note {
    margin-top: 20px;
    padding: 12px;
    background: #fff7d6;
    border-radius: 5px;
    font-size: 14px;
}

</style>

</head>

<body>

<div class="container">

<h2>
ESP-SWITCH9 WEEKLY SCHEDULE
</h2>

<div class="controller">
Controller: <?php echo h($controller_id); ?>
</div>


<!-- =====================================================
     DAY SELECTION
====================================================== -->

<div class="days">

<?php foreach ($days as $day) { ?>

<a
href="?controller_id=<?php echo urlencode($controller_id); ?>&day=<?php echo urlencode($day); ?>"
class="<?php echo ($selected_day == $day) ? 'selected' : ''; ?>"
>
<?php echo h($day); ?>
</a>

<?php } ?>

</div>


<?php if ($message != "") { ?>

<div class="message">
<?php echo h($message); ?>
</div>

<?php } ?>


<?php if ($error != "") { ?>

<div class="error">
<?php echo h($error); ?>
</div>

<?php } ?>


<form method="POST">

<input
type="hidden"
name="controller_id"
value="<?php echo h($controller_id); ?>"
>

<input
type="hidden"
name="day_week"
value="<?php echo h($selected_day); ?>"
>


<!-- =====================================================
     PERIOD 1
====================================================== -->

<div class="period">

<h3>
Period 1
</h3>

<div class="date-row">

<div class="date-box">

<label>
Start Date & Time
</label>

<input
type="datetime-local"
name="start_time_1"
value="<?php echo h($data["start_time_1"]); ?>"
>

</div>

<div class="date-box">

<label>
End Date & Time
</label>

<input
type="datetime-local"
name="end_time_1"
value="<?php echo h($data["end_time_1"]); ?>"
>

</div>

</div>


<div class="pins">

<strong>
Select Outputs:
</strong>

<div class="pin-grid">

<?php for ($i = 1; $i <= 8; $i++) { ?>

<label class="pin">

<input
type="checkbox"
name="pins_output_1[]"
value="D<?php echo $i; ?>"
<?php
if (in_array("D".$i, $pins1)) {
    echo "checked";
}
?>
>

D<?php echo $i; ?>

</label>

<?php } ?>

</div>

</div>


<div class="active-box">

<label>

<input
type="checkbox"
name="period_active_1"
value="1"
<?php
if ($data["period_active_1"] == 1) {
    echo "checked";
}
?>
>

Period 1 Active

</label>

</div>

</div>


<!-- =====================================================
     PERIOD 2
====================================================== -->

<div class="period">

<h3>
Period 2
</h3>

<div class="date-row">

<div class="date-box">

<label>
Start Date & Time
</label>

<input
type="datetime-local"
name="start_time_2"
value="<?php echo h($data["start_time_2"]); ?>"
>

</div>

<div class="date-box">

<label>
End Date & Time
</label>

<input
type="datetime-local"
name="end_time_2"
value="<?php echo h($data["end_time_2"]); ?>"
>

</div>

</div>


<div class="pins">

<strong>
Select Outputs:
</strong>

<div class="pin-grid">

<?php for ($i = 1; $i <= 8; $i++) { ?>

<label class="pin">

<input
type="checkbox"
name="pins_output_2[]"
value="D<?php echo $i; ?>"
<?php
if (in_array("D".$i, $pins2)) {
    echo "checked";
}
?>
>

D<?php echo $i; ?>

</label>

<?php } ?>

</div>

</div>


<div class="active-box">

<label>

<input
type="checkbox"
name="period_active_2"
value="1"
<?php
if ($data["period_active_2"] == 1) {
    echo "checked";
}
?>
>

Period 2 Active

</label>

</div>

</div>


<!-- =====================================================
     PERIOD 3
====================================================== -->

<div class="period">

<h3>
Period 3
</h3>

<div class="date-row">

<div class="date-box">

<label>
Start Date & Time
</label>

<input
type="datetime-local"
name="start_time_3"
value="<?php echo h($data["start_time_3"]); ?>"
>

</div>

<div class="date-box">

<label>
End Date & Time
</label>

<input
type="datetime-local"
name="end_time_3"
value="<?php echo h($data["end_time_3"]); ?>"
>

</div>

</div>


<div class="pins">

<strong>
Select Outputs:
</strong>

<div class="pin-grid">

<?php for ($i = 1; $i <= 8; $i++) { ?>

<label class="pin">

<input
type="checkbox"
name="pins_output_3[]"
value="D<?php echo $i; ?>"
<?php
if (in_array("D".$i, $pins3)) {
    echo "checked";
}
?>
>

D<?php echo $i; ?>

</label>

<?php } ?>

</div>

</div>


<div class="active-box">

<label>

<input
type="checkbox"
name="period_active_3"
value="1"
<?php
if ($data["period_active_3"] == 1) {
    echo "checked";
}
?>
>

Period 3 Active

</label>

</div>

</div>


<!-- =====================================================
     SAVE
====================================================== -->

<button
type="submit"
name="save_schedule"
>
SAVE <?php echo h($selected_day); ?> SCHEDULE
</button>

</form>


<div class="note">

<b>Important:</b>

If a period is unchecked, it is saved as
<b>DEACTIVATED</b>. Its date/time and selected D1-D8
pins remain saved, but the ESP will keep all outputs OFF
for that period.

</div>

</div>

</body>

