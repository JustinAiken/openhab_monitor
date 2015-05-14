#!/usr/bin/php

<?php

$INI_LOCATION='/etc/openhab_monitor.ini';

function parse_ini_advanced($array) {
    $returnArray = array();
    if (is_array($array)) {
        foreach ($array as $key => $value) {
            $e = explode(':', $key);
            if (!empty($e[1])) {
                $x = array();
                foreach ($e as $tk => $tv) {
                    $x[$tk] = trim($tv);
                }
                $x = array_reverse($x, true);
                foreach ($x as $k => $v) {
                    $c = $x[0];
                    if (empty($returnArray[$c])) {
                        $returnArray[$c] = array();
                    }
                    if (isset($returnArray[$x[1]])) {
                        $returnArray[$c] = array_merge($returnArray[$c], $returnArray[$x[1]]);
                    }
                    if ($k === 0) {
                        $returnArray[$c] = array_merge($returnArray[$c], $array[$key]);
                    }
                }
            } else {
                $returnArray[$key] = $array[$key];
            }
        }
    }
    return $returnArray;
}

function recursive_parse($array)
{
    $returnArray = array();
    if (is_array($array)) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = recursive_parse($value);
            }
            $x = explode('.', $key);
            if (!empty($x[1])) {
                $x = array_reverse($x, true);
                if (isset($returnArray[$key])) {
                    unset($returnArray[$key]);
                }
                if (!isset($returnArray[$x[0]])) {
                    $returnArray[$x[0]] = array();
                }
                $first = true;
                foreach ($x as $k => $v) {
                    if ($first === true) {
                        $b = $array[$key];
                        $first = false;
                    }
                    $b = array($v => $b);
                }
                $returnArray[$x[0]] = array_merge_recursive($returnArray[$x[0]], $b[$x[0]]);
            } else {
                $returnArray[$key] = $array[$key];
            }
        }
    }
    return $returnArray;
}

$array = parse_ini_file($INI_LOCATION, true);
$INI_ARRAY = recursive_parse(parse_ini_advanced($array));

$DEBUG                   = $INI_ARRAY['defaults']['DEBUG'];
$OPENHAB_ROOT            = $INI_ARRAY['defaults']['OPENHAB_ROOT'];
$APRON_DATABASE_LOCATION = $INI_ARRAY['defaults']['APRON_DATABASE_LOCATION'];
$SLEEP_INTERVAL          = $INI_ARRAY['defaults']['SLEEP_INTERVAL'];
$FORCE_INTERVAL          = $INI_ARRAY['defaults']['FORCE_INTERVAL'];

$ZIGBEE_DEVICES  = array();
$ZWAVE_DEVICES   = array();
$zigbee_attr_ids = array();
$zwave_attr_ids  = array();

foreach($INI_ARRAY['zigbee'] as $device_id => $zigbee_device) {
  $ZIGBEE_DEVICES[$device_id] = array();
  foreach ($zigbee_device as $attribute_id => $attributes) {
    array_push($zigbee_attr_ids, $attribute_id);
    $ZIGBEE_DEVICES[$device_id][$attribute_id]                = array();
    $ZIGBEE_DEVICES[$device_id][$attribute_id]['name']        = $attributes['name'];
    $ZIGBEE_DEVICES[$device_id][$attribute_id]['convert']     = isset($attributes['convert']) ? $attributes['convert'] : '';
    $ZIGBEE_DEVICES[$device_id][$attribute_id]['last_status'] = '';
  }
}

foreach($INI_ARRAY['zwave'] as $device_id => $zwave_device) {
  $ZWAVE_DEVICES[$device_id] = array();
  foreach ($zwave_device as $attribute_id => $attributes) {
    array_push($zwave_attr_ids, $attribute_id);
    $ZWAVE_DEVICES[$device_id][$attribute_id]                = array();
    $ZWAVE_DEVICES[$device_id][$attribute_id]['name']        = $attributes['name'];
    $ZWAVE_DEVICES[$device_id][$attribute_id]['convert']     = isset($attributes['convert']) ? $attributes['convert'] : '';
    $ZWAVE_DEVICES[$device_id][$attribute_id]['last_status'] = '';
  }
}

$ZIGBEE_ATTRS = "(" . implode(',', array_unique($zigbee_attr_ids)) . ")";
$ZWAVE_ATTRS  = "(" . implode(',', array_unique($zwave_attr_ids )) . ")";

$DEVICES = array(
  'zigbee' => $ZIGBEE_DEVICES,
  'zwave'  => $ZWAVE_DEVICES
);

function query_db($device_type) {
  global $APRON_DATABASE_LOCATION, $DEBUG, $ZIGBEE_ATTRS, $ZWAVE_ATTRS;

  if($device_type == 'zigbee'){
    $cmd = 'sqlite3 ' . $APRON_DATABASE_LOCATION . ' "SELECT masterId, attributeId, value_get FROM zigbeeDeviceState LEFT JOIN zigbeeDevice ON zigbeeDeviceState.globalID = zigbeeDevice.globalID WHERE attributeId IN ' . $ZIGBEE_ATTRS . ';"';
  } elseif ($device_type == 'zwave') {
    $cmd = 'sqlite3 ' . $APRON_DATABASE_LOCATION . ' "SELECT masterId, attributeId, value_get FROM zwaveDeviceState LEFT JOIN zwaveDevice ON zwaveDeviceState.nodeId = zwaveDevice.nodeId WHERE attributeId IN ' . $ZWAVE_ATTRS . ';"';
  };

  unset($out);
  if($DEBUG) echo "$cmd\n";
  exec($cmd,$out,$retval);

  if($retval == 0) {
    return $out;
  } else {
    echo "SQLITE FAIL!\n";
    echo $out;
  }
}

function curl_openhab($path, $state) {
  global $OPENHAB_ROOT, $DEBUG;

  $cmd = 'curl --silent --show-error --header "Content-Type: text/plain" --request PUT --data "' . $state . '" ' . $OPENHAB_ROOT . $path . "/state \n";
  if($DEBUG) echo $cmd;
  unset($out);
  exec($cmd,$out,$retval);
}

function query_and_check($device_type, $force = false) {
  global $DEVICES, $DEBUG;

  $out = query_db($device_type);
  foreach($out as $line) {
    list($apron_id, $attribute_id, $val) = explode("|", $line);
    if(array_key_exists($apron_id, $DEVICES[$device_type])) {
      if(array_key_exists($attribute_id, $DEVICES[$device_type][$apron_id])) {

        if($DEVICES[$device_type][$apron_id][$attribute_id]['last_status'] == $val && !$force) {
          # Nothing changed and we're not forcing, move along..,
        } else {
          $DEVICES[$device_type][$apron_id][$attribute_id]['last_status'] = $val;

          # Convert from 0-255 to 0-100...
          if($DEVICES[$device_type][$apron_id][$attribute_id]['convert']){
            $val = intval($val/255 * 100);
          }

          curl_openhab($DEVICES[$device_type][$apron_id][$attribute_id]['name'], $val);
        };
      } else {
        echo "--- skip attribute $attribute_id!\n";
      };
    } else {
      if($DEBUG) echo "Skipping $apron_id because it's not in the $device_type list.\n";
    };
  };
};

if($argc > 1 && $argv[1] == 'now') {
  if(count($DEVICES['zigbee']) > 0) query_and_check('zigbee', TRUE);
  if(count($DEVICES['zwave'])  > 0) query_and_check('zwave',  TRUE);
} else {
  $i = 0;
  while(true) {
    if ($i > $FORCE_INTERVAL) {
      if(count($DEVICES['zigbee']) > 0) query_and_check('zigbee', TRUE);
      if(count($DEVICES['zwave'])  > 0) query_and_check('zwave',  TRUE);
      $i = 0;
    } else {
      if(count($DEVICES['zigbee']) > 0) query_and_check('zigbee');
      if(count($DEVICES['zwave']) > 0) query_and_check('zwave');
      $i = $i + 1;
    }

    sleep($SLEEP_INTERVAL);
  }
}

?>
