<?php

require_once "connection.php";

class GetModel{

	// GET requests without filter
	static public function getData($table, $select,$orderBy,$orderMode,$startAt,$endAt){

		// Validate table and columns existence

		$selectArray = explode(",",$select);
		
		if(empty(Connection::getColumnsData($table, $selectArray))){
			
			return null;
		
		}

		$sql = "SELECT $select FROM $table";

		if($orderBy != null && $orderMode != null && $startAt == null && $endAt == null){

			$sql = "SELECT $select FROM $table ORDER BY $orderBy $orderMode";

		}

		if($orderBy != null && $orderMode != null && $startAt != null && $endAt != null){

			$sql = "SELECT $select FROM $table ORDER BY $orderBy $orderMode LIMIT $startAt, $endAt";

		}

		if($orderBy == null && $orderMode == null && $startAt != null && $endAt != null){

			$sql = "SELECT $select FROM $table LIMIT $startAt, $endAt";

		}

		$stmt = Connection::connect()->prepare($sql);
	

		try{

			$stmt -> execute();

		}catch(PDOException $Exception){

			return $Exception;
		
		}

		return $stmt -> fetchAll(PDO::FETCH_CLASS);

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


		if(empty(Connection::getColumnsData($table,$selectArray ))){	
			
			return null;

		}
		
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

		if($orderBy != null && $orderMode != null && $startAt == null && $endAt == null){

			$sql = "SELECT $select FROM $table WHERE $linkToArray[0] = :$linkToArray[0] $linkToText ORDER BY $orderBy $orderMode";

		}

		if($orderBy != null && $orderMode != null && $startAt != null && $endAt != null){

			$sql = "SELECT $select FROM $table WHERE $linkToArray[0] = :$linkToArray[0] $linkToText ORDER BY $orderBy $orderMode LIMIT $startAt, $endAt";

		}

		if($orderBy == null && $orderMode == null && $startAt != null && $endAt != null){

			$sql = "SELECT $select FROM $table WHERE $linkToArray[0] = :$linkToArray[0] $linkToText LIMIT $startAt, $endAt";

		}

		$stmt = Connection::connect()->prepare($sql);

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

		// Validate columns existence
	
		$relArray = explode(",", $rel);
		$typeArray = explode(",", $type);
		$innerJoinText = "";

		if(count($relArray)>1){

			foreach ($relArray as $key => $value) {

				/*=============================================
				Validar existencia de la tabla y de las columnas
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

			$stmt = Connection::connect()->prepare($sql);

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

			$stmt = Connection::connect()->prepare($sql);

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
		
		if(empty(Connection::getColumnsData($table,$selectArray ))){
			
			return null;

		}

		$searchArray = explode(",",$search);
		$linkToText = "";

		if(count($linkToArray)>1){

			foreach ($linkToArray as $key => $value) {
				
				if($key > 0){

					$linkToText .= "AND ".$value." = :".$value." ";
				}
			}

		}


		/*=============================================
		Sin ordenar y sin limitar datos
		=============================================*/

		$sql = "SELECT $select FROM $table WHERE $linkToArray[0] LIKE '%$searchArray[0]%' $linkToText";

		/*=============================================
		Ordenar datos sin limites
		=============================================*/

		if($orderBy != null && $orderMode != null && $startAt == null && $endAt == null){

			$sql = "SELECT $select FROM $table WHERE $linkToArray[0] LIKE '%$searchArray[0]%' $linkToText ORDER BY $orderBy $orderMode";

		}

		/*=============================================
		Ordenar y limitar datos
		=============================================*/

		if($orderBy != null && $orderMode != null && $startAt != null && $endAt != null){

			$sql = "SELECT $select FROM $table WHERE $linkToArray[0] LIKE '%$searchArray[0]%' $linkToText ORDER BY $orderBy $orderMode LIMIT $startAt, $endAt";

		}

		/*=============================================
		Limitar datos sin ordenar
		=============================================*/

		if($orderBy == null && $orderMode == null && $startAt != null && $endAt != null){

			$sql = "SELECT $select FROM $table WHERE $linkToArray[0] LIKE '%$searchArray[0]%' $linkToText LIMIT $startAt, $endAt";

		}

		$stmt = Connection::connect()->prepare($sql);

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


	}


	// GET requests for search between related tables

	static public function getRelDataSearch($rel, $type, $select, $linkTo, $search, $orderBy,$orderMode,$startAt,$endAt){


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



			$sql = "SELECT $select FROM $relArray[0] $innerJoinText WHERE $linkToArray[0] LIKE '%$searchArray[0]%' $linkToText";


			if($orderBy != null && $orderMode != null && $startAt == null && $endAt == null){

				$sql = "SELECT $select FROM $relArray[0] $innerJoinText WHERE $linkToArray[0] LIKE '%$searchArray[0]%' $linkToText ORDER BY $orderBy $orderMode";

			}


			if($orderBy != null && $orderMode != null && $startAt != null && $endAt != null){

				$sql = "SELECT $select FROM $relArray[0] $innerJoinText WHERE $linkToArray[0] LIKE '%$searchArray[0]%' $linkToText ORDER BY $orderBy $orderMode LIMIT $startAt, $endAt";

			}


			if($orderBy == null && $orderMode == null && $startAt != null && $endAt != null){

				$sql = "SELECT $select FROM $relArray[0] $innerJoinText WHERE $linkToArray[0] LIKE '%$searchArray[0]%' $linkToText LIMIT $startAt, $endAt";

			}

			$stmt = Connection::connect()->prepare($sql);

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
			$filterToArray =array();
		}

		$selectArray = explode(",",$select);

		foreach ($linkToArray  as $key => $value) {
			array_push($selectArray, $value);
		}

		foreach ($filterToArray  as $key => $value) {
			array_push($selectArray, $value);
		}

		$selectArray = array_unique($selectArray);
		
		if(empty(Connection::getColumnsData($table,$selectArray ))){
			
			return null;

		}

		$filter = "";

		if($filterTo != null && $inTo != null){

			$filter = 'AND '.$filterTo.' IN ('.$inTo.')';

		}

		/*=============================================
		Sin ordenar y sin limitar datos
		=============================================*/

		$sql = "SELECT $select FROM $table WHERE $linkTo BETWEEN '$between1' AND '$between2' $filter";

		/*=============================================
		Ordenar datos sin limites
		=============================================*/

		if($orderBy != null && $orderMode != null && $startAt == null && $endAt == null){

			$sql = "SELECT $select FROM $table WHERE $linkTo BETWEEN '$between1' AND '$between2' $filter ORDER BY $orderBy $orderMode";

		}

		/*=============================================
		Ordenar y limitar datos
		=============================================*/

		if($orderBy != null && $orderMode != null && $startAt != null && $endAt != null){

			$sql = "SELECT $select FROM $table WHERE $linkTo BETWEEN '$between1' AND '$between2' $filter ORDER BY $orderBy $orderMode LIMIT $startAt, $endAt";

		}

		/*=============================================
		Limitar datos sin ordenar
		=============================================*/

		if($orderBy == null && $orderMode == null && $startAt != null && $endAt != null){

			$sql = "SELECT $select FROM $table WHERE $linkTo BETWEEN '$between1' AND '$between2' $filter LIMIT $startAt, $endAt";

		}

		$stmt = Connection::connect()->prepare($sql);

		try{

			$stmt -> execute();

		}catch(PDOException $Exception){

			return null;
		
		}

		return $stmt -> fetchAll(PDO::FETCH_CLASS);

	}

	// GET requests for range selection with relations

	static public function getRelDataRange($rel,$type,$select,$linkTo,$between1,$between2,$orderBy,$orderMode,$startAt,$endAt, $filterTo, $inTo){

		// Validate table and columns existence

		$linkToArray = explode(",",$linkTo);
		
		if($filterTo != null){
			$filterToArray = explode(",",$filterTo);
		}else{
			$filterToArray =array();
		}

		$filter = "";

		if($filterTo != null && $inTo != null){

			$filter = 'AND '.$filterTo.' IN ('.$inTo.')';

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


			$sql = "SELECT $select FROM $relArray[0] $innerJoinText WHERE $linkTo BETWEEN '$between1' AND '$between2' $filter";


			if($orderBy != null && $orderMode != null && $startAt == null && $endAt == null){

				$sql = "SELECT $select FROM $relArray[0] $innerJoinText WHERE $linkTo BETWEEN '$between1' AND '$between2' $filter ORDER BY $orderBy $orderMode";

			}


			if($orderBy != null && $orderMode != null && $startAt != null && $endAt != null){

				$sql = "SELECT $select FROM $relArray[0] $innerJoinText WHERE $linkTo BETWEEN '$between1' AND '$between2' $filter ORDER BY $orderBy $orderMode LIMIT $startAt, $endAt";

			}


			if($orderBy == null && $orderMode == null && $startAt != null && $endAt != null){

				$sql = "SELECT $select FROM $relArray[0] $innerJoinText WHERE $linkTo BETWEEN '$between1' AND '$between2' $filter LIMIT $startAt, $endAt";

			}

			$stmt = Connection::connect()->prepare($sql);

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

