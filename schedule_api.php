<?php
/*
===========================================================
 ESP-SWITCH9 REMOTE
 schedule_api.php

 Render + TiDB Cloud
 Database : esp_switch7

 Purpose:
 - ESP8266 requests today's schedule
 - Server uses India Standard Time
 - Server decides which period is active
 - Deactivated period = all outputs OFF
 - Returns D1-D8 output information
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

    header("Content-Type: application/json");

    echo json_encode(array(
        "status" => "error",
        "message" => "Database connection failed"
    ));

    exit;
}

mysqli_set_charset($conn, "utf8mb4");


/* =========================================================
   JSON HEADER
========================================================= */

header("Content-Type: application/json");

header("Cache-Control: no-cache, no-store, must-revalidate");

header("Pragma: no-cache");

header("Expires: 0");


/* =========================================================
   CONTROLLER ID
========================================================= */

$controller_id = "";

if (isset($_GET["controller_id"])) {

    $controller_id =
        trim($_GET["controller_id"]);
}


if ($controller_id == "") {

    echo json_encode(array(
        "status" => "error",
        "message" => "controller_id is required"
    ));

    exit;
}


/* =========================================================
   CURRENT INDIA DATE/TIME
========================================================= */

$current_datetime =
    date("Y-m-d H:i:s");

$current_date =
    date("Y-m-d");

$current_time =
    date("H:i:s");

$current_day =
    date("l");


/* =========================================================
   GET TODAY'S SCHEDULE
========================================================= */

$sql = "
    SELECT

        id,
        controller_id,
        day_week,

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

    echo json_encode(array(
        "status" => "error",
        "message" => "SQL prepare failed"
    ));

    exit;
}


mysqli_stmt_bind_param(
    $stmt,
    "ss",
    $controller_id,
    $current_day
);


mysqli_stmt_execute($stmt);


$result =
    mysqli_stmt_get_result($stmt);


/* =========================================================
   NO SCHEDULE
========================================================= */

if (!$row = mysqli_fetch_assoc($result)) {

    echo json_encode(array(

        "status" => "success",

        "controller_id" =>
            $controller_id,

        "current_datetime" =>
            $current_datetime,

        "current_day" =>
            $current_day,

        "current_period" =>
            0,

        "period_active" =>
            0,

        "period_status" =>
            "NO_SCHEDULE",

        "pins_output" =>
            "",

        "D1" => 0,
        "D2" => 0,
        "D3" => 0,
        "D4" => 0,
        "D5" => 0,
        "D6" => 0,
        "D7" => 0,
        "D8" => 0,

        "periods" => array(

            "period_1" => array(
                "active" => 0,
                "status" => "NO_SCHEDULE",
                "start" => "",
                "end" => "",
                "pins_output" => ""
            ),

            "period_2" => array(
                "active" => 0,
                "status" => "NO_SCHEDULE",
                "start" => "",
                "end" => "",
                "pins_output" => ""
            ),

            "period_3" => array(
                "active" => 0,
                "status" => "NO_SCHEDULE",
                "start" => "",
                "end" => "",
                "pins_output" => ""
            )
        )
    ));

    exit;
}


/* =========================================================
   HELPER FUNCTION
========================================================= */

function getPeriodStatus(
    $start,
    $end,
    $active,
    $current_datetime
) {

    /*
    ---------------------------------------------------------
    Deactivated
    ---------------------------------------------------------
    */

    if ((int)$active !== 1) {

        return "DEACTIVATED";
    }


    /*
    ---------------------------------------------------------
    Missing date/time
    ---------------------------------------------------------
    */

    if ($start == "" || $end == "") {

        return "NOT_SET";
    }


    /*
    ---------------------------------------------------------
    Current time before period
    ---------------------------------------------------------
    */

    if ($current_datetime < $start) {

        return "NOT_STARTED";
    }


    /*
    ---------------------------------------------------------
    Current time inside period
    ---------------------------------------------------------
    */

    if (
        $current_datetime >= $start &&
        $current_datetime <= $end
    ) {

        return "ACTIVE";
    }


    /*
    ---------------------------------------------------------
    Current time after period
    ---------------------------------------------------------
    */

    return "EXPIRED";
}


/* =========================================================
   GET PERIOD STATUS
========================================================= */

$status1 = getPeriodStatus(
    $row["start_time_1"],
    $row["end_time_1"],
    $row["period_active_1"],
    $current_datetime
);


$status2 = getPeriodStatus(
    $row["start_time_2"],
    $row["end_time_2"],
    $row["period_active_2"],
    $current_datetime
);


$status3 = getPeriodStatus(
    $row["start_time_3"],
    $row["end_time_3"],
    $row["period_active_3"],
    $current_datetime
);


/* =========================================================
   DETERMINE CURRENT PERIOD
========================================================= */

$current_period = 0;

$current_status = "NO_ACTIVE_PERIOD";

$current_pins = "";


/*
-------------------------------------------------------------
PERIOD 1
-------------------------------------------------------------
*/

if ($status1 == "ACTIVE") {

    $current_period = 1;

    $current_status = "ACTIVE";

    $current_pins =
        $row["pins_output_1"];
}


/*
-------------------------------------------------------------
PERIOD 2
-------------------------------------------------------------
*/

elseif ($status2 == "ACTIVE") {

    $current_period = 2;

    $current_status = "ACTIVE";

    $current_pins =
        $row["pins_output_2"];
}


/*
-------------------------------------------------------------
PERIOD 3
-------------------------------------------------------------
*/

elseif ($status3 == "ACTIVE") {

    $current_period = 3;

    $current_status = "ACTIVE";

    $current_pins =
        $row["pins_output_3"];
}


/* =========================================================
   CONVERT PINS TO D1-D8
========================================================= */

$D1 = 0;
$D2 = 0;
$D3 = 0;
$D4 = 0;
$D5 = 0;
$D6 = 0;
$D7 = 0;
$D8 = 0;


/*
-------------------------------------------------------------
ONLY ACTIVE PERIOD CAN TURN OUTPUTS ON
-------------------------------------------------------------
*/

if ($current_status == "ACTIVE" &&
    $current_pins != "") {

    $pin_array =
        explode(",", $current_pins);


    foreach ($pin_array as $pin) {

        $pin = trim($pin);


        switch ($pin) {

            case "D1":
                $D1 = 1;
                break;

            case "D2":
                $D2 = 1;
                break;

            case "D3":
                $D3 = 1;
                break;

            case "D4":
                $D4 = 1;
                break;

            case "D5":
                $D5 = 1;
                break;

            case "D6":
                $D6 = 1;
                break;

            case "D7":
                $D7 = 1;
                break;

            case "D8":
                $D8 = 1;
                break;
        }
    }
}


/* =========================================================
   BUILD PERIOD INFORMATION
========================================================= */

$period1 = array(

    "period" => 1,

    "active" =>
        (int)$row["period_active_1"],

    "status" =>
        $status1,

    "start" =>
        $row["start_time_1"],

    "end" =>
        $row["end_time_1"],

    "pins_output" =>
        $row["pins_output_1"]
);


$period2 = array(

    "period" => 2,

    "active" =>
        (int)$row["period_active_2"],

    "status" =>
        $status2,

    "start" =>
        $row["start_time_2"],

    "end" =>
        $row["end_time_2"],

    "pins_output" =>
        $row["pins_output_2"]
);


$period3 = array(

    "period" => 3,

    "active" =>
        (int)$row["period_active_3"],

    "status" =>
        $status3,

    "start" =>
        $row["start_time_3"],

    "end" =>
        $row["end_time_3"],

    "pins_output" =>
        $row["pins_output_3"]
);


/* =========================================================
   FINAL JSON RESPONSE
========================================================= */

$response = array(

    "status" =>
        "success",

    "controller_id" =>
        $controller_id,

    "current_datetime" =>
        $current_datetime,

    "current_date" =>
        $current_date,

    "current_time" =>
        $current_time,

    "current_day" =>
        $current_day,

    "current_period" =>
        $current_period,

    "period_active" =>
        ($current_period > 0) ? 1 : 0,

    "period_status" =>
        $current_status,

    "pins_output" =>
        $current_pins,

    /*
    ---------------------------------------------------------
    D1-D8
    ---------------------------------------------------------
    */

    "D1" => $D1,
    "D2" => $D2,
    "D3" => $D3,
    "D4" => $D4,
    "D5" => $D5,
    "D6" => $D6,
    "D7" => $D7,
    "D8" => $D8,

    /*
    ---------------------------------------------------------
    ALL PERIODS
    ---------------------------------------------------------
    */

    "periods" => array(

        "period_1" =>
            $period1,

        "period_2" =>
            $period2,

        "period_3" =>
            $period3
    )
);


/* =========================================================
   SEND JSON
========================================================= */

echo json_encode(
    $response,
    JSON_PRETTY_PRINT
);


/* =========================================================
   CLOSE
========================================================= */

mysqli_stmt_close($stmt);

mysqli_close($conn);

?>

