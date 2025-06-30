<?php

function errorHandler($level, $message, $filename, $line){

  switch($level){
		case E_ERROR:
			$type = "Error";
		break;
  	case E_WARNING:
      $type = "Warning";
    break;
		case E_PARSE:
			$type = "Parse error";
		break;
    case E_NOTICE:
      $type = "Notice";
    break;
    default:
			$type = "Unknown";
		break;
  }

  $error = "[".date("Y-m-d H:i:s")."] (".$type.") ".$message." in ".$filename.":".$line;

	$filepath = "php_errors.log";
  if(file_exists($filepath))
    if(is_writable($filepath))
      file_put_contents($filepath, $error . "\n", FILE_APPEND);

  return true;
}

set_error_handler("errorHandler");

?>
