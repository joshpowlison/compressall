<?php

####THIS COULD REALLY BENEFIT FROM REACTPHP. We could take care of individual problems, such as converting, renaming, making folders, etc, individually and asyncronously####

/***********************************

	HOW THIS WORKS
		
	1. Accept correct settings from user (or set them to specific values over here)
	2. Send files from the user side to this file
	3. PROFIT!!!!!
		
	The goal was to make an easy way to upload files in any medium and convert, clean, and adapt them. Kinda due to my own multimedia obsessions. :)
	
	Thanks so much to the devs of FFMPEG and ImageMagick!

***********************************/



##############
#####PREP#####
##############

/***FFMPEG***

Used to convert and compress video and audio files.

Download FFMPEG from http://ffmpeg.org/download.html

***/

#Linux: path to _______
#$ffmpegPath='';
#Windows: path to ffmpeg.exe
$ffmpegPath='ffmpeg\windows\ffmpeg.exe';



/***ImageMagick***

Used to convert and compress image files (except svg).

Download ImageMagick from http://www.imagemagick.org/script/download.php
Get the portable version

***/

#Linux: path to _______
#$ffmpegPath='';
#Windows: path to magick
$imageMagickPath='imagemagick\windows\magick.exe';


##############
###GET FILE###
##############

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



##############
###SETTINGS###
##############

$json=[
	'convert'		=>$_POST['convert']									?? false	#Whether to convert the files
	,'compress'		=>$_POST['compress']								?? 0		#Level of compression, from 0 (none) to 5 (high)
	,'extension'	=>pathinfo($fileOutput["name"],PATHINFO_EXTENSION)	?? 'txt'	#
	,'path'			=>$_POST['path']									?? ''		#Path to put the new file in
	,'conversions'	=>[
		"wav"=>"flac"
		,"mp3"=>"wav"
		,"jpg"=>"png"
	]
	,'success'		=>false
];

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



##############
###SAVE FILE##
##############

#Go to the folder
if(!empty($json['path'])) chdir($json['path']);

move_uploaded_file(
	$fileOutput['tmp_name']
	,$tempName='temp'.time().'.'.$json['extension']
);



##############
####CONVERT###
##############

if($json['convert'] && array_key_exists($json['extension'],$json['conversions'])){
	$newName=pathinfo($fileOutput['name'],PATHINFO_FILENAME).'.'.$json['conversions'][$json['extension']];
	
	switch($json['extension']){
		#Video
		case 'mp4':
		case 'webm':
		#Audio
		case 'mp3':
		case 'wav':
		case 'flac':
			$shellString=$ffmpegPath
			.' -i '
			#Old file name
			.'"'.$tempName.'" '
			#New file name and extension
			.'"'.$newName.'"'
			;
			break;
		#Images
		case 'jpg':
		case 'jpeg':
		case 'png':
			$shellString=$imageMagickPath
			.' convert '
			#Old file name
			.'"'.$tempName.'" '
			#New file name and extension
			.'"'.$newName.'"'
			;
			break;
	}
	
	
	
	#die($shellString);
	
	#Run the shell command to convert the file
	shell_exec($shellString);
	
	#Delete the temporary file, we don't need it now
	unlink($tempName);
}else{
	rename($tempName,$fileOutput['name']);
}



##############
###COMPRESS###
##############

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