# REDCap Plugin Framework

 * Introduction
 * Plugin Structure
 * Requirements
 * Installation
 * Configuration
 * Testing
 * Validation
 * Maintainers


### INTRODUCTION
This is a lightweight, loose coupled, REDCap specific, MVC framework was developed to aid in
the development of REDCap plugins which extend REDCap's functionality.


### FRAMEWORK STRUCTURE
To seperate business, data, and display logic a simplified Model-View-Controller
(MVC) pattern was used to managed the complexity of creating REDCap plugins.
This framework consists of:

 * `index.php.example`: An example `index.php` file which contains the "glue" that 
   connects the rest of a plugin's code with REDCap code.

 * `PluginConfig.php`: Contains the class definition of PluginConfig, which 
   provides an immutable configuration object built from a provided ini file.

 * `PluginController.php`: Contains the PluginController class definition, which 
   provides a controler object to handle plugin specific HTTP requests.

 * `ProjectModel.php`: Contain the ProjectModel class definition, which provides an
   abstracted interface to REDCap project record data.

 * `routes.php.example`: An example routes.php file to which routes incoming HTTP 
   requests the appropriate controllers to handle the request.

 * `lib/`: Holds 3rd party packages used by the framework. Currently holds a
   version of the Twig rendering engine (http://twig.sensiolabs.org). 

 * `README.md`: This file.

 * `RestCallRequest.php`: A file provided by REDCap to facilitate PHP based REDCap
   API calls.


### REQUIREMENTS
This plugin framework requires the following to work correctly:

 * The `redcap_connect.php` file from the REDCap base install is required and 
   needs to be present in the root redcap directory (included in as a part of 
   the full REDCap install as of version 5.5.0).  Can also be found at:
   https://iwg.devguard.com/trac/redcap/browser/misc/redcap_connect.zip?format=rawthe

 * Twig [developed using version 1.18.2]: A PHP template engine which uses the
   same popular templating syntax as Jinja2 and Django.  More information can be
   found at http://twig.sensiolabs.org.


### INSTALLATION
Installation of this plugin framework consists of the following steps:

 1. Make sure that the `redcap_connect.php` file described above is present in
    the local REDCap installation's root directory.

 2. Clone the KUMC `redcap-plugin-framework` into the `<redcap-root>/plugins` 
    directory.  Create the `plugins` directory if necessary.

 3. Unpack the `Twig-<version>.tar.gz` file into 
    `<framework-root>/lib/twig/<version>/`.


### CONFIGURATION
No configuration is required.

### MAINTAINERS
Current maintainers:
 * Michael Prittie <mprittie@kumc.edu>
