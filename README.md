# ubiquity-screentime
Kids WiFi Management, allow them to grant their own time and force breaks
Kids get 120 minutes (configurable) every 24 hours (rolling)
They choose 15, 30, or 45 minutes to start WiFi for all of their configured devices
A call is made to Ubiquity to UnBlock their devices
Once their time is up their devices are blocked in Ubiquity again
They need to wait 20 minutes before requesting more time
They will see how much time they have left when requesting, how much time is in their session, and how long they have to wait for the cooling off period.

This is a BETA / Rough Draft of a way to allow kids to manage their own WiFi time.
It works, but I have not written php in over 10 years... but the ArtOfWifi lib was the best choice for this as it makes the Ubiquity integration seamless, so here we are.
I cobbled this together, it works for me, if people start to use it I will put time into it to make it better / easier to install, just give a shout so I know it is worth it.

I run this page in a docker instance, I have my Host Assistant setup with a screen for this page, the kids make their selections and things just work.

They are happy with this, it gives them full transparency and we don't have to constantly remind the to take a break from the Oculus.


There are a few things the need to be done with your Network Config for this to work properly.
1. You must make you you name your kids devices like so (Jane's - iPad, Jane's - Computer, Tom's - iPhone) etc. This app is using a regex match for the configured Names that match the device names.
2. You should probably use mac filtering on your networks
3. Many modern devices are hiding the real mac addresses, in the network settings on the device turn this feature off, this will make point two above work correctly


Now, to do the actual install:
On your Ubiquity Instance
Add a new local user, give it admin access to Network. If you want to figure out the actual perms needed please share!

In cron/crontab:
Replace REALIPOFDOCKERSERVER with the IP of your docker server, or the IP of the 'web' container
If you changed the port update it as well

In docker-compose.yml
Replace MYSQL_ROOT_PASSWORD: SOMESTRONGPASSWORD with any password you like

In html/config.json
Under Unifi, add the username and password for the local Ubiquity account you created above
Update the url you your Ubiquity Gateway
Change the site if needed

Under database update the IP to docker server ip
Update password to whatever you choose above

Under app, update the ip as well

Change time limits if you want

Under users enter names


Now you can run: docker-compose up -d

Wait a few moments and than the instances should be up, setup the sql tables (sorry)
In Chrome go to http://DOCKERSERVERIP:8080/ login with the root un and pw from db entry above
Click the screentime_db database, click run sql, take the sql scripts from this folder, run them (GO)

You should now be able to browse to: http://DOCKERSERVERIP:8080

You should see the names you enter, click the name and you should see your Ubiquity Devices and the ability to grant time


# Things I will eventually do
In list_devices.php and stop_by_name
  Make always allowed devices and patterns configs, currently set to things matching: Alexa|iPhone|Fire\sStick|TV
  
Make time allotments and cool off times configs

Move to use functions and just cleanup code in general

Run the sql table setup in compose