/*
   =========================================================
   ESP-SWITCH7
   ESP8266 NodeMCU ESP-12E
   Render + TiDB Cloud
   =========================================================

   Relay logic:

   HIGH = ON
   LOW  = OFF

   D1-D8 are used.

   The server decides which pins are active.

   ESP8266 only receives:

       "active_pins":"D1,D2,D3"

   and switches the corresponding outputs ON.

   =========================================================
*/


#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClientSecureBearSSL.h>
#include <time.h>


/* =========================================================
   WIFI SETTINGS
   ========================================================= */

const char* ssid =
    "Airtel_56";

const char* password =
    "Raviuma5658";


/* =========================================================
   RENDER API
   ========================================================= */

/*
   IMPORTANT:
   Render hostnames normally use hyphens (-), not underscores (_).
   Use the exact URL shown in your Render service.
   */

const char* scheduleURL =
    "https://esp-switch9-remote.onrender.com/schedule_api.php?controller_id=ESP0001";


/* =========================================================
   CONTROLLER
   ========================================================= */

const char* controllerID =
    "ESP0001";


/* =========================================================
   ESP8266 PINS
   ========================================================= */

const uint8_t pins[8] = {

    D1,
    D2,
    D3,
    D4,
    D5,
    D6,
    D7,
    D8

};


/* =========================================================
   POLLING INTERVAL
   ========================================================= */

unsigned long lastPoll = 0;

const unsigned long pollInterval =
    3000;


/* =========================================================
   WIFI FAILURE TIMER
   ========================================================= */

unsigned long wifiFailureStart = 0;

const unsigned long fullWifiFailureTime =
    120000;


/* =========================================================
   FUNCTION PROTOTYPES
   ========================================================= */

void connectWiFi();

void maintainWiFi();

void getSchedule();

void applyActivePins(
    String activePins
);

void printPinStatus();

int pinNumberFromName(
    String pinName
);


/* =========================================================
   SETUP
   ========================================================= */

void setup()
{

    Serial.begin(115200);

    delay(1000);


    Serial.println();
    Serial.println();
    Serial.println(
        "================================"
    );

    Serial.println(
        "ESP-SWITCH7"
    );

    Serial.println(
        "ESP8266 + Render + TiDB"
    );

    Serial.println(
        "================================"
    );


    /* -----------------------------------------------------
       SET ALL OUTPUTS OFF
       HIGH = ON
       LOW = OFF
       ----------------------------------------------------- */

    for (
        int i = 0;
        i < 8;
        i++
    )
    {

        pinMode(
            pins[i],
            OUTPUT
        );

        digitalWrite(
            pins[i],
            LOW
        );

    }


    Serial.println(
        "All outputs OFF"
    );


    /* -----------------------------------------------------
       CONNECT WIFI
       ----------------------------------------------------- */

    connectWiFi();


    /* -----------------------------------------------------
       NTP TIME
       ----------------------------------------------------- */

    /*
       India Standard Time:

       UTC + 5 hours 30 minutes

       19800 seconds
    */

    configTime(
        19800,
        0,
        "pool.ntp.org",
        "time.nist.gov",
        "time.google.com"
    );


    Serial.println(
        "NTP time configured"
    );


    Serial.println(
        "Setup complete"
    );

}


/* =========================================================
   LOOP
   ========================================================= */

void loop()
{

    /*
       VERY IMPORTANT

       WiFi maintenance must be called
       continuously.
    */

    maintainWiFi();


    /*
       Poll server every 3 seconds.
    */

    if (
        millis() - lastPoll >=
        pollInterval
    )
    {

        lastPoll = millis();


        if (
            WiFi.status() ==
            WL_CONNECTED
        )
        {

            getSchedule();

        }

    }

}


/* =========================================================
   CONNECT WIFI
   ========================================================= */

void connectWiFi()
{

    Serial.println();
    Serial.println(
        "Connecting to WiFi..."
    );


    WiFi.mode(
        WIFI_STA
    );


    WiFi.begin(
        ssid,
        password
    );


    unsigned long startTime =
        millis();


    while (
        WiFi.status() !=
        WL_CONNECTED
    )
    {

        delay(500);

        Serial.print(".");


        /*
           Stop waiting after 10 seconds.
        */

        if (
            millis() - startTime >=
            10000
        )
        {

            Serial.println();

            Serial.println(
                "WiFi connection timeout"
            );

            return;

        }

    }


    Serial.println();

    Serial.println(
        "WiFi connected"
    );


    Serial.print(
        "ESP IP: "
    );

    Serial.println(
        WiFi.localIP()
    );


    Serial.print(
        "RSSI: "
    );

    Serial.println(
        WiFi.RSSI()
    );


    wifiFailureStart =
        0;

}


/* =========================================================
   MAINTAIN WIFI
   ========================================================= */

void maintainWiFi()
{

    if (
        WiFi.status() ==
        WL_CONNECTED
    )
    {

        /*
           WiFi is OK.
        */

        wifiFailureStart =
            0;

        return;

    }


    /*
       WiFi is disconnected.
    */

    if (
        wifiFailureStart == 0
    )
    {

        wifiFailureStart =
            millis();

        Serial.println(
            "WiFi disconnected"
        );

    }


    /*
       Try to reconnect.
    */

    static unsigned long
        lastReconnectAttempt = 0;


    if (
        millis() -
        lastReconnectAttempt >=
        5000
    )
    {

        lastReconnectAttempt =
            millis();


        Serial.println(
            "Trying WiFi reconnect..."
        );


        WiFi.disconnect();

        WiFi.begin(
            ssid,
            password
        );

    }


    /*
       If WiFi remains unavailable
       for 120 seconds, restart ESP.
    */

    if (
        millis() -
        wifiFailureStart >=
        fullWifiFailureTime
    )
    {

        Serial.println(
            "WiFi failed for 120 seconds"
        );


        Serial.println(
            "Restarting ESP8266..."
        );


        delay(1000);


        ESP.restart();

    }

}


/* =========================================================
   GET SCHEDULE FROM RENDER
   ========================================================= */

void getSchedule()
{

    Serial.println();
    Serial.println(
        "Requesting schedule..."
    );


    /*
       Secure HTTPS client.

       Render uses HTTPS.
    */

    std::unique_ptr<
        BearSSL::WiFiClientSecure
    > client(
        new BearSSL::WiFiClientSecure
    );


    /*
       Accept Render certificate.

       This is simple for learning/testing.
    */

    client->setInsecure();


    HTTPClient https;


    /*
       Start HTTPS connection.
    */

    if (
        !https.begin(
            *client,
            scheduleURL
        )
    )
    {

        Serial.println(
            "HTTPS begin failed"
        );

        return;

    }


    /*
       Request timeout.
    */

    https.setTimeout(
        10000
    );


    /*
       GET request.
    */

    int httpCode =
        https.GET();


    Serial.print(
        "HTTP code: "
    );

    Serial.println(
        httpCode
    );


    /*
       Check successful response.
    */

    if (
        httpCode == HTTP_CODE_OK
    )
    {

        String response =
            https.getString();


        Serial.println(
            "Schedule API response:"
        );

        Serial.println(
            response
        );


        /*
           Extract active_pins.
        */

        String activePins =
            getActivePins(
                response
            );


        Serial.print(
            "Active pins: "
        );

        Serial.println(
            activePins
        );


        /*
           Apply pins.
        */

        applyActivePins(
            activePins
        );


        /*
           Show final output state.
        */

        Serial.println();

        Serial.println(
            "Final output status:"
        );

        printPinStatus();

    }
    else
    {

        Serial.print(
            "HTTP request failed: "
        );

        Serial.println(
            httpCode
        );

    }


    https.end();

}


/* =========================================================
   EXTRACT active_pins FROM JSON
   ========================================================= */

String getActivePins(
    String json
)
{

    /*
       Robust parser for:

       "active_pins":"D1,D2,D5"

       AND also:

       "active_pins": "D1,D2,D5"

       The server may insert whitespace after
       the colon. The ESP must accept both forms.
    */

    int keyStart =
        json.indexOf("\"active_pins\"");


    if (keyStart < 0)
    {

        Serial.println(
            "ERROR: active_pins field not found"
        );

        return "";
    }


    /*
       Find the colon after active_pins.
    */

    int colon =
        json.indexOf(":", keyStart);


    if (colon < 0)
    {

        Serial.println(
            "ERROR: active_pins colon not found"
        );

        return "";
    }


    /*
       Skip spaces, tabs and line breaks
       after the colon.
    */

    int valueStart =
        colon + 1;


    while (
        valueStart < json.length()
        &&
        (
            json[valueStart] == ' '
            ||
            json[valueStart] == '\t'
            ||
            json[valueStart] == '\r'
            ||
            json[valueStart] == '\n'
        )
    )
    {
        valueStart++;
    }


    /*
       We expect a quotation mark.
    */

    if (
        valueStart >= json.length()
        ||
        json[valueStart] != '"'
    )
    {

        Serial.println(
            "ERROR: active_pins value quote not found"
        );

        return "";
    }


    /*
       Move past opening quote.
    */

    valueStart++;


    /*
       Find closing quote.
    */

    int valueEnd =
        json.indexOf(
            '"',
            valueStart
        );


    if (valueEnd < 0)
    {

        Serial.println(
            "ERROR: active_pins closing quote not found"
        );

        return "";
    }


    String activePins =
        json.substring(
            valueStart,
            valueEnd
        );


    activePins.trim();


    Serial.print(
        "Parsed active_pins: "
    );

    if (activePins.length() == 0)
    {
        Serial.println("NONE");
    }
    else
    {
        Serial.println(activePins);
    }


    return activePins;
}



/* =========================================================
   APPLY ACTIVE PINS
   ========================================================= */

void applyActivePins(
    String activePins
)
{

    /*
       FIRST:
       Turn ALL pins OFF.

       LOW = OFF
    */

    for (
        int i = 0;
        i < 8;
        i++
    )
    {

        digitalWrite(
            pins[i],
            LOW
        );

    }


    /*
       If there are no active pins,
       everything remains OFF.
    */

    if (
        activePins.length() == 0
    )
    {

        Serial.println(
            "No active pins - all OFF"
        );

        return;

    }


    /*
       Example:

       D1,D3,D5
    */


    int startIndex = 0;


    while (
        startIndex <
        activePins.length()
    )
    {

        /*
           Find comma.
        */

        int commaIndex =
            activePins.indexOf(
                ',',
                startIndex
            );


        String pinName;


        if (
            commaIndex < 0
        )
        {

            /*
               Last pin.
            */

            pinName =
                activePins.substring(
                    startIndex
                );

            startIndex =
                activePins.length();

        }
        else
        {

            /*
               Extract one pin.
            */

            pinName =
                activePins.substring(
                    startIndex,
                    commaIndex
                );

            startIndex =
                commaIndex + 1;

        }


        pinName.trim();


        /*
           Convert D1...D8
           into array number.
        */

        int pinIndex =
            pinNumberFromName(
                pinName
            );


        /*
           Valid pin?
        */

        if (
            pinIndex >= 0 &&
            pinIndex < 8
        )
        {

            /*
               HIGH = ON
            */

            digitalWrite(
                pins[pinIndex],
                HIGH
            );


            Serial.print(
                pinName
            );

            Serial.println(
                " = ON"
            );

        }
        else
        {

            Serial.print(
                "Invalid pin: "
            );

            Serial.println(
                pinName
            );

        }

    }

}


/* =========================================================
   CONVERT D1-D8 TO ARRAY INDEX
   ========================================================= */

int pinNumberFromName(
    String pinName
)
{

    if (
        pinName == "D1"
    )
    {
        return 0;
    }


    if (
        pinName == "D2"
    )
    {
        return 1;
    }


    if (
        pinName == "D3"
    )
    {
        return 2;
    }


    if (
        pinName == "D4"
    )
    {
        return 3;
    }


    if (
        pinName == "D5"
    )
    {
        return 4;
    }


    if (
        pinName == "D6"
    )
    {
        return 5;
    }


    if (
        pinName == "D7"
    )
    {
        return 6;
    }


    if (
        pinName == "D8"
    )
    {
        return 7;
    }


    return -1;

}


/* =========================================================
   PRINT FINAL PIN STATUS
   ========================================================= */

void printPinStatus()
{

    for (
        int i = 0;
        i < 8;
        i++
    )
    {

        int state =
            digitalRead(
                pins[i]
            );


        Serial.print(
            "D"
        );

        Serial.print(
            i + 1
        );

        Serial.print(
            " = "
        );


        if (
            state == HIGH
        )
        {

            Serial.println(
                "ON"
            );

        }
        else
        {

            Serial.println(
                "OFF"
            );

        }

    }

}
