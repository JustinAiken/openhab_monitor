# Winkhub -> OpenHAB Monitor

This is a script that watches the apron database on your rooted winkhub, and updates openHAB when stuff changes.
This way if you trigger a light outside of openHAB, such as through the Wink app or with a Lutron remote, your status in openHAB stays correct.

## Updated!

The initial version of this call would do *n* database calls, one for each device... which would tend to lock the sqlite database.
Now it only makes 2 calls - one for -all- zwaves, one for -all- zigbees. I ported this script to PHP from Bash - I needed associate arrays to accomplish that.

## Features

- Keep device status updated when changed
- Cache status to avoid unnecassary PUTs to your openHAB box
- ...except you can force an update every x intervals to ensure everything stays in sync.
- If you call it with the commandline param 'now' it will do one forced update - you can have OpenHAB do this on boot.

### Supported Devices

In theory any zigbee/zwave device should work.. I've only personally tested it with GE Link bulbs and a schlage lock though.

## Config

- `cp openhab_monitor.ini.sample openhab_monitor.ini`
- Adjust settings like intervals/timing
- Add your devices

## Installation

### Automatic

If you have your wink hub set up with ssh keys and named `winkhub`, you can just run the [install script](install.sh) after you've configured it.

### Manual

1. Copy [openhab_monitor.ini](openhab_monitor.ini) to `/etc`
2. Copy [openhab_monitor.php](openhab_monitor.php) to `/bin`
3. Copy [S81openhab_monitor](S81openhab_monitor) to `/etc/init.d`
4. Make sure all of those ^^ are executable (`chmod +x ...`)

If not, just copy the three files over manually.

## Usage

#### Long-running

`/etc/init.d/S81openhab_monitor start` will start the monitor... next reboot it should start automatically.

#### Single update

To force a one-time update from your openHAB, do something like:

```java
rule "Get current status updated on boot"
when System started
then
  executeCommandLine('ssh winkhub "/usr/bin/php /bin/openhab_monitor.php now"');
end
```

### Credits

- Author: [JustinAiken](https://github.com/JustinAiken)
- Database polling idea: This comment by Reddit user [izzy_monster](http://www.reddit.com/r/winkhub/comments/2r8xuz/fastest_way_to_get_a_command_to_aprontest_locally/cpxd6j9).
- Init script idea: This comment posted by Reddit user [wpskier](https://www.reddit.com/r/winkhub/comments/2r8xuz/fastest_way_to_get_a_command_to_aprontest_locally/cnn386d).

### License

[MIT](LICENSE)
