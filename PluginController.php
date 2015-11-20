<?php
/**
 * An object to help with handling REDCap Plugin HTTP requests.
 */
class PluginController {

    protected $GET;
    protected $POST;
    protected $TWIG;
    protected $CONN;
    protected $USER;
    protected $CONFIG;
    protected $ERRORS;

    public function __construct($_GET, $_POST, $twig, $conn, $USERID, $CONFIG) {
        $this->GET = $_GET; // Request GET vars
        $this->POST = $_POST; // Request POST vars
        $this->TWIG = $twig; // Twig templating engine
        $this->CONN = $conn; // REDCap database connection
        $this->USER = $USERID;  // Currently logged in user's REDCap user id
        $this->CONFIG = $CONFIG; // Plugin configuration
    }

    /**
     * Handles the internal routing of the HTTP request.
     */
    public function process_request() {
        if(!empty($this->POST)) {
            return $this->handlePOST();
        } else {
            return $this->handleGET();
        }
    }

    /**
     * Wrapper for Twig's render method. Processes template and returns result
     * HTML.
     */
    protected function render($template, $params=array()) {
        return $this->TWIG->render($template, $params);
    }

    /**
     * Handle HTTP GET request.
     */
    protected function handleGET() {
        /* ... Do the stuff ... */

        return $this->render('default.html', array());
    }

    /**
     * Placeholder method for valuidation of POST data. 
     */
    protected function validatePOST() {
        return true;
    }

    /**
     * Handle HTTP POST request.
     */
    protected function handlePOST() {
        // handlePOST forwards to handleGET unless redefined.
        return $this->handleGET();

        // A defined handlePOST should look something like ...
        // if(!$this->validatePOST()) {
        //     $this->render('error_page.html', params);
        // } else {
        //     ... Do the stuff ...
        //
        //     $this->render('success_page.html', $params);
        // }
    }

    /**
     * Generates a simple, per-request, CSRF token for use in form submission.
     */
    protected function generate_csrf_token() {
        
        if(!isset($_SESSION['repower_csrf_tokens'])) { 
            $_SESSION['repower_csrf_tokens'] = array();
        }

        $csrf_token = md5(mt_rand());
        while(in_array($csrf_token, $_SESSION['repower_csrf_tokens'])) {
            $csrf_token = md5(mt_rand());
        } 
        $_SESSION['repower_csrf_tokens'][] = $csrf_token;

        return $csrf_token;
    }

    /**
     * Verifies that the given CSRF token is valid.  If a token is not passed in
     * as a parameter, then the method will use the "csrf_token" POST var if
     * available.
     *
     * When a token is verified it is removed form the session token store.
     */
    protected function verify_csrf_token($token=null) {
        if(!$token and isset($this->POST['csrf_token'])) {
            $token = $this->POST['csrf_token'];
        }

        if(isset($_SESSION['repower_csrf_tokens'])) {
            $key = array_search(
                $this->POST['csrf_token'],
                $_SESSION['repower_csrf_tokens']
            );
            if($key !== false) {
                unset($_SESSION['repower_csrf_tokens'][$key]);
                return true;
            }
        }
        return false;
    }
}
?>
