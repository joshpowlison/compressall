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

$pathOutput='';				#Path to put the new file in. Blank is same as compressall.php's folder

$conversions=[
	'jpeg'		=>	'jpg'
	,'audio'	=>	'mp3'
];

$compressions=[
	'jpg'		=>	'-strip -quality 50' #From https://www.smashingmagazine.com/2015/06/efficient-image-resizing-with-imagemagick/
	,'png'		=>	'-define png:compression-filter=5 -define png:compression-level=9 -define png:compression-strategy=1' #From https://www.smashingmagazine.com/2015/06/efficient-image-resizing-with-imagemagick/
	#,'png'		=>	'mogrify -filter Triangle -define filter:support=2 -thumbnail 300 -unsharp 0.25x0.08+8.3+0.045 -dither None -posterize 136 -quality 82 -define jpeg:fancy-upsampling=off -define png:compression-filter=5 -define png:compression-level=9 -define png:compression-strategy=1 -define png:exclude-chunk=all -interlace none -colorspace sRGB' #From https://www.smashingmagazine.com/2015/06/efficient-image-resizing-with-imagemagick/
	,'mp3'		=>	'-b:a 128k' #From http://williamyaps.blogspot.com/2016/12/i-success-with-this-command-ffmpeg-i.html
	,'mp4'		=>	' \ -c:v libx264 -crf 19 -level 3.1 -preset slow -tune film \ -filter:v scale=-1:720 -sws_flags lanczos \ -c:a libfdk_aac -vbr 5 \ ' #From https://superuser.com/questions/582198/how-can-i-get-high-quality-low-size-mp4s-like-the-lol-release-group
];

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

$tempExtension=pathinfo($fileOutput["name"],PATHINFO_EXTENSION)	?? 'txt';

$response=[
	'success'		=>false
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

if(!empty($pathOutput)) makePath($pathOutput);

##########################
##### TRANSFER FILE  #####
##########################

#Go to the folder
if(!empty($pathOutput)) chdir($pathOutput);

move_uploaded_file(
	$fileOutput['tmp_name']
	,$tempName='temp'.time().'.'.$tempExtension
);

##########################
### CONVERT & COMPRESS ###
##########################

$tempType=explode('/',$tempInfo)[0];
$convertTo=$tempExtension;

if($conversions){
	#Check based on extension or general file type
	if(array_key_exists($tempExtension,$conversions)) $convertTo=$conversions[$tempExtension];
	else if(array_key_exists($tempType,$conversions)) $convertTo=$conversions[$tempType];
}

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
$shellString=$packageFolder;

switch($tempType){
	case 'video':
	case 'audio':
		$shellString.='ffmpeg\ffmpeg.'.$packageExtension
		.' -i '
		.'"'.$tempName.'" ';
		
		#Compression
		if(array_key_exists($convertTo,$compressions)) $shellString.=$compressions[$convertTo];
		
		$shellString.=' "'.$newName.'"';
		break;
	case 'image':
		#Exception for svg; ImageMagick doesn't support SVG
	
		$shellString.='imagemagick\magick.'.$packageExtension
		.' convert '
		.'"'.$tempName.'" ';
		
		#Compression
		if(array_key_exists($convertTo,$compressions)) $shellString.=$compressions[$convertTo];
		
		$shellString.=' "'.$newName.'"';
		break;
	default:
		echo 'This type of file is unsupported!';
		break;
}

#Run the shell command to convert the file
shell_exec($shellString);

#Delete the temporary file, we don't need it now
unlink($tempName);

#Basic upload
#rename($tempName,$fileOutput['name']);

$response['file']=$newName;

$response['success']=true;
die(json_encode($response));

?>