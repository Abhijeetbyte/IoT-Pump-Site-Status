/************************** CT Current Sensor Config *****************/
#include <EmonLib.h>

#define CT_SENSOR_PIN 34  // CT sensor input pin
#define ADC_MAX 4095.0    // 12-bit ADC max value
#define REF_VOLTAGE 3.3   // ESP32 ADC reference voltage
#define CALIBRATION 7.0   // Adjust this based on testing
#define THRESHOLD 2.0     // Current threshold (2 amps)

EnergyMonitor emon1;

/************************** RTC Config *****************/
#include <DS1307.h>

uint8_t sec, minute, hour, day, month;
uint16_t year;
DS1307 rtc;

/************************** SIM 900L Config *****************/
#include <HardwareSerial.h>

#define RXD2 16        // SIM800L RX Pin
#define TXD2 17        // SIM800L TX Pin
#define SENSOR_PIN 34  // Sensor input pin

HardwareSerial sim800(1);
bool gprsActive = false;

// Helper Functions
String readRTC();
double readCurrent();
void sendATCommand(String command, int timeout = 2000);
void activateGPRS();
void deactivateGPRS();
void sendPing(double currentValue, String timestamp);



void setup() {
    Serial.begin(115200);
    sim800.begin(9600, SERIAL_8N1, RXD2, TXD2);
    rtc.begin();
    rtc.start();
    emon1.current(CT_SENSOR_PIN, CALIBRATION);
    Serial.println("Initializing device.....");
    pinMode(SENSOR_PIN, INPUT);
    delay(3000);
}



void loop() {
    double currentValue = readCurrent();
    String timestamp = readRTC();

    Serial.printf("Current: %.3f A, Timestamp: %s\n", currentValue, timestamp.c_str());
    
    if (currentValue > THRESHOLD) {
        if (!gprsActive) {
            activateGPRS();
            gprsActive = true;
        }

        sendPing(currentValue, timestamp); //Call HTTP ping function, send URL parameters

    } else {
        if (gprsActive) {
            deactivateGPRS();
            gprsActive = false;
        }
    }
    delay(5000); // delay 5 seconds, in between pings
}






String readRTC() {
    rtc.get(&sec, &minute, &hour, &day, &month, &year);
    char timestamp[20];
    sprintf(timestamp, "%04d-%02d-%02d %02d:%02d:%02d", year, month, day, hour, minute, sec);
    return String(timestamp);
}



double readCurrent() {
    int rawADC = analogRead(CT_SENSOR_PIN);
    float adcVoltage = (rawADC / ADC_MAX) * REF_VOLTAGE;
    double Irms = emon1.calcIrms(1480);
    Serial.printf("Raw ADC: %d | ADC Voltage: %.3fV | Current: %.3fA\n", rawADC, adcVoltage, Irms);
    //Irms = 4.5; // Debug value
    return Irms;
}

void activateGPRS() {
    Serial.println("Initializing GPRS...");
    sendATCommand("AT+CPIN?");
    sendATCommand("AT+CREG?");
    sendATCommand("AT+CGATT?");
    sendATCommand("AT+COPS?");
    sendATCommand("AT+CSQ");
    sendATCommand("AT+SAPBR=0,1", 3000);
    sendATCommand("AT+SAPBR=0,1", 3000);
    sendATCommand("AT+SAPBR=3,1,\"Contype\",\"GPRS\"");
    sendATCommand("AT+SAPBR=3,1,\"APN\",\"airtelgprs.com\"");   // APN here ( current SIMcard : Airtel India)
    sendATCommand("AT+SAPBR=1,1", 5000);
    sendATCommand("AT+SAPBR=2,1");
    Serial.println("GPRS Activated");
}



void sendPing(double currentValue, String timestamp) {
    String url = "http://iot.navmarg.in/sitepump/api.php?current=" + String(currentValue, 3) +
                 "&timestamp=" + timestamp +
                 "&timezone=Asia/Kolkata"
                 "&deviceId=01xd02m25";   // hardware, device id
    Serial.println("Sending Data: " + url);
    sendATCommand("AT+HTTPTERM", 2000);
    sendATCommand("AT+HTTPINIT");
    sendATCommand("AT+HTTPPARA=\"CID\",1");
    sendATCommand("AT+HTTPPARA=\"URL\",\"" + url + "\"");
    sendATCommand("AT+HTTPACTION=0", 5000);
    sendATCommand("AT+HTTPREAD", 5000);
    sendATCommand("AT+HTTPTERM");
}


void deactivateGPRS() {
    Serial.println("Deactivating GPRS...");
    sendATCommand("AT+SAPBR=0,1");
    Serial.println("GPRS Disconnected");
}



void sendATCommand(String command, int timeout) {
    Serial.println("Sending: " + command);
    sim800.println(command);
    delay(timeout);
    while (sim800.available()) {
        String response = sim800.readString();
        Serial.println("Response: " + response);
        if (response.indexOf("ERROR") != -1) {
            Serial.println("ERROR DETECTED: " + command);
        }
    }
}
