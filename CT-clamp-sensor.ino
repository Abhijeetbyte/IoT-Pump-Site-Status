#include <EmonLib.h>

#define CT_SENSOR_PIN 34   // CT sensor input pin
#define ADC_MAX 4095.0     // 12-bit ADC max value
#define REF_VOLTAGE 3.3    // ESP32 ADC reference voltage
#define CALIBRATION 7.0    // Adjust this based on testing

EnergyMonitor emon1;

void setup() {
    Serial.begin(115200);
    emon1.current(CT_SENSOR_PIN, CALIBRATION);
}

void loop() {
    int rawADC = analogRead(CT_SENSOR_PIN);  // Read raw ADC value
    float adcVoltage = (rawADC / ADC_MAX) * REF_VOLTAGE;  // Convert to voltage
    double Irms = emon1.calcIrms(1480);  // Calculate RMS current

    // Print Debug Values
    Serial.printf("Raw ADC: %d | ADC Voltage: %.3fV | Current: %.3fA\n", 
                  rawADC, adcVoltage, Irms);

    delay(1000);  // Update every second
}
