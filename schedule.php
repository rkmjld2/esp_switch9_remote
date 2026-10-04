<?php

date_default_timezone_set("Asia/Kolkata");

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
    die(
        "Database connection failed: " .
        mysqli_connect_error()
    );
}

mysqli_set_charset($conn, "utf8mb4");

$controller_id =
    isset($_REQUEST["controller_id"])
    ? trim($_REQUEST["controller_id"])
    : "ESP0001";

$controller_sql =
    mysqli_real_escape_string(
        $conn,
        $controller_id
    );

function make_datetime($date, $time)
{
    if (empty($date) || empty($time)) {
        return NULL;
    }

    return $date . " " . $time . ":00";
}

function sql_datetime($conn, $value)
{
    if ($value === NULL || $value === "") {
        return "NULL";
    }

    return "'" .
        mysqli_real_escape_string(
            $conn,
            $value
        ) .
        "'";
}

function date_value($value)
{
    if (empty($value)) {
        return "";
    }

    return date(
        "Y-m-d",
        strtotime($value)
    );
}

function time_value($value)
{
    if (empty($value)) {
        return "";
    }

    return date(
        "H:i",
        strtotime($value)
    );
}

$message = "";
$error   = "";


/* =====================================================
   SAVE USER SCHEDULE
   ===================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset($_POST["save_schedule"])
) {

    $id = intval($_POST["id"]);

    $periods = array();

    for ($p = 1; $p <= 3; $p++) {

        $start_date =
            isset($_POST["start_date_$p"])
            ? trim($_POST["start_date_$p"])
            : "";

        $start_time =
            isset($_POST["start_time_$p"])
            ? trim($_POST["start_time_$p"])
            : "";

        $end_date =
            isset($_POST["end_date_$p"])
            ? trim($_POST["end_date_$p"])
            : "";

        $end_time =
            isset($_POST["end_time_$p"])
            ? trim($_POST["end_time_$p"])
            : "";

        $start_datetime =
            make_datetime(
                $start_date,
                $start_time
            );

        $end_datetime =
            make_datetime(
                $end_date,
                $end_time
            );

        $period_active =
            isset($_POST["period_active_$p"])
            ? intval($_POST["period_active_$p"])
            : 0;

        $pins = "";

        if (
            isset($_POST["pins_$p"])
            &&
            is_array($_POST["pins_$p"])
        ) {

            $pin_array = array();

            foreach ($_POST["pins_$p"] as $pin) {

                $pin = trim($pin);

                if (
                    preg_match(
                        '/^D[1-8]$/',
                        $pin
                    )
                ) {
                    $pin_array[] = $pin;
                }
            }

            $pins =
                implode(
                    ",",
                    $pin_array
                );
        }

        $periods[$p] = array(
            "start"  => $start_datetime,
            "end"    => $end_datetime,
            "pins"   => $pins,
            "active" => $period_active
        );
    }


    /* =================================================
       VALIDATE PERIODS
       ================================================= */

    $valid = true;

    for ($p = 1; $p <= 3; $p++) {

        if (
            $periods[$p]["start"] !== NULL
            &&
            $periods[$p]["end"] !== NULL
        ) {

            if (
                strtotime($periods[$p]["start"])
                >=
                strtotime($periods[$p]["end"])
            ) {

                $error =
                    "Period $p: Start must be before End.";

                $valid = false;
                break;
            }
        }
    }

    if ($valid) {

        if (
            $periods[1]["end"] !== NULL &&
            $periods[2]["start"] !== NULL &&
            strtotime($periods[1]["end"]) >
            strtotime($periods[2]["start"])
        ) {

            $error =
                "Period 1 must finish before Period 2 starts.";

            $valid = false;
        }
    }

    if ($valid) {

        if (
            $periods[2]["end"] !== NULL &&
            $periods[3]["start"] !== NULL &&
            strtotime($periods[2]["end"]) >
            strtotime($periods[3]["start"])
        ) {

            $error =
                "Period 2 must finish before Period 3 starts.";

            $valid = false;
        }
    }


    /* =================================================
       IMPORTANT:
       ONLY USER FIELDS ARE UPDATED.

       owner_deactivated_1/2/3 are NOT changed here.
       ================================================= */

    if ($valid) {

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
        intval(
            $periods[1]["active"]
        ) . ",


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
        intval(
            $periods[2]["active"]
        ) . ",


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
        intval(
            $periods[3]["active"]
        ) . "

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
   READ SCHEDULE
   ===================================================== */

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

$result =
    mysqli_query(
        $conn,
        $sql
    );

if (!$result) {
    die(
        "Schedule query failed: " .
        mysqli_error($conn)
    );
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>ESP-SWITCH7 Schedule</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 20px;
    font-family: Arial, sans-serif;
    background:
        linear-gradient(
            135deg,
            #dbeafe,
            #ede9fe
        );
}

h1 {
    text-align: center;
    color: #17365d;
}

.controller {
    text-align: center;
    font-weight: bold;
    font-size: 20px;
    color: #17365d;
    margin-bottom: 20px;
}

.message,
.error {
    max-width: 1100px;
    margin: 10px auto;
    padding: 13px;
    border-radius: 8px;
    text-align: center;
    font-weight: bold;
}

.message {
    background: #d1e7dd;
    color: #0f5132;
}

.error {
    background: #f8d7da;
    color: #842029;
}

.day-box {
    max-width: 1100px;
    margin: 0 auto 25px;
    background: white;
    padding: 18px;
    border-radius: 15px;
    box-shadow:
        0 5px 16px rgba(0,0,0,0.15);
}

.day-title {
    background: #17365d;
    color: white;
    padding: 12px;
    border-radius: 8px;
    text-align: center;
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 15px;
}

.period {
    border: 2px solid #d0d7de;
    border-radius: 12px;
    padding: 18px;
    margin-bottom: 18px;
    background: #f8fbff;
}

.period-title {
    font-size: 22px;
    font-weight: bold;
    color: #17365d;
    margin-bottom: 12px;
}

input[type="date"],
input[type="time"] {
    padding: 7px;
    margin: 4px;
    border: 1px solid #aaa;
    border-radius: 6px;
}

.pin-title {
    margin-top: 16px;
    font-weight: bold;
}

.pin-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.pin-option {
    position: relative;
}

.pin-option input {
    display: none;
}

.pin-option span {
    display: block;
    min-width: 62px;
    padding: 10px;
    text-align: center;
    border-radius: 8px;
    background: #e9ecef;
    border: 2px solid #adb5bd;
    cursor: pointer;
    font-weight: bold;
}

.pin-option input:checked + span {
    background: #198754;
    color: white;
    border-color: #198754;
}

.control-box {
    margin-top: 15px;
    padding: 12px;
    border-radius: 8px;
    background: #e7f1ff;
}

.owner-box {
    margin-top: 12px;
    padding: 12px;
    border-radius: 8px;
    background: #fff3cd;
    border: 1px solid #ffe69c;
}

.owner-lock {
    margin-top: 10px;
    padding: 10px;
    background: #f8d7da;
    color: #842029;
    border-radius: 7px;
    font-weight: bold;
}

.save-button {
    margin-top: 12px;
    padding: 12px 25px;
    border: 0;
    border-radius: 8px;
    background: #0d6efd;
    color: white;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

</style>

</head>

<body>

<h1>ESP-SWITCH7 WEEKLY SCHEDULE</h1>

<div class="controller">
Controller:
<?php
echo htmlspecialchars($controller_id);
?>
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


<?php while (
    $row =
    mysqli_fetch_assoc($result)
): ?>

<div class="day-box">

<div class="day-title">
<?php
echo htmlspecialchars(
    $row["day_week"]
);
?>
</div>

<form method="POST">

<input type="hidden"
       name="save_schedule"
       value="1">

<input type="hidden"
       name="id"
       value="<?php
       echo intval($row["id"]);
       ?>">

<input type="hidden"
       name="controller_id"
       value="<?php
       echo htmlspecialchars($controller_id);
       ?>">


<?php for (
    $p = 1;
    $p <= 3;
    $p++
): ?>

<?php

$start =
    $row["start_time_" . $p];

$end =
    $row["end_time_" . $p];

$pins =
    trim(
        $row["pins_output_" . $p]
    );

$user_active =
    intval(
        $row["period_active_" . $p]
    );

$owner_deactivated =
    intval(
        $row["owner_deactivated_" . $p]
    );

$selected_pins =
    $pins == ""
    ? array()
    : explode(
        ",",
        $pins
    );

?>

<div class="period">

<div class="period-title">
Period <?php echo $p; ?>
</div>

<div>
<strong>Start:</strong>

<input type="date"
       name="start_date_<?php echo $p; ?>"
       value="<?php
       echo date_value($start);
       ?>">

<input type="time"
       name="start_time_<?php echo $p; ?>"
       value="<?php
       echo time_value($start);
       ?>">
</div>

<div>
<strong>End:</strong>

<input type="date"
       name="end_date_<?php echo $p; ?>"
       value="<?php
       echo date_value($end);
       ?>">

<input type="time"
       name="end_time_<?php echo $p; ?>"
       value="<?php
       echo time_value($end);
       ?>">
</div>


<div class="pin-title">
Scheduled D1-D8:
</div>

<div class="pin-grid">

<?php for (
    $d = 1;
    $d <= 8;
    $d++
): ?>

<?php

$pin_name =
    "D" . $d;

$checked =
    in_array(
        $pin_name,
        $selected_pins
    );

?>

<label class="pin-option">

<input type="checkbox"
       name="pins_<?php echo $p; ?>[]"
       value="<?php echo $pin_name; ?>"
       <?php
       echo $checked
           ? "checked"
           : "";
       ?>>

<span>
<?php echo $pin_name; ?>
</span>

</label>

<?php endfor; ?>

</div>


<div class="control-box">

<input type="hidden"
       name="period_active_<?php echo $p; ?>"
       value="0">

<label>

<input type="checkbox"
       name="period_active_<?php echo $p; ?>"
       value="1"
       <?php
       echo $user_active == 1
           ? "checked"
           : "";
       ?>>

<strong>
User Period Active
</strong>

</label>

<br><br>

<strong>
User setting:
</strong>

<?php

echo $user_active == 1
    ? "ACTIVE"
    : "DEACTIVATED";

?>

</div>


<?php if (
    $owner_deactivated == 1
): ?>

<div class="owner-lock">

OWNER DEACTIVATED

<br>

The selected pins are saved, but the actual ESP
outputs must remain OFF.

</div>

<?php else: ?>

<div class="owner-box">

<strong>
OWNER STATUS:
</strong>

OWNER ACTIVATED

</div>

<?php endif; ?>


</div>

<?php endfor; ?>


<button
    type="submit"
    class="save-button">

Save
<?php
echo htmlspecialchars(
    $row["day_week"]
);
?>

</button>

</form>

</div>

<?php endwhile; ?>

</body>
</html>

<?php

mysqli_close($conn);

?>
