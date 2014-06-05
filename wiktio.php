<?php

/*****************************
 HELPERS
 *****************************/

function startsWith($haystack, $needle)
{
    return $needle === "" || strpos($haystack, $needle) === 0;
}

function endsWith($haystack, $needle)
{
    return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
}

function contains($haystack, $needle)
{
	if (strpos($haystack, $needle) !== FALSE)
		return true;
	else
		return false;
}

/*****************************
 CORE
 *****************************/

function getSections($data, $level=3)
{
	preg_match_all("/(?:^|\n)".str_repeat("=",$level)."([^=]+?)".str_repeat("=",$level)."/", $data, $matches);

	$pozs = array();

	if (isset($matches[1]) && count($matches[1])>0)
	{
		$result = array();

		foreach ($matches[1] as $match)
		{
			$pozs[] = strpos($data, str_repeat("=",$level)."$match".str_repeat("=",$level));
		}

		$initial = trim(substr($data, 0, $pozs[0]));
		if ($initial!="") $result['main'] = $initial;

		for ($k=0; $k<count($pozs); $k++)
		{
			$match = $matches[1][$k];
			// echo str_repeat("--", $level-2)." ".$match."\n"; // DEBUG
			$len = strlen($match)+(2*$level);

			if ($k<count($pozs)-1)
				$result[$match] = substr($data, $pozs[$k]+$len, $pozs[$k+1] - ($pozs[$k]+$len) );
			else
				$result[$match] = substr($data, $pozs[$k]+$len);

			$result[$match] = getSections($result[$match], $level+1);
		}

		return $result;
	}
	else
	{
		return trim($data);
	}
}

function parseEtymology($data)
{
	$result = $data;

	$result = preg_replace("/{{(?:etyl)(?:\|)+(.+?)\|.+?}}\s?/", "$1. ", $result);
	$result = preg_replace("/{{(?:m)\|[^\|]+?\|([^\|]+?)(?:\|.+?)?}}\s?/", "`$1`", $result);
	$result = preg_replace("/{{term\|\s?(.+?)\s?\|(?:[^}{}]+)?lang=(.+?)}}/","`$1`", $result);
	$result = preg_replace("/''([^']+)''/", "$1", $result);
	$result = preg_replace("/\[\[(.+?)\]\]/", "`$1`", $result);
	$result = preg_replace("/{{l.+?\|(.+?)}}/", "`$1`", $result);

	$result = preg_replace("/{{[^}]+?}}/", "", $result);

	if (!contains($result,"{"))
		return $result;
	else
		return "";
}

function parsePronunciation($data)
{
	echo "PRON : |$data|";
	preg_match_all("/{{IPA\|([^}]+?)}}/", $data, $matches);

	if (!isset($matches[1])) return "";
	else
	{
		$pronuns = array();

		foreach ($matches[1] as $match)
		{
			$submatches = explode("|",$match);

			foreach ($submatches as $subm)
			{
				if (!contains($subm,"lang="))
					$pronuns[] = $subm;
			}
		}

		return implode(", ", $pronuns);
	}
}

function parseVerbForm($data)
{
	preg_match_all("/{{es\-verb([^}]+)}}/", $data, $matches);

	print_r($matches);

	$submatches = explode("|",$matches[1][0]);



	$result = array();

	foreach ($submatches as $submatch)
	{
		$parts = explode("=",$submatch);
		print_r($submatch);
		print_r($parts);

		if (count($parts)>1)
			$result[$parts[0]] = $parts[1];
		else
			$result['verb'] = $submatch;
	}

	return $result;
}

function parseDefinitions($data)
{
	preg_match_all("/\#\s+(.+?)(?:\n|$)/", $data, $matches);

	$defs = array();
	foreach ($matches[1] as $match)
	{
		$def = preg_replace("/\[\[(.+?)\]\]/", "$1", $match);
		$def = preg_replace("/{{gloss\|(.+?)}}/", "", $def);

		$predef = $def;

		$entry = array();

		$def = preg_replace("/{{context\|(.+?)\|lang=es}}/", "", $def);
		$entry['meaning'] = lcfirst(trim($def, " ."));

		if (contains($entry['meaning'], "es-verb form"))
			$entry['meaning'] = parseVerbForm($entry['meaning']);

		preg_match_all("/{{context\|(.+?)\|lang=es}}/", $predef, $submatches);

		if (isset($submatches[1][0]))
		{
			$subms = explode("|",$submatches[1][0]);
			$finals = array();
			foreach ($subms as $subm)
			{
				if (!contains($subm,"lang="))
					$finals[] = $subm;
			}
			$entry['context'] = implode(", ", $finals);
		}


		$defs[] = $entry;
	}

	return $defs;
}

function parseSubtype($data,$what)
{
	if ($what=="verb")
	{
		preg_match_all("/{{es\-verb\|(.+?)}}/", $data, $matches);

		return $matches[1];
	}
	elseif ($what=="noun")
	{
		preg_match_all("/{{es\-noun\|(.+?)}}/", $data, $matches);

		if (isset($matches[1]))
		{
			$result = array();

			if (isset($matches[1][0])) $result['gender'] = $matches[1][0];
			if (isset($matches[1][1])) $result['plural'] = $matches[1][1];

			return $result;
		}
	}

	return array();
}

function parse($data)
{
	echo "====== DATA ======\n";
	print_r($data);
	echo "===================\n";
	if (isset($data['Pronunciation']))
	{
		$got = parsePronunciation($data['Pronunciation']);
		if ($got!="") $result['Pronunciation'] = $got;
	}

	if (isset($data['Etymology']))
	{
		$got = parseEtymology($data['Etymology']);
		if ($got!="") $result['Etymology'] = $got;
	}

	// parse type

	if (isset($data['Verb'])) 
	{
		if (isset($data['Verb']['main'])) $content = $data['Verb']['main'];
		else $content = $data['Verb'];

		$result['Types']['Verb']['Definition'] = parseDefinitions($content);
		$result['Types']['Verb']['Subtype'] = parseSubtype($content, "verb");
	}
	if (isset($data['Noun']))
	{
		if (isset($data['Noun']['main'])) $content = $data['Noun']['main'];
		else $content = $data['Noun'];

		$result['Types']['Noun']['Definition'] = parseDefinitions($content);
		$result['Types']['Noun']['Subtype'] = parseSubtype($content, "noun");
	}

	return $result;
}

function lookUp($word,$from="Spanish",$to="en")
{
	$url = "http://$to.wiktionary.org/w/index.php?title=".urlencode($word)."&action=raw";
	$data = @file_get_contents($url);

	//$main = getMain($data);
	$sections = getSections($data,2);

	if (isset($sections[$from]))
		return parse($sections[$from]);
	else
		return NULL;
}

/*****************************
 MAIN
 *****************************/

$result = lookUp($argv[1],"Spanish");
print_r($result);

?>