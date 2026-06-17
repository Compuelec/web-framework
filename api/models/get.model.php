<?php

require_once __DIR__ . "/connection.php";

class GetModel{

	// GET requests without filter
	static public function getData($table, $select,$orderBy,$orderMode,$startAt,$endAt){

		// Validate table and columns existence
		$selectArray = explode(",",$select);

		if(empty(Connection::getColumnsData($table, $selectArray))){
			return null;
		}

		// Sanitize ORDER BY and LIMIT to prevent SQL injection
		$orderBy   = $orderBy   !== null ? Connection::sanitizeIdentifier($orderBy)   : null;
		$orderMode = $orderMode !== null ? Connection::sanitizeOrderMode($orderMode)   : null;
		$startAt   = $startAt   !== null ? (int)$startAt : null;
		$endAt     = $endAt     !== null ? (int)$endAt   : null;

		$sql = "SELECT $select FROM $table";

		if($orderBy !== null && $orderMode !== null && $startAt === null && $endAt === null){
			$sql = "SELECT $select FROM $table ORDER BY $orderBy $orderMode";
		}

		if($orderBy !== null && $orderMode !== null && $startAt !== null && $endAt !== null){
			$sql = "SELECT $select FROM $table ORDER BY $orderBy $orderMode LIMIT $startAt, $endAt";
		}

		if($orderBy === null && $orderMode === null && $startAt !== null && $endAt !== null){
			$sql = "SELECT $select FROM $table LIMIT $startAt, $endAt";
		}

		$link = Connection::connect();
		if ($link === null) {
			return null;
		}
		$stmt = $link->prepare($sql);
	

		try{

			$stmt->execute();

		}catch(PDOException $Exception){

			error_log("GetModel::getData error: " . $Exception->getMessage());
			return null;

		}

		return $stmt->fetchAll(PDO::FETCH_CLASS);

	}

	// GET requests with filter
	static public function getDataFilter($table, $select, $linkTo, $equalTo, $orderBy,$orderMode,$startAt,$endAt){

		// Validate table and columns existence
		$linkToArray = explode(",",$linkTo);
		$selectArray = explode(",",$select);

		foreach ($linkToArray  as $key => $value) {
			array_push($selectArray, $value);
		}

		$selectArray = array_unique($selectArray);

		if(empty(Connection::getColumnsData($table,$selectArray))){
			return null;
		}

		// Sanitize ORDER BY and LIMIT to prevent SQL injection
		$orderBy   = $orderBy   !== null ? Connection::sanitizeIdentifier($orderBy)   : null;
		$orderMode = $orderMode !== null ? Connection::sanitizeOrderMode($orderMode)   : null;
		$startAt   = $startAt   !== null ? (int)$startAt : null;
		$endAt     = $endAt     !== null ? (int)$endAt   : null;

		$equalToArray = explode(",",$equalTo);
		$linkToText = "";

		if(count($linkToArray)>1){
			foreach ($linkToArray as $key => $value) {
				if($key > 0){
					$linkToText .= "AND ".$value." = :".$value." ";
				}
			}
		}

		$sql = "SELECT $select FROM $table WHERE $linkToArray[0] = :$linkToArray[0] $linkToText";

		if($orderBy !== null && $orderMode !== null && $startAt === null && $endAt === null){
			$sql = "SELECT $select FROM $table WHERE $linkToArray[0] = :$linkToArray[0] $linkToText ORDER BY $orderBy $orderMode";
		}

		if($orderBy !== null && $orderMode !== null && $startAt !== null && $endAt !== null){
			$sql = "SELECT $select FROM $table WHERE $linkToArray[0] = :$linkToArray[0] $linkToText ORDER BY $orderBy $orderMode LIMIT $startAt, $endAt";
		}

		if($orderBy === null && $orderMode === null && $startAt !== null && $endAt !== null){
			$sql = "SELECT $select FROM $table WHERE $linkToArray[0] = :$linkToArray[0] $linkToText LIMIT $startAt, $endAt";
		}

		$link = Connection::connect();
		if ($link === null) {
			return null;
		}
		$stmt = $link->prepare($sql);

		foreach ($linkToArray as $key => $value) {
			
			$stmt -> bindParam(":".$value, $equalToArray[$key], PDO::PARAM_STR);

		}

		try{

			$stmt -> execute();

		}catch(PDOException $Exception){

			return null;
		
		}

		return $stmt -> fetchAll(PDO::FETCH_CLASS);

	}

	// GET requests without filter between related tables

	static public function getRelData($rel, $type, $select, $orderBy,$orderMode,$startAt,$endAt){

		// Reject unsafe identifiers before building the SQL (SQLi guard)
		if(!Connection::validIdentifierList($select)){ return null; }
		if(!Connection::validIdentifierList($type)){ return null; }
		$orderBy   = $orderBy   !== null ? Connection::sanitizeQualifiedIdentifier($orderBy) : null;
		$orderMode = $orderMode !== null ? Connection::sanitizeOrderMode($orderMode) : null;
		$startAt   = $startAt   !== null ? (int)$startAt : null;
		$endAt     = $endAt     !== null ? (int)$endAt   : null;

		// Validate columns existence

		$relArray = explode(",", $rel);
		$typeArray = explode(",", $type);
		$innerJoinText = "";

		if(count($relArray)>1){

			foreach ($relArray as $key => $value) {

				/*=============================================
				Validate table and columns existence
				=============================================*/
				
				if(empty(Connection::getColumnsData($value,["*"]))){

					return null;

				}
				
				if($key > 0){

					$innerJoinText .= "INNER JOIN ".$value." ON ".$relArray[0].".id_".$typeArray[$key]."_".$typeArray[0] ." = ".$value.".id_".$typeArray[$key]." ";
				}
			}



			$sql = "SELECT $select FROM $relArray[0] $innerJoinText";


			if($orderBy != null && $orderMode != null && $startAt == null && $endAt == null){

				$sql = "SELECT $select FROM $relArray[0] $innerJoinText ORDER BY $orderBy $orderMode";

			}


			if($orderBy != null && $orderMode != null && $startAt != null && $endAt != null){

				$sql = "SELECT $select FROM $relArray[0] $innerJoinText ORDER BY $orderBy $orderMode LIMIT $startAt, $endAt";

			}


			if($orderBy == null && $orderMode == null && $startAt != null && $endAt != null){

				$sql = "SELECT $select FROM $relArray[0] $innerJoinText LIMIT $startAt, $endAt";

			}

			$link = Connection::connect();
		if ($link === null) {
			return null;
		}
		$stmt = $link->prepare($sql);

			try{

				$stmt -> execute();

			}catch(PDOException $Exception){

				return null;
			
			}

			return $stmt -> fetchAll(PDO::FETCH_CLASS);

		}else{

			return null;
		}
		
	}

	// GET requests with filter between related tables

	static public function getRelDataFilter($rel, $type, $select, $linkTo, $equalTo, $orderBy,$orderMode,$startAt,$endAt){

		// Reject unsafe identifiers before building the SQL (SQLi guard)
		if(!Connection::validIdentifierList($select)){ return null; }
		if(!Connection::validIdentifierList($type)){ return null; }
		if(!Connection::validIdentifierList($linkTo)){ return null; }
		$orderBy   = $orderBy   !== null ? Connection::sanitizeQualifiedIdentifier($orderBy) : null;
		$orderMode = $orderMode !== null ? Connection::sanitizeOrderMode($orderMode) : null;
		$startAt   = $startAt   !== null ? (int)$startAt : null;
		$endAt     = $endAt     !== null ? (int)$endAt   : null;

		// Organize filters

		$linkToArray = explode(",",$linkTo);
		$equalToArray = explode(",",$equalTo);
		$linkToText = "";

		if(count($linkToArray)>1){

			foreach ($linkToArray as $key => $value) {

				if($key > 0){

					$linkToText .= "AND ".$value." = :".$value." ";
				}
			}

		}

		// Organize relations

		$relArray = explode(",", $rel);
		$typeArray = explode(",", $type);
		$innerJoinText = "";

		if(count($relArray)>1){

			foreach ($relArray as $key => $value) {

		// Validate table existence
				
				if(empty(Connection::getColumnsData($value, ["*"]))){

					return null;

				}
				
				if($key > 0){

					$innerJoinText .= "INNER JOIN ".$value." ON ".$relArray[0].".id_".$typeArray[$key]."_".$typeArray[0] ." = ".$value.".id_".$typeArray[$key]." ";
				}
			}



			$sql = "SELECT $select FROM $relArray[0] $innerJoinText WHERE $linkToArray[0] = :$linkToArray[0] $linkToText";


			if($orderBy != null && $orderMode != null && $startAt == null && $endAt == null){

				$sql = "SELECT $select FROM $relArray[0] $innerJoinText WHERE $linkToArray[0] = :$linkToArray[0] $linkToText ORDER BY $orderBy $orderMode";

			}


			if($orderBy != null && $orderMode != null && $startAt != null && $endAt != null){

				$sql = "SELECT $select FROM $relArray[0] $innerJoinText WHERE $linkToArray[0] = :$linkToArray[0] $linkToText ORDER BY $orderBy $orderMode LIMIT $startAt, $endAt";

			}


			if($orderBy == null && $orderMode == null && $startAt != null && $endAt != null){

				$sql = "SELECT $select FROM $relArray[0] $innerJoinText WHERE $linkToArray[0] = :$linkToArray[0] $linkToText LIMIT $startAt, $endAt";

			}

			$link = Connection::connect();
		if ($link === null) {
			return null;
		}
		$stmt = $link->prepare($sql);

			foreach ($linkToArray as $key => $value) {
			
				$stmt -> bindParam(":".$value, $equalToArray[$key], PDO::PARAM_STR);

			}

			try{

				$stmt -> execute();

			}catch(PDOException $Exception){

				return null;
			
			}

			return $stmt -> fetchAll(PDO::FETCH_CLASS);

		}else{

			return null;
		}
		
	}

	// GET requests for search without relations

	static public function getDataSearch($table, $select, $linkTo, $search,$orderBy,$orderMode,$startAt,$endAt){

		// Validate table and columns existence
		$linkToArray = explode(",",$linkTo);
		$selectArray = explode(",",$select);

		foreach ($linkToArray  as $key => $value) {
			array_push($selectArray, $value);
		}

		$selectArray = array_unique($selectArray);

		if(empty(Connection::getColumnsData($table,$selectArray))){
			return null;
		}

		// Sanitize ORDER BY and LIMIT to prevent SQL injection
		$orderBy   = $orderBy   !== null ? Connection::sanitizeIdentifier($orderBy)   : null;
		$orderMode = $orderMode !== null ? Connection::sanitizeOrderMode($orderMode)   : null;
		$startAt   = $startAt   !== null ? (int)$startAt : null;
		$endAt     = $endAt     !== null ? (int)$endAt   : null;

		$searchArray = explode(",",$search);
		$linkToText = "";

		if(count($linkToArray)>1){
			foreach ($linkToArray as $key => $value) {
				if($key > 0){
					$linkToText .= "AND ".$value." = :".$value." ";
				}
			}
		}

		// Use :search placeholder to safely bind LIKE value
		$sql = "SELECT $select FROM $table WHERE $linkToArray[0] LIKE :search $linkToText";

		if($orderBy !== null && $orderMode !== null && $startAt === null && $endAt === null){
			$sql = "SELECT $select FROM $table WHERE $linkToArray[0] LIKE :search $linkToText ORDER BY $orderBy $orderMode";
		}

		if($orderBy !== null && $orderMode !== null && $startAt !== null && $endAt !== null){
			$sql = "SELECT $select FROM $table WHERE $linkToArray[0] LIKE :search $linkToText ORDER BY $orderBy $orderMode LIMIT $startAt, $endAt";
		}

		if($orderBy === null && $orderMode === null && $startAt !== null && $endAt !== null){
			$sql = "SELECT $select FROM $table WHERE $linkToArray[0] LIKE :search $linkToText LIMIT $startAt, $endAt";
		}

		$link = Connection::connect();
		if ($link === null) {
			return null;
		}
		$stmt = $link->prepare($sql);

		// Bind LIKE value safely with wildcards
		$likeValue = '%' . $searchArray[0] . '%';
		$stmt->bindParam(':search', $likeValue, PDO::PARAM_STR);

		foreach ($linkToArray as $key => $value) {
			if($key > 0){
				$stmt->bindParam(":".$value, $searchArray[$key], PDO::PARAM_STR);
			}
		}

		try{

			$stmt -> execute();

		}catch(PDOException $Exception){

			return null;
		
		}

		return $stmt -> fetchAll(PDO::FETCH_CLASS);


	}


	// GET requests for search between related tables

	static public function getRelDataSearch($rel, $type, $select, $linkTo, $search, $orderBy,$orderMode,$startAt,$endAt){

		// Reject unsafe identifiers before building the SQL (SQLi guard)
		if(!Connection::validIdentifierList($select)){ return null; }
		if(!Connection::validIdentifierList($type)){ return null; }
		if(!Connection::validIdentifierList($linkTo)){ return null; }
		$orderBy   = $orderBy   !== null ? Connection::sanitizeQualifiedIdentifier($orderBy) : null;
		$orderMode = $orderMode !== null ? Connection::sanitizeOrderMode($orderMode) : null;
		$startAt   = $startAt   !== null ? (int)$startAt : null;
		$endAt     = $endAt     !== null ? (int)$endAt   : null;

		// Organize filters
		$linkToArray = explode(",",$linkTo);
		$searchArray = explode(",",$search);
		$linkToText = "";

		if(count($linkToArray)>1){

			foreach ($linkToArray as $key => $value) {
				
				if($key > 0){

					$linkToText .= "AND ".$value." = :".$value." ";
				}
			}

		}
	
		// Organize relations

		$relArray = explode(",", $rel);
		$typeArray = explode(",", $type);
		$innerJoinText = "";

		if(count($relArray)>1){

			foreach ($relArray as $key => $value) {

		// Validate table existence
				
				if(empty(Connection::getColumnsData($value, ["*"]))){

					return null;

				}
				
				if($key > 0){

					$innerJoinText .= "INNER JOIN ".$value." ON ".$relArray[0].".id_".$typeArray[$key]."_".$typeArray[0] ." = ".$value.".id_".$typeArray[$key]." ";
				}
			}



			$sql = "SELECT $select FROM $relArray[0] $innerJoinText WHERE $linkToArray[0] LIKE :search $linkToText";


			if($orderBy != null && $orderMode != null && $startAt == null && $endAt == null){

				$sql = "SELECT $select FROM $relArray[0] $innerJoinText WHERE $linkToArray[0] LIKE :search $linkToText ORDER BY $orderBy $orderMode";

			}


			if($orderBy != null && $orderMode != null && $startAt != null && $endAt != null){

				$sql = "SELECT $select FROM $relArray[0] $innerJoinText WHERE $linkToArray[0] LIKE :search $linkToText ORDER BY $orderBy $orderMode LIMIT $startAt, $endAt";

			}


			if($orderBy == null && $orderMode == null && $startAt != null && $endAt != null){

				$sql = "SELECT $select FROM $relArray[0] $innerJoinText WHERE $linkToArray[0] LIKE :search $linkToText LIMIT $startAt, $endAt";

			}

			$link = Connection::connect();
		if ($link === null) {
			return null;
		}
		$stmt = $link->prepare($sql);

			$likeValue = '%' . $searchArray[0] . '%';
			$stmt->bindParam(':search', $likeValue, PDO::PARAM_STR);

			foreach ($linkToArray as $key => $value) {

				if($key > 0){

					$stmt -> bindParam(":".$value, $searchArray[$key], PDO::PARAM_STR);

				}

			}

			try{

				$stmt -> execute();

			}catch(PDOException $Exception){

				return null;
			
			}

			return $stmt -> fetchAll(PDO::FETCH_CLASS);

		}else{

			return null;
		}
		
	}

	// GET requests for range selection

	static public function getDataRange($table,$select,$linkTo,$between1,$between2,$orderBy,$orderMode,$startAt,$endAt, $filterTo, $inTo){

		// Validate table and columns existence
		$linkToArray = explode(",",$linkTo);

		if($filterTo != null){
			$filterToArray = explode(",",$filterTo);
		}else{
			$filterToArray = array();
		}

		$selectArray = explode(",",$select);

		foreach ($linkToArray  as $key => $value) {
			array_push($selectArray, $value);
		}

		foreach ($filterToArray  as $key => $value) {
			array_push($selectArray, $value);
		}

		$selectArray = array_unique($selectArray);

		if(empty(Connection::getColumnsData($table,$selectArray))){
			return null;
		}

		// Sanitize ORDER BY and LIMIT to prevent SQL injection
		$orderBy   = $orderBy   !== null ? Connection::sanitizeIdentifier($orderBy)   : null;
		$orderMode = $orderMode !== null ? Connection::sanitizeOrderMode($orderMode)   : null;
		$startAt   = $startAt   !== null ? (int)$startAt : null;
		$endAt     = $endAt     !== null ? (int)$endAt   : null;

		// Build IN() clause safely using individual bound parameters
		$filter = "";
		$filterParams = [];
		if($filterTo !== null && $inTo !== null) {
			$inValues = explode(",", $inTo);
			$placeholders = [];
			foreach ($inValues as $i => $val) {
				$key = ':in_' . $i;
				$placeholders[] = $key;
				$filterParams[$key] = $val;
			}
			$filter = 'AND ' . $filterTo . ' IN (' . implode(',', $placeholders) . ')';
		}

		$sql = "SELECT $select FROM $table WHERE $linkTo BETWEEN :between1 AND :between2 $filter";

		if($orderBy !== null && $orderMode !== null && $startAt === null && $endAt === null){
			$sql = "SELECT $select FROM $table WHERE $linkTo BETWEEN :between1 AND :between2 $filter ORDER BY $orderBy $orderMode";
		}

		if($orderBy !== null && $orderMode !== null && $startAt !== null && $endAt !== null){
			$sql = "SELECT $select FROM $table WHERE $linkTo BETWEEN :between1 AND :between2 $filter ORDER BY $orderBy $orderMode LIMIT $startAt, $endAt";
		}

		if($orderBy === null && $orderMode === null && $startAt !== null && $endAt !== null){
			$sql = "SELECT $select FROM $table WHERE $linkTo BETWEEN :between1 AND :between2 $filter LIMIT $startAt, $endAt";
		}

		$link = Connection::connect();
		if ($link === null) {
			return null;
		}
		$stmt = $link->prepare($sql);
		$stmt->bindParam(':between1', $between1, PDO::PARAM_STR);
		$stmt->bindParam(':between2', $between2, PDO::PARAM_STR);

		foreach ($filterParams as $key => $val) {
			$stmt->bindValue($key, $val, PDO::PARAM_STR);
		}

		try{
			$stmt->execute();
		}catch(PDOException $Exception){
			return null;
		}

		return $stmt->fetchAll(PDO::FETCH_CLASS);

	}

	// GET requests for range selection with relations

	static public function getRelDataRange($rel,$type,$select,$linkTo,$between1,$between2,$orderBy,$orderMode,$startAt,$endAt, $filterTo, $inTo){

		// Reject unsafe identifiers before building the SQL (SQLi guard)
		if(!Connection::validIdentifierList($select)){ return null; }
		if(!Connection::validIdentifierList($type)){ return null; }
		if(!Connection::validIdentifierList($linkTo)){ return null; }
		if($filterTo !== null && !Connection::validIdentifierList($filterTo)){ return null; }
		$orderBy   = $orderBy   !== null ? Connection::sanitizeQualifiedIdentifier($orderBy) : null;
		$orderMode = $orderMode !== null ? Connection::sanitizeOrderMode($orderMode) : null;
		$startAt   = $startAt   !== null ? (int)$startAt : null;
		$endAt     = $endAt     !== null ? (int)$endAt   : null;

		// Validate table and columns existence

		$linkToArray = explode(",",$linkTo);
		
		if($filterTo != null){
			$filterToArray = explode(",",$filterTo);
		}else{
			$filterToArray =array();
		}

		$filter = "";
		$filterParams = [];

		if($filterTo != null && $inTo != null){
			$inValues = explode(",", $inTo);
			$placeholders = [];
			foreach ($inValues as $i => $val) {
				$k = ':rel_in_' . $i;
				$placeholders[] = $k;
				$filterParams[$k] = $val;
			}
			$filter = 'AND ' . $filterTo . ' IN (' . implode(',', $placeholders) . ')';
		}

		$relArray = explode(",", $rel);
		$typeArray = explode(",", $type);
		$innerJoinText = "";

		if(count($relArray)>1){

			foreach ($relArray as $key => $value) {

		// Validate table existence

				if(empty(Connection::getColumnsData($value, ["*"]))){

					return null;

				}


				if($key > 0){

					$innerJoinText .= "INNER JOIN ".$value." ON ".$relArray[0].".id_".$typeArray[$key]."_".$typeArray[0]." = ".$value.".id_".$typeArray[$key]." ";
				}
			}


			$sql = "SELECT $select FROM $relArray[0] $innerJoinText WHERE $linkTo BETWEEN :between1 AND :between2 $filter";


			if($orderBy != null && $orderMode != null && $startAt == null && $endAt == null){

				$sql = "SELECT $select FROM $relArray[0] $innerJoinText WHERE $linkTo BETWEEN :between1 AND :between2 $filter ORDER BY $orderBy $orderMode";

			}


			if($orderBy != null && $orderMode != null && $startAt != null && $endAt != null){

				$sql = "SELECT $select FROM $relArray[0] $innerJoinText WHERE $linkTo BETWEEN :between1 AND :between2 $filter ORDER BY $orderBy $orderMode LIMIT $startAt, $endAt";

			}


			if($orderBy == null && $orderMode == null && $startAt != null && $endAt != null){

				$sql = "SELECT $select FROM $relArray[0] $innerJoinText WHERE $linkTo BETWEEN :between1 AND :between2 $filter LIMIT $startAt, $endAt";

			}

			$link = Connection::connect();
		if ($link === null) {
			return null;
		}
		$stmt = $link->prepare($sql);
			$stmt->bindParam(':between1', $between1, PDO::PARAM_STR);
			$stmt->bindParam(':between2', $between2, PDO::PARAM_STR);
			foreach ($filterParams as $k => $val) {
				$stmt->bindValue($k, $val, PDO::PARAM_STR);
			}

			try{

				$stmt -> execute();

			}catch(PDOException $Exception){

				return null;

			}

			return $stmt -> fetchAll(PDO::FETCH_CLASS);

		}else{

			return null;
		}

	}


}

