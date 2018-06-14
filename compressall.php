<?php
###PHP 7 required (and recommended, because it's MUST faster)###

##########################################
######### HOW TO USE COMPRESSALL #########
##########################################

/*
Compressall is just one part of a package. In order to get full functionality, get the right services for your needs!

Make sure to get either the Linux or Windows version, depending on your server.

	Video, Audio:		FFMPEG http://ffmpeg.org/download.html
	Images:				ImageMagick (Portable Version) http://www.imagemagick.org/script/download.php
	Text:				LibreOffice (Full Install; get "LibreOffice 5" folder and paste contents) https://www.libreoffice.org/download/download/
	NOT IN USE: HTML:	HTML Purifier (Standalone Distribution) http://htmlpurifier.org/download
*/

#Uncomment the below to show errors
//*
error_reporting(E_ALL);
ini_set('display_errors',1);
//*/

#We'll store all errors and code that's echoed, so we can send that info to the user (in a way that won't break the JSON object).
ob_start();

####THIS COULD REALLY BENEFIT FROM REACTPHP. We could take care of individual problems, such as converting, renaming, making folders, etc, individually and asyncronously####

/***********************************

	HOW THIS WORKS
		
	1. Accept correct settings from user (or set them to specific values over here)
	2. Send files from the user side to this file
	3. PROFIT!!!!!
		
	The goal was to make an easy way to upload files in any medium and convert, clean, and adapt them. Kinda due to my own multimedia obsessions. :)
	
	Thanks so much to the devs of FFMPEG and ImageMagick!

***********************************/

##########################################
######### FILL OUT THE FOLLOWING #########
##########################################

$os='windows';				#linux/windows. Remember to switch if you're moving between windows and linux for local and server!
$libraries='libraries/';	#The folder for the libraries
$pathOutput='';				#Path to put the files in

$whitelist=[];				#If empty, anything can pass

$blacklist=[
	'exe'
	,'js'
	,'svg'
];

$conversions=[
	'jpeg'		=>	'jpg'
	,'audio'	=>	'mp3'
	,'odt'		=>	'pdf'
	,'docx'		=>	'txt'
	,'rtf'		=>	'txt'
];

#Command-line compression commands
$compressions=[
	#From https://www.smashingmagazine.com/2015/06/efficient-image-resizing-with-imagemagick/
	'jpg'		=>	'-strip -quality 50'
	#From https://www.smashingmagazine.com/2015/06/efficient-image-resizing-with-imagemagick/
	,'png'		=>	'-define png:compression-filter=5 -define png:compression-level=9 -define png:compression-strategy=1'
	#From http://williamyaps.blogspot.com/2016/12/i-success-with-this-command-ffmpeg-i.html
	,'mp3'		=>	'-b:a 128k'
	#From https://superuser.com/questions/582198/how-can-i-get-high-quality-low-size-mp4s-like-the-lol-release-group
	,'mp4'		=>	' \ -c:v libx264 -crf 19 -level 3.1 -preset slow -tune film \ -filter:v scale=-1:720 -sws_flags lanczos \ -c:a libfdk_aac -vbr 5 \ '
];

##########################################
################## CODE ##################
##########################################

#Null=OK; true=success; false=failure
$success=null;

#Get file

if($_FILES['file']['error']!==UPLOAD_ERR_OK){
	#Error messages: http://php.net/manual/en/features.file-upload.errors.php
	echo [
		'Upload okay but we\'re still here somehow!'
		,'Uploaded file size too big according to php.ini!'
		,'Uploaded file size too big according to HTML form!'
		,'File only uploaded partially!'
		,'No file found!'
		,'Missing a temporary folder!'
		,'Failed to write to disk!'
		,'A PHP extension stopped the upload!'
	][$_FILES['file']['error']];
	
	$success=false;
}

if($success!==false){
	$fileOutput=$_FILES["file"];

	#finfo can be spoofed, but is harder to spoof
	$finfo=finfo_open(FILEINFO_MIME_TYPE);
	$tempInfo=finfo_file($finfo,$fileOutput['tmp_name']);
	finfo_close($finfo);
	$tempType=explode('/',$tempInfo)[0];

	$tempExtension=pathinfo($fileOutput["name"],PATHINFO_EXTENSION)	?? 'txt';

	#Check file against whitelist and blacklist
	if(
		(in_array($tempType,$blacklist)
		or in_array($tempExtension,$blacklist))
		or
		(!empty($whitelist) and
			(!in_array($tempType,$whitelist)
			and !in_array($tempExtension,$whitelist))
		)
	){
		echo 'That file type isn\'t allowed!';
		$success=false;
	}
}

#Go through all the folders listed and make sure they exist
function makePath($path){
	$folders=explode('/',$path);
	$currentFolder='';
	$l=count($folders);
	
	for($i=0;$i<$l;$i++){
		$currentFolder.=$folders[$i];
		
		#If the path doesn't exist, make it!
		if(!file_exists($path)){
			mkdir($currentFolder);
		}
		
		$currentFolder.='/';
	}
}

if(!empty($pathOutput)) makePath($pathOutput);

##########################
##### TRANSFER FILE  #####
##########################

#Go to the folder
if(!empty($pathOutput)) chdir($pathOutput);

if(!move_uploaded_file(
	$fileOutput['tmp_name']
	,$tempName='temp'.time().'.'.$tempExtension
)){
	$success=false;
	echo 'Failed to move the file!';
}

##########################
### CONVERT & COMPRESS ###
##########################

if($success!==false){
	$convertTo=$tempExtension;

	if($conversions){
		#Check based on extension or general file type
		if(array_key_exists($tempExtension,$conversions)) $convertTo=$conversions[$tempExtension];
		else if(array_key_exists($tempType,$conversions)) $convertTo=$conversions[$tempType];
	}

	#LibreOffice uses : for special conversion values
	$convertTo=explode(':',$convertTo)[0];

	#Get the new filename, but make sure current file doesn't exist
	$newName=pathinfo($fileOutput['name'],PATHINFO_FILENAME);

	$append='';
	$i=2;

	while(file_exists($newName.$append.'.'.$convertTo)){
		$append='-'.$i;
		$i++;
	}

	$newName.=$append.'.'.$convertTo;

	#Build the shell command
	$shellString=null;

	switch($tempType){
		#Used for a lot of special documents
		case 'application':
			switch($tempExtension){
				case 'doc':
				case 'docx':
				case 'odt':
				case 'pdf':
					$shellString='"'.$libraries.'\libreoffice/program/soffice.bin" -headless --convert-to '.$conversions[$tempExtension].' "'.$tempName.'" ';
					break;
				default:
					break;
			}
			break;
		case 'text':
			if(array_key_exists($tempExtension,$conversions)) $shellString='"'.$libraries.'libreoffice/program/soffice.bin" -headless --convert-to '.$conversions[$tempExtension].' "'.$tempName.'" ';
			break;
		case 'video':
		case 'audio':
			$shellString=$libraries.'ffmpeg/ffmpeg.'.($os=='windows' ? 'exe' : 'bin')
			.' -i '
			.'"'.$tempName.'" ';
			
			#Compression
			if(array_key_exists($convertTo,$compressions)) $shellString.=$compressions[$convertTo];
			
			$shellString.=' "'.$newName.'"';
			break;
		case 'image':
			switch($convertTo){
				#Exception for svg; ImageMagick doesn't support SVG
					case 'svg':
					break;
				default:
				
					$shellString=$libraries.'imagemagick/magick.'.($os=='windows' ? 'exe' : 'bin')
					.' convert '
					.'"'.$tempName.'" ';
					
					#Compression
					if(array_key_exists($convertTo,$compressions)) $shellString.=$compressions[$convertTo];
					
					$shellString.=' "'.$newName.'"';
					break;
			}
			break;
		default:
			echo 'This type of file is unsupported! '.$tempInfo;
			break;
	}
}

if($success!==false){
	$shellResponse=[];
	#Not every option uses shell commands
	if(!empty($shellString)){
		if($os='windows') $shellString=str_replace('/','\\',$shellString);
		
		exec($shellString.' 2>&1',$shellResponse,$shellResponse);

		#Anything other than 0 is an error. We could expand upon this if we want: http://www.hiteksoftware.com/knowledge/articles/049.htm
		if($shellResponse==0) $success=true;
		else{
			$success=false;
			echo 'Failed to convert the file!';
		}
		
		unlink($tempName);
	}
	else $success=rename($tempName,$newName);
}

#For testing
//*
$response['shell']=$shellString;
$response['shellResponse']=$shellResponse;
$response['originalInfo']=$tempInfo;
//*/

$response['file']=$newName;
$response['success']=$success;
$response['message']=ob_get_clean();
die(json_encode($response));

?>