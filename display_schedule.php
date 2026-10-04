<?php
/*
========================================================
ESP-SWITCH7
display_schedule.php
========================================================

OWNER DEACTIVATION HAS PRIORITY

Database table:
weekly_schedule

Fields:
period_active_1
period_active_2
period_active_3

owner_deactivated_1
owner_deactivated_2
owner_deactivated_3

RULE:

owner_deactivated = 1
        |
        V
OWNER DEACTIVATED
        |
        V
OUTPUT OFF

Even if period_active = 1.

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
    ----------------------------------------------------
    OWNER DEACTIVATION HAS FIRST PRIORITY
    ----------------------------------------------------
    */

    if (intval($owner_deactivated) == 1) {

        return "OWNER DEACTIVATED";

    }


    /*
    ----------------------------------------------------
    USER PERIOD DEACTIVATED
    ----------------------------------------------------
    */

    if (intval($period_active) != 1) {

        return "DEACTIVATED";

    }


    /*
    ----------------------------------------------------
    DATE/TIME NOT SET
    ----------------------------------------------------
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
    ----------------------------------------------------
    CURRENT TIME INSIDE PERIOD
    ----------------------------------------------------
    */

    if (
        $current_timestamp >=
        $start_timestamp
        &&
        $current_timestamp <=
        $end_timestamp
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

    if (
        empty($value)
    ) {

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

    if (
        empty($value)
    ) {

        return array();

    }


    $parts =
        explode(
            ",",
            $value
        );


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


    /*
    ----------------------------------------------------
    OUTPUT IS ON ONLY WHEN STATUS = ACTIVE
    ----------------------------------------------------
    */

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


<meta http-equiv="refresh"
      content="60">


<style>

body {

    font-family: Arial, sans-serif;

    background: #f2f2f2;

    margin: 0;

    padding: 15px;

}


h1 {

    text-align: center;

    margin-bottom: 5px;

}


.controller {

    text-align: center;

    font-size: 20px;

    font-weight: bold;

    margin-bottom: 10px;

}


.current {

    text-align: center;

    background: white;

    padding: 15px;

    border-radius: 8px;

    margin-bottom: 20px;

    box-shadow:
        0 2px 6px
        rgba(0,0,0,0.15);

}


.period-container {

    display: flex;

    flex-direction: column;

    gap: 20px;

}


.period-box {

    background: white;

    border-radius: 12px;

    padding: 20px;

    box-shadow:
        0 3px 8px
        rgba(0,0,0,0.18);

    cursor: pointer;

}


.period-box.active {

    border: 5px solid #28a745;

}


.period-box.inactive {

    border: 5px solid #777;

}


.period-box.deactivated {

    border: 5px solid #dc3545;

}


.period-box.owner-deactivated {

    border: 5px solid #8b0000;

    background: #fff1f1;

}


.period-title {

    font-size: 26px;

    font-weight: bold;

    margin-bottom: 12px;

}


.status {

    font-size: 22px;

    font-weight: bold;

    margin: 10px 0;

}


.status-active {

    color: #28a745;

}


.status-inactive {

    color: #555;

}


.status-deactivated {

    color: #dc3545;

}


.status-owner {

    color: #8b0000;

}


.time {

    font-size: 18px;

    margin-bottom: 8px;

}


.output {

    font-size: 20px;

    font-weight: bold;

    margin-top: 15px;

}


.pins {

    display: flex;

    flex-wrap: wrap;

    gap: 8px;

    margin-top: 15px;

}


.pin {

    width: 55px;

    height: 45px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 7px;

    background: #777;

    color: white;

    font-weight: bold;

    font-size: 17px;

}


.pin.on {

    background: #28a745;

}


.pin.off {

    background: #777;

}


.owner-message {

    margin-top: 12px;

    padding: 12px;

    background: #f8d7da;

    color: #721c24;

    border-radius: 6px;

    font-weight: bold;

}


.user-message {

    margin-top: 12px;

    padding: 12px;

    background: #fff3cd;

    color: #856404;

    border-radius: 6px;

    font-weight: bold;

}

</style>


<script>

/*
========================================================
AUTO REFRESH
========================================================

The page reloads every second so that the display
changes automatically when a schedule becomes active
or inactive.

========================================================
*/

setTimeout(
    function () {
        location.reload();
    },
    1000
);


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


<div class="current">

<strong>
Current India Time:
</strong>

<br>

<?php

echo date(
    "d-m-Y H:i:s"
);

?>


<br><br>


<strong>
Today:
</strong>

<?php

echo htmlspecialchars(
    $current_day
);

?>

</div>


<div class="period-container">


<?php for (
    $p = 1;
    $p <= 3;
    $p++
): ?>


<?php

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

} elseif (
    $status ===
    "OWNER DEACTIVATED"
) {

    $box_class =
        "owner-deactivated";

} elseif (
    $status ===
    "DEACTIVATED"
) {

    $box_class =
        "deactivated";

} else {

    $box_class =
        "inactive";

}


?>


<div class="period-box <?php echo $box_class; ?>"
     onclick="showPeriod(
        <?php echo $p; ?>,
        '<?php echo htmlspecialchars($status, ENT_QUOTES); ?>',
        '<?php echo htmlspecialchars($pin_text, ENT_QUOTES); ?>'
     )">


<div class="period-title">

Period
<?php echo $p; ?>

</div>


<div class="time">

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


<?php if (
    $status ===
    "ACTIVE"
): ?>


<div class="status status-active">

● ACTIVE

</div>


<?php elseif (
    $status ===
    "OWNER DEACTIVATED"
): ?>


<div class="status status-owner">

● OWNER DEACTIVATED

</div>


<?php elseif (
    $status ===
    "DEACTIVATED"
): ?>


<div class="status status-deactivated">

● DEACTIVATED

</div>


<?php else: ?>


<div class="status status-inactive">

● INACTIVE

</div>


<?php endif; ?>


<?php if (
    $status ===
    "OWNER DEACTIVATED"
): ?>


<div class="owner-message">

OWNER HAS DEACTIVATED THIS PERIOD.

<br>

ALL OUTPUTS ARE OFF.

</div>


<?php elseif (
    $status ===
    "DEACTIVATED"
): ?>


<div class="user-message">

USER PERIOD IS DEACTIVATED.

<br>

ALL OUTPUTS ARE OFF.

</div>


<?php endif; ?>


<div class="output">

Output:

<?php

if (
    $status ===
    "ACTIVE"
) {

    echo "ON";

} else {

    echo "OFF";

}

?>

</div>


<div class="pins">


<?php for (
    $d = 1;
    $d <= 8;
    $d++
): ?>


<?php

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


<?php echo $pin_name; ?>


<br>


<?php

echo $is_on
    ? "ON"
    : "OFF";

?>


</div>


<?php endfor; ?>


</div>


</div>


<?php endfor; ?>


</div>


</body>

</html>


<?php

mysqli_close($conn);

?>
