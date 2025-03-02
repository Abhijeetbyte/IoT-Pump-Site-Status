#include <HardwareSerial.h>

#define RXD2 16  // SIM800L RX Pin
#define TXD2 17  // SIM800L TX Pin
#define SENSOR_PIN 34  // Sensor input pin
#define THRESHOLD 500  // Sensor threshold

HardwareSerial sim800(1);
bool gprsActive = false;

void sendATCommand(String command, int timeout = 2000);
void activateGPRS();
void deactivateGPRS();
void sendPing(int value);

void setup() {
    Serial.begin(115200);
    sim800.begin(9600, SERIAL_8N1, RXD2, TXD2);
    pinMode(SENSOR_PIN, INPUT);
    delay(3000);
}

void loop() {
    int sensorValue = analogRead(SENSOR_PIN);
    sensorValue = 501 ;  //debug test
    Serial.println("Sensor Value: " + String(sensorValue));
    
    if (sensorValue > THRESHOLD) {
        if (!gprsActive) {
            activateGPRS();
            gprsActive = true;
        }

        sensorValue = random(500,1000); // dynamic test
        sendPing(sensorValue);
    } else {
        if (gprsActive) {
            deactivateGPRS();
            gprsActive = false;
        }
    }
    delay(5000);
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
    sendATCommand("AT+SAPBR=3,1,\"APN\",\"www\"");
    
    sendATCommand("AT+SAPBR=1,1", 5000);
    sendATCommand("AT+SAPBR=2,1");  
    Serial.println("GPRS Activated");
}

void sendPing(int value) {
    String url = "http://iot.navmarg.in/sitedata.php?value=" + String(value);
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
