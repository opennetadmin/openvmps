<?php


// Lets do some initial install related stuff
if (file_exists(dirname(__FILE__)."/install.php")) {
    printmsg("DEBUG => Found install file for ".basename(dirname(__FILE__))." plugin.", 1);
    include(dirname(__FILE__)."/install.php");
} else {

// Place initial popupwindow content here if this plugin uses one.





}






///////////////////////////////////////////////////////////////////////
//  Function: openvmps_lookup (string $options='')
//
//  Input Options:
//    $options = key=value pairs of options for this function.
//               multiple sets of key=value pairs should be separated
//               by an "&" symbol.
//
//  Output:
//    Returns a two part list:
//      1. The exit status of the function.  0 on success, non-zero on
//         error.  All errors messages are stored in $self['error'].
//      2. A textual message for display on the console or web interface.
//
//  Example: list($status, $result) = openvmps_lookup('');
///////////////////////////////////////////////////////////////////////
function openvmps_lookup($options="") {
    global $conf, $self, $onadb;

    // Version - UPDATE on every edit!
    $version = '1.00';

    printmsg("DEBUG => openvmps_lookup({$options}) called", 3);

    // Parse incoming options string to an array
    $options = parse_options($options);

    // Return the usage summary if we need to
    if ($options['help'] or !($options['mac']) ) {
        // NOTE: Help message lines should not exceed 80 characters for proper display on a console
        $self['error'] = 'ERROR => Insufficient parameters';
        return(array(1,
<<<EOM

openvmps_lookup-v{$version}
Lookup VLAN information for use by a VMPS server

  Synopsis: openvmps_lookup [KEY=VALUE] ...

    mac=STRING       MAC address to request VLAN information for
    domain=STRING    VMPS/VTP domain. AKA VLAN campus
    clientip=IP      IP of the client switch asking for VMPS
    port=STRING      Port information from client switch
    vlan=STRING	     VLAN name used for reconfirmations
    update=Y|N       Optionaly update last_response and int description


EOM
        ));
    }

/*
    // Check permissions
    if (!auth('openvmps_lookup')) {
        $self['error'] = "Permission denied!";
        printmsg($self['error'], 0);
        return(array(10, $self['error'] . "\n"));
    }

/*
Per the external.c file with openvmps the following output types are used:

external program output

        ALLOW <vlan name>
        DENY
        SHUTDOWN
        DOMAIN

return

        0 - deny
        1 - allow
        2 - shutdown
        3 - domain

I will follow these specs.  DOMAIN=wrong domain, this will be returned when what is sent
from the VQP client does not match what is defined in ONA for the specified MAC.  if a MAC
is not found, it will do the default response below and not send DOMAIN.

There are basic responses to consider when planning your VMPS.  If we dont ALLOW, then what should happen?

1. DENY: deny and keep existing port configs on switch
2. SHUTDOWN: which causes switch to shutdown the port until a new connection attempt (reconnect, new mac)
3. <VLAN NAME>: Use the specified VLAN name as the default VLAN assignment.  This should probably be specific to each VMPS/VTP/CAMPUS domain

One of the above keywords should be defined for each campus in ONA.  If one is not defined, use DENY

other good info: http://en.wikipedia.org/wiki/VQP
*/

    // Set default VMPS response. must be either DENY or SHUTDOWN
    $default_vmps_response = 'DENY';

    $options['update'] = sanitize_YN($options['update'], 'N');

    printmsg("INFO => openvmps_lookup() Looking for MAC {$options['mac']}", 4);

    // Validate its a mac and make it like XXXXXXXXXXXX format
    $mac = mac_mangle($options['mac'], 1);
    if ($mac != -1) {

        // Search for it
        list($status, $rows, $interface) = ona_get_interface_record(array('mac_addr' => $mac));

        // if we dont find the MAC, return the default response
        if ($rows == 0) {
            // If there is a system config that defines a campus default, use it instead of the default_vmps_response value
            $campus_default = strtolower('vmps_campus_'.$options['domain']);
            if (isset($conf[$campus_default])) {
                // if we have a system config variable set for this campus, use its value
                printmsg("INFO => openvmps_lookup() Using campus default: {$conf[$campus_default]} for domain {$options['domain']}", 0);
                if ($conf[$campus_default] == 'SHUTDOWN' or $conf[$campus_default] == 'shutdown') return(array(2, "SHUTDOWN\n"));
                if ($conf[$campus_default] == 'DENY' or $conf[$campus_default] == 'deny') return(array(1, "DENY\n"));
                return(array(1, "ALLOW {$conf[$campus_default]}\n"));
            } else {
                // use the system default if we cant find the MAC
                $self['error'] = "INFO => openvmps_lookup() No interface has the MAC address: $mac returning system default";
                printmsg($self['error'], 4);
                return(array(1, "{$default_vmps_response}\n"));
            }
        }

        // TODO: if we have more than one interface with this mac, lets deal with it
        if ($rows > 1) {
        }

    } else {
        // If the mac address was invalid, give the default response and go home
        $self['error'] = "ERROR => openvmps_lookup() Invalid MAC address: {$options['mac']}";
        printmsg($self['error'], 2);
        return(array(1, "{$default_vmps_response}\n"));
    }

    // Get subnet info
    list($status, $rows, $subnet) = ona_get_subnet_record(array('id' => $interface['subnet_id']));
    list($status, $rows, $vlan) = ona_get_vlan_record(array('id' => $subnet['vlan_id']));

    // Does MAC we found match domain?
  //  if ($vlan['vlan_campus_name'] != $options['domain']) {
  //      printmsg("INFO => openvmps_lookup() MAC matches a different domain than requested: {$options['domain']} vs {$vlan['vlan_campus_name']}", 0);
  //      return(array(3, "DOMAIN\n"));
  //  }

    // Does MAC match vlan during reconfirm?
    //if (($options['vlan'] != '-NONE-') and ($vlan['name'] != $options['vlan'])) {
    //    printmsg("INFO => openvmps_lookup() VLAN mismatch during reconfirm: {$options['vlan']} vs {$vlan['name']}, Denying request", 0);
    //    return(array(1, "DENY\n"));
    //}
 
    // If we found it and all is ok then Allow it
    $text = "ALLOW {$vlan['name']}\n";
    printmsg("INFO => openvmps_lookup() MAC {$options['mac']} connected to {$options['clientip']}:{$options['port']}", 0);

    // update last_response and description if requested to do so
    if ($options['update'] == 'Y') {
        // update current description text
        $interface['description'] = preg_replace('/^\[VMPS:.*?\] /', '', $interface['description']);
        $intdesc = "[VMPS:{$options['clientip']}-{$options['port']}] {$interface['description']}";
        $vmpsnow = date('Y-m-j G:i:s');

        list($status, $output) = run_module('interface_modify', array('interface' => $interface['id'], 'set_last_response' => $vmpsnow, 'set_description' => $intdesc));
        if ($status) {
            $self['error'] = "ERROR => Failed to update interface info for '{$mac}': " . $output;
            printmsg($self['error'], 1);
        }
    }

    // Return the success notice
    return(array(0, $text));
}










?>
