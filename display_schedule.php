<?php
/*
========================================================
ESP-SWITCH7
display_schedule.php
========================================================

OWNER DEACTIVATION HAS PRIORITY

IMPORTANT:
The displayed India clock is updated by JavaScript
every 1 second.

The PHP server time is used for schedule/status
calculation.

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

    die(
        "Database connection failed: "
        . mysqli_connect_error()
    );

}


mysqli_set_charset(
    $conn,
    "utf8mb4"
);


/* =====================================================
   CONTROLLER ID
   ===================================================== */

$controller_id =
    isset($_GET["controller_id"])
    ? trim($_GET["controller_id"])
    : "ESP0001";


$controller_sql =
    mysqli_real_escape_string(
        $conn,
        $controller_id
    );


/* =====================================================
   CURRENT INDIA TIME
   ===================================================== */

$current_datetime =
    date("Y-m-d H:i:s");


$current_day =
    date("l");


$current_timestamp =
    strtotime($current_datetime);


/* =====================================================
   GET TODAY'S SCHEDULE
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

WHERE day_week =
'" . mysqli_real_escape_string(
        $conn,
        $current_day
    ) . "'

AND controller_id =
'$controller_sql'

LIMIT 1

";


$result =
    mysqli_query(
        $conn,
        $sql
    );


if (!$result) {

    die(
        "Schedule query failed: "
        . mysqli_error($conn)
    );

}


$row =
    mysqli_fetch_assoc($result);


/* =====================================================
   PERIOD STATUS FUNCTION
   ===================================================== */

function check_status(
    $owner_deactivated,
    $period_active,
    $start,
    $end,
    $current_timestamp
) {

    /*
    OWNER DEACTIVATION HAS FIRST PRIORITY
    */

    if (intval($owner_deactivated) == 1) {

        return "OWNER DEACTIVATED";

    }


    /*
    USER PERIOD DEACTIVATED
    */

    if (intval($period_active) != 1) {

        return "DEACTIVATED";

    }


    /*
    DATE/TIME NOT SET
    */

    if (
        empty($start)
        ||
        empty($end)
    ) {

        return "INACTIVE";

    }


    $start_timestamp =
        strtotime($start);


    $end_timestamp =
        strtotime($end);


    /*
    CURRENT TIME INSIDE PERIOD
    */

    if (
        $current_timestamp >= $start_timestamp
        &&
        $current_timestamp <= $end_timestamp
    ) {

        return "ACTIVE";

    }


    return "INACTIVE";
}


/* =====================================================
   FORMAT DATE/TIME
   ===================================================== */

function display_datetime($value)
{

    if (empty($value)) {

        return "--";

    }


    return date(
        "d-m-Y H:i",
        strtotime($value)
    );
}


/* =====================================================
   GET PIN ARRAY
   ===================================================== */

function get_pins($value)
{

    if (empty($value)) {

        return array();

    }


    $parts =
        explode(",", $value);


    $pins =
        array();


    foreach ($parts as $pin) {

        $pin =
            trim($pin);


        if (
            preg_match(
                '/^D[1-8]$/',
                $pin
            )
        ) {

            $pins[] =
                $pin;

        }

    }


    return $pins;
}


/* =====================================================
   BUILD PERIOD INFORMATION
   ===================================================== */

$periods =
    array();


for ($p = 1; $p <= 3; $p++) {

    if ($row) {

        $start =
            $row["start_time_" . $p];

        $end =
            $row["end_time_" . $p];

        $pins =
            get_pins(
                $row["pins_output_" . $p]
            );

        $period_active =
            intval(
                $row["period_active_" . $p]
            );

        $owner_deactivated =
            intval(
                $row["owner_deactivated_" . $p]
            );

    } else {

        $start = null;

        $end = null;

        $pins = array();

        $period_active = 0;

        $owner_deactivated = 0;

    }


    $status =
        check_status(
            $owner_deactivated,
            $period_active,
            $start,
            $end,
            $current_timestamp
        );


    $output_on =
        ($status === "ACTIVE");


    $periods[$p] =
        array(

            "start" =>
                $start,

            "end" =>
                $end,

            "pins" =>
                $pins,

            "period_active" =>
                $period_active,

            "owner_deactivated" =>
                $owner_deactivated,

            "status" =>
                $status,

            "output_on" =>
                $output_on
        );
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">


<title>
ESP-SWITCH7 Schedule Display
</title>


<!-- ==================================================
     PAGE RELOAD
     ==================================================

     Reload every 60 seconds.

     This is separate from the live clock.
     The JavaScript clock changes every second.

     ================================================== -->

<meta http-equiv="refresh"
      content="60">


<style>

/* =====================================================
   GENERAL PAGE
   ===================================================== */

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    padding: 20px;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        linear-gradient(
            135deg,
            #eef5ff,
            #f8fbff
        );

    color: #222;

}


.main-container {

    max-width: 1000px;

    margin: auto;

}


/* =====================================================
   HEADER
   ===================================================== */

.header {

    background:
        linear-gradient(
            135deg,
            #0d6efd,
            #174ea6
        );

    color: white;

    padding: 25px 20px;

    border-radius: 18px;

    text-align: center;

    box-shadow:
        0 8px 20px
        rgba(0,0,0,0.15);

    margin-bottom: 18px;

}


.header h1 {

    margin: 0;

    font-size: 32px;

    letter-spacing: 1px;

}


.header .controller {

    margin-top: 8px;

    font-size: 20px;

    font-weight: bold;

}


/* =====================================================
   CURRENT TIME CARD
   ===================================================== */

.current-card {

    background: white;

    border-radius: 16px;

    padding: 18px;

    text-align: center;

    box-shadow:
        0 5px 15px
        rgba(0,0,0,0.10);

    margin-bottom: 20px;

}


.current-label {

    font-size: 15px;

    color: #666;

}


.current-time {

    font-size: 28px;

    font-weight: bold;

    color: #0d6efd;

    margin: 5px 0;

}


.current-day {

    display: inline-block;

    background: #e7f1ff;

    color: #0d6efd;

    padding: 7px 16px;

    border-radius: 20px;

    font-weight: bold;

    margin-top: 5px;

}


/* =====================================================
   PERIODS
   ===================================================== */

.period-container {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;

}


.period-box {

    background: white;

    border-radius: 18px;

    padding: 20px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,0.12);

    position: relative;

    overflow: hidden;

    transition:
        transform 0.2s,
        box-shadow 0.2s;

}


.period-box:hover {

    transform:
        translateY(-3px);

    box-shadow:
        0 10px 25px
        rgba(0,0,0,0.16);

}


.period-box.active {

    border-top:
        7px solid #198754;

}


.period-box.inactive {

    border-top:
        7px solid #6c757d;

}


.period-box.deactivated {

    border-top:
        7px solid #dc3545;

}


.period-box.owner-deactivated {

    border-top:
        7px solid #8b0000;

    background:
        #fff8f8;

}


/* =====================================================
   PERIOD TITLE
   ===================================================== */

.period-title {

    font-size: 24px;

    font-weight: bold;

    margin-bottom: 14px;

}


.active .period-title {

    color: #198754;

}


.inactive .period-title {

    color: #555;

}


.deactivated .period-title {

    color: #dc3545;

}


.owner-deactivated .period-title {

    color: #8b0000;

}


/* =====================================================
   STATUS
   ===================================================== */

.status {

    display: inline-block;

    padding: 8px 14px;

    border-radius: 20px;

    font-size: 16px;

    font-weight: bold;

    margin: 10px 0 15px 0;

}


.status-active {

    background: #d1e7dd;

    color: #0f5132;

}


.status-inactive {

    background: #e9ecef;

    color: #495057;

}


.status-deactivated {

    background: #f8d7da;

    color: #842029;

}


.status-owner {

    background: #f5c2c7;

    color: #721c24;

}


/* =====================================================
   DATE / TIME
   ===================================================== */

.time-box {

    background: #f8f9fa;

    border-radius: 10px;

    padding: 12px;

    font-size: 15px;

    line-height: 1.8;

    margin-bottom: 12px;

}


.time-box strong {

    color: #495057;

}


/* =====================================================
   OUTPUT
   ===================================================== */

.output-box {

    text-align: center;

    padding: 12px;

    border-radius: 12px;

    font-size: 21px;

    font-weight: bold;

    margin-top: 12px;

}


.output-on {

    background: #d1e7dd;

    color: #0f5132;

}


.output-off {

    background: #e9ecef;

    color: #495057;

}


/* =====================================================
   PIN SECTION
   ===================================================== */

.pin-title {

    margin-top: 18px;

    margin-bottom: 8px;

    font-size: 15px;

    font-weight: bold;

    color: #555;

}


.pins {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 7px;

}


.pin {

    min-height: 48px;

    border-radius: 9px;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    font-weight: bold;

    font-size: 13px;

    background: #e9ecef;

    color: #6c757d;

    border:
        1px solid #ced4da;

}


.pin.on {

    background: #198754;

    color: white;

    border-color: #146c43;

}


.pin.off {

    background: #f1f3f5;

    color: #adb5bd;

}


/* =====================================================
   MESSAGES
   ===================================================== */

.owner-message {

    margin-top: 12px;

    padding: 12px;

    background: #f8d7da;

    color: #842029;

    border-radius: 10px;

    font-size: 14px;

    line-height: 1.5;

    font-weight: bold;

}


.user-message {

    margin-top: 12px;

    padding: 12px;

    background: #fff3cd;

    color: #664d03;

    border-radius: 10px;

    font-size: 14px;

    line-height: 1.5;

    font-weight: bold;

}


/* =====================================================
   FOOTER
   ===================================================== */

.footer {

    text-align: center;

    margin-top: 22px;

    color: #777;

    font-size: 13px;

}


/* =====================================================
   MOBILE
   ===================================================== */

@media (max-width: 800px) {

    body {

        padding: 12px;

    }


    .header h1 {

        font-size: 27px;

    }


    .period-container {

        grid-template-columns: 1fr;

    }


    .current-time {

        font-size: 23px;

    }

}

</style>


<script>

/* =====================================================
   LIVE INDIA CLOCK
   =====================================================

   This clock runs entirely in the browser.

   It updates every 1 second.

   India timezone:
   UTC + 5:30

   ===================================================== */


function updateIndiaClock()
{

    /*
    -----------------------------------------------------
    Get current UTC time.
    -----------------------------------------------------
    */

    const now =
        new Date();


    /*
    -----------------------------------------------------
    Convert to India Standard Time.

    We use the browser's Intl formatter with the
    Asia/Kolkata timezone.

    -----------------------------------------------------
    */

    const indiaTime =
        new Intl.DateTimeFormat(
            "en-GB",
            {
                timeZone:
                    "Asia/Kolkata",

                day:
                    "2-digit",

                month:
                    "2-digit",

                year:
                    "numeric",

                hour:
                    "2-digit",

                minute:
                    "2-digit",

                second:
                    "2-digit",

                hour12:
                    false
            }
        ).formatToParts(now);


    /*
    -----------------------------------------------------
    Create an object containing each time part.
    -----------------------------------------------------
    */

    let parts = {};


    indiaTime.forEach(
        function(part)
        {

            parts[part.type] =
                part.value;

        }
    );


    /*
    -----------------------------------------------------
    Create:

    DD-MM-YYYY HH:MM:SS

    -----------------------------------------------------
    */

    const formattedTime =
        parts.day +
        "-" +
        parts.month +
        "-" +
        parts.year +
        " " +
        parts.hour +
        ":" +
        parts.minute +
        ":" +
        parts.second;


    /*
    -----------------------------------------------------
    Display the live time.
    -----------------------------------------------------
    */

    const clock =
        document.getElementById(
            "india-clock"
        );


    if (clock) {

        clock.textContent =
            formattedTime;

    }


    /*
    -----------------------------------------------------
    Display current day.
    -----------------------------------------------------
    */

    const day =
        new Intl.DateTimeFormat(
            "en-IN",
            {
                timeZone:
                    "Asia/Kolkata",

                weekday:
                    "long"
            }
        ).format(now);


    const dayElement =
        document.getElementById(
            "india-day"
        );


    if (dayElement) {

        dayElement.textContent =
            day;

    }

}


/*
========================================================
START CLOCK IMMEDIATELY
========================================================
*/

updateIndiaClock();


/*
========================================================
UPDATE CLOCK EVERY ONE SECOND
========================================================
*/

setInterval(
    updateIndiaClock,
    1000
);


/* =====================================================
   PERIOD INFORMATION
   ===================================================== */

function showPeriod(
    period,
    status,
    pins
) {

    if (
        status ===
        "OWNER DEACTIVATED"
    ) {

        alert(
            "Period " +
            period +
            "\n\n" +
            "OWNER DEACTIVATED" +
            "\n\n" +
            "Output: OFF"
        );

        return;

    }


    if (
        status ===
        "DEACTIVATED"
    ) {

        alert(
            "Period " +
            period +
            "\n\n" +
            "Period Deactivated" +
            "\n\n" +
            "Output: OFF"
        );

        return;

    }


    if (
        status ===
        "ACTIVE"
    ) {

        alert(
            "Period " +
            period +
            "\n\n" +
            "Status: ACTIVE" +
            "\n\n" +
            "Outputs: " +
            pins
        );

        return;

    }


    alert(
        "Period " +
        period +
        "\n\n" +
        "Status: INACTIVE" +
        "\n\n" +
        "Output: OFF"
    );

}

</script>

</head>


<body>


<div class="main-container">


<!-- ==================================================
     HEADER
     ================================================== -->

<div class="header">

<h1>
ESP-SWITCH7
</h1>


<div class="controller">

Controller:
<?php

echo htmlspecialchars(
    $controller_id
);

?>

</div>

</div>


<!-- ==================================================
     CURRENT TIME
     ================================================== -->

<div class="current-card">

<div class="current-label">

CURRENT INDIA TIME

</div>


<!--
========================================================
THIS VALUE IS UPDATED BY JAVASCRIPT EVERY SECOND
========================================================
-->

<div
    class="current-time"
    id="india-clock"
>

<?php

echo date(
    "d-m-Y H:i:s"
);

?>

</div>


<div
    class="current-day"
    id="india-day"
>

<?php

echo htmlspecialchars(
    $current_day
);

?>

</div>

</div>


<!-- ==================================================
     PERIODS
     ================================================== -->

<div class="period-container">


<?php

for (
    $p = 1;
    $p <= 3;
    $p++
):


$period =
    $periods[$p];


$status =
    $period["status"];


$pins =
    $period["pins"];


$pin_text =
    implode(
        ", ",
        $pins
    );


if (
    $status ===
    "ACTIVE"
) {

    $box_class =
        "active";

}
elseif (
    $status ===
    "OWNER DEACTIVATED"
) {

    $box_class =
        "owner-deactivated";

}
elseif (
    $status ===
    "DEACTIVATED"
) {

    $box_class =
        "deactivated";

}
else {

    $box_class =
        "inactive";

}

?>


<div
    class="period-box <?php echo $box_class; ?>"
    onclick="showPeriod(
        <?php echo $p; ?>,
        '<?php
        echo htmlspecialchars(
            $status,
            ENT_QUOTES
        );
        ?>',
        '<?php
        echo htmlspecialchars(
            $pin_text,
            ENT_QUOTES
        );
        ?>'
    )"
>


<div class="period-title">

Period <?php echo $p; ?>

</div>


<div class="time-box">

<strong>
Start:
</strong>

<?php

echo display_datetime(
    $period["start"]
);

?>

<br>


<strong>
End:
</strong>

<?php

echo display_datetime(
    $period["end"]
);

?>

</div>


<?php

if (
    $status ===
    "ACTIVE"
):

?>

<div class="status status-active">

● ACTIVE

</div>

<?php

elseif (
    $status ===
    "OWNER DEACTIVATED"
):

?>

<div class="status status-owner">

● OWNER DEACTIVATED

</div>

<?php

elseif (
    $status ===
    "DEACTIVATED"
):

?>

<div class="status status-deactivated">

● DEACTIVATED

</div>

<?php

else:

?>

<div class="status status-inactive">

● INACTIVE

</div>

<?php

endif;

?>


<?php

if (
    $status ===
    "OWNER DEACTIVATED"
):

?>

<div class="owner-message">

OWNER HAS DEACTIVATED THIS PERIOD.

<br>

ALL OUTPUTS ARE OFF.

</div>

<?php

elseif (
    $status ===
    "DEACTIVATED"
):

?>

<div class="user-message">

USER PERIOD IS DEACTIVATED.

<br>

ALL OUTPUTS ARE OFF.

</div>

<?php

endif;

?>


<?php

if (
    $status ===
    "ACTIVE"
) {

    $output_class =
        "output-on";

    $output_text =
        "● OUTPUT ON";

}
else {

    $output_class =
        "output-off";

    $output_text =
        "● OUTPUT OFF";

}

?>


<div class="output-box <?php echo $output_class; ?>">

<?php echo $output_text; ?>

</div>


<div class="pin-title">

D1 – D8 OUTPUT STATUS

</div>


<div class="pins">


<?php

for (
    $d = 1;
    $d <= 8;
    $d++
):


$pin_name =
    "D" . $d;


$is_on =
    (
        $status ===
        "ACTIVE"
        &&
        in_array(
            $pin_name,
            $pins
        )
    );

?>


<div class="pin <?php

echo $is_on
    ? "on"
    : "off";

?>">

<span>

<?php echo $pin_name; ?>

</span>

<span>

<?php

echo $is_on
    ? "ON"
    : "OFF";

?>

</span>

</div>


<?php

endfor;

?>


</div>


</div>


<?php

endfor;

?>


</div>


<div class="footer">

ESP-SWITCH7
&nbsp; | &nbsp;
Automatic schedule display
&nbsp; | &nbsp;
India Standard Time (IST)

</div>


</div>


</body>

</html>


<?php

mysqli_close($conn);

?>

