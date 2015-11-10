<?php
class PluginController {

    protected $GET;
    protected $POST;
    protected $TWIG;
    protected $CONN;
    protected $USER;
    protected $CONFIG;
    protected $ERRORS;

    public function __construct($_GET, $_POST, $twig, $conn, $USERID, $CONFIG) {
        $this->GET = $_GET;
        $this->POST = $_POST;
        $this->TWIG = $twig;
        $this->CONN = $conn;
        $this->USER = $USERID;
        $this->CONFIG = $CONFIG;
    }

    public function render($template, $params=array()) {
        $params['PID'] = $this->CONFIG['project_ids']['participant_pid'];
        return $this->TWIG->render($template, $params);
    }

    public function handleGET() {
        /* ... Do the stuff ... */

        return $this->render('default.html', array());
    }

    public function validatePOST() {
        return true;
    }

    public function handlePOST() {
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

    public function process_request() {
        if(!empty($this->POST)) {
            return $this->handlePOST();
        } else {
            return $this->handleGET();
        }
    }
}
?>
