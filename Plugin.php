<?php
/**
 * Handles inteactions between REDCap plugin space (e.g. index.php) and plugin
 * framework components (i.e. controllers, renders, routers, etc.).
 */
class Plugin {

    protected $CONN;
    protected $USER;
    protected $CONFIG;
    protected $RENDERER;

    /**
     * Given the REDCap MySQL database connection, the current user's username,
     * and the path to a plugin config file, create a plugin object.
     *
     * Expects a config.ini file in the same directory (unless specified).
     */
    public function __construct(
        $conn, $username, $config_file_path='config.ini'
    ) {
        $this->CONN = $conn;
        $this->USER = $username;
        
        // Create plugin configuration object.
        require_once(FRAMEWORK_ROOT.'PluginConfig.php');
        $this->CONFIG = new PluginConfig($config_file_path);

        $this->set_default_renderer();
    }

    /**
     * Sets the default renderer to Twig.
     */
    protected function set_default_renderer() {
        // Include and configure Twig template engine 
        // (http://twig.sensiolabs.org/)
        require_once(
            FRAMEWORK_ROOT.'lib/twig/'.$this->CONFIG['versions']['twig']
            .'/lib/Twig/Autoloader.php'
        );
        Twig_Autoloader::register();
        $twig_loader = new Twig_Loader_Filesystem(array(
            './templates',
            FRAMEWORK_ROOT.'templates'
        ));
        $twig =  new Twig_Environment($twig_loader, array());
        $this->set_renderer($twig);
    }

    /**
     * Allows for Twig to be replaced with another renderer.
     */
    public function set_renderer($renderer) {
        $this->RENDERER = $renderer;
    }

    /**
     * An overridable method for including plugin authorization.
     */
    public function authorize() {
        // Limit access to plugin using REDCap helper functions
        // REDCap::allowProjects(...);
        // REDCap::allowUsers(...);
        return True;
    }

    /**
     * Given the PHP HTTP request "super globals" and the path to a routes file,
     * handle the routing, controller instantiation, and return the response 
     * HTML. 
     *
     * Expects a routes.php file in the same directory (unless specified).
     */
    public function request_to_response(
        $_GET, $_POST, $_REQUEST, $router_file_path='routes.php'
    ) {
        // Query router for relavent controller based on plugin $_REQUEST vars
        require_once($router_file_path);
        $ControllerClass = route($_REQUEST);

        // Include and instantiate controller class
        if(file_exists('controllers/'.$ControllerClass.'.php')) {
            require_once('controllers/'.$ControllerClass.'.php');
        } else {
            require_once(FRAMEWORK_ROOT.'controllers/'.$ControllerClass.'.php');
        }
        $controller = new $ControllerClass(
            $_GET,
            $_POST,
            $this->RENDERER,
            $this->CONN,
            $this->USER,
            $this->CONFIG
        );
        // Generate response HTML
        return $controller->process_request();
    }
}
?>
