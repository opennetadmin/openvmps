OpenVMPS
========

OpenNetAdmin plugin for managing an OpenVMPS service. This provides a module that can be used by an OpenVMPS http://vmps.sourceforge.net/ server as an external lookup helper.  This way your OpenVMPS instance will automatically look for information in ONA.

At this time the code has been minimaly tested but should work.

Install
=======

  * If you have not already, run the following command `echo '/opt/ona' > /etc/onabase`.  This assumes you installed ONA into /opt/ona 
  * Ensure you have the following prerequisites installed:
    * An OpenVMPS server. It is not required to be on the same host as the ONA system.
    * A functioning `dcm.pl` install on your OpenVMPS server.
  * Download the archive and place it in your $ONABASE/www/local/plugins directory, the directory must be named `openvmps`
  * Make the plugin directory owned by your webserver user I.E.: `chown -R www-data /opt/ona/www/local/plugins/openvmps`
  * From within the GUI, click _Plugins->Manage Plugins_ while logged in as an admin user
  * Click the install icon for the plugin which should be listed by the plugin name 
  * Follow any instructions it prompts you with.
  * Create a file on your OpenVMPS server that contains the following:

        #!/bin/bash
        PATH=$PATH:/opt/ona/bin
        while true; do
            read DOMAIN CLIENTIP PORT VLAN MAC
            dcm.pl -r openvmps_lookup mac=$MAC domain=$DOMAIN clientip=$CLIENTIP port=$PORT vlan=$VLAN update
        done
    
    * The update flag is an optional parameter, add or remove it as desired
    * In this example we are creating the following file: `/opt/ona/bin/openvmps_external`

Usage
-----

After installation a new `dcm.pl` module will be created called `openvmps_lookup`.  This is the module that will be executed by your OpenVMPS server when it invokes the script passed using the `-e` option.  The external script simply sits in a loop and passes the data sent to it by the OpenVMPS server to the openvmps_lookup ONA module.

As an example you would run the OpenVMPS daemon with the following command:
    ./vmpsd -l 0x0807 -d -e /opt/ona/bin/openvmps_external

ONA must be configured with a VLAN Campus that is named the same as your VTP Domain. You should also configure your VLANS within that campus to match how they would be defined within your VTP domain on the switch. Remember to name your campus and VLANs in ONA exactly as you have them on your switch. You should then also associate the Campus/VLAN to the appropriate Subnet definition within ONA.

By default each Campus/VTP domain will send a DENY (thus denying access) for clients that are not found in ONA by their MAC address. You can configure the default behavior to also be SHUTDOWN (to shutdown the port on the switch) or to assign a default VLAN for use in a captive portal situation.

To set a default value to return for a given campus, set a value in the system configuration of ONA. Click Menu->Admin->Manage system config.  Click the `Add config option` button.  The name should be in the format of 'vmps_campus_<campusname>'. campusname should be lower case but any punctuation should match. Then set the value to one of DENY,SHUTDOWN, or the vlan name to assign to the port (use upper case names)

Decide if you want updates to happen. This flag updates the last_response timestamp column of the interface with this MAC address. It also puts [VMPS:x.x.x.x-portname] in the interface description field for loose tracking purposes. It will retain whatever other description you have on the interface and just manipulate the [VMPS] description at the begining of it. This update is enabled by including the `update` keyword in the openvmps_external script defined during install.

NOTE: You must determine how to handle multiple MAC addresses. TODO: for now if multiple are found it will try to match against the one in the campus that was requested from the switch. This means that you can only have one MAC address per campus. otherwise VMPS will just take the first one it finds in ONA for that campus.
