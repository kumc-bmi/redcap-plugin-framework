<?php
require_once(FRAMEWORK_ROOT.'PluginController.php');


/**
 * Generic controller for use when a resource is not found.
 */
class NotFoundController extends PluginController {

    public function handleGET() {
        return $this->render('not_found.html', array(
            'PID' => $this->GET['pid']
        ));
    }
}
?>
