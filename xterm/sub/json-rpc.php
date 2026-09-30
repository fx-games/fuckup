<?php
// ----------------------------------------------------------------------------
// ----------------------------------------------------------------------------
// check if object has field
function has_field($object, $field)
{
    //return in_array($field, array_keys(get_object_vars($object)));
    return array_key_exists($field, get_object_vars($object));
}

// ----------------------------------------------------------------------------
// return object field if exist otherwise return default value
function get_field($object, $field, $default)
{
    $array = get_object_vars($object);
    if (isset($array[$field])) {
        return $array[$field];
    } else {
        return $default;
    }
}


// ----------------------------------------------------------------------------
//create json-rpc response
function response($result, $id, $error, $customData = array())
{
    return json_encode(array("jsonrpc" => "2.0",
        'result' => $result,
        'id' => $id,
        'error' => $error,
		'custom' => $customData));
}

// ----------------------------------------------------------------------------
// try to extract id from broken json
function extract_id()
{
    $regex = '/[\'"]id[\'"] *: *([0-9]*)/';
    if (preg_match($regex, file_get_contents('php://input'), $m)) {
        return $m[1];
    } else {
        return 0;
    }
}

function delete_from_array(&$array, $deleteIt, $useOldKeys = FALSE)
{
    $tmpArray = array();
    $found = FALSE;
    foreach ($array as $key => $value) {
        if ($value !== $deleteIt) {
            if (FALSE === $useOldKeys) {
                $tmpArray[] = $value;
            } else {
                $tmpArray[$key] = $value;
            }
        } else {
            $found = TRUE;
        }
    }

    $array = $tmpArray;

    return $found;
}

// ----------------------------------------------------------------------------
function handle_json_rpc($serverobject)
{
    global $hidden_methods;

    $input = decode_input();
    header('Content-Type: text/plain');

    // handle Errors
    if (!$input) {
        check_for_parse_errors();
    } else {
        $method = get_field($input, 'method', null);
        $params = get_field($input, 'params', null);
        $id = get_field($input, 'id', null);

        // json rpc error
        if (!($method && $id)) {
            check_for_rpc_error($id, $method);
        }
    }
    $params = fix_params($params);


    // call Service Method
    try {
        // switch to connected server object

        log_command($method, $params);

        $class = get_class($serverobject);

        try_call_method($class, $method, $params, $id);

        exit;
    } catch (Exception $e) {
        //catch all exceptions from user code
        $msg = "[[;#f22;]ERROR:] " . $e->getMessage();
        echo response(null, $id, array("code" => -23000, "message" => $msg));
    }
}

function try_call_method($class, $method, $params, $id)
{
    $modelReflector = new ReflectionClass($class);
    $object = $modelReflector->newInstanceArgs(array());
    //$methodObject = $modelReflector->getMethod($method);
    //$parameters = $methodObject->getParameters();
    //var_dump($params);
    if (strcmp($method,'_get_tablist') == 0)
    {
        $result['metadata'] = $object->get_tab_completion_list();
        echo response('', $id, null, $result['metadata']);
    }
    else if (strcmp($method,'login') != 0)
    {
        $token = $params[0];
        delete_from_array($params,$token);
        $result = $object->run($token, $method, $params);
        echo response($result['msg'], $id, null, $result['metadata']);
    }
    else // login
    {
        $result = $object->login($params);
        echo response($result['msg'], $id, null, $result['metadata']);
    }
}

function log_command($method, $params)
{
    // never log passwords or session tokens: login keeps only the name, other calls drop the token
    $params = $method == 'login' ? array_slice($params, 0, 1) : array_slice($params, 1);
    $logString = date(DATE_RFC822) . ': ' . // no IPs: nothing personal to retain
        $method . ' ' . implode(' ', array_map('strval', $params)) . "\n";
    $logfilename = '../../webStats/termAccessLog.txt';
    file_put_contents($logfilename, $logString, FILE_APPEND | LOCK_EX);
}

function fix_params($params)
{
    // fix params (if params is null set it to empty array)
    if (!$params) {
        $params = array();
    }

    // if params is object change it to array
    if (is_object($params)) {
        if (count(get_object_vars($params)) == 0) {
            $params = array();
            return $params;
        } else {
            $params = get_object_vars($params);
            return $params;
        }
    }
    return $params;
}

function check_for_rpc_error($id, $method)
{
    if (!$id) {
        $id = extract_id();
    }
    if (!$method) {
        $error = "no command";
    } else if (!$id) {
        $error = "no id";
    } else {
        $error = "unknown reason";
    }
    echo response("Invalid Request: $error", $id, null);
    exit;
}

function check_for_parse_errors()
{
    if (file_get_contents('php://input') == "") {
        echo response("Parse Error: no data", 0, null);
    } else {
        // json parse error
        $id = extract_id();
        echo response("Parse Error: JSON Parsing", $id, null);
    }
    exit;
}

function decode_input()
{
    $input = file_get_contents('php://input');
    $encoding = mb_detect_encoding($input, 'auto');
    //convert to unicode
    if ($encoding != 'UTF-8') {
        $input = iconv($encoding, 'UTF-8', $input);
    }
    $input = json_decode($input);
    return $input;
}


?>
