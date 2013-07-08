<?php

function ini_write($ini, $path, $has_sections=false) { 
    $content = ""; 
    if ($has_sections) { 
        foreach ($ini as $key=>$section) { 
            $content .= "[".$key."]\n"; 
			$content .= format_ini_section($section);
        } 
    } else { 
		$content .= format_ini_section($ini);
    } 

    if (!$handle = fopen($path, 'w')) { 
        return false; 
    } 

	$res = fwrite($handle, $content); 
	fclose($handle); 
	return $res; 
}

function format_ini_section($section) { 
    $content = ""; 
	foreach ($section as $key=>$elem) { 
		if (is_array($elem)) { 
			foreach ($elem as $array_elem) {
				$content .= $key. "[] = \"" . $array_elem . "\"\n";  
			}
		} elseif ($elem=="") {
			$content .= $key . " = \n";
		} else {
			$content .= $key . " = \"" . $elem . "\"\n";
		}
	} 
	return $content;
} 
