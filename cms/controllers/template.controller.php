<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Escape a string for safe HTML output.
 * Use this everywhere a variable is echoed into HTML.
 */
function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

class TemplateController{

	// Calculate CMS base path (URL path)
	// Supports installations in subfolders (e.g.: /chatcenter/cms)

	static public function cmsBasePath(){

		$scriptName = $_SERVER["SCRIPT_NAME"] ?? "";

		// Search for "/cms" segment in path
		if($scriptName !== "" && preg_match("#^(.*?/cms)(?:/|$)#", $scriptName, $matches)){
			return $matches[1];
		}

		// Fallback: script directory (for root installations)
		$dir = rtrim(dirname($scriptName), "/");

		if($dir === "/"){
			return "";
		}

		return $dir;
	}

	// Load main template view

	public function index(){

		include "views/template.php";
	
	}

	// Identify column type

	static public function typeColumn($value){

		if($value == "text" || 
		   $value == "textarea" ||
		   $value == "image" || 
		   $value == "video" ||
		   $value == "file" ||
		   $value == "link" ||
		   $value == "select" ||
		   $value == "array" ||
		   $value == "color" ||
		   $value == "password" || 
		   $value == "email"){

			$type = "TEXT NULL DEFAULT NULL";
		}

		if($value == "object"){

			$type = "TEXT NULL DEFAULT '{}'";
		}

		if($value == "json"){

			$type = "TEXT NULL DEFAULT '[]'";

		}

		if($value == "multiimage"){

			// Stores a JSON array of image URLs.
			$type = "TEXT NULL DEFAULT '[]'";

		}

		if($value == "int" || $value == "relations" || $value == "order"){
	       
	       	$type = "INT NULL DEFAULT '0'";
		
		}

		if($value == "boolean"){
	       
	       	$type = "INT NULL DEFAULT '1'";
		
		}

		if($value == "double" || $value == "money"){
	       
	       	$type = "DOUBLE NULL DEFAULT '0'";
		
		}

		if($value == "date"){
	       	
	       	$type = "DATE NULL DEFAULT NULL";
	    
	    }

	    if($value == "time"){
	       	
	       	$type = "TIME NULL DEFAULT NULL";
	    
	    }

	    if($value == "datetime"){
	      	
	      	$type = "DATETIME NULL DEFAULT NULL";
	    
	    }

	    if($value == "timestamp"){
	      	
	      	$type = "TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP";
	    }

	    if($value == "code" || $value == "chatgpt"){

	       	$type = "LONGTEXT NULL DEFAULT NULL";

	    }

	    if($value == "workflow"){

	       	$type = "VARCHAR(100) NULL DEFAULT 'draft'";

	    }

	    return $type;

	}

	// Reduce text function

	static public function reduceText($value, $limit){

		if(strlen($value) > $limit){

			$value = substr($value, 0, $limit)."...";
		}

		return $value;
	}

	// Return list thumbnail

	static public function returnThumbnailList($value){

		$cmsBasePath = self::cmsBasePath();

		// Build full URL from relative path stored in database
		$fileUrl = self::buildFileUrl($value->link_file);

		// Capture image thumbnail

		if(explode("/",$value->type_file)[0] == "image"){

			$path = '<img src="'.$fileUrl.'" class="rounded" style="width:100px; height:100px; object-fit: cover; object-position: center;">';

		}

		// Capture video thumbnail

		if(explode("/",$value->type_file)[0] == "video" && $value->id_folder_file != 4){

			if(explode("/",$value->type_file)[1] == "mp4"){

				$path = '<video class="rounded" style="width:100px; height:100px; object-fit: cover; object-position: center;">
				<source src="'.$fileUrl.'" type="'.$value->type_file.'">
				</video>';

			}else{

				$path = '<img src="'.$cmsBasePath.'/views/assets/img/multimedia.png" class="rounded" style="width:100px; height:100px; object-fit: cover; object-position: center;">';
			}

		}

		if(explode("/",$value->type_file)[0] == "video" && $value->id_folder_file == 4){

			$path = '<img src="'.$value->thumbnail_vimeo_file.'" class="rounded" style="width:100px; height:100px; object-fit: cover; object-position: center;">';

		}

		// Capture audio thumbnail

		if(explode("/",$value->type_file)[0] == "audio"){

			$path = '<img src="'.$cmsBasePath.'/views/assets/img/multimedia.png" class="rounded" style="width:100px; height:100px; object-fit: cover; object-position: center;">';

		}

		// Capture PDF thumbnail

		if(explode("/",$value->type_file)[1] == "pdf"){

			$path = '<img src="'.$cmsBasePath.'/views/assets/img/pdf.jpeg" class="rounded" style="width:100px; height:100px; object-fit: cover; object-position: center;">';
		}

		// Capture ZIP thumbnail

		if(explode("/",$value->type_file)[1] == "zip"){

			$path = '<img src="'.$cmsBasePath.'/views/assets/img/zip.jpg" class="rounded" style="width:100px; height:100px; object-fit: cover; object-position: center;">';
		}

		return $path;
	}

	// Return grid thumbnail

	static public function returnThumbnailGrid($value){

		$cmsBasePath = self::cmsBasePath();

		// Build full URL from relative path stored in database
		$fileUrl = self::buildFileUrl($value->link_file);

		// Capture image thumbnail

		if(explode("/",$value->type_file)[0] == "image"){

			$path = '<img src="'.$fileUrl.'" class="rounded card-img-top w-100">';

		}

		// Capture video thumbnail

		if(explode("/",$value->type_file)[0] == "video" && $value->id_folder_file != 4){

			if(explode("/",$value->type_file)[1] == "mp4"){

				$path = '<video class="rounded card-img-top w-100">
					<source src="'.$fileUrl.'" type="'.$value->type_file.'">
				</video>';

			}else{

				$path = '<img src="'.$cmsBasePath.'/views/assets/img/multimedia.png" class="rounded card-img-top w-100">';
			}

		}

		if(explode("/",$value->type_file)[0] == "video" && $value->id_folder_file == 4){

			$path = '<img src="'.$value->thumbnail_vimeo_file.'" class="rounded card-img-top w-100">';
			
		}

		// Capture audio thumbnail

		if(explode("/",$value->type_file)[0] == "audio"){

			$path = '<img src="'.$cmsBasePath.'/views/assets/img/multimedia.png" class="rounded card-img-top w-100">';

		}

		// Capture PDF thumbnail

 		if(explode("/",$value->type_file)[1] == "pdf"){

 			$path = '<img src="'.$cmsBasePath.'/views/assets/img/pdf.jpeg" class="rounded card-img-top w-100">';
 		}

 		// Capture ZIP thumbnail

 		if(explode("/",$value->type_file)[1] == "zip"){

 			$path = '<img src="'.$cmsBasePath.'/views/assets/img/zip.jpg" class="rounded card-img-top w-100">';
 		}
	 		
		return $path;
	}

	// Build full file URL from relative path stored in database
	// Handles both relative paths (views/assets/files/...) and full URLs (for backward compatibility)
	static public function buildFileUrl($linkFile){
		// If link_file already contains http:// or https://, return as-is (backward compatibility)
		if(strpos($linkFile, 'http://') === 0 || strpos($linkFile, 'https://') === 0){
			return $linkFile;
		}
		
		// Build full URL from relative path
		$cmsBasePath = self::cmsBasePath();
		$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
		
		// Ensure link_file starts with / if it's a relative path
		$relativePath = ltrim($linkFile, '/');
		
		// Build complete URL
		$fullUrl = $protocol . '://' . $host . $cmsBasePath . '/' . $relativePath;
		
		// Clean double slashes (but preserve http:// or https://)
		$fullUrl = preg_replace('#([^:])//+#', '$1/', $fullUrl);
		
		return $fullUrl;
	}

	// Generate random alphanumeric codes

	static public function genPassword($length){

		$password = "";
		$chain = "0123456789abcdefghijklmnopqrstuvwxyz";

		$password = substr(str_shuffle($chain),0,$length);

		return $password;
	}

	// Get password salt from configuration
	static public function getPasswordSalt(){
		$configPath = __DIR__ . '/../config.php';
		if(file_exists($configPath)){
			$config = require $configPath;
		} else {
			$examplePath = __DIR__ . '/../config.example.php';
			$config = file_exists($examplePath) ? require $examplePath : [];
		}
		return $config['password']['salt'] ?? '$2a$07$azybxcags23425sdg23sdfhsd$';
	}

	// Localization settings (currency + date formats), cached per request.
	// Defaults preserve the previous money rendering ($ + 2 decimals) and add a
	// friendly date format; override the `localization` block in config.php.
	static private $localizationCache = null;
	static private function localization(){
		if(self::$localizationCache !== null){ return self::$localizationCache; }
		$configPath  = __DIR__ . '/../config.php';
		$examplePath = __DIR__ . '/../config.example.php';
		$config = file_exists($configPath) ? require $configPath : (file_exists($examplePath) ? require $examplePath : []);
		$loc = (isset($config['localization']) && is_array($config['localization'])) ? $config['localization'] : [];
		$currency = (isset($loc['currency']) && is_array($loc['currency'])) ? $loc['currency'] : [];
		self::$localizationCache = [
			'currency' => array_merge(['symbol' => '$', 'decimals' => 2, 'thousands_sep' => ',', 'decimal_sep' => '.'], $currency),
			'date_format'     => $loc['date_format']     ?? 'd-m-Y',
			'datetime_format' => $loc['datetime_format'] ?? 'd-m-Y H:i',
			'time_format'     => $loc['time_format']     ?? 'H:i',
		];
		return self::$localizationCache;
	}

	// Format a numeric value as currency using the localization config.
	static public function formatMoney($value){
		$c = self::localization()['currency'];
		return $c['symbol'] . number_format((float)$value, (int)$c['decimals'], $c['decimal_sep'], $c['thousands_sep']);
	}

	// Format a date/datetime/time value for a listing; falls back to the raw
	// string if it cannot be parsed (e.g. empty or zero dates).
	static public function formatListDate($type, $value){
		$raw = trim((string)$value);
		if($raw === '' || strpos($raw, '0000-00-00') === 0){ return htmlspecialchars($raw); }
		$ts = strtotime($raw);
		if($ts === false){ return htmlspecialchars($raw); }
		$loc = self::localization();
		$fmt = ($type === 'date') ? $loc['date_format'] : (($type === 'time') ? $loc['time_format'] : $loc['datetime_format']);
		return htmlspecialchars(date($fmt, $ts));
	}

	// Deterministic Bootstrap-ish color for a select/enum value so the same value
	// always renders the same colored pill.
	static public function badgeColor($value){
		$palette = ['#0d6efd','#198754','#dc3545','#fd7e14','#6f42c1','#0dcaf0','#d63384','#20c997','#6c757d'];
		return $palette[ abs(crc32((string)$value)) % count($palette) ];
	}

	// Send email function
	// Uses EmailService for professional email sending with SMTP support

	static public function sendEmail($subject, $email, $title, $message, $link){

		// Load EmailService
		$emailServicePath = __DIR__ . '/email.service.php';
		if (!file_exists($emailServicePath)) {
			return "Email service not found";
		}
		
		require_once $emailServicePath;
		
		// Create email service instance
		$emailService = new EmailService();
		
		// Send email using the service
		$result = $emailService->sendEmail($email, $subject, $title, $message, $link);
		
		// Return result (maintain backward compatibility)
		if ($result['success']) {
			return "ok";
		} else {
			return $result['message'];
		}

	}

	// Format dates function

	static public function formatDate($type, $value){

		// Load timezone from config
		$configPath = __DIR__ . '/../config.php';
		if(file_exists($configPath)){
			$config = require $configPath;
			$timezone = $config['timezone'] ?? 'America/Santiago';
		} else {
			$timezone = 'America/Santiago';
		}

		// Create DateTime object with date
		$fecha = new DateTime($value, new DateTimeZone($timezone));

		if($type == 1){

			$format = "d 'de' MMMM, yyyy";
		}

		if($type == 2){

			$format = "MMM yyyy";
		}

		if($type == 3){

			$format = "d - MM - yyyy";
		}

		if($type == 4){

			$format = "EEEE d 'de' MMMM yyyy 'a las' h a";
		}

		if($type == 5){

			$format = "d/MM/yyyy";
		}

		if($type == 6){

			$format = "h':'mm a";
		}

		if($type == 7){

			$format = "EEEE d 'de' MMMM, yyyy";
		}

		if($type == 8){

			$format = "yyyy-MM-dd";
		}


		// Create date formatter
		$formatter = new IntlDateFormatter(
		    'es_ES',
		    IntlDateFormatter::FULL,
		    IntlDateFormatter::NONE,
		    $timezone,
		    IntlDateFormatter::GREGORIAN,
		    $format
		);

		// Format date
		$fecha_formateada = $formatter->format($fecha);

		return $fecha_formateada;

	}

}

?>