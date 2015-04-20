<?php

if (isset($_POST['scan'])) {

	$uploadDir = realpath($_POST['base']);

	$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadDir), RecursiveIteratorIterator::SELF_FIRST);

	$return = [];

	foreach($objects as $name => $object){
		if (substr($name, -3) === '.bi') {
			$return[] = $name;
		}
	}

	echo json_encode($return);

}

function rrmdir($dir) {
	if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) {
			if ($object != "." && $object != "..") {
				if (filetype($dir."/".$object) == "dir") rrmdir($dir."/".$object); else unlink($dir."/".$object);
			}
		}
		reset($objects);
		rmdir($dir);
	}
}

if (isset($_POST['del'])) {
	$dir = $_POST['dir'];
	$base = $_POST['base'];

	$match = str_replace($base, '', $dir);
	$final = str_replace('.bi', '', $match);

	rrmdir($dir);

	echo '/' . $final . ' <strong>cache cleared</strong>';
}