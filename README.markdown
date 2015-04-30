# Winkhub -> OpenHAB Monitor

This is a script that watches the apron database on your rooted winkhub, and updates openHAB when stuff changes.
This way if you trigger a light outside of openHAB, such as through the Wink app or with a Lutron remote, your status in openHAB stays correct.

The inspiration from this came from a comment posted by Reddit user [wpskier](https://www.reddit.com/r/winkhub/comments/2r8xuz/fastest_way_to_get_a_command_to_aprontest_locally/cnn386d).

## Features

- Keep device status updated when changed
- Cache status to avoid unnecassary PUTs to your openHAB box
- ...except you can force an update every x intervals to ensure everything stays in sync.

### Supported Devices

- GE Link bulbs

## Config

First, edit `openhab_config` to put in your devices, and adjust your intervals/timing.

## Installation

If you have your wink hub set up with ssh keys and named `winkhub`, you can just use the [install script](install.sh).
If not, just copy the three files over manually.

### Credits

Author: [JustinAiken](https://github.com/JustinAiken)

### License

[MIT](LICENSE)
