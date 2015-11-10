<?php

class ProjectModel {

    protected $PID;
    protected $CONN;
    protected $API_URL;
    protected $API_TOKEN;
    protected $FIELD_NAME_MAP;
    protected $REVERSE_FIELD_NAME_MAP;

    /**
     *
     */
    public function __construct($pid, $conn, $field_name_map=array()) {
        $this->PID = $pid;
        $this->CONN = $conn;

        if(!empty($field_name_map)) {
            $this->FIELD_NAME_MAP = $field_name_map;
            $this->REVERSE_FIELD_NAME_MAP = array_flip($field_name_map);
        }
    }

    /**
     *
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
     *
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
     *
     */
    public function make_writeable($api_url, $api_token) {
        $this->API_URL = $api_url;
        $this->API_TOKEN = $api_token;
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
     *
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
     *
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

    protected function test_execute_query($query, $params) {
        $fields = array();
        $bind_params = array();
        $results = array();

        $stmt = $this->CONN->stmt_init();
        if($stmt->prepare($query)) {
            $types = '';
            foreach($params as $param) {
                if(is_string($param)) {
                    $types .= 's';
                } else if(is_int($param)) {
                    $types .= 'i';
                } else if(is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 'b';
                }
            }

            $bind_params[] = $types;
        }
    }


    /**
     *
     */

/*    public function get_record_by_id($record_id, $in_redcap_format=true) {
        if($in_redcap_format) {
            require_once(APP_PATH_DOCROOT.'Classes/Records.php');
            return Records::getData('array', $record);
        } else {
            return get_record_by('record', $record_id);
        }
}*/
    protected function get_record_id_by($field_name, $value) {
        $record_ids = $this->get_record_ids_by($field_name, $value);
        return $record_ids[0];
    }

    /**
     *
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
     *
     */
    protected function get_record_data($record_id) {
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
