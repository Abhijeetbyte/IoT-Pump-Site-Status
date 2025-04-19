
### Sample API URL

```
https://iot.navmarg.in/sitepump/api.php?current=4.500&timestamp=2025-04-20%2014:30:00&timezone=Asia/Kolkata&deviceId=device_id
```
- Spaces in URLs must be URL encoded (%20) — already done here.

### Notes:

- The RTC sends time in **24-hour format**.
   - timestamp=2025-04-19 14:30:00 → Replace with actual timestamp in "Y-m-d H:i:s" format.
- Ensure the **date** and **time** are accurate** before sending, to calculate the correct time difference.
- Always **mention the correct `deviceId`** while making the request.

