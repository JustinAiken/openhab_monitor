# Winkhub -> OpenHAB Monitor

This is a script that watches the apron database on your rooted winkhub, and updates openHAB when stuff changes.
This way if you trigger a light outside of openHAB, such as through the Wink app or with a Lutron remote, your status in openHAB stays correct.

## Features

- Keep device status updated when changed
- Cache status to avoid unnecassary PUTs to your openHAB box
- ...except you can force an update every x intervals to ensure everything stays in sync.

### Supported Devices

- GE Link bulbs

## Config

First, edit `openhab_config` to put in your devices, and adjust your intervals/timing.

## Installation

### Automatic

If you have your wink hub set up with ssh keys and named `winkhub`, you can just run the [install script](install.sh) after you've configured it.

### Manual

1. Copy [openhab_config](openhab_config) to `/etc`
2. Copy [openhab_monitor.sh](openhab_monitor.sh) to `/bin`
3. Copy [S81openhab_monitor](S81openhab_monitor) to `/etc/init.d`
4. Make sure all of those ^^ are executable (`chmod +x ...`)
5. `/etc/init.d/S81openhab_monitor start`

If not, just copy the three files over manually.

### Credits

- Author: [JustinAiken](https://github.com/JustinAiken)
- Database polling idea: This comment by Reddit user [izzy_monster](http://www.reddit.com/r/winkhub/comments/2r8xuz/fastest_way_to_get_a_command_to_aprontest_locally/cpxd6j9).
- Init script idea: This comment posted by Reddit user [wpskier](https://www.reddit.com/r/winkhub/comments/2r8xuz/fastest_way_to_get_a_command_to_aprontest_locally/cnn386d).

### License

[MIT](LICENSE)
