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
     */
    public function make_writeable($api_url, $api_token) {
        $this->API_URL = $api_url;
        $this->API_TOKEN = $api_token;
    }

    /**
     * Return the record data of the first record which matches the given
     * field/value pair.
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
     * Should take $record_id, $field_value_array, $optional_event_name
     * TODO: Needs to fnmap field names.
     */
    public function save_redcap_data($record_data) {
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
     *  Given a field name, or an array of field/value pairs, map the field
     *  names using the field name map provided to the constructor.
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
     */
    protected function execute_query($query, $bind_params) {
        // TODO: Discover bind parameter type on-the-fly, instead of requiring
        // that it be passed in as the first element of the $bind_param 
        // parameter.

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
     */
    protected function get_record_id_by($field_name, $value) {
        $record_ids = $this->get_record_ids_by($field_name, $value);
        return $record_ids[0];
    }

    /**
     * Get all record ids in the project which are associated with the given
     * field/value pair.
     */
    protected function get_record_ids_by($field_name, $value) {
        $bind_pattern = (is_integer($value) ? 'isi' : 'iss');

        $query = 'SELECT record '.
                 'FROM redcap_data '.
                 'WHERE project_id=? '.
                 'AND field_name=? '.
                 'AND value=?';

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
     * Return all record data associated with the given $record_id.  If
     * $in_redcap_format is set to "true" REDCap's native Record object will be
     * used instead of a direct SQL query (this is sometimes useful for
     * compatibility with other REDCap classes, such as LogicTester).
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

            $params = array('ii', $this->PID, $record_id);
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
