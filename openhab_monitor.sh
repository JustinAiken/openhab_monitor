#!/bin/bash

# These values aren't likely to change:
APRON_DATABASE_LOCATION="/database/apron.db"
GE_STATUS_ATTRIBUTE=1
GE_LEVEL_ATTRIBUTE=2
ZWAVE_LEVEL_ATTRIBUTE=7

# These are:
OPENHAB_POLL_INTERVAL=3
OPENHAB_POLL_FORCE=20
OPENHAB_ROOT=""
GE_BULBS=()
ZWAVE_DEVICES=()

# Load the config file
source /etc/openhab_config

function curl_openhab()
{
  local data=$1
  local item_name=$2
  curl --header "Content-Type: text/plain" --request PUT --data "$data" $OPENHAB_ROOT/$item_name/state
}

function watch_zwave_devices()
{
  local force_update=$1

  for device in "${ZWAVE_DEVICES[@]}" ; do
    local id=${device%%:*}
    local item_name=${device##*:}

    last=zwave_last_$id
    val=$(sqlite3 $APRON_DATABASE_LOCATION "SELECT value_get FROM zwaveDeviceState LEFT JOIN zwaveDevice ON zwaveDeviceState.nodeId = zwaveDevice.nodeId WHERE masterId = $id AND attributeId = $ZWAVE_LEVEL_ATTRIBUTE;");

    # Initialize vars to hold state
    if [ -z "${!last}" ]; then
      echo "$item_name initial val: ${!last} -> $val"
      eval "zwave_last_$id=$val"
      curl_openhab $val $item_name
    elif [ "$val" != "${!last}" ]; then
      echo "$item_name Change:  ${!last} -> $val"
      eval "zwave_last_$id=$val"
      curl_openhab $val $item_name
    fi

    # Force an update of the state if it's time:
    if [ -n "$force_update" ]; then
      echo "Forcing update of $item_name"
      curl_openhab $val $item_name
    fi
  done
}

function watch_ge_bulbs()
{
  local force_update=$1

  for bulb in "${GE_BULBS[@]}" ; do

    # Break the string into 3 seperate parts:
    local apron_id=${bulb%%:*}
    local foo=${bulb%:*} # <- There's prolly a way to do this w/o this temp var..
    local status_item=${foo##*:}
    local level_item=${bulb##*:}

    # Read the bulb status and level from the database:
    read bulb_status bulb_level <<<$(sqlite3 $APRON_DATABASE_LOCATION "SELECT value_get FROM zigbeeDeviceState LEFT JOIN zigbeeDevice ON zigbeeDeviceState.globalID = zigbeeDevice.globalID WHERE masterId = $apron_id AND (attributeId = $GE_STATUS_ATTRIBUTE OR attributeId = $GE_LEVEL_ATTRIBUTE);")

    # Get last state+level from cache vars:
    last_status=ge_bulb_status_last_$apron_id
    last_level=ge_bulb_level_last_$apron_id

    # Initialize bulb state cache:
    if [ -z "${!last_status}" ]; then
      echo "$status_item initial val: ${!last_status} -> $bulb_status"
      eval "ge_bulb_status_last_$apron_id=$bulb_status"

      # ..and post off to openhab!
      curl_openhab $bulb_status $status_item
    elif [ "$bulb_status" != "${!last_status}" ]; then
      echo "$status_item change:  ${!last_status} -> $bulb_status"

      # Set new value in cache..
      eval "ge_bulb_status_last_$apron_id=$bulb_status"

      # ..and post off to openhab!
      curl_openhab $bulb_status $status_item
    fi

    # Convert the 0-255 bulb level to a 0-100 percent for openHAB
    lvl_as_per=$(printf "%.0f" "$(awk "BEGIN { print "$bulb_level/255*100" }")")

    # Initialize bulb level cache:
    if [ -z "${!last_level}" ]; then
      echo "$level_item initial val:  ${!last_level} -> $lvl_as_per"
      eval "ge_bulb_level_last_$apron_id=$lvl_as_per"

      # ..and post off to openhab!
      curl_openhab $lvl_as_per $level_item
    elif [ "$lvl_as_per" != "${!last_level}" ]; then
      echo "$level_item change:  ${!last_level} -> $lvl_as_per"

      # Set new value in cache..
      eval "ge_bulb_level_last_$apron_id=$lvl_as_per"

      # ..and post off to openhab!
      curl_openhab $lvl_as_per $level_item
    fi

    # Force an update of both things if it's time:
    if [ -n "$force_update" ]; then
      echo "Forcing update of $state_item and $level_item..."
      curl_openhab $bulb_status $status_item
      curl_openhab $lvl_as_per  $level_item
    fi
  done
}

while true
do
  for (( c=1; c<=$OPENHAB_POLL_FORCE; c++ ))
  do
    watch_ge_bulbs
    watch_zwave_devices
    sleep $OPENHAB_POLL_INTERVAL
  done

  watch_ge_bulbs true
  watch_zwave_devices true
  sleep $OPENHAB_POLL_INTERVAL
done
