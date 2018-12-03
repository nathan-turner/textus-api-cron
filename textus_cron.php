<?php

require_once("conn.php"); //database connection code goes here

//multiple actual TextUs api users/keys can be added here
$api_keys = array(
"test@adsdf.com" => "aaE345365465645dx"
);

$ch = curl_init();

//results per page
$page_limit = 100;
//max number of pages to crawl 
$max_pages = 2;
$note_sql = '';

foreach ($api_keys as $email=>$key)
{
	$page = 0;
	if(isset($_GET["p"]) && $_GET["p"] > 0)
		$page = $_GET["p"];
	$res_cnt=1;	

	while( $page <= $max_pages)
	{
		curl_setopt($ch, CURLOPT_URL, "https://app.textus.com/api/messages?per_page=$page_limit&page=$page");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
		
		curl_setopt($ch, CURLOPT_USERPWD, $email . ":" . $key);

		$result = curl_exec($ch);
		if (curl_errno($ch)) {
			echo 'Error:' . curl_error($ch);
		}
		
		$res = json_decode($result);
		
		$page++;

		foreach ($res as $row)
		{			
			$text_id = $row->id;
			$status = $row->status; //received is incoming
			$is_read = $row->read;
			$note = $row->content;
			$sender_phone = str_replace('+1','',$row->sender_phone);
			$receiver_phone = str_replace('+1','',$row->receiver_phone);
			$sender_name = str_replace(' ADMIN','',$row->sender_name);
			$receiver_name = str_replace(' ADMIN','',$row->receiver_name);
			$sender_email = $row->sender_email;
			//$receiver_email = $row->receiver_email;
			$deliver_at = str_replace('T', ' ', explode('.',$row->deliver_at)[0]);
			$sender_type = $row->sender_type;
			$receiver_type = $row->receiver_type; //account means it was sent to API user
			$send_user = explode('@',$row->sender_email)[0];  
			$prefix = '(TU) ';
			
			//format bulk inserts to be inserted into database via custom stored procedure
			$note_sql .= '("'.$deliver_at.'","'.$prefix.$sender_name.'",0,0,"'.$note.'","'.$text_id.'","'.$is_read.'","'.$status.'","'.$sender_email.'","'.$sender_name.'","'.$sender_phone.'","'.$receiver_email.'","'.$receiver_name.'","'.$receiver_phone.'","'.$sender_type.'"),'; 
		}

	} //end while
		
}
curl_close ($ch);

$note_sql = trim($note_sql, ',');	
	try{
		$stmt = $conn->prepare("call InsertTextUs(?)"); //do custom stored proc here for data inserts and updating

		$stmt->bindParam(1, $note_sql);
		$results = $stmt->execute();		
	}
	catch (PDOException $e)
	{
		echo "Error : " . $e->getMessage() . "<br/>";
		die();
	} 

?>