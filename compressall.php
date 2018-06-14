<?php
###PHP 7 required (and recommended, because it's MUST faster)###

##########################################
######### HOW TO USE COMPRESSALL #########
##########################################

/*
Compressall is just one part of a package. In order to get full functionality, get the right services for your needs!

Make sure to get either the Linux or Windows version, depending on your server.

	Video, Audio:	FFMPEG http://ffmpeg.org/download.html
	Images:			ImageMagick (Portable Version) http://www.imagemagick.org/script/download.php
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

$packageFolder='';			#If same as current folder, keep blank
#$packageExtension='...';	#Linux
$packageExtension='exe';	#Windows

##########################################
################ GET FILE ################
##########################################

#If upload failed, exit
if($_FILES['file']['error']!==UPLOAD_ERR_OK){
	die(
		#Error messages: http://php.net/manual/en/features.file-upload.errors.php
		[
			'Upload okay but we\'re still here somehow!'
			,'Uploaded file size too big according to php.ini!'
			,'Uploaded file size too big according to HTML form!'
			,'File only uploaded partially!'
			,'No file found!'
			,'Missing a temporary folder!'
			,'Failed to write to disk!'
			,'A PHP extension stopped the upload!'
		][$_FILES['file']['error']]
	);
}

$fileOutput=$_FILES["file"];

#finfo can be spoofed, but is harder to spoof
$finfo=finfo_open(FILEINFO_MIME_TYPE);
$tempInfo=finfo_file($finfo,$fileOutput['tmp_name']);
finfo_close($finfo);

$json=[
	'convert'		=>$_POST['convert']									?? false	#Whether to convert the files
	,'compress'		=>$_POST['compress']								?? 0		#Level of compression, from 0 (none) to 5 (high)
	,'extension'	=>pathinfo($fileOutput["name"],PATHINFO_EXTENSION)	?? 'txt'	#
	,'path'			=>$_POST['path']									?? ''		#Path to put the new file in
	,'conversions'	=>[
		'png'		=>	'jpg'
		,'audio'	=>	'mp3'
	]
	,'success'		=>false
];

##########################################
################## CODE ##################
##########################################

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

if(!empty($json['path'])) makePath($json['path']);

###############
## SAVE FILE ##
###############

#Go to the folder
if(!empty($json['path'])) chdir($json['path']);

move_uploaded_file(
	$fileOutput['tmp_name']
	,$tempName='temp'.time().'.'.$json['extension']
);

###############
### CONVERT ###
###############

$tempType=explode('/',$tempInfo)[0];
$conversion=false;

if($json['convert']){
	#Check based on extension or general file type
	if(array_key_exists($json['extension'],$json['conversions'])) $conversion=$json['conversions'][$json['extension']];
	else if(array_key_exists($tempType,$json['conversions'])) $conversion=$json['conversions'][$tempType];
	
	#Don't convert if it's the type (extensions could be the same but different file type)
	if($conversion==$json['extension']){
		$conversion=false;
	}else{
		
	}
}

if($conversion){
	#Get the new filename with extension
	$newName=pathinfo($fileOutput['name'],PATHINFO_FILENAME).'.'.$conversion;
	
	$shellString=$packageFolder;
	
	switch($tempType){
		case 'video':
		case 'audio':
			$shellString='ffmpeg\ffmpeg.'.$packageExtension
			.' -i '
			.'"'.$tempName.'" '
			.'"'.$newName.'"'
			;
			break;
		case 'image':
			#Exception for svg; ImageMagick doesn't support SVG
		
			$shellString='imagemagick\magick.'.$packageExtension
			.' convert '
			.'"'.$tempName.'" '
			.'"'.$newName.'"'
			;
			break;
		default:
			echo 'This type of file is unsupported!';
			break;
	}
	
	#Run the shell command to convert the file
	shell_exec($shellString);
	
	#Delete the temporary file, we don't need it now
	unlink($tempName);
}else{
	rename($tempName,$fileOutput['name']);
}



###############
## COMPRESS  ##
###############

#shell_exec('command_packages\ffmpeg-20180412-8d381b5-win64-static\bin\ffmpeg.exe -i '.$tempName.' '.$fileOutput['name']);

if($json['compress']){
	/*switch(pathinfo($fileOutput["name"],PATHINFO_EXTENSION)){
		case "jpg":
		case "jpeg":
		case "png":
		case "svg":
			require('Squeezio-master/Squeezio.php');
			$sqz = Sqz\Squeezio::getInstance($fileOutput["tmp_name"]);
			$sqz->exec();
			
			$fileOutput['tmp_name']=$sqz;
			break;
	}*/
}else{
	
}

$json['success']=true;
die(json_encode($json));

?>