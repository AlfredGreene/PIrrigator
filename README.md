PIrrigator
==========

An irrigation control system based on a Raspberry PI

This application allows controlling a set of valves connected via I2C to a Raspberry PI.

It allows a private scheduling program for each valve,
along with manual control via WEB interface optimized for mobile devices.

Software part includes a PHP background process which runs the scheduling program,
and a WEB page composed of HTML combined with PHP.

Hardware part includes:
 * Raspberry PI - which runs the web server and controls the valves (35$ http://www.raspberrypi.org/)
 * I2C PCF8574 IO Expansion Board (10$ in DealExtream: http://dx.com/p/151551 )
 * 8 Channel 5V Relay Module (10$ in DealExtream: http://dx.com/p/171630 )

Installation instructions:
 ***** NOT Updated ******

1. Get a Raspberry PI and connect it to your local network.
2. On the PI, install Apache, PHP, Samba, FTP, I2C...
   Setup apache and PHP
   apt-get install apache2 php5 i2c-tools php-pear

3. add these lines to /etc/apache2/mods-enabled/php5.conf
<FilesMatch ".+\.html$">
    SetHandler application/x-httpd-php
</FilesMatch>

4. Enable I2C - follow https://www.abelectronics.co.uk/kb/article/1/i2c--smbus-and-raspbian-linux
chmod 777 /dev/i2c*

5. Set dynamic DNS
add systemd service to run the following command every 5 minutes
wget -O - http://freedns.afraid.org/dynamic/update.php?WkNicER4UGprSUxEVGl3eW1VY0Q6MTE2MTcyNTM=  &> /tmp/freedns_michaeli_mooo_com.log



   Add root SMB share:
   sudo apt-get install samba samba-common-bin winbind smbclient
   a. nano /etc/samba/smb.conf
     uncomment line: "name resolve order = lmhosts host wins bcast"
     uncomment line "security = user"
	 in SHARES add: 
[root]
comment = Root share
path = /
valid users = @users
force group = users
create mask = 0660
directory mask = 0771
read only = no
   b. usermod pi -G users
   c. smbpasswd -a pi   
   d. sudo service smbd restart

??   d. nano /etc/nsswitch.conf
??      add wins to end of hosts line

   
   a. nano /etc/apache2/apache2.conf
     add to the end:
# Set ServerName
ServerName localhost

# Add PHP in HTML support
AddHandler application/x-httpd-php .html

   b. apachectl -k restart




   
   c. enable I2C	  
	  sudo nano /etc/modules
           i2c-bcm2708
           i2c-dev
	  if any problems see https://www.raspberrypi.org/forums/viewtopic.php?t=97314   
		   
   

   Add root FTP share
   apt-get install vsftpd
   a. nano /etc/vsftpd.conf
anonymous_enable=NO
local_enable=YES
write_enable=YES
	b. sudo /etc/init.d/vsftpd restart 
	
#	Add Hebrew
#	a. Enable root user in xbian-config
#	b. su
#	c. dpkg-reconfigure locales
#	d. select he_il UTF-8

3. If you want to access it from the internet, register with a dns service.
   I use http://freedns.afraid.org/
4. Create a directory called /var/www-data/valves and give the user www-data full access to it.
5. Copy the demo valves INI files from demo-valve to the above directory.
6. Run the background process deamon.sh 
   Connect via SSH to your PI, cd to /var/www, type ./deamon.sh
7. That it. You can open the webpage from a browser and play with it.

Set cron to reboot at 3AM
	sudo nano /etc/crontab -e
	0  2    * * *   root    reboot

Notes
1. If you don't have any HW connected, it will say 'NO HW FOUND - Simulation mode'.
   This will allow you to play around with it, without actually buying or connecting any HW.
2. Clicking on the header image will return you to the main page.
3. For now I'm using random images from lorempixel to have something colorful on the page...
   It should be replaced with real images to improve performace a bit.
4. Deamon logs, named mutex files etc. will be created in /var/www-data/valves. 

You may use it free for personal use, for commercial use please contact me at micronen@gmail.com
If you have any questions, comments or ideas, you can contact me at the above email.

Created by Ronen Michaeli 2013. 
