PIrrigator
==========

An irrigation control system based on a Raspberry PI

This application allowes controlling a set of valves connected via I2C to a Raspberry PI.

It allows a private scheduling program for each valve,
along with manual control via WEB interface optimized for mobile devices.

Software part includes a PHP background process which runs the scheduling program,
and a WEB page composed of HTML combined with PHP.

Hardware part includes:
 * Raspberry PI - which runs the web server and controls the valves (35$ http://www.raspberrypi.org/)
 * I2C PCF8574 IO Expansion Board (10$ in DealExtream: http://dx.com/p/151551 )
 * 8 Channel 5V Relay Module (10$ in DealExtream: http://dx.com/p/171630 )

You may use it free for personal use, for commercial use please contact me at micronen@gmail.com

Created by Ronen Michaeli 2013. 