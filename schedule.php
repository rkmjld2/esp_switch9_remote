<?php

/* =========================================================
   ESP-SWITCH9 REMOTE
   schedule.php

   DATABASE:
       esp_switch7

   TABLE:
       weekly_schedule

   IMPORTANT:

       This is the same working schedule program.

       Only the database connection has been changed
       for Render + TiDB Cloud.

   DATETIME ORDER:

       Period 1 Start < Period 1 End
       Period 1 End   <= Period 2 Start
       Period 2 Start < Period 2 End
       Period 2 End   <= Period 3 Start
       Period 3 Start < Period 3 End

   PERIOD ACTIVE:

       period_active_1 = 1 -> Period 1 Active
       period_active_1 = 0 -> Period 1 Deactivated

       period_active_2 = 1 -> Period 2 Active
       period_active_2 = 0 -> Period 2 Deactivated

       period_active_3 = 1 -> Period 3 Active
       period_active_3 = 0 -> Period 3 Deactivated

   ========================================================= */


/* =========================================================
   DATABASE CONNECTION
   Render + TiDB Cloud
   ========================================================= */

$host =
    getenv("DB_HOST");

$user =
    getenv("DB_USER");

$password =
    getenv("DB_PASSWORD");

$database =
    getenv("DB_NAME");

$port =
    intval(
        getenv("DB_PORT")
    );


if (
    empty($host) ||
    empty($user) ||
    empty($database) ||
    $port <= 0
) {

    die(
        "Database environment variables are not configured."
    );

}


/* =========================================================
   CONNECT TO TiDB CLOUD
   ========================================================= */

$conn =
    mysqli_init();


/*
   TiDB Cloud requires SSL.
*/

mysqli_ssl_set(
    $conn,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL
);


if (
    !mysqli_real_connect(
        $conn,
        $host,
        $user,
        $password,
        $database,
        $port,
        NULL,
        MYSQLI_CLIENT_SSL
    )
) {

    die(
        "Database connection failed: "
        . mysqli_connect_error()
    );

}


/* =========================================================
   CHARACTER SET
   ========================================================= */

mysqli_set_charset(
    $conn,
    "utf8mb4"
);


/* =========================================================
   INDIA TIME
   ========================================================= */

date_default_timezone_set(
    "Asia/Kolkata"
);


/* =========================================================
   CONTROLLER
   ========================================================= */

$controller_id =
    isset($_POST["controller_id"])
    ? trim($_POST["controller_id"])
    : (
        isset($_GET["controller_id"])
        ? trim($_GET["controller_id"])
        : "ESP0001"
    );


if (
    $controller_id === ""
) {

    $controller_id =
        "ESP0001";

}


$controller_sql =
    mysqli_real_escape_string(
        $conn,
        $controller_id
    );


/* =========================================================
   FUNCTION
   COMBINE DATE + TIME
   ========================================================= */

function make_datetime(
    $date,
    $time
) {

    if (
        empty($date) ||
        empty($time)
    ) {

        return NULL;

    }

    return $date . " " . $time . ":00";

}


/* =========================================================
   FUNCTION
   CONVERT DATETIME FOR SQL
   ========================================================= */

function sql_datetime(
    $conn,
    $datetime
) {

    if (
        $datetime === NULL
    ) {

        return "NULL";

    }

    return
        "'"
        .
        mysqli_real_escape_string(
            $conn,
            $datetime
        )
        .
        "'";

}


/* =========================================================
   SAVE SCHEDULE
   ========================================================= */

if (
    isset($_POST["save_schedule"])
) {


    /* =====================================================
       ID
       ===================================================== */

    $id =
        intval(
            $_POST["id"]
        );


    /* =====================================================
       PERIOD 1
       ===================================================== */

    $start_date_1 =
        isset($_POST["start_date_1"])
        ? $_POST["start_date_1"]
        : "";

    $start_time_1 =
        isset($_POST["start_time_1"])
        ? $_POST["start_time_1"]
        : "";

    $end_date_1 =
        isset($_POST["end_date_1"])
        ? $_POST["end_date_1"]
        : "";

    $end_time_1 =
        isset($_POST["end_time_1"])
        ? $_POST["end_time_1"]
        : "";


    $start_datetime_1 =
        make_datetime(
            $start_date_1,
            $start_time_1
        );


    $end_datetime_1 =
        make_datetime(
            $end_date_1,
            $end_time_1
        );


    /* =====================================================
       PERIOD 1 PINS
       ===================================================== */

    if (
        isset($_POST["pins_output_1"]) &&
        is_array(
            $_POST["pins_output_1"]
        )
    ) {

        $pins_output_1 =
            implode(
                ",",
                $_POST["pins_output_1"]
            );

    } else {

        $pins_output_1 = "";

    }


    /* =====================================================
       PERIOD 1 ACTIVE
       ===================================================== */

    if (
        isset(
            $_POST["period_active_1"]
        )
    ) {

        $period_active_1 =
            intval(
                $_POST["period_active_1"]
            );

    } else {

        $period_active_1 = 0;

    }


    /* =====================================================
       PERIOD 2
       ===================================================== */

    $start_date_2 =
        isset($_POST["start_date_2"])
        ? $_POST["start_date_2"]
        : "";

    $start_time_2 =
        isset($_POST["start_time_2"])
        ? $_POST["start_time_2"]
        : "";

    $end_date_2 =
        isset($_POST["end_date_2"])
        ? $_POST["end_date_2"]
        : "";

    $end_time_2 =
        isset($_POST["end_time_2"])
        ? $_POST["end_time_2"]
        : "";


    $start_datetime_2 =
        make_datetime(
            $start_date_2,
            $start_time_2
        );


    $end_datetime_2 =
        make_datetime(
            $end_date_2,
            $end_time_2
        );


    /* =====================================================
       PERIOD 2 PINS
       ===================================================== */

    if (
        isset($_POST["pins_output_2"]) &&
        is_array(
            $_POST["pins_output_2"]
        )
    ) {

        $pins_output_2 =
            implode(
                ",",
                $_POST["pins_output_2"]
            );

    } else {

        $pins_output_2 = "";

    }


    /* =====================================================
       PERIOD 2 ACTIVE
       ===================================================== */

    if (
        isset(
            $_POST["period_active_2"]
        )
    ) {

        $period_active_2 =
            intval(
                $_POST["period_active_2"]
            );

    } else {

        $period_active_2 = 0;

    }


    /* =====================================================
       PERIOD 3
       ===================================================== */

    $start_date_3 =
        isset($_POST["start_date_3"])
        ? $_POST["start_date_3"]
        : "";

    $start_time_3 =
        isset($_POST["start_time_3"])
        ? $_POST["start_time_3"]
        : "";

    $end_date_3 =
        isset($_POST["end_date_3"])
        ? $_POST["end_date_3"]
        : "";

    $end_time_3 =
        isset($_POST["end_time_3"])
        ? $_POST["end_time_3"]
        : "";


    $start_datetime_3 =
        make_datetime(
            $start_date_3,
            $start_time_3
        );


    $end_datetime_3 =
        make_datetime(
            $end_date_3,
            $end_time_3
        );


    /* =====================================================
       PERIOD 3 PINS
       ===================================================== */

    if (
        isset($_POST["pins_output_3"]) &&
        is_array(
            $_POST["pins_output_3"]
        )
    ) {

        $pins_output_3 =
            implode(
                ",",
                $_POST["pins_output_3"]
            );

    } else {

        $pins_output_3 = "";

    }


    /* =====================================================
       PERIOD 3 ACTIVE
       ===================================================== */

    if (
        isset(
            $_POST["period_active_3"]
        )
    ) {

        $period_active_3 =
            intval(
                $_POST["period_active_3"]
            );

    } else {

        $period_active_3 = 0;

    }


    /* =====================================================
       DATETIME VALIDATION
       ===================================================== */

    $error = "";


    /* -----------------------------------------------------
       PERIOD 1
       ----------------------------------------------------- */

    if (
        $start_datetime_1 !== NULL &&
        $end_datetime_1 !== NULL
    ) {

        if (
            strtotime(
                $start_datetime_1
            )
            >=
            strtotime(
                $end_datetime_1
            )
        ) {

            $error =
                "Period 1 error: "
                .
                "Start Date/Time must be earlier than "
                .
                "End Date/Time.";

        }

    }


    /* -----------------------------------------------------
       PERIOD 2
       ----------------------------------------------------- */

    if (
        $error == "" &&
        $start_datetime_2 !== NULL &&
        $end_datetime_2 !== NULL
    ) {

        if (
            strtotime(
                $start_datetime_2
            )
            >=
            strtotime(
                $end_datetime_2
            )
        ) {

            $error =
                "Period 2 error: "
                .
                "Start Date/Time must be earlier than "
                .
                "End Date/Time.";

        }

    }


    /* -----------------------------------------------------
       PERIOD 3
       ----------------------------------------------------- */

    if (
        $error == "" &&
        $start_datetime_3 !== NULL &&
        $end_datetime_3 !== NULL
    ) {

        if (
            strtotime(
                $start_datetime_3
            )
            >=
            strtotime(
                $end_datetime_3
            )
        ) {

            $error =
                "Period 3 error: "
                .
                "Start Date/Time must be earlier than "
                .
                "End Date/Time.";

        }

    }


    /* -----------------------------------------------------
       PERIOD 1 BEFORE PERIOD 2
       ----------------------------------------------------- */

    if (
        $error == "" &&
        $end_datetime_1 !== NULL &&
        $start_datetime_2 !== NULL
    ) {

        if (
            strtotime(
                $end_datetime_1
            )
            >
            strtotime(
                $start_datetime_2
            )
        ) {

            $error =
                "Schedule order error: "
                .
                "Period 1 must finish before "
                .
                "Period 2 starts.";

        }

    }


    /* -----------------------------------------------------
       PERIOD 2 BEFORE PERIOD 3
       ----------------------------------------------------- */

    if (
        $error == "" &&
        $end_datetime_2 !== NULL &&
        $start_datetime_3 !== NULL
    ) {

        if (
            strtotime(
                $end_datetime_2
            )
            >
            strtotime(
                $start_datetime_3
            )
        ) {

            $error =
                "Schedule order error: "
                .
                "Period 2 must finish before "
                .
                "Period 3 starts.";

        }

    }


    /* =====================================================
       SAVE ONLY IF THERE IS NO ERROR
       ===================================================== */

    if (
        $error == ""
    ) {


        $sql_start_1 =
            sql_datetime(
                $conn,
                $start_datetime_1
            );

        $sql_end_1 =
            sql_datetime(
                $conn,
                $end_datetime_1
            );


        $sql_start_2 =
            sql_datetime(
                $conn,
                $start_datetime_2
            );

        $sql_end_2 =
            sql_datetime(
                $conn,
                $end_datetime_2
            );


        $sql_start_3 =
            sql_datetime(
                $conn,
                $start_datetime_3
            );

        $sql_end_3 =
            sql_datetime(
                $conn,
                $end_datetime_3
            );


        /* =================================================
           UPDATE DATABASE
           ================================================= */

        $sql = "

            UPDATE weekly_schedule

            SET

                start_time_1 =
                    $sql_start_1,

                end_time_1 =
                    $sql_end_1,

                pins_output_1 = '"
                    .
                    mysqli_real_escape_string(
                        $conn,
                        $pins_output_1
                    )
                    .
                    "',

                period_active_1 =
                    $period_active_1,


                start_time_2 =
                    $sql_start_2,

                end_time_2 =
                    $sql_end_2,

                pins_output_2 = '"
                    .
                    mysqli_real_escape_string(
                        $conn,
                        $pins_output_2
                    )
                    .
                    "',

                period_active_2 =
                    $period_active_2,


                start_time_3 =
                    $sql_start_3,

                end_time_3 =
                    $sql_end_3,

                pins_output_3 = '"
                    .
                    mysqli_real_escape_string(
                        $conn,
                        $pins_output_3
                    )
                    .
                    "',

                period_active_3 =
                    $period_active_3

            WHERE id =
                $id

              AND controller_id =
                '$controller_sql'

        ";


        $save_result =
            mysqli_query(
                $conn,
                $sql
            );


        if (
            !$save_result
        ) {

            die(
                "Schedule update failed:<br><br>"
                .
                mysqli_error(
                    $conn
                )
                .
                "<br><br>"
                .
                "SQL:<br>"
                .
                htmlspecialchars(
                    $sql
                )
            );

        }


        $message =
            "Schedule saved successfully.";

    }

}


/* =========================================================
   READ ALL DAYS
   ========================================================= */

$sql = "

    SELECT *

    FROM weekly_schedule

    WHERE controller_id =
        '$controller_sql'

    ORDER BY id

";


$result =
    mysqli_query(
        $conn,
        $sql
    );


if (
    !$result
) {

    die(
        "Unable to read weekly_schedule:<br>"
        .
        mysqli_error(
            $conn
        )
    );

}

?>

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<title>
ESP-SWITCH9 Remote - Weekly Schedule
</title>

<style>

body {

    font-family:
        Arial,
        sans-serif;

    background:
        #f2f2f2;

    margin:
        20px;

}

h1 {

    text-align:
        center;

    color:
        #333;

}

.success {

    background:
        #dff0d8;

    border:
        1px solid #82b366;

    color:
        #256b25;

    padding:
        12px;

    margin-bottom:
        20px;

    text-align:
        center;

    font-size:
        18px;

    font-weight:
        bold;

}

.error {

    background:
        #ffdede;

    border:
        2px solid #cc0000;

    color:
        #990000;

    padding:
        15px;

    margin-bottom:
        20px;

    text-align:
        center;

    font-size:
        18px;

    font-weight:
        bold;

    border-radius:
        5px;

}

.help {

    background:
        #fff8dc;

    border:
        1px solid #e0c060;

    padding:
        15px;

    margin-bottom:
        20px;

    font-size:
        16px;

    line-height:
        1.6;

}

.day-box {

    background:
        white;

    border:
        1px solid #ccc;

    margin-bottom:
        25px;

    padding:
        20px;

    border-radius:
        8px;

}

.day-title {

    font-size:
        26px;

    font-weight:
        bold;

    margin-bottom:
        20px;

    color:
        #0055aa;

}

.period {

    border:
        1px solid #ddd;

    padding:
        18px;

    margin-bottom:
        15px;

    background:
        #fafafa;

    border-radius:
        5px;

}

.period-title {

    font-size:
        20px;

    font-weight:
        bold;

    margin-bottom:
        15px;

    color:
        #444;

}

.datetime-row {

    margin-bottom:
        12px;

}

.datetime-row label {

    display:
        inline-block;

    width:
        100px;

    font-weight:
        bold;

}

input[type="date"] {

    padding:
        7px;

    width:
        155px;

    font-size:
        16px;

}

input[type="time"] {

    padding:
        7px;

    width:
        110px;

    font-size:
        16px;

}

.pin-area {

    margin-top:
        15px;

}

.pin {

    display:
        inline-block;

    margin-right:
        18px;

    margin-bottom:
        10px;

    font-size:
        17px;

}

.pin input {

    width:
        18px;

    height:
        18px;

    vertical-align:
        middle;

}

.active-area {

    margin-top:
        15px;

    padding:
        10px;

    background:
        #eeeeee;

    border-radius:
        5px;

}

.active-area label {

    font-weight:
        bold;

    font-size:
        17px;

}

.active-area input {

    width:
        18px;

    height:
        18px;

    vertical-align:
        middle;

}

.save-button {

    margin-top:
        10px;

    padding:
        12px 30px;

    background:
        green;

    color:
        white;

    border:
        none;

    border-radius:
        5px;

    font-size:
        17px;

    cursor:
        pointer;

}

.save-button:hover {

    background:
        darkgreen;

}

</style>

</head>

<body>


<h1>
ESP-SWITCH9 REMOTE
<br>
Weekly Schedule
</h1>


<?php

if (
    isset($message)
    &&
    $message != ""
) {

    echo
        "<div class='success'>"
        .
        htmlspecialchars(
            $message
        )
        .
        "</div>";

}


if (
    $error != ""
) {

    echo
        "<div class='error'>"
        .
        htmlspecialchars(
            $error
        )
        .
        "</div>";

}

?>


<div class="help">

<b>
Controller:
</b>

<?php

echo
    htmlspecialchars(
        $controller_id
    );

?>

<br><br>

<b>
Important:
</b>

If a period is unchecked,
that period is saved as
<b>DEACTIVATED</b>.

Its saved date/time and
D1-D8 selections remain in
the database.

The ESP API will not activate
the pins of a deactivated period.

</div>


<?php

while (
    $row =
        mysqli_fetch_assoc(
            $result
        )
) {

?>


<div class="day-box">


<div class="day-title">

<?php

echo
    htmlspecialchars(
        $row["day_week"]
    );

?>

</div>


<form
    method="POST"
    action=""
>


<input
    type="hidden"
    name="save_schedule"
    value="1"
>


<input
    type="hidden"
    name="id"
    value="<?php
        echo
            intval(
                $row["id"]
            );
    ?>"
>


<input
    type="hidden"
    name="controller_id"
    value="<?php
        echo
            htmlspecialchars(
                $controller_id
            );
    ?>"
>


<?php

for (
    $p = 1;
    $p <= 3;
    $p++
) {

    $start_value =
        $row[
            "start_time_" . $p
        ];

    $end_value =
        $row[
            "end_time_" . $p
        ];

    $pins_value =
        $row[
            "pins_output_" . $p
        ];

    $active_value =
        intval(
            $row[
                "period_active_" . $p
            ]
        );


    $start_date_value = "";

    $start_time_value = "";

    $end_date_value = "";

    $end_time_value = "";


    if (
        !empty(
            $start_value
        )
        &&
        $start_value !=
            "0000-00-00 00:00:00"
    ) {

        $start_timestamp =
            strtotime(
                $start_value
            );

        if (
            $start_timestamp !== false
        ) {

            $start_date_value =
                date(
                    "Y-m-d",
                    $start_timestamp
                );

            $start_time_value =
                date(
                    "H:i",
                    $start_timestamp
                );

        }

    }


    if (
        !empty(
            $end_value
        )
        &&
        $end_value !=
            "0000-00-00 00:00:00"
    ) {

        $end_timestamp =
            strtotime(
                $end_value
            );

        if (
            $end_timestamp !== false
        ) {

            $end_date_value =
                date(
                    "Y-m-d",
                    $end_timestamp
                );

            $end_time_value =
                date(
                    "H:i",
                    $end_timestamp
                );

        }

    }


    $pins_array = [];

    if (
        !empty(
            $pins_value
        )
    ) {

        $pins_array =
            explode(
                ",",
                $pins_value
            );

    }

?>


<div class="period">


<div class="period-title">

Period
<?php
echo $p;
?>

</div>


<div class="datetime-row">

<label>
Start:
</label>

<input
    type="date"
    name="start_date_<?php
        echo $p;
    ?>"
    value="<?php
        echo htmlspecialchars(
            $start_date_value
        );
    ?>"
>


<input
    type="time"
    name="start_time_<?php
        echo $p;
    ?>"
    value="<?php
        echo htmlspecialchars(
            $start_time_value
        );
    ?>"
>

</div>


<div class="datetime-row">

<label>
End:
</label>

<input
    type="date"
    name="end_date_<?php
        echo $p;
    ?>"
    value="<?php
        echo htmlspecialchars(
            $end_date_value
        );
    ?>"
>


<input
    type="time"
    name="end_time_<?php
        echo $p;
    ?>"
    value="<?php
        echo htmlspecialchars(
            $end_time_value
        );
    ?>"
>

</div>


<div class="pin-area">

<b>
Output Pins:
</b>

<br><br>


<?php

for (
    $pin = 1;
    $pin <= 8;
    $pin++
) {

    $checked =
        in_array(
            "D" . $pin,
            $pins_array
        )
        ? "checked"
        : "";

?>


<span class="pin">

<label>

<input
    type="checkbox"
    name="pins_output_<?php
        echo $p;
    ?>[]"
    value="D<?php
        echo $pin;
    ?>"
    <?php
        echo $checked;
    ?>
>

D<?php
echo $pin;
?>

</label>

</span>


<?php

}

?>

</div>


<div class="active-area">

<label>

<input
    type="checkbox"
    name="period_active_<?php
        echo $p;
    ?>"
    value="1"
    <?php
        echo
            (
                $active_value == 1
                ? "checked"
                : ""
            );
    ?>
>

Period
<?php
echo $p;
?>
Active

</label>

</div>


</div>


<?php

}

?>


<button
    type="submit"
    class="save-button"
>
Save Schedule
</button>


</form>


</div>


<?php

}

?>


</body>

</html>

