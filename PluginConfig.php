<?php
/**
 * An object for storing plugin configuration values. The constructor takes an
 * ini filepath as a single construction parameter and populates itself with its
 * contents. Otherwise, the object behaves like an immutable PHP Array.
 */
class PluginConfig implements ArrayAccess {

    private $container = array();

    public function __construct($config_file) {
        if(is_readable($config_file)) {
            $this->container = parse_ini_file($config_file, true);
        } else {
            throw new Exception("Config file not readable at $config_file.");
        }
    }

    // Do not allow offset to be set, and therefore changed.
    public function offsetSet($index, $value) {
        return;
    }

    public function offsetExists($offset) {
        return isset($this->container[$offset]);
    }

    // Do not allow offset to be unset, and therefore changed.
    public function offsetUnset($offset) {
        return;
    }

    public function offsetGet($offset) {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }
}
?>
