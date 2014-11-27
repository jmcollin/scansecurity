<?php

function p($obj)
{
	echo '<pre>';
	print_r($obj);
	echo '</pre>';
}

function logToFile($filename, $msg)
{
	$fd = fopen(dirname(__FILE__).'/'.$filename, 'a');
	$str = '[' . date('d/m/Y h:i:s', time()) . '] ' . $msg;
	fwrite($fd, $str . "\n");
	fclose($fd);
}

function isRealImage($filename, $file_mime_type = null, $mime_type_list = null)
{
	$mime_type = false;
	if (!$mime_type_list)
		$mime_type_list = array('image/gif', 'image/jpg', 'image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/x-icon');

	if (function_exists('finfo_open'))
	{
		$const = defined('FILEINFO_MIME_TYPE') ? FILEINFO_MIME_TYPE : FILEINFO_MIME;
		$finfo = finfo_open($const);
		$mime_type = finfo_file($finfo, $filename);
		finfo_close($finfo);
	}
	elseif (function_exists('mime_content_type'))
		$mime_type = mime_content_type($filename);
	elseif (function_exists('exec'))
	{
		$mime_type = trim(exec('file -b --mime-type '.escapeshellarg($filename)));
		if (!$mime_type)
			$mime_type = trim(exec('file --mime '.escapeshellarg($filename)));
		if (!$mime_type)
			$mime_type = trim(exec('file -bi '.escapeshellarg($filename)));
	}

	if ($file_mime_type && (empty($mime_type) || $mime_type == 'regular file' || $mime_type == 'text/plain'))
		$mime_type = $file_mime_type;

	foreach ($mime_type_list as $type)
		if (strstr($mime_type, $type))
			return true;

	return false;
}

function displayMime($filename)
{
	$mime_type = false;
	if (function_exists('finfo_open'))
	{
		$const = defined('FILEINFO_MIME_TYPE') ? FILEINFO_MIME_TYPE : FILEINFO_MIME;
		$finfo = finfo_open($const);
		$mime_type = finfo_file($finfo, $filename);
		finfo_close($finfo);
	}
	elseif (function_exists('mime_content_type'))
		$mime_type = mime_content_type($filename);
	elseif (function_exists('exec'))
	{
		$mime_type = trim(exec('file -b --mime-type '.escapeshellarg($filename)));
		if (!$mime_type)
			$mime_type = trim(exec('file --mime '.escapeshellarg($filename)));
		if (!$mime_type)
			$mime_type = trim(exec('file -bi '.escapeshellarg($filename)));
	}
	return $mime_type;
}

function displayTime($timestamp)
{
	return date ('F d Y H:i:s', $timestamp);
}

function displayPerms($perms)
{
	return substr(sprintf('%o', $perms), -4);
}

function displaySize($bytes)
{
	$types = array( 'B', 'KB', 'MB', 'GB', 'TB' );
	for( $i = 0; $bytes >= 1024 && $i < ( count( $types ) -1 );
		$bytes /= 1024, $i++ );

	return( round( $bytes, 2 ) . ' ' . $types[$i] );
}

function scanSecurity($path, $cli = false)
{
	$files = array();
	$is_dot = array ('.', '..');
	$is_image = array('gif', 'png', 'jpg', 'jpeg');
	if (is_dir($path))
	{
		if (version_compare(phpversion(), '5.3', '<'))
		{
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($path),
				RecursiveIteratorIterator::SELF_FIRST
			);
		}
		else
		{
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::CHILD_FIRST
			);
		}
		foreach ($iterator as $pathname => $file)
		{
			if (version_compare(phpversion(), '5.2.17', '<='))
			{
				if (in_array($file->getBasename(), $is_dot))
					continue;
			}
			elseif (version_compare(phpversion(), '5.3', '<'))
			{
				if ($file->isDot())
					continue;
			}

			if (version_compare(phpversion(), '5.3.6', '<'))
				$ext = substr(strrchr($file->getFilename(), '.'), 1);
			else
				$ext = $file->getExtension();

			if (in_array($ext, $is_image))
			{
				if((int)isRealImage($file->getPathname()) === 0 && $file->getSize() > 0)
				{
					$path = dirname($file->getPathname());
					$files[$path]['name'] = $file->getFilename();
					$files[$path]['mime'] = displayMime($file->getPathname());
					$files[$path]['mtime'] = displayTime($file->getMTime());
					$files[$path]['perms'] = displayPerms($file->getPerms());
					$files[$path]['size'] = displaySize($file->getSize());
					unlink($file->getPathname());
				}
			}
		}
		unset($iterator, $file);

		$logmsg = '';
		if (!empty($files))
		{
			$msg = 'File(s) infected:'."\n";
			if (!empty($files))
			{
				ksort($files);
				foreach($files as $file => $f)
					$logmsg .= $file.' > '.$f['name'].' '.$f['mtime'].' '.$f['mime'].' '.$f['perms'].' '.$f['size']."\n";
				unset($files, $file, $f);
				logToFile('scanLogs.txt', $logmsg);
			}
		}
		else
			$msg = 'All good';

		if ($cli === true)
			echo $msg.$logmsg."\n";
		else
			p($msg.$logmsg);
	}
	else
	{
		$msg = $path.' isn\'t a directory';
		if ($cli === true)
			echo $msg."\n";
		else
			p($msg);
	}
}

if (php_sapi_name() === 'cli')
{
	if (isset($argv) &&  (isset($argc) && $argc >= 2))
	{
		array_shift($argv);
		foreach($argv as $dir)
			scanSecurity($dir, true);
	}
}
elseif (isset($argv) &&  (isset($argc) && $argc < 2))
{
	echo 'Usage: php [directory...]';
	echo "\n\t".'php index.php /var/www/images/'."\n";
}
else
{
	if(isset($_GET['path']))
	{
		$get_paths = $_GET['path'];
		$paths = explode(',', $get_paths);
		foreach($paths as $path)
			scanSecurity(trim(strip_tags($path)));
	}
	else
	{

		if(!empty($_POST))
		{
			$get_paths = $_POST['path'];
			$paths = explode(',', $get_paths);
			foreach($paths as $path)
				scanSecurity(trim(strip_tags($path)));
		}
		else
		{
			echo '<!DOCTYPE html>
			<html lang="en">
			<head>
				<meta charset="utf-8">
				<meta http-equiv="X-UA-Compatible" content="IE=edge">
				<meta name="viewport" content="width=device-width, initial-scale=1">
				<title>Security Scan</title>
				<link href="css/bootstrap.min.css" rel="stylesheet">
				<link href="css/theme.css" rel="stylesheet">
				<link href="http://fonts.googleapis.com/css?family=Montserrat:400,700" rel="stylesheet" type="text/css">
				<link href="http://fonts.googleapis.com/css?family=Lato:400,700,400italic,700italic" rel="stylesheet" type="text/css">
			</head>
			<body id="page-top" class="index">
				<nav class="navbar navbar-default navbar-fixed-top">
					<div class="container">
						<div class="navbar-header page-scroll">
							<a class="navbar-brand">Security Scan</a>
						</div>
					</div>
				</nav>
				<section id="contact">
					<div class="container">
						<div class="row">
							<div class="col-lg-8 col-lg-offset-2">
								<form name="sentMessage" id="contactForm" action="index.php" method="post">
									<div class="row control-group">
										<div class="form-group col-xs-12 floating-label-form-group controls">
											<label>Path to your directory</label>
											<input type="text" class="form-control" placeholder="/var/www/images/" id="path">
											<p class="help-block text-danger"></p>
										</div>
									</div>
									<br>
									<div id="success">
									</div>
									<div class="row">
										<div class="form-group col-xs-12">
											<input type="submit" class="btn btn-success btn-lg" value="Scan" />
										</div>
									</div>
								</form>
							</div>
						</div>
					</div>
				</section>
				<script src="js/jquery.js"></script>
				<script>
					$(".btn").on("click", function (e) {
						e.preventDefault();
						$("#success").html("<pre>Scan is in progressing...</pre>");
						$.ajax({
							type: "POST",
							url: "index.php",
							dataType: "html",
							data: {
								path : $("#path").val(),
							},
							success : function(data) {
								$("#success").html(data);
							}
						});
					});
				</script>
			</body>
			</html>';
		}
	}
}