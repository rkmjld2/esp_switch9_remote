<?php
/*
========================================================
ESP-SWITCH7
schedule_api.php
========================================================

ESP8266 reads this API.

OWNER DEACTIVATION HAS HIGHEST PRIORITY.

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
PERIOD OUTPUT = OFF

The ESP receives active_pins only from periods which
are actually ACTIVE.

========================================================
*/

header("Content-Type: application/json");

date_default_timezone_set("Asia/Kolkata");


/* =====================================================
   DATABASE CONNECTION
   ===================================================== */

$host     = getenv("DB_HOST");
$user     = getenv("DB_USER");
$password = getenv("DB_PASSWORD");
$database = getenv("DB_NAME");
$port     = intval(getenv("DB_PORT"));


if (
    !$host ||
    !$user ||
    !$database ||
    !$port
) {

    echo json_encode(array(
        "status" => "error",
        "message" => "Database environment variables are missing."
    ));

    exit;
}


/* =====================================================
   MYSQL SSL CONNECTION
   ===================================================== */

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

    echo json_encode(array(
        "status" => "error",
        "message" =>
            "Database connection failed: " .
            mysqli_connect_error()
    ));

    exit;
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
   CURRENT INDIA DATE/TIME
   ===================================================== */

$current_datetime =
    date("Y-m-d H:i:s");


$current_day =
    date("l");


$current_timestamp =
    strtotime($current_datetime);


/* =====================================================
   FUNCTION: CHECK PERIOD
   ===================================================== */

function isPeriodActive(
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

    if (
        intval($owner_deactivated) == 1
    ) {

        return false;
    }


    /*
    ----------------------------------------------------
    USER PERIOD MUST ALSO BE ACTIVE
    ----------------------------------------------------
    */

    if (
        intval($period_active) != 1
    ) {

        return false;
    }


    /*
    ----------------------------------------------------
    START AND END TIME MUST EXIST
    ----------------------------------------------------
    */

    if (
        empty($start)
        ||
        empty($end)
    ) {

        return false;
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

        return true;
    }


    return false;
}


/* =====================================================
   GET TODAY'S SCHEDULE
   ===================================================== */

$current_day_sql =
    mysqli_real_escape_string(
        $conn,
        $current_day
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

WHERE day_week =
'$current_day_sql'

AND controller_id =
'$controller_sql'

ORDER BY id

LIMIT 1

";


$result =
    mysqli_query(
        $conn,
        $sql
    );


if (!$result) {

    echo json_encode(array(
        "status" => "error",
        "message" =>
            "Schedule query failed: " .
            mysqli_error($conn)
    ));

    mysqli_close($conn);

    exit;
}


/* =====================================================
   NO SCHEDULE FOUND
   ===================================================== */

if (
    mysqli_num_rows($result) == 0
) {

    echo json_encode(array(

        "status" =>
            "success",

        "current_datetime" =>
            $current_datetime,

        "current_day" =>
            $current_day,

        "controller_id" =>
            $controller_id,

        "periods" =>
            array(),

        "active_periods" =>
            array(),

        "active_pins" =>
            ""

    ));

    mysqli_close($conn);

    exit;
}


$row =
    mysqli_fetch_assoc($result);


/* =====================================================
   ARRAYS
   ===================================================== */

$periods =
    array();


$active_periods =
    array();


$active_pins =
    array();


/* =====================================================
   PROCESS PERIOD 1, 2, 3
   ===================================================== */

for ($p = 1; $p <= 3; $p++) {

    $start =
        $row["start_time_" . $p];


    $end =
        $row["end_time_" . $p];


    $pins_string =
        trim(
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


    /* ---------------------------------------------
       PINS ARRAY
       --------------------------------------------- */

    $pins =
        array();


    if (
        $pins_string != ""
    ) {

        $temp_pins =
            explode(
                ",",
                $pins_string
            );


        foreach (
            $temp_pins as $pin
        ) {

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
    }


    /* ---------------------------------------------
       DETERMINE STATUS
       --------------------------------------------- */

    /*
    OWNER DEACTIVATED
    has highest priority.
    */

    if (
        $owner_deactivated == 1
    ) {

        $status =
            "OWNER DEACTIVATED";

        $is_active =
            false;

    }

    /*
    USER PERIOD DEACTIVATED
    */

    elseif (
        $period_active != 1
    ) {

        $status =
            "DEACTIVATED";

        $is_active =
            false;

    }

    /*
    PERIOD ACTIVE BY DATE/TIME
    */

    elseif (
        isPeriodActive(
            $owner_deactivated,
            $period_active,
            $start,
            $end,
            $current_timestamp
        )
    ) {

        $status =
            "ACTIVE";

        $is_active =
            true;

    }

    /*
    PERIOD OUTSIDE DATE/TIME
    */

    else {

        $status =
            "INACTIVE";

        $is_active =
            false;
    }


    /* ---------------------------------------------
       PERIOD JSON DATA
       --------------------------------------------- */

    $periods[] = array(

        "period" =>
            $p,

        "start_time" =>
            $start,

        "end_time" =>
            $end,

        "pins_output" =>
            $pins_string,

        "period_active" =>
            $period_active,

        "owner_deactivated" =>
            $owner_deactivated,

        "status" =>
            $status
    );


    /* ---------------------------------------------
       CURRENTLY ACTIVE PERIOD
       --------------------------------------------- */

    if (
        $is_active
    ) {

        $active_periods[] =
            $p;


        /*
        Add this period's pins
        to active_pins.
        */

        foreach (
            $pins as $pin
        ) {

            if (
                !in_array(
                    $pin,
                    $active_pins
                )
            ) {

                $active_pins[] =
                    $pin;
            }
        }
    }
}


/* =====================================================
   ACTIVE PIN STRING
   ===================================================== */

$active_pins_string =
    implode(
        ",",
        $active_pins
    );


/* =====================================================
   SEND JSON TO ESP8266
   ===================================================== */

$response = array(

    "status" =>
        "success",

    "current_datetime" =>
        $current_datetime,

    "current_day" =>
        $current_day,

    "controller_id" =>
        $controller_id,

    "periods" =>
        $periods,

    "active_periods" =>
        $active_periods,

    "active_pins" =>
        $active_pins_string
);


echo json_encode(
    $response,
    JSON_PRETTY_PRINT
);


mysqli_close($conn);

?>
