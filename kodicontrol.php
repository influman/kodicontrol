<?php
   $xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?>";      
   //***********************************************************************************************************************
   // V1.0 : Script KODI, basé sur le partage de la team eedomus
   
$action = getArg("action", $mandatory = true, $default = '');
$status_arg = getArg("status", $mandatory = false, $default = '');
$api_script = getArg('eedomus_controller_module_id'); 
$global_kodi_ip = getArg('ip');
$global_kodi_port = getArg('port');
$global_kodi_userpass = getArg('userpass');

/*
 * Function
 * Returns:
 *     The filename
 * Argument:
 *     The file absolute path
 */
function sdk_getFilenameFromPath($path)
{
   $ret = $path;

   /* Linux style path */
   $separatorPosition = strpos($ret, "/");
   while($separatorPosition !== FALSE){
      $ret = substr($ret, $separatorPosition+1);
      $separatorPosition = strpos($ret, "/");
   }

   /* Windows style path */
   $separatorPosition = strpos($ret, "\\");
   while($separatorPosition !== FALSE){
      $ret = substr($ret, $separatorPosition+1);
      $separatorPosition = strpos($ret, "\\");
   }

   return $ret;
}

sdk_header('text/xml');
echo "<KODI><error>";

/* 
 * Path to kodi api
 */
$kodi_http_path = "http://$global_kodi_userpass@$global_kodi_ip:$global_kodi_port/jsonrpc";


if ($action == "lecture") {
/* 
 * Global variable for what is playing
 * Nothing by default
 */
$playing_now = "--";

/* 
/* 
 * Get the active players and current track
 */
$kodi_active_player = NULL;
$kodi_get_active = '?request={"jsonrpc":"2.0","method":"Player.GetActivePlayers","id":1}';
$response_string = httpQuery($kodi_http_path.$kodi_get_active, 'GET', NULL);
if($response_string != NULL) {

   $response_decoded = sdk_json_decode($response_string);

   if($response_decoded != NULL) {
      saveVariable('KODIVIDEO', '');

      foreach($response_decoded["result"] as $active) {

         if($active["type"] == "audio") {

            $kodi_get_audio = '?request={"jsonrpc":"2.0","method":"Player.GetItem","params":{"properties":["title","album","artist","file"],"playerid":'.$active["playerid"].'},"id":"AudioGetItem"}';
            $response_string = httpQuery($kodi_http_path.$kodi_get_audio, 'GET', NULL);
            
            if($response_string != NULL) {

               $response_decoded = sdk_json_decode($response_string);

               if($response_decoded != NULL && $response_decoded["result"] != NULL) {

                  $item = $response_decoded["result"]["item"];

                  if($item != NULL) {

                     if($item["artist"] != NULL && $item["artist"][0] != NULL && $item["title"] != NULL){

                        $playing_now = $item["artist"][0]." - ".$item["title"];
                     }
                     else if($item["title"] != NULL){

                        $playing_now = $item["title"];
                     }
                     else if($item["file"] != NULL){

                        $playing_now = sdk_getFilenameFromPath($item["file"]);
                     }
                  }
               }
            }

            break;
         }
         else if ($active["type"] == "video") {

            $kodi_get_video = '?request={"jsonrpc":"2.0","method":"Player.GetItem","params":{"properties":["title","season","episode","showtitle","file"],"playerid":'.$active["playerid"].'},"id":"VideoGetItem"}';
            $response_string = httpQuery($kodi_http_path.$kodi_get_video, 'GET', NULL);
            
            if($response_string != NULL) {

               $response_decoded = sdk_json_decode($response_string);

               if($response_decoded != NULL && $response_decoded["result"] != NULL) {

                  $item = $response_decoded["result"]["item"];

                  if($item != NULL) {
                  	 if ($item["file"] != NULL) {
                  	 	saveVariable('KODIVIDEO', strtolower($item["file"]));
                  	 }

                     if($item["type"] == "episode"){

                        if($item["showtitle"] != NULL){
                           $playing_now = $item["showtitle"]." S".$item["season"]."E".$item["episode"];
                        }
                        else if($item["title"] != NULL){
                           $playing_now = $item["title"];
                        }
                        else if($item["file"] != NULL){
                           $playing_now = sdk_getFilenameFromPath($item["file"]);
                        }
                     }
                     else{

                        if($item["title"] != NULL){
                           $playing_now = $item["title"];
                        }
                        else if($item["file"] != NULL){
                           $playing_now = sdk_getFilenameFromPath($item["file"]);
                        }
                     }
                  }
               }
            }

            break;
         }
      }
   }
}

/* 
 * Finishing XML response
 */
echo "</error><LECTURE>$playing_now</LECTURE>";
}

if ($action == "volume") {
	
$kodi_get_volume = '?request={"jsonrpc":"2.0","method":"Application.GetProperties","params":{"properties":["volume"]},"id":1}';
$response_string = httpQuery(trim($kodi_http_path.$kodi_get_volume), 'GET', NULL);
$volume_kodi = 0;
if($response_string != NULL) {

   $response_decoded = sdk_json_decode($response_string);

   if($response_decoded != NULL) {

      $volume_kodi = $response_decoded["result"]["volume"] * 1;
      $volume_modulo_5 = $volume_kodi % 5;
      switch ($volume_modulo_5) {
          case 1:
         $volume_kodi -= 1;
         break;
          case 2:
         $volume_kodi -= 2;
         break;
          case 3:
         $volume_kodi += 2;
         break;
          case 4:
         $volume_kodi += 1;
         break;
      }
   }
}



/* 
 * Finishing XML response
 */
echo "</error><VOLUME>$volume_kodi</VOLUME>";
}

if ($action == "parental") {
	if ($status_arg == "Actif") {
		// positionnement des mots interdits
		$words = getValue($api_script, true);
		saveVariable('KODIWORDS', $words['value_text']);
		$status = "Actif";
	}
	if ($status_arg == "Inactif") {
		saveVariable('KODIWORDS', '');
		$status = "Inactif";
	}
	if ($status_arg == "poll") {
		$tab_status = getValue($api_script);
		$status = $tab_status['value'];
		if (loadVariable('KODIVIDEO') != '' && $status == "Actif") {
			$playing_now = loadVariable('KODIVIDEO');
			$tab_words = explode(",", loadVariable('KODIWORDS'));
			foreach($tab_words as $words) {
				if (strpos($playing_now, $words) >= 0) {
					//mot interdit trouvé
					//message eedomus
					$kodi_get_video = '?request={"jsonrpc":"2.0","method":"GUI.ShowNotification","params":{"title":"Message%20Eedomus","message":"Contrôle%20Parental%20Actif","displaytime":4000},"id":1}}';
        			 $response_string = httpQuery($kodi_http_path.$kodi_get_video, 'GET', NULL);
					usleep(4000000);
					//stop de la video
					$kodi_get_video = '?request={"jsonrpc":"2.0","id":1,"method":"Player.Stop","params":{"playerid":1}}';
            		$response_string = httpQuery($kodi_http_path.$kodi_get_video, 'GET', NULL);
            		break;
				}
			}
		}
		if ($status == '') { 
			$status = "Inactif";
		}
	}
	echo "</error><PARENTAL>$status</PARENTAL>";
}

echo "</KODI>";
?>
