<?php

if(!isset($page_title)) $page_title = 'Sample Page';
if(!isset($page_type)) $page_type = '';

$links[] = array('rel' => 'stylesheet', 'type' => 'text/css', 'href' => $skin_iri . 'screen.css');

?>
<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-type" content="text/html;charset=UTF-8" />
		<meta name="generator" content="Eregansu" />
		<?php foreach($scripts as $script)
		{
			echo "\t\t" . '<script type="text/javascript" src="' . htmlspecialchars($script) . '"></script>' . "\n";
		}
		foreach($links as $link)
		{
			$s = '<link';
			foreach($link as $k => $v)
			{
				$s .= ' ' . $k . '="' . htmlspecialchars($v) . '"';
			}
			$s .= ' />';
			echo "\t\t" . $s . "\n";
		}
		?>
		<title><?php echo $page_title; ?></title>
	</head>
	<body class="<?php echo $page_type; ?>">
		<div id="surround">
			<div id="content">
