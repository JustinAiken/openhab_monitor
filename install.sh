#!/bin/bash

scp openhab_monitor.php winkhub:/bin
scp openhab_monitor.ini winkhub:/etc
scp S81openhab_monitor  winkhub:/etc/init.d

echo "Files copied!"
echo "On your winkhub, run '/etc/init.d/S81openhab_monitor start' to get started!"
