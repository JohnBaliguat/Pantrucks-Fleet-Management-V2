<?php
	include "../../config/config.php";
	
    $Id = $_POST['unit_Id'];
	
    
    $msg = "";


	$sql = $conn->query("DELETE FROM units WHERE unit_id = '$Id'");

	if($sql){

		$msg = array("valid"=>true, "msg"=>"Delete Successfully");
		
	}else{
		$msg = array("valid"=>true, "msg"=>"Delete Unsuccessful");
		
	}
echo json_encode($msg)
?>
