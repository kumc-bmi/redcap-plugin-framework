<?php
/**
 * Provides an interface to REDCap record data, for a given project.
 */
class ProjectModel {

    protected $PID;
    protected $CONN;
    protected $API_URL;
    protected $API_TOKEN;
    protected $FIELD_NAME_MAP;
    protected $REVERSE_FIELD_NAME_MAP;

    /**
     * @param int $pid The project id of the relevant REDCap project
     * @param mysqli $conn The mysqli object provided by redcap_connect.php
     * @param string[] $field_name_map An assoc. array which maps 'code' field
     *        names to REDCap 'project' fieldname.  This exists to prevent the
     *        need changing hardcoded fieldname in plugin code.
     */
    public function __construct($pid, $conn, $field_name_map=array()) {
        $this->PID = $pid;
        $this->CONN = $conn;

        /**
         * This field map allows for the field names used in plugin code to
         * differ from those defined in associated REDCap projects.  This allows
         * REDCap project field names to change without heavily impacting plugin
         * code. If a given field lacks an associated mapped field name, then
         * the REDCap field name will be used.
         */
        if(!empty($field_name_map)) {
            $this->FIELD_NAME_MAP = $field_name_map;
            $this->REVERSE_FIELD_NAME_MAP = array_flip($field_name_map);
        }
    }

    /**
     * Provide a REDCap API URL and project API token to make writeable.
     * NOTE: Maybe this should just be rolled into the constructor.
     *
     * @param string $api_url Full REDCap API URL path
     * @param string $api_token A project specific API token (with write priviledges)
     */
    public function make_writeable($api_url, $api_token) {
        $this->API_URL = $api_url;
        $this->API_TOKEN = $api_token;
    }

    /**
     * Wrapper method for beginning a transaction.
     * NOTE: Not supported by PHP versions < 5.5.0
     *
     * @param $flag The mysqli begin_transation $flag to be used
     * @link http://php.net/manual/en/mysqli.begin-transaction.php
     */
    public function begin_transaction($flag=MYSQLI_TRANS_START_READ_WRITE) {
        $this->CONN->begin_transaction($flag);
    }

    /**
     * Wrapper method for committing a transaction.
     * NOTE: Not supported by PHP versions < 5.5.0
     *
     * @link http://php.net/manual/en/mysqli.commit.php
     */
    public function commit_transaction() {
        $this->CONN->commit();
    }

    /**
     * Wrapper method for rolling back a transaction.
     * NOTE: Not supported by PHP versions < 5.5.0
     *
     * @link http://php.net/manual/en/mysqli.rollback.php
     */
    public function rollback_transction() {
        $this->CONN->rollback();
    }

    /**
     * Return the record data of the first record which matches the given
     * field/value pair.
     *
     * @param string $field_name The REDCap record field name with which to
     *        retrieve a record
     * @param mixed $value The value of the field name to query by
     *
     * @return mixed[] An associative array of field_name / value pairs associated
     *         with the first matching record
     */
    public function get_record_by($field_name, $value) {
        if($field_name == 'record') {
            return $this->get_record_data($value);
        } else {
            return $this->get_record_data(
                $this->get_record_id_by($field_name, $value)
            );
        }
    }

    /**
     * Return the record data of all of the records that match the given
     * field/value pair.
     *
     * @param string $field_name The REDCap record field name with which to
     *        retrieve records
     * @param mixed $value The value of the field name to query by
     *
     * @return mixed[] An array of matching records, each represented by an nested
     *         associative array of field_name / value pairs.
     */
    public function get_records_by($field_name, $value) {
        $record_ids = $this->get_record_ids_by($field_name, $value);
        $records = array();
        foreach($record_ids as $record_id) {
            $records[] = $this->get_record_data($record_id);
        }
        return $records;
    }

    /**
     * Saves REDCap record data using the REDCap API.
     * @todo Needs to fnmap field names.
     *
     * @param mixed[] $record_data An array of array of REDCap record data
     *        elements represented as an associative array.  Each data element 
     *        represents a row in the redcap_data table.
     *
     * @example $record_data = array(
     *              array(
     *                  'record'            => $record_id,
     *                  'redcap_event_name' => $optional_event_name,
     *                  'field_name'        => 'this_field',
     *                  'value'             => 'this_value'
     *              ),
     *              array(
     *                  'record'            => $record_id,
     *                  'redcap_event_name' => $optional_event_name,
     *                  'field_name'        => 'that_field',
     *                  'value'             => 'that_value'
     *              )...
     *          );
     *
     * @return mixed[] A two element array with the follow elements...
     *         bool $success
     *         string $error_msg
     *
     * @todo Errors should be handle using try/catch blocks, with this method only
     *       returning the $success boolean.
     */
    public function save_record_data($record_data) {
        require_once(dirname(__FILE__).'/RestCallRequest.php');

        $api_request = new RestCallRequest(
            $this->API_URL,
            'POST',
            array(
                'content'   => 'record',
                'type'      => 'eav',
                'format'    => 'json',
                'token'     => $this->API_TOKEN,
                'data'      => json_encode($record_data)
            )
        );
        $api_request->execute();
        $response_info = $api_request->getResponseInfo();
        $error_msg = '';
        if($response_info['http_code'] == 200) {
            return array(true, $error_msg);
        } else {
            $api_response = json_decode($api_request->getResponseBody(), true);
            $error_msg = (isset($api_response['error'])
                       ? $api_response['error']
                       : 'No error returned.');
            return array(false, $error_msg);
        }
    }

    /**
     * A simplified method for saving data to a single record.  This provides a
     * cleaner interface for saving record data than that of save_record_data.
     *
     * @param mixed[] $record An array of field_name / value pairs for a
     *        particular record.
     * @param string $record The record id of the record to be updated.
     * @param string $event_name An optional event name for longitutinal projects.
     *
     * @example $record_data = array(
     *              'this_field' => 'this_value',
     *              'that_field' => 'that_value'
     *          );
     *
     * @return mixed[] A two element array with the follow elements...
     *         bool $success
     *         string $error_msg
     *
     * @todo Errors should be handle using try/catch blocks, with this method only
     *       returning the $success boolean.
     */
    public function save_record($record_data, $record, $event_name=null) {
        $formatted_data = array();

        foreach($record_data as $field_name => $value) {
            if(!is_null($event_name)) {
                $formatted_data[] = array(
                    'record' => $record,
                    'redcap_event_name' => $event_name,
                    'field_name' => $field_name,
                    'value' => $value
                );
            } else {
                $formatted_data[] = array(
                    'record' => $record,
                    'field_name' => $field_name,
                    'value' => $value 
                );
            }
        }

        return $this->save_record_data($formatted_data);
    }

    /**
     *  Given a field name, or an array of field/value pairs, map the field
     *  names using the field name map provided to the class constructor.
     *
     *  @param mixed[] $map_target The target to be mapped.  Can either be a
     *         string, or an associative array of field name / value pairs.
     *  @param bool $reverse Whether the mapping is a forward or reverse mapping.
     *
     *  @return mixed[] The mapped result
     */
    protected function fnmap($map_target, $reverse=false) {
        if($reverse) {
            $FIELD_NAME_MAP = $this->REVERSE_FIELD_NAME_MAP;
        } else {
            $FIELD_NAME_MAP = $this->FIELD_NAME_MAP;
        }

        if(is_array($map_target)) {
            if(!empty($FIELD_NAME_MAP)) {
                $mapped_array = array();
                foreach($map_target as $key => $value) {
                    if(array_key_exists($key, $FIELD_NAME_MAP)) {
                        $mapped_array[$FIELD_NAME_MAP[$key]] = $value;
                    } else {
                        $mapped_array[$key] = $value;
                    }
                }
                return $mapped_array;
            }
            return $map_target; 
        } else {
            if(!empty($FIELD_NAME_MAP)) {
                if(array_key_exists($map_target, $FIELD_NAME_MAP)) {
                    return $FIELD_NAME_MAP[$map_target];
                }
            }
            return $map_target;
        }
    }

    /**
     * Executes the given SQL query with the given bind parameters.
     *
     * @todo Discover bind parameter type on-the-fly, instead of requiring that it
     *       be passed in as the first element of the $bind_param parameter.
     *
     * @param string $query The unbound query to be executed
     * @prarm mixed[] $bind_params An array which consists of the parameters to
     *        mysqli's bind_param method.
     *
     * @link http://php.net/manual/en/book.mysqli.php
     */
    protected function execute_query($query, $bind_params) {

        $fields = $results = array();
        $stmt = $this->CONN->stmt_init();
        if($stmt->prepare($query)) {
            call_user_func_array(array($stmt,"bind_param"), $bind_params);
            $stmt->execute();
            $meta = $stmt->result_metadata();

            while($field = $meta->fetch_field()) {
                $var = $field->name;
                $$var = null;
                $fields[$var] = &$$var;
            }

            call_user_func_array(array($stmt, 'bind_result'), $fields);
            while($stmt->fetch()) {
                $row = array();
                foreach($fields as $field_name => $var) {
                    $row[$field_name] = $var;
                }
                $results[] = $row;
            }
        }
        $stmt->close();

        return $results;
    }


    /**
     * Get the first record id in the project which is assoicated with the given
     * field/value pair.
     *
     * @param string $field_name The record field name to query by.
     * @param string $value The field name value to query by.
     * @param bool $order_asc Whether to order result by ASC or DESC order.
     *
     * return string 
     */
    public function get_record_id_by($field_name, $value, $order_asc=true) {
        $record_ids = $this->get_record_ids_by($field_name, $value, $order_asc);
        return $record_ids[0];
    }

    /**
     * Get all record ids in the project which are associated with the given
     * field/value pair.
     *
     * @todo Should ordering be made complex, to allow for ordering by field name?
     *
     * @param string $field_name The record field name to query by.
     * @param string $value The field name value to query by.
     * @param bool $order_asc Whether to order result by ASC or DESC order.
     *
     * return string[] 
     */
    public function get_record_ids_by($field_name, $value, $order_asc=true) {
        $bind_pattern = (is_integer($value) ? 'isi' : 'iss');

        if($order_asc) {
            $order = 'ASC';
        } else {
            $order = 'DESC';
        }

        $query = 'SELECT record '.
                 'FROM redcap_data '.
                 'WHERE project_id=? '.
                 'AND field_name=? '.
                 'AND value=? '.
                 'ORDER BY record '.$order;

        $params = array($bind_pattern, $this->PID, $this->fnmap($field_name),
                        $value);
        $record_ids = array();
        $result_data = $this->execute_query($query, $params);
        foreach($result_data as $row) {
            $record_ids[] = $row['record'];
        }

        return $record_ids;
    }

    /**
     * Return all record data associated with the given $record_id.
     *
     * @param string $record_id 
     * @param bool $in_redcap_format If is set to "true" REDCap's native Record
     *        object will be used instead of a direct SQL query (this is sometimes
     *        useful for compatibility with other REDCap classes, such as
     *        LogicTester).
     */
    protected function get_record_data($record_id, $in_redcap_format=false) {
        if($in_redcap_format) {
            // TODO:  Apply fnmap to result???
            require_once(APP_PATH_DOCROOT.'Classes/Records.php');
            return Records::getData('array', $record);
        } else {
            $query = 'SELECT field_name, value '.
                     'FROM redcap_data '.
                     'WHERE project_id=? '.
                     'AND record=?';

            $bind_pattern = (is_integer($record_id) ? 'ii' : 'is');
            $params = array($bind_pattern, $this->PID, $record_id);
            $result_data = $this->execute_query($query, $params);

            // Collapse record data into a single assoc. array
            $record_data = array();
            foreach($result_data as $row) {
                $record_data[$row['field_name']] = $row['value'];
            }

            return $this->fnmap($record_data, true);
        }
    }
}
